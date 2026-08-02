<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Core\Tenancy\TenantContext;
use App\Livewire\Ai\Assistente;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Assistente CLT (Livewire): responde com a engine da folha (tabelas vigentes)
 * e cita base legal; conversa persistida. Os valores batem com o motor validado.
 */
class AssistenteUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
        app(TenantContext::class)->set($tenant);

        $user = User::query()->where('email', 'admin@demo.com')->firstOrFail();
        $this->actingAs($user);
        session(['tenant_slug' => 'demo']);
    }

    public function test_calcula_o_liquido_com_a_engine(): void
    {
        Livewire::test(Assistente::class)
            ->set('draft', 'Salário líquido de R$ 5.200')
            ->call('enviar')
            ->assertSee('4.304,51'); // líquido de 5200 — bate com o motor validado

        $this->assertSame(2, AiMessage::query()->count()); // user + assistant
        $this->assertSame('calculated', AiMessage::query()->where('role', 'assistant')->value('provider'));
    }

    public function test_calcula_o_inss_com_a_engine(): void
    {
        Livewire::test(Assistente::class)
            ->set('draft', 'INSS de R$ 3.000')
            ->call('enviar')
            ->assertSee('253,41'); // INSS(3000) = 25341 centavos
    }

    public function test_responde_regras_com_base_legal(): void
    {
        Livewire::test(Assistente::class)
            ->set('draft', 'Quais as regras de aviso prévio?')
            ->call('enviar')
            ->assertSee('Lei 12.506');
    }

    public function test_persiste_a_conversa_entre_montagens(): void
    {
        Livewire::test(Assistente::class)
            ->set('draft', 'INSS de R$ 3.000')
            ->call('enviar');

        // Nova montagem reaproveita a mesma conversa do usuário e mostra o histórico.
        Livewire::test(Assistente::class)->assertSee('253,41');

        $this->assertSame(1, AiConversation::query()->where('agent', 'clt')->count());
    }

    public function test_a_rota_do_assistente_exige_login(): void
    {
        auth()->logout();
        $this->get('/assistente')->assertRedirect('/entrar');
    }
}
