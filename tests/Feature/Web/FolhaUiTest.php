<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Core\Tenancy\TenantContext;
use App\Livewire\Payroll\Folha;
use App\Livewire\Payroll\Holerite;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tela de folha (Livewire) sobre o PayrollService: calcular, listar, fechar,
 * reabrir e holerite. Autentica no tenant demo (admin = todas permissões).
 */
class FolhaUiTest extends TestCase
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

    private function makeEmployee(int $salaryCents): Employee
    {
        $employee = Employee::query()->create([
            'company_id' => $this->company->id,
            'registration_number' => 'UI'.mt_rand(1000, 9999),
            'full_name' => 'Colaborador UI',
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

    public function test_calcula_a_folha_e_lista_o_colaborador(): void
    {
        $this->makeEmployee(520000);

        Livewire::test(Folha::class)
            ->set('competency', '2026-07')
            ->call('calcular')
            ->assertSee('Folha calculada para 1 colaborador')
            ->assertSee('Colaborador UI')
            ->assertSee('4.304,51'); // líquido formatado (430451 centavos)
    }

    public function test_fecha_e_reabre_a_competencia(): void
    {
        $this->makeEmployee(520000);

        $component = Livewire::test(Folha::class)
            ->set('competency', '2026-07')
            ->call('calcular')
            ->call('fechar')
            ->assertSee('Competência fechada');

        $this->assertSame(
            PayrollPeriod::STATUS_CLOSED,
            PayrollPeriod::query()->where('competency', '2026-07')->value('status'),
        );

        $component->call('reabrir')->assertSee('Competência reaberta');
    }

    public function test_a_rota_de_folha_exige_login(): void
    {
        auth()->logout();
        $this->get('/folha')->assertRedirect('/entrar');
    }

    public function test_requisicao_web_resolve_o_tenant_da_sessao(): void
    {
        // Regressão: o tenant precisa ser resolvido em TODA requisição web
        // (não só nas rotas nomeadas) para o endpoint do Livewire funcionar.
        $this->makeEmployee(520000);

        $this->withSession(['tenant_slug' => 'demo'])
            ->get('/folha')
            ->assertOk()
            ->assertSee('Folha de pagamento');
    }

    public function test_holerite_mostra_itens_e_encargos(): void
    {
        $employee = $this->makeEmployee(520000);
        Livewire::test(Folha::class)->set('competency', '2026-07')->call('calcular');
        $payroll = Payroll::query()->where('employee_id', $employee->id)->firstOrFail();

        Livewire::test(Holerite::class, ['payroll' => $payroll])
            ->assertSee('Salário Base')
            ->assertSee('INSS')
            ->assertSee('FGTS');
    }
}
