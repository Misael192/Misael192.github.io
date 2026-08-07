<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Core\Tenancy\TenantContext;
use App\Livewire\Payroll\FolhasEspeciais;
use App\Livewire\People\Ferias;
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
 * Tela de férias (Livewire) sobre o VacationService: solicitar, aprovar/recusar
 * com guardas CLT. Aprovado, o pedido vira recibo nas folhas especiais.
 */
class FeriasUiTest extends TestCase
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

    private function makeEmployee(int $salaryCents = 300000): Employee
    {
        $employee = Employee::query()->create([
            'company_id' => $this->company->id,
            'registration_number' => 'V'.mt_rand(1000, 9999),
            'full_name' => 'Colaborador Férias',
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

    public function test_solicita_ferias_calculando_o_periodo(): void
    {
        $employee = $this->makeEmployee();

        Livewire::test(Ferias::class)
            ->set('employeeId', $employee->id)
            ->set('startDate', '2026-07-01')
            ->set('days', 30)
            ->set('sellDays', 0)
            ->call('solicitar')
            ->assertSee('Solicitação de férias registrada');

        $vacation = VacationRequest::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(VacationRequest::STATUS_REQUESTED, $vacation->status);
        $this->assertSame(30, (int) $vacation->days);
        $this->assertSame('2026-07-30', $vacation->end_date->format('Y-m-d')); // início + 29 dias
    }

    public function test_abono_acima_de_dez_dias_e_recusado(): void
    {
        $employee = $this->makeEmployee();

        Livewire::test(Ferias::class)
            ->set('employeeId', $employee->id)
            ->set('startDate', '2026-07-01')
            ->set('days', 30)
            ->set('sellDays', 11)
            ->call('solicitar')
            ->assertHasErrors(['sellDays']);

        $this->assertSame(0, VacationRequest::query()->count());
    }

    public function test_aprova_e_o_pedido_vira_recibo_nas_folhas_especiais(): void
    {
        $employee = $this->makeEmployee();

        $component = Livewire::test(Ferias::class)
            ->set('employeeId', $employee->id)
            ->set('startDate', '2026-07-01')
            ->set('days', 30)
            ->set('sellDays', 0)
            ->call('solicitar');

        $vacation = VacationRequest::query()->where('employee_id', $employee->id)->firstOrFail();
        $component->call('aprovar', $vacation->id)->assertSee('Férias aprovadas');
        $this->assertSame(VacationRequest::STATUS_APPROVED, $vacation->fresh()->status);

        // Ponta a ponta: a tela de folhas especiais gera o recibo do pedido aprovado.
        Livewire::test(FolhasEspeciais::class)
            ->call('gerarRecibo', $vacation->id)
            ->assertSee('Recibo de férias gerado');

        $payroll = Payroll::query()->where('kind', 'vacation')->firstOrFail();
        $this->assertSame(351183, (int) $payroll->net_cents); // bate com o motor validado
        $this->assertSame($vacation->id, $payroll->source_id);
    }

    public function test_recusa_a_solicitacao(): void
    {
        $employee = $this->makeEmployee();

        $component = Livewire::test(Ferias::class)
            ->set('employeeId', $employee->id)
            ->set('startDate', '2026-07-01')
            ->set('days', 20)
            ->set('sellDays', 0)
            ->call('solicitar');

        $vacation = VacationRequest::query()->where('employee_id', $employee->id)->firstOrFail();
        $component->call('recusar', $vacation->id)->assertSee('recusada');

        $this->assertSame(VacationRequest::STATUS_REJECTED, $vacation->fresh()->status);
    }

    public function test_a_rota_de_ferias_exige_login(): void
    {
        auth()->logout();
        $this->get('/ferias')->assertRedirect('/entrar');
    }
}
