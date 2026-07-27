<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Core\Tenancy\TenantContext;
use App\Livewire\Payroll\FolhasEspeciais;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\Payroll;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tela de folhas especiais (Livewire) sobre o SpecialPayrollService: 13º,
 * recibo de férias e rescisão (simular → efetivar). Autentica no tenant demo.
 */
class FolhasEspeciaisUiTest extends TestCase
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
            'registration_number' => 'ESP'.mt_rand(1000, 9999),
            'full_name' => 'Colaborador Especial',
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

    public function test_calcula_o_decimo_terceiro(): void
    {
        $employee = $this->makeEmployee(240000);

        Livewire::test(FolhasEspeciais::class)
            ->set('decimoAno', 2026)
            ->set('decimoParcela', 1)
            ->call('calcularDecimo')
            ->assertSee('1ª parcela');

        $payroll = Payroll::query()->where('employee_id', $employee->id)->where('kind', 'thirteenth_1')->first();
        $this->assertNotNull($payroll);
        $this->assertSame(120000, (int) $payroll->net_cents);
    }

    public function test_gera_o_recibo_de_ferias_de_um_pedido_aprovado(): void
    {
        $employee = $this->makeEmployee(300000);
        $vacation = VacationRequest::query()->create([
            'employee_id' => $employee->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-30',
            'days' => 30,
            'sell_days' => 0,
            'status' => VacationRequest::STATUS_APPROVED,
        ]);

        Livewire::test(FolhasEspeciais::class)
            ->assertSee('Colaborador Especial')
            ->call('gerarRecibo', $vacation->id)
            ->assertSee('Recibo de férias gerado');

        $payroll = Payroll::query()->where('kind', 'vacation')->first();
        $this->assertNotNull($payroll);
        $this->assertSame(351183, (int) $payroll->net_cents);
        $this->assertSame($vacation->id, $payroll->source_id);
    }

    public function test_simula_e_efetiva_a_rescisao(): void
    {
        $employee = $this->makeEmployee(300000);

        $component = Livewire::test(FolhasEspeciais::class)
            ->set('rescisaoEmployeeId', $employee->id)
            ->set('rescisaoData', '2026-07-15')
            ->set('rescisaoTipo', 'sem_justa_causa')
            ->set('rescisaoAviso', 'indenizado')
            ->set('rescisaoSaldoFgts', '10000.00')
            ->set('rescisaoDiasFerias', 0)
            ->call('simular')
            ->assertSee('Simulação calculada');

        // Simulação não persiste nem desliga o colaborador.
        $this->assertSame(0, Payroll::query()->count());
        $this->assertSame(Employee::STATUS_ACTIVE, $employee->fresh()->status);

        $component->call('efetivar')->assertSee('desligado');

        $payroll = Payroll::query()->where('employee_id', $employee->id)->where('kind', 'termination')->first();
        $this->assertNotNull($payroll);
        $this->assertSame(Employee::STATUS_TERMINATED, $employee->fresh()->status);
    }

    public function test_saldo_fgts_em_reais_vira_centavos_no_servico(): void
    {
        // R$ 10.000,00 de saldo → base do FGTS na multa de 40% = 400000 centavos.
        $employee = $this->makeEmployee(300000);

        Livewire::test(FolhasEspeciais::class)
            ->set('rescisaoEmployeeId', $employee->id)
            ->set('rescisaoData', '2026-07-15')
            ->set('rescisaoTipo', 'sem_justa_causa')
            ->set('rescisaoAviso', 'indenizado')
            ->set('rescisaoSaldoFgts', '10000.00')
            ->set('rescisaoDiasFerias', 0)
            ->call('efetivar');

        $payroll = Payroll::query()->where('kind', 'termination')->firstOrFail();
        $this->assertSame(400000, (int) $payroll->items()->where('description', 'like', '%FGTS%')->sum('amount_cents'));
    }

    public function test_a_rota_de_folhas_especiais_exige_login(): void
    {
        auth()->logout();
        $this->get('/folha/especiais')->assertRedirect('/entrar');
    }
}
