<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Core\Tenancy\TenantContext;
use App\Livewire\Esocial\Esocial;
use App\Livewire\Payroll\Folha;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\EsocialEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tela de eSocial (Livewire): gera S-2200 (admissão) e S-1200 (remuneração da
 * folha fechada) nos leiautes; aponta pendências de cadastro; mostra o XML.
 */
class EsocialUiTest extends TestCase
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

    private function makeEmployee(int $salaryCents = 520000, ?string $cpf = '12345678901'): Employee
    {
        $employee = Employee::query()->create([
            'company_id' => $this->company->id,
            'registration_number' => 'E'.mt_rand(1000, 9999),
            'full_name' => 'Colaborador eSocial',
            'cpf' => $cpf,
            'birth_date' => '1990-05-10',
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

    public function test_gera_s2200_para_colaborador_com_cadastro_completo(): void
    {
        $employee = $this->makeEmployee();

        Livewire::test(Esocial::class)
            ->call('gerarAdmissoes')
            ->assertSee('S-2200 gerado para 1 colaborador');

        $event = EsocialEvent::query()->where('event_type', 'S-2200')->firstOrFail();
        $this->assertSame($employee->registration_number, $event->reference);
        $this->assertStringContainsString('<evtAdmissao', $event->xml);
        $this->assertStringContainsString('<vrSalFx>5200.00</vrSalFx>', $event->xml);
    }

    public function test_s2200_aponta_pendencia_sem_cpf(): void
    {
        $this->makeEmployee(520000, cpf: null);

        Livewire::test(Esocial::class)
            ->call('gerarAdmissoes')
            ->assertSee('pendências de cadastro');

        $this->assertSame(0, EsocialEvent::query()->count());
    }

    public function test_s1200_exige_folha_fechada(): void
    {
        $this->makeEmployee();

        // Só calculada (não fechada) → recusa.
        Livewire::test(Folha::class)->set('competency', '2026-07')->call('calcular');

        Livewire::test(Esocial::class)
            ->set('competency', '2026-07')
            ->call('gerarRemuneracao')
            ->assertSee('precisa estar FECHADA');

        $this->assertSame(0, EsocialEvent::query()->where('event_type', 'S-1200')->count());
    }

    public function test_s1200_gera_da_folha_fechada_com_rubricas(): void
    {
        $this->makeEmployee();

        Livewire::test(Folha::class)->set('competency', '2026-07')->call('calcular')->call('fechar');

        Livewire::test(Esocial::class)
            ->set('competency', '2026-07')
            ->call('gerarRemuneracao')
            ->assertSee('S-1200 de 2026-07 gerado');

        $event = EsocialEvent::query()->where('event_type', 'S-1200')->firstOrFail();
        $this->assertSame('2026-07', $event->reference);
        $this->assertStringContainsString('<evtRemun', $event->xml);
        $this->assertStringContainsString('<perApur>2026-07</perApur>', $event->xml);
        $this->assertStringContainsString('<codRubr>', $event->xml); // rubricas da folha
    }

    public function test_a_rota_do_esocial_exige_login(): void
    {
        auth()->logout();
        $this->get('/esocial')->assertRedirect('/entrar');
    }
}
