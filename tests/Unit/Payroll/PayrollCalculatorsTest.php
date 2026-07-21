<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Services\Payroll\FgtsCalculator;
use App\Services\Payroll\InssCalculator;
use App\Services\Payroll\IrrfCalculator;
use App\Services\Payroll\PayrollEngine;
use App\Services\Payroll\TerminationCalculator;
use App\Services\Payroll\ThirteenthCalculator;
use App\Services\Payroll\VacationCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Motor de folha — calculadoras PURAS (sem banco). Espelha as asserções
 * de mvp/tests/payroll_tests.php, já conferidas manualmente contra as
 * tabelas INSS/IRRF vigentes (2025) usadas nos seeds.
 */
class PayrollCalculatorsTest extends TestCase
{
    private InssCalculator $inss;

    private IrrfCalculator $irrf;

    private FgtsCalculator $fgts;

    private array $rubrics;

    private PayrollEngine $engine;

    protected function setUp(): void
    {
        $this->inss = new InssCalculator([
            ['up_to' => 151800, 'rate' => 7.5],
            ['up_to' => 279388, 'rate' => 9.0],
            ['up_to' => 419083, 'rate' => 12.0],
            ['up_to' => 815741, 'rate' => 14.0],
        ], 815741);

        $this->irrf = new IrrfCalculator([
            ['up_to' => 242880, 'rate' => 0.0, 'deduction' => 0],
            ['up_to' => 282665, 'rate' => 7.5, 'deduction' => 18216],
            ['up_to' => 375105, 'rate' => 15.0, 'deduction' => 39416],
            ['up_to' => 466468, 'rate' => 22.5, 'deduction' => 67549],
            ['up_to' => null, 'rate' => 27.5, 'deduction' => 90873],
        ], 18959, 60720);

        $this->fgts = new FgtsCalculator(8.0, 2.0);

        $this->rubrics = [
            '1000' => ['type' => 'earning', 'name' => 'Salário Base', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'base_salary'],
            '1001' => ['type' => 'earning', 'name' => 'Hora Extra 50%', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'overtime_50'],
            '1002' => ['type' => 'earning', 'name' => 'Hora Extra 100%', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'overtime_100'],
            '1300' => ['type' => 'earning', 'name' => 'Salário Família', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'family_allowance'],
            '2000' => ['type' => 'deduction', 'name' => 'INSS', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'inss'],
            '2001' => ['type' => 'deduction', 'name' => 'IRRF', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'irrf'],
            '2002' => ['type' => 'deduction', 'name' => 'Vale Transporte (6%)', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'vt_discount'],
            '2005' => ['type' => 'deduction', 'name' => 'Faltas', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'absence'],
            '2006' => ['type' => 'deduction', 'name' => 'Desconto diverso', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'manual'],
            '3000' => ['type' => 'info', 'name' => 'FGTS', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'fgts'],
        ];

        $this->engine = new PayrollEngine($this->inss, $this->irrf, $this->fgts, $this->rubrics, ['limit' => 190604, 'per_child' => 6504]);
    }

    // ═══ INSS ═══════════════════════════════════════════════════════════════

    public function test_inss_salario_minimo_uma_faixa(): void
    {
        $this->assertSame(11385, $this->inss->calculate(151800));
    }

    public function test_inss_tres_mil_tres_faixas(): void
    {
        $this->assertSame(25341, $this->inss->calculate(300000));
    }

    public function test_inss_cinco_mil_e_duzentos_quatro_faixas(): void
    {
        $this->assertSame(53760, $this->inss->calculate(520000));
    }

    public function test_inss_dez_mil_limitado_ao_teto(): void
    {
        $this->assertSame(95163, $this->inss->calculate(1000000));
    }

    public function test_inss_teto_exato(): void
    {
        $this->assertSame(95163, $this->inss->calculate(815741));
    }

    // ═══ IRRF ═══════════════════════════════════════════════════════════════

    public function test_irrf_faixa_isenta(): void
    {
        $this->assertSame(0, $this->irrf->calculate(240000, 0, 0));
    }

    public function test_irrf_legal_sem_dependentes(): void
    {
        $this->assertSame(37355, $this->irrf->calculateLegal(520000, 53760, 0));
    }

    public function test_irrf_simplificado(): void
    {
        $this->assertSame(35789, $this->irrf->calculateSimplified(520000));
    }

    public function test_irrf_aplica_o_menor_simplificado(): void
    {
        $this->assertSame(35789, $this->irrf->calculate(520000, 53760, 0));
    }

    public function test_irrf_com_dois_dependentes_legal_vence(): void
    {
        $this->assertSame(28823, $this->irrf->calculate(520000, 53760, 2));
    }

    // ═══ FGTS ═══════════════════════════════════════════════════════════════

    public function test_fgts_oito_por_cento_padrao(): void
    {
        $this->assertSame(41600, $this->fgts->calculate(520000));
    }

    public function test_fgts_dois_por_cento_aprendiz(): void
    {
        $this->assertSame(2000, $this->fgts->calculate(100000, 'aprendiz'));
    }

    // ═══ Férias ═════════════════════════════════════════════════════════════

    public function test_ferias_trinta_dias_bruto(): void
    {
        $vacation = new VacationCalculator($this->inss, $this->irrf);
        $v = $vacation->calculate(300000, 30, 0, 0);

        $this->assertSame(400000, $v['gross']);
        $this->assertSame(37341, $v['inss']);
        $this->assertSame(11476, $v['irrf']);
        $this->assertSame(351183, $v['net']);
    }

