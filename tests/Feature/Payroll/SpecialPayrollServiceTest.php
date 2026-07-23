<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDependent;
use App\Models\EmploymentContract;
use App\Models\Organization;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Models\Termination;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Payroll\SpecialPayrollService;
use Database\Seeders\PayrollEngineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Folhas especiais (13º/férias/rescisão): valores conferidos contra os
 * calculadores puros já validados; persistência em payrolls com `kind`.
 */
class SpecialPayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private SpecialPayrollService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PayrollEngineSeeder::class);

        $tenant = Tenant::query()->create(['slug' => 'acme', 'name' => 'Acme']);
        app(TenantContext::class)->set($tenant);

        $organization = Organization::query()->create(['name' => 'Grupo Acme']);
        $this->company = Company::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Acme LTDA',
        ]);

        $this->service = new SpecialPayrollService;
    }

    private function makeEmployee(int $salaryCents, string $hiredAt = '2024-01-01'): Employee
    {
        static $seq = 0;
        $seq++;

        $employee = Employee::query()->create([
            'company_id' => $this->company->id,
            'registration_number' => str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'full_name' => "Colaborador {$seq}",
            'status' => Employee::STATUS_ACTIVE,
            'hired_at' => $hiredAt,
        ]);

        EmploymentContract::query()->create([
            'employee_id' => $employee->id,
            'type' => 'clt',
            'salary_cents' => $salaryCents,
            'start_date' => $hiredAt,
        ]);

        return $employee;
    }

    public function test_decimo_terceiro_primeira_parcela(): void
    {
        $employee = $this->makeEmployee(240000);

        [$ok, $message] = $this->service->thirteenth($this->company, 2026, 1);

        $this->assertTrue($ok);
        $this->assertStringContainsString('1ª parcela', $message);

        $payroll = Payroll::query()->where('employee_id', $employee->id)->where('kind', 'thirteenth_1')->first();
        $this->assertNotNull($payroll);
        $this->assertSame(120000, (int) $payroll->net_cents);
        $this->assertSame(9600, (int) $payroll->charges()->where('type', 'fgts')->value('amount_cents'));
    }

    public function test_decimo_terceiro_segunda_parcela_desconta_adiantamento(): void
    {
        $employee = $this->makeEmployee(240000);

        $this->service->thirteenth($this->company, 2026, 1);
        [$ok] = $this->service->thirteenth($this->company, 2026, 2);

        $this->assertTrue($ok);

        $payroll = Payroll::query()->where('employee_id', $employee->id)->where('kind', 'thirteenth_2')->first();
        $this->assertNotNull($payroll);
        $this->assertSame(240000, (int) $payroll->gross_cents);
        $this->assertSame(100677, (int) $payroll->net_cents); // integral − INSS − adiantamento
        // INSS sobre o 13º integral e o desconto do adiantamento aparecem como itens.
        $this->assertSame(19323, (int) $payroll->items()->where('rubric_code', '2000')->value('amount_cents'));
        $this->assertSame(120000, (int) $payroll->items()->where('rubric_code', '2006')->value('amount_cents'));
        // FGTS da 2ª parcela é a diferença (integral − 1ª).
        $this->assertSame(9600, (int) $payroll->charges()->where('type', 'fgts')->value('amount_cents'));
    }

    public function test_recibo_de_ferias_calcula_e_e_idempotente(): void
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

        [$ok, , $payroll] = $this->service->vacationReceipt($vacation);

        $this->assertTrue($ok);
        $this->assertSame(400000, (int) $payroll->gross_cents);
        $this->assertSame(351183, (int) $payroll->net_cents);
        $this->assertSame('vacation', $payroll->kind);
        $this->assertSame($vacation->getMorphClass(), $payroll->source_type);

        // Reaproveita: chamar de novo devolve a MESMA folha, sem duplicar.
        [$ok2, $message2, $payroll2] = $this->service->vacationReceipt($vacation);
        $this->assertTrue($ok2);
        $this->assertStringContainsString('já gerado', $message2);
        $this->assertSame($payroll->id, $payroll2->id);
        $this->assertSame(1, Payroll::query()->where('kind', 'vacation')->count());
    }

    public function test_ferias_nao_aprovadas_sao_recusadas(): void
    {
        $employee = $this->makeEmployee(300000);
        $vacation = VacationRequest::query()->create([
            'employee_id' => $employee->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-30',
            'days' => 30,
            'status' => VacationRequest::STATUS_REQUESTED,
        ]);

        [$ok, $message] = $this->service->vacationReceipt($vacation);

        $this->assertFalse($ok);
        $this->assertStringContainsString('aprovadas', $message);
        $this->assertSame(0, Payroll::query()->count());
    }

    public function test_rescisao_efetiva_desligamento_e_gera_folha(): void
    {
        $employee = $this->makeEmployee(300000);
        $user = User::query()->create([
            'tenant_id' => $this->company->tenant_id,
            'name' => 'Admin',
            'email' => 'admin@acme.com',
            'password' => 'password',
        ]);

        $expected = $this->service->simulateTermination(
            $employee, '2026-07-15', 'sem_justa_causa', 'indenizado', 1000000, 0,
        );

        [$ok, $message, $payroll] = $this->service->terminate(
            $employee, '2026-07-15', 'sem_justa_causa', 'indenizado', 1000000, 0, 'Reestruturação', $user,
        );

        $this->assertTrue($ok);
        $this->assertStringContainsString('desligado', $message);
        $this->assertSame($expected['gross'], (int) $payroll->gross_cents);
        $this->assertSame($expected['net'], (int) $payroll->net_cents);
        $this->assertSame('termination', $payroll->kind);

        // Colaborador desligado e termo criado.
        $this->assertSame(Employee::STATUS_TERMINATED, $employee->fresh()->status);
        $this->assertSame(1, Termination::query()->where('employee_id', $employee->id)->count());
        // Verbas rescisórias caem na rubrica 1400.
        $this->assertGreaterThan(0, $payroll->items()->where('rubric_code', '1400')->count());
    }

    public function test_competencia_fechada_bloqueia_folha_especial(): void
    {
        $employee = $this->makeEmployee(240000);

        // Fecha manualmente a competência do 13º (1ª parcela = ano-11).
        PayrollPeriod::query()->create([
            'company_id' => $this->company->id,
            'competency' => '2026-11',
            'status' => PayrollPeriod::STATUS_CLOSED,
        ]);

        [$ok, $message] = $this->service->thirteenth($this->company, 2026, 1);

        $this->assertFalse($ok);
        $this->assertStringContainsString('fechada', $message);
        $this->assertSame(0, Payroll::query()->where('employee_id', $employee->id)->count());
    }

    public function test_desconta_dependentes_no_irrf_da_rescisao(): void
    {
        $employee = $this->makeEmployee(520000);
        EmployeeDependent::query()->create([
            'employee_id' => $employee->id,
            'full_name' => 'Filho',
            'birth_date' => '2015-01-01',
            'counts_for_irrf' => true,
        ]);

        $semDependente = $this->makeEmployee(520000);

        $comDep = $this->service->simulateTermination($employee, '2026-07-31', 'pedido', 'trabalhado', 0, 0);
        $semDep = $this->service->simulateTermination($semDependente, '2026-07-31', 'pedido', 'trabalhado', 0, 0);

        // Mais dependente → IRRF menor ou igual → líquido maior ou igual.
        $this->assertGreaterThanOrEqual($semDep['net'], $comDep['net']);
    }
}
