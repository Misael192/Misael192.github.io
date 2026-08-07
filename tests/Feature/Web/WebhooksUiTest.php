<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Core\Tenancy\TenantContext;
use App\Livewire\Integrations\Webhooks;
use App\Livewire\Payroll\Folha;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\Integration;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\Integrations\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Webhooks de saída: entrega assinada (HMAC-SHA256) ao fechar a folha, com log
 * rastreado e reenvio. A assinatura é conferida contra o corpo enviado.
 */
class WebhooksUiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        $this->company = Company::query()->firstOrFail();

        $user = User::query()->where('email', 'admin@demo.com')->firstOrFail();
        $this->actingAs($user);
        session(['tenant_slug' => 'demo']);
    }

    private function makeEmployee(int $salaryCents = 520000): Employee
    {
        $employee = Employee::query()->create([
            'company_id' => $this->company->id,
            'registration_number' => 'W'.mt_rand(1000, 9999),
            'full_name' => 'Colaborador Webhook',
            'status' => Employee::STATUS_ACTIVE,
            'hired_at' => '2024-01-01',
        ]);
        EmploymentContract::query()->create([
            'employee_id' => $employee->id,
            'type' => 'clt',
            'salary_cents' => $salaryCents,
            'start_date' => '2024-01-01',
        ]);

        return $employee;
    }

    private function webhook(string $secret = 'super-secret-key-1234'): Integration
    {
        return Integration::query()->create([
            'provider' => 'webhook',
            'name' => 'ERP',
            'config' => ['url' => 'https://erp.example.com/hook', 'secret' => $secret],
            'is_active' => true,
        ]);
    }

    public function test_cadastra_webhook(): void
    {
        Livewire::test(Webhooks::class)
            ->set('name', 'Financeiro')
            ->set('url', 'https://exemplo.com/hook')
            ->set('secret', 'chave-secreta-com-16+')
            ->call('cadastrar')
            ->assertSee('Webhook cadastrado');

        $integration = Integration::query()->where('provider', 'webhook')->firstOrFail();
        $this->assertSame('https://exemplo.com/hook', $integration->config['url']);
    }

    public function test_fechar_a_folha_entrega_webhook_assinado(): void
    {
        Http::fake(['erp.example.com/*' => Http::response('', 200)]);
        $secret = 'super-secret-key-1234';
        $this->webhook($secret);
        $this->makeEmployee();

        Livewire::test(Folha::class)->set('competency', '2026-07')->call('calcular')->call('fechar');

        $log = WebhookLog::query()->where('event', 'payroll.closed')->firstOrFail();
        $this->assertSame(200, (int) $log->response_code);
        $this->assertNotNull($log->delivered_at);
        $this->assertSame('2026-07', $log->payload['competency']);

        // A assinatura enviada bate com o HMAC do corpo exato.
        Http::assertSent(function ($request) use ($secret) {
            $expected = 'sha256='.hash_hmac('sha256', $request->body(), $secret);

            return $request->hasHeader(WebhookDispatcher::SIGNATURE_HEADER, $expected);
        });
    }

    public function test_reenvia_uma_entrega_que_falhou(): void
    {
        Http::fake(['erp.example.com/*' => Http::sequence()->push('', 500)->push('', 200)]);
        $this->webhook();
        $this->makeEmployee();

        Livewire::test(Folha::class)->set('competency', '2026-07')->call('calcular')->call('fechar');

        $log = WebhookLog::query()->where('event', 'payroll.closed')->firstOrFail();
        $this->assertNull($log->delivered_at); // 1ª tentativa: 500
        $this->assertSame(1, (int) $log->attempts);

        Livewire::test(Webhooks::class)->call('reenviar', $log->id)->assertSee('Reenviado com sucesso');

        $log->refresh();
        $this->assertSame(200, (int) $log->response_code);
        $this->assertNotNull($log->delivered_at);
        $this->assertSame(2, (int) $log->attempts);
    }

    public function test_a_rota_de_webhooks_exige_login(): void
    {
        auth()->logout();
        $this->get('/webhooks')->assertRedirect('/entrar');
    }
}
