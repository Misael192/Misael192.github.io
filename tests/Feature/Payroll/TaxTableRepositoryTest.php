<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Services\Payroll\TaxTableRepository;
use Database\Seeders\PayrollEngineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TaxTableRepository — parte Eloquent do motor de folha (rubrics/tax_tables
 * são catálogo global, seedado por PayrollEngineSeeder). Confere que os
 * mesmos valores das calculadoras puras (PayrollCalculatorsTest) saem
 * corretos quando vêm do banco.
 */
class TaxTableRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TaxTableRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PayrollEngineSeeder::class);
        $this->repository = new TaxTableRepository;
    }

    public function test_for_competency_retorna_as_tabelas_vigentes(): void
    {
        $tables = $this->repository->forCompetency('2026-07');

        $this->assertArrayHasKey('inss', $tables);
        $this->assertArrayHasKey('teto_inss', $tables);
        $this->assertArrayHasKey('irrf', $tables);
        $this->assertArrayHasKey('salario_familia', $tables);
        $this->assertArrayHasKey('fgts', $tables);
        $this->assertSame(815741, $tables['teto_inss']['value']);
    }

    public function test_calculadoras_construidas_do_banco_batem_com_as_fixtures(): void
    {
        $tables = $this->repository->forCompetency('2026-07');

        $inss = $this->repository->buildInss($tables);
        $this->assertSame(25341, $inss->calculate(300000));

        $irrf = $this->repository->buildIrrf($tables);
        $this->assertSame(35789, $irrf->calculateSimplified(520000));

        $fgts = $this->repository->buildFgts($tables);
        $this->assertSame(41600, $fgts->calculate(520000));

        $this->assertSame(['limit' => 190604, 'per_child' => 6504], $this->repository->familyAllowance($tables));
    }

    public function test_rubrics_map_traz_as_vinte_rubricas_ativas(): void
    {
        $map = $this->repository->rubricsMap();

        $this->assertCount(20, $map);
        $this->assertSame('overtime_50', $map['1001']['formula']);
        $this->assertSame('termination_item', $map['1400']['formula']);
        $this->assertTrue($map['1000']['incides_inss']);
        $this->assertFalse($map['2000']['incides_inss']);
    }

    public function test_engine_for_calcula_ponta_a_ponta(): void
    {
        $engine = $this->repository->engineFor('2026-07');
        $run = $engine->calculate(['salary_cents' => 520000, 'contract_type' => 'clt']);

        $this->assertSame(520000, $run['gross']);
        $this->assertSame(430451, $run['net']);
    }
}
