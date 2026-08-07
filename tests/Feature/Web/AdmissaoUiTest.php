<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Core\Tenancy\TenantContext;
use App\Livewire\People\Admissao;
use App\Livewire\People\Colaboradores;
use App\Models\AdmissionTask;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\People\AdmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admissão digital (Livewire): cadastro em admissão cria o checklist; concluir
 * todos os itens ativa o colaborador automaticamente.
 */
class AdmissaoUiTest extends TestCase
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

    private function makeAdmissionEmployee(): Employee
    {
        $employee = Employee::query()->create([
            'company_id' => $this->company->id,
            'registration_number' => 'ADM'.mt_rand(1000, 9999),
            'full_name' => 'Novo Colaborador',
            'status' => Employee::STATUS_ADMISSION,
            'hired_at' => '2026-02-01',
        ]);
        (new AdmissionService)->startFor($employee);

        return $employee;
    }

    public function test_cadastro_em_admissao_cria_o_checklist(): void
    {
        Livewire::test(Colaboradores::class)
            ->set('fullName', 'Maria Admissão')
            ->set('registrationNumber', 'A001')
            ->set('hiredAt', '2026-02-01')
            ->set('type', 'clt')
            ->set('salary', '3000')
            ->set('initialStatus', 'admission')
            ->call('cadastrar')
            ->assertSee('checklist criado');

        $employee = Employee::query()->where('registration_number', 'A001')->firstOrFail();
        $this->assertSame(Employee::STATUS_ADMISSION, $employee->status);
        $this->assertSame(count(AdmissionService::CHECKLIST), $employee->admissionTasks()->count());
    }

    public function test_concluir_todo_o_checklist_ativa_o_colaborador(): void
    {
        $employee = $this->makeAdmissionEmployee();
        $tasks = AdmissionTask::query()->where('employee_id', $employee->id)->get();

        $component = Livewire::test(Admissao::class)->assertSee('Novo Colaborador');

        // Marca todos menos o último — ainda em admissão.
        foreach ($tasks->take($tasks->count() - 1) as $task) {
            $component->call('alternarItem', $task->id);
        }
        $this->assertSame(Employee::STATUS_ADMISSION, $employee->fresh()->status);

        // Último item → ativa.
        $component->call('alternarItem', $tasks->last()->id)->assertSee('ativado');
        $this->assertSame(Employee::STATUS_ACTIVE, $employee->fresh()->status);
    }

    public function test_desmarcar_item_nao_reverte_ativacao_ja_feita(): void
    {
        // Ativado; depois some da lista de admissão (fica ativo).
        $employee = $this->makeAdmissionEmployee();
        $service = new AdmissionService;
        foreach (AdmissionTask::query()->where('employee_id', $employee->id)->get() as $task) {
            $service->toggle($task);
        }

        $this->assertSame(Employee::STATUS_ACTIVE, $employee->fresh()->status);

        // Desmarcar um item não rebaixa quem já saiu da admissão.
        $service->toggle(AdmissionTask::query()->where('employee_id', $employee->id)->first());
        $this->assertSame(Employee::STATUS_ACTIVE, $employee->fresh()->status);
    }

    public function test_a_rota_de_admissao_exige_login(): void
    {
        auth()->logout();
        $this->get('/admissao')->assertRedirect('/entrar');
    }
}
