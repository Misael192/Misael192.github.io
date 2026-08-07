<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Identity\Totp;
use App\Http\Controllers\Controller;
use App\Models\MfaCredential;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Autenticação da API: Sanctum emite o access token (curto, 15 min via
 * sanctum.expiration) e o RefreshTokenService cuida da rotação (ADR-005).
 */
class AuthController extends Controller
{
    public function __construct(private readonly RefreshTokenService $refreshTokens) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'mfa_code' => ['nullable', 'digits:6'],
        ]);

        // Escopo global de tenant já aplicado pelo middleware ResolveTenant.
        $user = User::query()->where('email', $data['email'])->first();

        // Mensagem idêntica para usuário inexistente e senha errada — evita enumeração.
        if ($user === null || ! $user->is_active || ! Hash::check($data['password'], (string) $user->password)) {
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas']);
        }

        if ($user->mfa_enabled) {
            $this->verifyMfa($user, $data['mfa_code'] ?? null);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $issued = $this->refreshTokens->issue($user, $request->ip(), $request->userAgent());

        return response()->json([
            'access_token' => $user->createToken('api')->plainTextToken,
            'refresh_token' => $issued['refresh_token'],
            'token_type' => 'Bearer',
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate(['refresh_token' => ['required', 'string']]);

        $rotated = $this->refreshTokens->rotate($data['refresh_token']);

        // Revoga access tokens antigos do usuário antes de emitir o novo.
        $rotated['user']->tokens()->delete();

        return response()->json([
            'access_token' => $rotated['user']->createToken('api')->plainTextToken,
            'refresh_token' => $rotated['refresh_token'],
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $data = $request->validate(['refresh_token' => ['required', 'string']]);

        $this->refreshTokens->revoke($data['refresh_token']);
        $request->user()?->tokens()->delete();

        return response()->json(status: 204);
    }

    private function verifyMfa(User $user, ?string $code): void
    {
        if ($code === null) {
            throw ValidationException::withMessages(['mfa_code' => 'Código MFA obrigatório']);
        }

        $credential = $user->hasOne(MfaCredential::class)
            ->whereNotNull('verified_at')
            ->first();

        // O secret é gravado com cast encrypted; Crypt cobre credenciais legadas.
        $secret = $credential?->secret;

        if ($credential === null || ! Totp::verify((string) $secret, $code)) {
            throw ValidationException::withMessages(['mfa_code' => 'Código MFA inválido']);
        }
    }
}
