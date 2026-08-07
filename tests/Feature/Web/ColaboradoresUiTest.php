<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Core\Tenancy\TenantContext;
use App\Livewire\Payroll\Folha;
use App\Livewire\People\Colaboradores;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tela de colaboradores (Livewire) sobre o EmployeeService: cadastra pessoa +
 * contrato, lista e ativa. O colaborador cadastrado alimenta a folha mensal.
 */
class ColaboradoresUiTest extends TestCase
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

    public function test_cadastra_colaborador_com_contrato(): void
    {
        Livewire::test(Colaboradores::class)
            ->set('fullName', 'Maria da Silva')
            ->set('registrationNumber', '0001')
            ->set('hiredAt', '2026-01-05')
            ->set('type', 'clt')
            ->set('salary', '5200.00')
            ->set('weeklyHours', '44')
            ->call('cadastrar')
            ->assertSee('Colaborador cadastrado')
            ->assertSee('Maria da Silva');

        $employee = Employee::query()->where('registration_number', '0001')->firstOrFail();
        $this->assertSame(Employee::STATUS_ACTIVE, $employee->status);

        $contract = EmploymentContract::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(520000, (int) $contract->salary_cents); // reais → centavos
        $this->assertSame(44, (int) $contract->weekly_hours);
    }

    public function test_matricula_duplicada_na_mesma_empresa_e_recusada(): void
    {
        Employee::query()->create([
            'company_id' => $this->company->id,
            'registration_number' => '0001',
            'full_name' => 'Já existe',
            'status' => Employee::STATUS_ACTIVE,
            'hired_at' => '2025-01-01',
        ]);

        Livewire::test(Colaboradores::class)
            ->set('fullName', 'Outro')
            ->set('registrationNumber', '0001')
            ->set('hiredAt', '2026-01-05')
            ->set('type', 'clt')
            ->set('salary', '3000.00')
            ->call('cadastrar')
            ->assertHasErrors(['registrationNumber' => 'unique']);

        $this->assertSame(1, Employee::query()->where('registration_number', '0001')->count());
    }

    public function test_colaborador_cadastrado_entra_na_folha(): void
    {
        // Ponta a ponta: cadastra pela tela → aparece no cálculo da folha.
        Livewire::test(Colaboradores::class)
            ->set('fullName', 'João Folha')
            ->set('registrationNumber', 'F001')
            ->set('hiredAt', '2024-01-01')
            ->set('type', 'clt')
            ->set('salary', '5200.00')
            ->call('cadastrar');

        Livewire::test(Folha::class)
            ->set('competency', '2026-07')
            ->call('calcular')
            ->assertSee('Folha calculada para 1 colaborador')
            ->assertSee('João Folha')
            ->assertSee('4.304,51'); // líquido de 5200 (bate com o motor validado)
    }

    public function test_ativa_colaborador_em_admissao(): void
    {
        $employee = Employee::query()->create([
            'company_id' => $this->company->id,
            'registration_number' => 'ADM1',
            'full_name' => 'Em Admissão',
            'status' => Employee::STATUS_ADMISSION,
            'hired_at' => '2026-02-01',
        ]);

        Livewire::test(Colaboradores::class)
            ->call('ativar', $employee->id)
            ->assertSee('Colaborador ativado');

        $this->assertSame(Employee::STATUS_ACTIVE, $employee->fresh()->status);
    }

    public function test_a_rota_de_colaboradores_exige_login(): void
    {
        auth()->logout();
        $this->get('/colaboradores')->assertRedirect('/entrar');
    }
}
