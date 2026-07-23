<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\Module;
use App\Models\Payroll;
use App\Models\Tenant;
use App\Models\VacationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * API v1 de folha: pipeline completo tenant → auth → módulo → RBAC sobre os
 * serviços já portados. Autentica como admin@demo.com (papel ADMIN = todas
 * as permissões); o módulo payroll é habilitado para o tenant demo no seed.
 */
class PayrollApiTest extends TestCase
{
    use RefreshDatabase;

    private array $headers;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        $this->company = Company::query()->firstOrFail();

        $token = $this->withHeader('X-Tenant-Id', 'demo')
            ->postJson('/api/v1/auth/login', ['email' => 'admin@demo.com', 'password' => 'password'])
            ->assertOk()
            ->json('access_token');

        $this->headers = ['X-Tenant-Id' => 'demo', 'Authorization' => "Bearer {$token}"];
    }

    private function makeEmployee(int $salaryCents): Employee
    {
        static $seq = 0;
        $seq++;

        $employee = Employee::query()->create([
            'company_id' => $this->company->id,
            'registration_number' => 'API'.$seq,
            'full_name' => "Colaborador API {$seq}",
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

    public function test_calcula_fecha_e_reabre_a_competencia(): void
    {
        $employee = $this->makeEmployee(520000);

        $this->postJson("/api/v1/payroll/periods/{$this->company->id}/calculate", ['competency' => '2026-07'], $this->headers)
            ->assertOk()
            ->assertJsonFragment(['message' => 'Folha calculada para 1 colaborador(es).']);

        $payroll = Payroll::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(430451, (int) $payroll->net_cents);

        $this->postJson("/api/v1/payroll/periods/{$this->company->id}/close", ['competency' => '2026-07'], $this->headers)
            ->assertOk();

        // Fechada é imutável: recalcular retorna 409.
        $this->postJson("/api/v1/payroll/periods/{$this->company->id}/calculate", ['competency' => '2026-07'], $this->headers)
            ->assertStatus(409);

        $this->postJson("/api/v1/payroll/periods/{$this->company->id}/reopen", ['competency' => '2026-07'], $this->headers)
            ->assertOk();

        $this->postJson("/api/v1/payroll/periods/{$this->company->id}/calculate", ['competency' => '2026-07'], $this->headers)
            ->assertOk();
    }

    public function test_holerite_traz_itens_e_encargos(): void
    {
        $employee = $this->makeEmployee(520000);
        $this->postJson("/api/v1/payroll/periods/{$this->company->id}/calculate", ['competency' => '2026-07'], $this->headers)->assertOk();
        $payroll = Payroll::query()->where('employee_id', $employee->id)->firstOrFail();

        $this->getJson("/api/v1/payrolls/{$payroll->id}", $this->headers)
            ->assertOk()
            ->assertJsonPath('net_cents', 430451)
            ->assertJsonPath('charges.0.type', 'fgts');
    }

    public function test_competencia_invalida_e_rejeitada(): void
    {
        $this->postJson("/api/v1/payroll/periods/{$this->company->id}/calculate", ['competency' => '07/2026'], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['competency']);
    }

    public function test_decimo_terceiro_via_api(): void
    {
        $this->makeEmployee(240000);

        $this->postJson("/api/v1/payroll/thirteenth/{$this->company->id}", ['year' => 2026, 'installment' => 1], $this->headers)
            ->assertOk()
            ->assertJsonFragment(['message' => '13º 1ª parcela (adiantamento) calculada para 1 colaborador(es).']);
    }

    public function test_recibo_de_ferias_via_api(): void
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

        $this->postJson("/api/v1/payroll/vacations/{$vacation->id}/receipt", [], $this->headers)
            ->assertOk()
            ->assertJsonPath('payroll.net_cents', 351183);
    }

    public function test_rescisao_via_api_desliga_o_colaborador(): void
    {
        $employee = $this->makeEmployee(300000);

        $this->postJson("/api/v1/payroll/employees/{$employee->id}/termination", [
            'date' => '2026-07-15',
            'type' => 'sem_justa_causa',
            'notice' => 'indenizado',
            'fgts_balance_cents' => 1000000,
            'reason' => 'Reestruturação',
        ], $this->headers)
            ->assertStatus(201)
            ->assertJsonPath('payroll.kind', 'termination');

        $this->assertSame(Employee::STATUS_TERMINATED, $employee->fresh()->status);
    }

    public function test_sem_o_modulo_payroll_a_rota_e_bloqueada(): void
    {
        // Desabilita o módulo payroll do tenant demo.
        DB::table('tenant_modules')
            ->where('tenant_id', $this->company->tenant_id)
            ->whereIn('module_id', Module::query()->where('code', 'payroll')->pluck('id'))
            ->update(['is_enabled' => false]);

        $this->postJson("/api/v1/payroll/periods/{$this->company->id}/calculate", ['competency' => '2026-07'], $this->headers)
            ->assertStatus(403);
    }
}
