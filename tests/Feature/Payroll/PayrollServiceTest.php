<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\Organization;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Models\TimeBankEntry;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Database\Seeders\PayrollEngineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fechamento da folha mensal: engine + persistência + máquina de estados
 * (open → calculated → closed → reopen). Rubricas/tabelas vêm do
 * PayrollEngineSeeder (catálogo global).
 */
class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private PayrollService $service;

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

        $this->service = new PayrollService;
    }

    private function makeEmployee(int $salaryCents, string $status = Employee::STATUS_ACTIVE, string $type = 'clt'): Employee
    {
        static $seq = 0;
        $seq++;

        $employee = Employee::query()->create([
            'company_id' => $this->company->id,
            'registration_number' => str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'full_name' => "Colaborador {$seq}",
            'status' => $status,
            'hired_at' => '2024-01-01',
        ]);

        EmploymentContract::query()->create([
            'employee_id' => $employee->id,
            'type' => $type,
            'salary_cents' => $salaryCents,
            'start_date' => '2024-01-01',
        ]);

        return $employee;
    }

    public function test_calcula_a_folha_e_persiste_os_totais(): void
    {
        $employee = $this->makeEmployee(520000);

        [$ok, $message] = $this->service->calculatePeriod($this->company, '2026-07');

        $this->assertTrue($ok);
        $this->assertStringContainsString('1 colaborador', $message);

        $payroll = Payroll::query()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($payroll);
        $this->assertSame(520000, (int) $payroll->gross_cents);
        $this->assertSame(430451, (int) $payroll->net_cents);
        $this->assertSame(PayrollPeriod::STATUS_CALCULATED, $payroll->period->status);

        // FGTS registrado como encargo (não sai do líquido).
        $this->assertSame(41600, (int) $payroll->charges()->where('type', 'fgts')->value('amount_cents'));
    }

    public function test_importa_banco_de_horas_como_hora_extra(): void
    {
        $employee = $this->makeEmployee(220000);
        TimeBankEntry::query()->create([
            'employee_id' => $employee->id,
            'minutes' => 600, // 10h
            'reason' => 'overtime',
            'reference_date' => '2026-07-10',
        ]);

        $this->service->calculatePeriod($this->company, '2026-07');

        $payroll = Payroll::query()->where('employee_id', $employee->id)->first();
        $heItem = PayrollItem::query()
            ->where('payroll_id', $payroll->id)
            ->where('rubric_code', '1001')
            ->first();

        $this->assertNotNull($heItem);
        $this->assertSame(15000, (int) $heItem->amount_cents); // 10h × (2200/220 × 1,5)
        $this->assertSame(235000, (int) $payroll->gross_cents);
    }

    public function test_recalcular_e_idempotente(): void
    {
        $employee = $this->makeEmployee(300000);

        $this->service->calculatePeriod($this->company, '2026-07');
        $this->service->calculatePeriod($this->company, '2026-07');

        $this->assertSame(1, Payroll::query()->where('employee_id', $employee->id)->count());
    }

    public function test_ignora_colaboradores_sem_contrato_ou_desligados(): void
    {
        $this->makeEmployee(300000, Employee::STATUS_TERMINATED);
        $active = $this->makeEmployee(300000, Employee::STATUS_ACTIVE);

        [$ok, $message] = $this->service->calculatePeriod($this->company, '2026-07');

        $this->assertTrue($ok);
        $this->assertStringContainsString('1 colaborador', $message);
        $this->assertSame(1, Payroll::query()->count());
        $this->assertSame($active->id, Payroll::query()->value('employee_id'));
    }

    public function test_competencia_fechada_e_imutavel(): void
    {
        $this->makeEmployee(300000);
        $user = User::query()->create([
            'tenant_id' => $this->company->tenant_id,
            'name' => 'Admin',
            'email' => 'admin@acme.com',
            'password' => 'password',
        ]);

        $this->service->calculatePeriod($this->company, '2026-07');
        $this->assertTrue($this->service->closePeriod($this->company, '2026-07', $user));

        [$ok, $message] = $this->service->calculatePeriod($this->company, '2026-07');
        $this->assertFalse($ok);
        $this->assertStringContainsString('fechada', $message);
    }

    public function test_reabrir_permite_recalcular(): void
    {
        $this->makeEmployee(300000);
        $user = User::query()->create([
            'tenant_id' => $this->company->tenant_id,
            'name' => 'Admin',
            'email' => 'admin2@acme.com',
            'password' => 'password',
        ]);

        $this->service->calculatePeriod($this->company, '2026-07');
        $this->service->closePeriod($this->company, '2026-07', $user);

        $this->assertTrue($this->service->reopenPeriod($this->company, '2026-07'));

        [$ok] = $this->service->calculatePeriod($this->company, '2026-07');
        $this->assertTrue($ok);
    }
}
