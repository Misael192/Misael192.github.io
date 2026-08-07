<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Core\Tenancy\TenantContext;
use App\Livewire\Portal\Portal;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VacationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Portal do colaborador (Livewire): holerites próprios, bater ponto e férias
 * self-service — com acesso alheio BLOQUEADO (403). Autentica um usuário
 * colaborador (papel EMPLOYEE) vinculado ao seu Employee.
 */
class PortalUiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        $this->company = Company::query()->firstOrFail();

        $this->employee = $this->makeEmployee('Colaborador Portal');
        $user = $this->makeEmployeeUser($tenant->id, 'colab@demo.com', $this->employee);

        $this->actingAs($user);
        session(['tenant_slug' => 'demo']);
    }

    private function makeEmployee(string $name): Employee
    {
        return Employee::query()->create([
            'company_id' => $this->company->id,
            'registration_number' => 'PT'.mt_rand(1000, 9999),
            'full_name' => $name,
            'status' => Employee::STATUS_ACTIVE,
            'hired_at' => '2024-01-01',
        ]);
    }

    private function makeEmployeeUser(string $tenantId, string $email, Employee $employee): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenantId,
            'name' => $employee->full_name,
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $role = Role::query()->where('code', 'EMPLOYEE')->firstOrFail();
        UserRole::query()->create(['user_id' => $user->id, 'role_id' => $role->id]);

        $employee->update(['user_id' => $user->id]);

        return $user;
    }

    private function payrollFor(Employee $employee): Payroll
    {
        $period = PayrollPeriod::query()->firstOrCreate(
            ['company_id' => $this->company->id, 'competency' => '2026-07'],
            ['status' => PayrollPeriod::STATUS_CLOSED],
        );

        return Payroll::query()->create([
            'period_id' => $period->id,
            'employee_id' => $employee->id,
            'kind' => Payroll::KIND_PAYSLIP,
            'gross_cents' => 520000,
            'deductions_cents' => 89549,
            'net_cents' => 430451,
            'inss_base_cents' => 520000,
            'irrf_base_cents' => 520000,
            'fgts_base_cents' => 520000,
            'calculated_at' => now(),
        ]);
    }

    public function test_ve_o_proprio_holerite(): void
    {
        $payroll = $this->payrollFor($this->employee);

        $this->get('/portal/holerite/'.$payroll->id)
            ->assertOk()
            ->assertSee('4.304,51'); // líquido próprio
    }

    public function test_acesso_ao_holerite_alheio_e_bloqueado(): void
    {
        $outro = $this->makeEmployee('Outro Colaborador');
        $payrollAlheio = $this->payrollFor($outro);

        $this->get('/portal/holerite/'.$payrollAlheio->id)->assertForbidden();
    }

    public function test_bate_ponto_alternando_entrada_e_saida(): void
    {
        Livewire::test(Portal::class)->call('baterPonto')->call('baterPonto');

        $punches = TimeEntry::query()->where('employee_id', $this->employee->id)->orderBy('recorded_at')->get();
        $this->assertCount(2, $punches);
        $this->assertSame('clock_in', $punches[0]->type);
        $this->assertSame('clock_out', $punches[1]->type);
    }

    public function test_solicita_ferias_self_service_para_o_proprio_vinculo(): void
    {
        Livewire::test(Portal::class)
            ->set('startDate', '2026-09-01')
            ->set('days', 15)
            ->call('solicitarFerias')
            ->assertSee('enviada para aprovação');

        $vacation = VacationRequest::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(VacationRequest::STATUS_REQUESTED, $vacation->status);
        $this->assertSame(15, (int) $vacation->days);
    }

    public function test_usuario_sem_vinculo_nao_acessa_o_portal(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@demo.com')->firstOrFail());

        $this->get('/portal')->assertForbidden();
    }
}
