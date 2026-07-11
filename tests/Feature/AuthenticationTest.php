<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RefreshSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comportamento de segurança da autenticação (ADR-005):
 * rotação de refresh token e detecção de reuso.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function login(): array
    {
        return $this->withHeader('X-Tenant-Id', 'demo')
            ->postJson('/api/v1/auth/login', [
                'email' => 'admin@demo.com',
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token', 'token_type'])
            ->json();
    }

    public function test_login_emite_access_e_refresh_tokens(): void
    {
        $tokens = $this->login();

        $this->assertNotEmpty($tokens['access_token']);
        $this->assertStringContainsString('.', $tokens['refresh_token']);
    }

    public function test_credenciais_invalidas_sao_rejeitadas_sem_enumeracao(): void
    {
        $this->withHeader('X-Tenant-Id', 'demo')
            ->postJson('/api/v1/auth/login', [
                'email' => 'admin@demo.com',
                'password' => 'senha-errada',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_refresh_rotaciona_o_token(): void
    {
        $tokens = $this->login();

        $rotated = $this->withHeader('X-Tenant-Id', 'demo')
            ->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])
            ->assertOk()
            ->json();

        // Mesma sessão (prefixo do id), segredo novo.
        [$sessionId] = explode('.', $tokens['refresh_token']);
        $this->assertStringStartsWith($sessionId.'.', $rotated['refresh_token']);
        $this->assertNotSame($tokens['refresh_token'], $rotated['refresh_token']);
    }

    public function test_reuso_de_refresh_token_antigo_revoga_a_sessao_inteira(): void
    {
        $tokens = $this->login();

        // Primeira rotação: token original torna-se obsoleto.
        $this->withHeader('X-Tenant-Id', 'demo')
            ->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])
            ->assertOk();

        // Reuso do token JÁ ROTACIONADO → 401 + sessão revogada.
        $this->withHeader('X-Tenant-Id', 'demo')
            ->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])
            ->assertUnauthorized();

        [$sessionId] = explode('.', $tokens['refresh_token']);
        $session = RefreshSession::withoutGlobalScope('tenant')->find($sessionId);
        $this->assertNotNull($session->revoked_at, 'A família de tokens deve ser revogada após reuso');
    }

    public function test_requisicao_sem_tenant_e_rejeitada(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@demo.com',
            'password' => 'password',
        ])->assertBadRequest();
    }
}