    public function test_ferias_venda_de_dez_dias_abono_sem_tributo_no_bruto(): void
    {
        $vacation = new VacationCalculator($this->inss, $this->irrf);
        $v2 = $vacation->calculate(300000, 20, 10, 0);

        $expected = (int) round(300000 / 30 * 20) + (int) round(300000 / 30 * 20 / 3)
            + (int) round(300000 / 30 * 10) + (int) round(300000 / 30 * 10 / 3);
        $this->assertSame($expected, $v2['gross']);
    }

    // ═══ 13º salário ════════════════════════════════════════════════════════

    public function test_decimo_terceiro_avos_seis_meses(): void
    {
        $thirteenth = new ThirteenthCalculator($this->inss, $this->irrf);
        $this->assertSame(6, $thirteenth->months('2026-01-10', '2026-07-14'));
    }

    public function test_decimo_terceiro_avos_ano_completo(): void
    {
        $thirteenth = new ThirteenthCalculator($this->inss, $this->irrf);
        $this->assertSame(12, $thirteenth->months('2020-03-01', '2026-12-20'));
    }

    public function test_decimo_terceiro_primeira_parcela_metade_sem_descontos(): void
    {
        $thirteenth = new ThirteenthCalculator($this->inss, $this->irrf);
        $p1 = $thirteenth->firstInstallment(240000, 12);
        $this->assertSame(120000, $p1['net']);
    }

    public function test_decimo_terceiro_segunda_parcela(): void
    {
        $thirteenth = new ThirteenthCalculator($this->inss, $this->irrf);
        $p2 = $thirteenth->secondInstallment(240000, 12, 120000, 0);

        $this->assertSame(19323, $p2['inss']);
        $this->assertSame(0, $p2['irrf']);
        $this->assertSame(100677, $p2['net']);
    }

    // ═══ Rescisão ═══════════════════════════════════════════════════════════

    public function test_rescisao_sem_justa_causa(): void
    {
        $thirteenth = new ThirteenthCalculator($this->inss, $this->irrf);
        $termination = new TerminationCalculator($this->inss, $this->irrf, $thirteenth);
        $t = $termination->calculate(300000, '2024-01-01', '2026-07-15', 'sem_justa_causa', 'indenizado', 1000000, 0, 0);

        $this->assertSame(400000, $t['fgts_fine']);
        $this->assertSame($t['gross'] - $t['deductions'], $t['net']);

        $noticeItem = array_values(array_filter($t['items'], fn ($i) => str_contains($i['description'], 'Aviso prévio')))[0] ?? null;
        $this->assertNotNull($noticeItem);
        $this->assertStringContainsString('36 dias', $noticeItem['description']);
        $this->assertSame(360000, $noticeItem['amount']);
    }

    public function test_rescisao_justa_causa_sem_multa_e_sem_decimo_terceiro(): void
    {
        $thirteenth = new ThirteenthCalculator($this->inss, $this->irrf);
        $termination = new TerminationCalculator($this->inss, $this->irrf, $thirteenth);
        $tj = $termination->calculate(300000, '2024-01-01', '2026-07-15', 'justa_causa', 'dispensado', 1000000, 0, 0);

        $this->assertSame(0, $tj['fgts_fine']);
        $this->assertSame([], array_values(array_filter($tj['items'], fn ($i) => str_contains($i['description'], '13º'))));
    }

    // ═══ Horas extras (via engine) ═════════════════════════════════════════

    public function test_engine_hora_extra_cinquenta_por_cento(): void
    {
        $he = $this->engine->calculate(
            ['salary_cents' => 220000, 'contract_type' => 'clt'],
            ['events' => [['rubric_code' => '1001', 'reference' => 10.0, 'amount_cents' => null]]],
        );
        $heItem = array_values(array_filter($he['items'], fn ($i) => $i['code'] === '1001'))[0];
        $this->assertSame(15000, $heItem['amount']);
    }

    public function test_engine_hora_extra_cem_por_cento(): void
    {
        $he100 = $this->engine->calculate(
            ['salary_cents' => 220000, 'contract_type' => 'clt'],
            ['events' => [['rubric_code' => '1002', 'reference' => 5.0, 'amount_cents' => null]]],
        );
        $he100Item = array_values(array_filter($he100['items'], fn ($i) => $i['code'] === '1002'))[0];
        $this->assertSame(10000, $he100Item['amount']);
    }

    // ═══ Salário líquido (integração da engine) ═══════════════════════════

    public function test_engine_salario_liquido_completo(): void
    {
        $run = $this->engine->calculate(['salary_cents' => 520000, 'contract_type' => 'clt']);

        $this->assertSame(520000, $run['gross']);
        $this->assertSame(520000, $run['inss_base']);
        $this->assertSame(430451, $run['net']);
        $this->assertSame(41600, $run['charges'][0]['amount']);
    }

    public function test_engine_vale_transporte_limitado_a_seis_por_cento(): void
    {
        $vt = $this->engine->calculate(
            ['salary_cents' => 220000, 'contract_type' => 'clt'],
            ['benefits' => [['type' => 'vt', 'description' => 'VT', 'amount_cents' => 30000,
                'employee_share_percent' => null, 'employee_share_cents' => null]]],
        );
        $vtItem = array_values(array_filter($vt['items'], fn ($i) => $i['code'] === '2002'))[0];
        $this->assertSame(13200, $vtItem['amount']);
    }

    public function test_engine_salario_familia_dois_filhos_dentro_do_limite(): void
    {
        $familyRun = $this->engine->calculate(
            ['salary_cents' => 151800, 'contract_type' => 'clt'],
            ['children_under_14' => 2],
        );
        $famItem = array_values(array_filter($familyRun['items'], fn ($i) => $i['code'] === '1300'))[0] ?? null;
        $this->assertSame(13008, $famItem['amount'] ?? null);
    }
}
