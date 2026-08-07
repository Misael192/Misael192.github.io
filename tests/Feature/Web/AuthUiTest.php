<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Core\Tenancy\TenantContext;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UI web: login por sessão (com tenant no formulário), guarda das rotas
 * autenticadas e isolamento por tenant. Usa o seed demo (admin@demo.com).
 */
class AuthUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_a_raiz_e_o_painel_exigem_login(): void
    {
        $this->get('/painel')->assertRedirect('/entrar');
    }

    public function test_login_valido_entra_e_grava_o_tenant_na_sessao(): void
    {
        Livewire::test(Login::class)
            ->set('tenant', 'demo')
            ->set('email', 'admin@demo.com')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect('/painel');

        $this->assertAuthenticated();
        $this->assertSame('demo', session('tenant_slug'));
    }

    public function test_credenciais_invalidas_sao_recusadas(): void
    {
        Livewire::test(Login::class)
            ->set('tenant', 'demo')
            ->set('email', 'admin@demo.com')
            ->set('password', 'senha-errada')
            ->call('authenticate')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_empresa_inexistente_e_recusada(): void
    {
        Livewire::test(Login::class)
            ->set('tenant', 'nao-existe')
            ->set('email', 'admin@demo.com')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasErrors('tenant');

        $this->assertGuest();
    }

    public function test_nao_loga_usuario_de_outro_tenant(): void
    {
        // Cria um segundo tenant com o MESMO e-mail, mas a empresa 'demo' no form
        // não pode autenticar esse usuário (escopo por tenant).
        $other = Tenant::query()->create(['slug' => 'outra', 'name' => 'Outra']);
        User::withoutGlobalScope('tenant')->create([
            'tenant_id' => $other->id,
            'name' => 'Intruso',
            'email' => 'intruso@outra.com',
            'password' => 'password',
        ]);

        Livewire::test(Login::class)
            ->set('tenant', 'demo')
            ->set('email', 'intruso@outra.com')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_painel_autenticado_renderiza_kpis(): void
    {
        $this->loginAsDemo();

        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertSee('Painel')
            ->assertSee('Colaboradores ativos');
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $this->loginAsDemo();

        Livewire::test(Dashboard::class)
            ->call('logout')
            ->assertRedirect('/entrar');

        $this->assertGuest();
    }

    private function loginAsDemo(): void
    {
        $tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        $user = User::query()->where('email', 'admin@demo.com')->firstOrFail();

        $this->actingAs($user);
        session(['tenant_slug' => 'demo']);
    }
}
