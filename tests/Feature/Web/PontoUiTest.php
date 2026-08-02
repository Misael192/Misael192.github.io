<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Core\Tenancy\TenantContext;
use App\Livewire\Payroll\Folha;
use App\Livewire\People\Ponto;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\Payroll;
use App\Models\Tenant;
use App\Models\TimeBankEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tela de ponto / banco de horas (Livewire) sobre o TimeBankService: lança
 * crédito/débito e o crédito do mês alimenta a folha (HE 50%, rubrica 1001).
 */
class PontoUiTest extends TestCase
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
            'registration_number' => 'P'.mt_rand(1000, 9999),
            'full_name' => 'Colaborador Ponto',
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

    public function test_lanca_credito_em_horas_convertendo_para_minutos(): void
    {
        $employee = $this->makeEmployee();

        Livewire::test(Ponto::class)
            ->set('employeeId', $employee->id)
            ->set('referenceDate', '2026-07-10')
            ->set('hours', '10')
            ->set('kind', 'credito')
            ->call('lancar')
            ->assertSee('Lançamento registrado');

        $entry = TimeBankEntry::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(600, (int) $entry->minutes); // 10h = 600min
        $this->assertSame('overtime', $entry->reason);
    }

    public function test_debito_entra_como_minutos_negativos(): void
    {
        $employee = $this->makeEmployee();

        Livewire::test(Ponto::class)
            ->set('employeeId', $employee->id)
            ->set('referenceDate', '2026-07-11')
            ->set('hours', '2')
            ->set('kind', 'debito')
            ->call('lancar');

        $entry = TimeBankEntry::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(-120, (int) $entry->minutes); // −2h
        $this->assertSame('compensation', $entry->reason);
    }

    public function test_credito_do_mes_vira_hora_extra_na_folha(): void
    {
        // Ponta a ponta: lança HE pela tela → a folha mensal soma a rubrica 1001.
        $employee = $this->makeEmployee(520000);

        Livewire::test(Ponto::class)
            ->set('employeeId', $employee->id)
            ->set('referenceDate', '2026-07-10')
            ->set('hours', '10')
            ->set('kind', 'credito')
            ->call('lancar');

        Livewire::test(Folha::class)
            ->set('competency', '2026-07')
            ->call('calcular')
            ->assertSee('Folha calculada para 1 colaborador');

        $payroll = Payroll::query()->where('employee_id', $employee->id)->firstOrFail();
        $overtime = $payroll->items()->where('rubric_code', '1001')->first();
        $this->assertNotNull($overtime, 'A folha deve ter a rubrica de hora-extra (1001).');
        $this->assertGreaterThan(0, (int) $overtime->amount_cents);
        // Bruto maior que o salário-base por causa da HE.
        $this->assertGreaterThan(520000, (int) $payroll->gross_cents);
    }

    public function test_a_rota_de_ponto_exige_login(): void
    {
        auth()->logout();
        $this->get('/ponto')->assertRedirect('/entrar');
    }
}
