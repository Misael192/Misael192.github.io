<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\RefreshSession;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;

/**
 * Refresh tokens rotativos com detecção de reuso (ADR-005).
 *
 * O token opaco tem o formato "<sessionId>.<segredo>"; apenas o hash SHA-256
 * do segredo ATUAL vai ao banco. Cada uso emite um novo segredo e invalida o
 * anterior. Se um token JÁ ROTACIONADO for apresentado, alguém está reusando
 * um token vazado → a família inteira é revogada.
 */
class RefreshTokenService
{
    private const TTL_DAYS = 7;

    /** @return array{session: RefreshSession, refresh_token: string} */
    public function issue(User $user, ?string $ip = null, ?string $userAgent = null): array
    {
        $secret = Str::random(64);

        $session = RefreshSession::query()->create([
            'user_id' => $user->id,
            'refresh_token_hash' => hash('sha256', $secret),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'expires_at' => now()->addDays(self::TTL_DAYS),
        ]);

        return ['session' => $session, 'refresh_token' => "{$session->id}.{$secret}"];
    }

    /**
     * Rotaciona o refresh token e devolve o novo par.
     *
     * @return array{user: User, refresh_token: string}
     *
     * @throws AuthenticationException
     */
    public function rotate(string $refreshToken): array
    {
        [$sessionId, $secret] = array_pad(explode('.', $refreshToken, 2), 2, null);

        if ($sessionId === null || $secret === null) {
            throw new AuthenticationException('Refresh token malformado');
        }

        /** @var RefreshSession|null $session */
        $session = RefreshSession::query()->withoutGlobalScope('tenant')->find($sessionId);

        if ($session === null || ! $session->isUsable()) {
            throw new AuthenticationException('Sessão expirada ou revogada');
        }

        if (! hash_equals($session->refresh_token_hash, hash('sha256', $secret))) {
            // Reuso detectado: token antigo apresentado. Revoga a família.
            $session->update(['revoked_at' => now()]);

            throw new AuthenticationException('Reuso de refresh token detectado — sessão revogada');
        }

        $newSecret = Str::random(64);
        $session->update([
            'refresh_token_hash' => hash('sha256', $newSecret),
            'rotation_counter' => $session->rotation_counter + 1,
        ]);

        return [
            'user' => $session->user()->withoutGlobalScope('tenant')->first(),
            'refresh_token' => "{$session->id}.{$newSecret}",
        ];
    }

    public function revoke(string $refreshToken): void
    {
        [$sessionId] = explode('.', $refreshToken, 2);

        RefreshSession::query()
            ->withoutGlobalScope('tenant')
            ->whereKey($sessionId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
