<?php

declare(strict_types=1);

namespace Tests\Feature\Cutover;

use App\Core\Tenancy\TenantContext;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Rubric;
use App\Models\TaxTable;
use App\Models\Tenant;
use App\Services\Cutover\CutoverImporter;
use Database\Seeders\PayrollEngineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ETL do cutover: importa o export portátil de uma empresa do MVP para o schema
 * multi-tenant, preservando valores (centavos), cifrando PII, sem duplicar o
 * catálogo global (rubricas/tabelas) e de forma idempotente.
 */
class CutoverImportTest extends TestCase
{
    use RefreshDatabase;

    private function export(): array
    {
        return [
            'tenant' => ['slug' => 'acme', 'name' => 'Acme'],
            'organization' => ['name' => 'Grupo Acme'],
            'company' => ['name' => 'Acme LTDA', 'cnpj' => '12345678000199'],
            'employees' => [[
                'registration_number' => '0001',
                'full_name' => 'Maria da Silva',
                'cpf' => '12345678901',
                'hired_at' => '2024-01-01',
                'status' => 'active',
                'contracts' => [['type' => 'clt', 'salary_cents' => 520000, 'start_date' => '2024-01-01']],
                'dependents' => [['full_name' => 'Filho', 'birth_date' => '2015-01-01', 'counts_for_irrf' => true]],
            ]],
            'periods' => [[
                'competency' => '2026-07',
                'status' => 'closed',
                'payrolls' => [[
                    'registration_number' => '0001',
                    'kind' => 'payslip',
                    'gross_cents' => 520000,
                    'deductions_cents' => 89549,
                    'net_cents' => 430451,
                    'inss_base_cents' => 520000,
                    'irrf_base_cents' => 520000,
                    'fgts_base_cents' => 520000,
                    'items' => [
                        ['rubric_code' => '1000', 'description' => 'Salário Base', 'amount_cents' => 520000, 'type' => 'earning', 'reference' => null],
                        ['rubric_code' => '2000', 'description' => 'INSS', 'amount_cents' => 57349, 'type' => 'deduction', 'reference' => null],
                    ],
                    'charges' => [['type' => 'fgts', 'base_cents' => 520000, 'rate' => 8, 'amount_cents' => 41600]],
                ]],
            ]],
        ];
    }

    public function test_importa_empresa_preservando_valores_e_fechamento(): void
    {
        $summary = (new CutoverImporter)->import($this->export());

        $this->assertSame(['tenant' => 'acme', 'employees' => 1, 'periods' => 1, 'payrolls' => 1], $summary);

        // Escopo do tenant importado para ler os models tenant-scoped.
        app(TenantContext::class)->set(Tenant::query()->where('slug', 'acme')->firstOrFail());

        $period = PayrollPeriod::query()->where('competency', '2026-07')->firstOrFail();
        $this->assertSame(PayrollPeriod::STATUS_CLOSED, $period->status);

        $payroll = Payroll::query()->where('period_id', $period->id)->firstOrFail();
        $this->assertSame(430451, (int) $payroll->net_cents); // líquido preservado
        $this->assertSame(2, $payroll->items()->count());
        $this->assertSame(41600, (int) $payroll->charges()->where('type', 'fgts')->value('amount_cents'));
    }

    public function test_cifra_a_pii_no_destino(): void
    {
        (new CutoverImporter)->import($this->export());
        app(TenantContext::class)->set(Tenant::query()->where('slug', 'acme')->firstOrFail());

        $employee = Employee::query()->where('registration_number', '0001')->firstOrFail();
        $this->assertSame('12345678901', $employee->cpf); // model decifra

        // Em repouso o CPF está cifrado (valor cru difere do texto claro).
        $raw = DB::table('employees')->where('id', $employee->id)->value('cpf');
        $this->assertNotSame('12345678901', $raw);
    }

    public function test_nao_duplica_o_catalogo_global(): void
    {
        $this->seed(PayrollEngineSeeder::class);
        $rubrics = Rubric::query()->count();
        $taxTables = TaxTable::query()->count();

        (new CutoverImporter)->import($this->export());

        $this->assertSame($rubrics, Rubric::query()->count());
        $this->assertSame($taxTables, TaxTable::query()->count());
    }

    public function test_e_idempotente(): void
    {
        $importer = new CutoverImporter;
        $importer->import($this->export());
        $importer->import($this->export()); // reexecução (delta)

        app(TenantContext::class)->set(Tenant::query()->where('slug', 'acme')->firstOrFail());

        $this->assertSame(1, Tenant::query()->where('slug', 'acme')->count());
        $this->assertSame(1, Employee::query()->where('registration_number', '0001')->count());
        $payroll = Payroll::query()->firstOrFail();
        $this->assertSame(1, Payroll::query()->count());
        $this->assertSame(2, $payroll->items()->count()); // itens não acumulam
    }

    public function test_dry_run_conta_sem_escrever(): void
    {
        $plan = (new CutoverImporter)->plan($this->export());

        $this->assertSame(1, $plan['employees']);
        $this->assertSame(1, $plan['periods']);
        $this->assertSame(1, $plan['payrolls']);
        $this->assertSame([], $plan['issues']);

        // Nada foi gravado.
        $this->assertSame(0, Tenant::query()->where('slug', 'acme')->count());
    }

    public function test_dry_run_aponta_folha_sem_colaborador(): void
    {
        $export = $this->export();
        $export['periods'][0]['payrolls'][0]['registration_number'] = '9999'; // matrícula inexistente

        $plan = (new CutoverImporter)->plan($export);

        $this->assertCount(1, $plan['issues']);
        $this->assertStringContainsString('9999', $plan['issues'][0]);
    }

    public function test_comando_dry_run_falha_com_inconsistencia_e_nao_grava(): void
    {
        $export = $this->export();
        $export['periods'][0]['payrolls'][0]['registration_number'] = '9999';
        $path = tempnam(sys_get_temp_dir(), 'cutover').'.json';
        file_put_contents($path, json_encode($export));

        $this->artisan('cutover:import', ['path' => $path, '--dry-run' => true])
            ->expectsOutputToContain('nada foi gravado')
            ->assertFailed();

        @unlink($path);
        $this->assertSame(0, Tenant::query()->where('slug', 'acme')->count());
    }

    public function test_comando_artisan_importa_de_um_arquivo(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cutover').'.json';
        file_put_contents($path, json_encode($this->export()));

        $this->artisan('cutover:import', ['path' => $path])
            ->expectsOutputToContain("tenant 'acme'")
            ->assertSuccessful();

        @unlink($path);

        $this->assertSame(1, Tenant::query()->where('slug', 'acme')->count());
    }
}
