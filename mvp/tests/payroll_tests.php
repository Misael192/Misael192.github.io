<?php

declare(strict_types=1);

/**
 * Testes do motor de folha — rode com: php tests/payroll_tests.php
 *
 * As calculadoras são PURAS: nenhum teste toca banco ou HTTP. Os valores
 * esperados foram conferidos manualmente contra as tabelas INSS/IRRF
 * vigentes (2025) usadas nos seeds. Se um cálculo mudar sem intenção,
 * este arquivo acusa.
 */

require __DIR__.'/../app/services/Payroll/InssCalculator.php';
require __DIR__.'/../app/services/Payroll/IrrfCalculator.php';
require __DIR__.'/../app/services/Payroll/FgtsCalculator.php';
require __DIR__.'/../app/services/Payroll/VacationCalculator.php';
require __DIR__.'/../app/services/Payroll/ThirteenthCalculator.php';
require __DIR__.'/../app/services/Payroll/TerminationCalculator.php';
require __DIR__.'/../app/services/Payroll/PayrollEngine.php';

use App\Services\Payroll\FgtsCalculator;
use App\Services\Payroll\InssCalculator;
use App\Services\Payroll\IrrfCalculator;
use App\Services\Payroll\PayrollEngine;
use App\Services\Payroll\TerminationCalculator;
use App\Services\Payroll\ThirteenthCalculator;
use App\Services\Payroll\VacationCalculator;

// ── Mini-runner ──────────────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function check(string $name, mixed $expected, mixed $actual): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        echo "  ✓ {$name}\n";
    } else {
        $failed++;
        $e = var_export($expected, true);
        $a = var_export($actual, true);
        echo "  ✗ {$name}\n      esperado: {$e}\n      obtido:   {$a}\n";
    }
}

// ── Fixtures: tabelas vigentes (mesmos valores dos seeds) ────────────────────
$inss = new InssCalculator([
    ['up_to' => 151800, 'rate' => 7.5],
    ['up_to' => 279388, 'rate' => 9.0],
    ['up_to' => 419083, 'rate' => 12.0],
    ['up_to' => 815741, 'rate' => 14.0],
], 815741);

$irrf = new IrrfCalculator([
    ['up_to' => 242880, 'rate' => 0.0, 'deduction' => 0],
    ['up_to' => 282665, 'rate' => 7.5, 'deduction' => 18216],
    ['up_to' => 375105, 'rate' => 15.0, 'deduction' => 39416],
    ['up_to' => 466468, 'rate' => 22.5, 'deduction' => 67549],
    ['up_to' => null, 'rate' => 27.5, 'deduction' => 90873],
], 18959, 60720);

$fgts = new FgtsCalculator(8.0, 2.0);

$rubrics = [
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
$family = ['limit' => 190604, 'per_child' => 6504];

$engine = new PayrollEngine($inss, $irrf, $fgts, $rubrics, $family);

// ═══ INSS ════════════════════════════════════════════════════════════════════
echo "INSS (progressivo)\n";
check('salário mínimo (R$ 1.518,00) → 7,5%', 11385, $inss->calculate(151800));
check('R$ 3.000,00 → faixas 1+2+3', 25341, $inss->calculate(300000));
check('R$ 5.200,00 → quatro faixas', 53760, $inss->calculate(520000));
check('R$ 10.000,00 → limitado ao teto', 95163, $inss->calculate(1000000));
check('teto exato', 95163, $inss->calculate(815741));

// ═══ IRRF ════════════════════════════════════════════════════════════════════
echo "IRRF\n";
check('faixa isenta', 0, $irrf->calculate(240000, 0, 0));
check('R$ 5.200 legal (0 dep)', 37355, $irrf->calculateLegal(520000, 53760, 0));
check('R$ 5.200 simplificado', 35789, $irrf->calculateSimplified(520000));
check('R$ 5.200 → aplica o menor (simplificado)', 35789, $irrf->calculate(520000, 53760, 0));
check('R$ 5.200 com 2 dependentes → legal vence', 28823, $irrf->calculate(520000, 53760, 2));

// ═══ FGTS ════════════════════════════════════════════════════════════════════
echo "FGTS\n";
check('8% padrão sobre R$ 5.200', 41600, $fgts->calculate(520000));
check('2% aprendiz sobre R$ 1.000', 2000, $fgts->calculate(100000, 'aprendiz'));

// ═══ Férias ══════════════════════════════════════════════════════════════════
echo "Férias\n";
$vacation = new VacationCalculator($inss, $irrf);
$v = $vacation->calculate(300000, 30, 0, 0);
check('30 dias de R$ 3.000: bruto = férias + 1/3', 400000, $v['gross']);
check('INSS sobre férias + 1/3', 37341, $v['inss']);
check('IRRF sobre férias (simplificado vence)', 11476, $v['irrf']);
check('líquido de férias', 351183, $v['net']);
$v2 = $vacation->calculate(300000, 20, 10, 0);
check('venda de 10 dias: abono + 1/3 sem tributos no bruto', true,
    $v2['gross'] === (int) round(300000 / 30 * 20) + (int) round(300000 / 30 * 20 / 3)
        + (int) round(300000 / 30 * 10) + (int) round(300000 / 30 * 10 / 3));

// ═══ 13º salário ═════════════════════════════════════════════════════════════
echo "13º salário\n";
$thirteenth = new ThirteenthCalculator($inss, $irrf);
check('avos: admitido 10/01, referência 14/07 → 6 avos', 6, $thirteenth->months('2026-01-10', '2026-07-14'));
check('avos: ano completo → 12', 12, $thirteenth->months('2020-03-01', '2026-12-20'));
$p1 = $thirteenth->firstInstallment(240000, 12);
check('1ª parcela: metade sem descontos', 120000, $p1['net']);
$p2 = $thirteenth->secondInstallment(240000, 12, 120000, 0);
check('2ª parcela: INSS sobre o integral', 19323, $p2['inss']);
check('2ª parcela: IRRF isento nesta faixa', 0, $p2['irrf']);
check('2ª parcela: líquido = integral − INSS − adiantamento', 100677, $p2['net']);

// ═══ Rescisão ════════════════════════════════════════════════════════════════
echo "Rescisão\n";
$termination = new TerminationCalculator($inss, $irrf, $thirteenth);
$t = $termination->calculate(300000, '2024-01-01', '2026-07-15', 'sem_justa_causa', 'indenizado', 1000000, 0, 0);
check('multa FGTS 40% de R$ 10.000', 400000, $t['fgts_fine']);
$noticeItem = array_values(array_filter($t['items'], fn ($i) => str_contains($i['description'], 'Aviso prévio')))[0] ?? null;
check('aviso prévio: 30 + 3×2 anos = 36 dias', true, $noticeItem !== null && str_contains($noticeItem['description'], '36 dias'));
check('aviso indenizado = 36 diárias', 360000, $noticeItem['amount']);
check('líquido = bruto − descontos', $t['gross'] - $t['deductions'], $t['net']);
$tj = $termination->calculate(300000, '2024-01-01', '2026-07-15', 'justa_causa', 'dispensado', 1000000, 0, 0);
check('justa causa: sem multa FGTS', 0, $tj['fgts_fine']);
check('justa causa: sem 13º proporcional', true,
    array_filter($tj['items'], fn ($i) => str_contains($i['description'], '13º')) === []);

// ═══ Horas extras e banco de horas (via engine) ══════════════════════════════
echo "Horas extras\n";
$he = $engine->calculate(
    ['salary_cents' => 220000, 'contract_type' => 'clt'],
    ['events' => [['rubric_code' => '1001', 'reference' => 10.0, 'amount_cents' => null]]],
);
$heItem = array_values(array_filter($he['items'], fn ($i) => $i['code'] === '1001'))[0];
check('HE 50%: 10h sobre R$ 2.200 (divisor 220) = R$ 150,00', 15000, $heItem['amount']);
$he100 = $engine->calculate(
    ['salary_cents' => 220000, 'contract_type' => 'clt'],
    ['events' => [['rubric_code' => '1002', 'reference' => 5.0, 'amount_cents' => null]]],
);
$he100Item = array_values(array_filter($he100['items'], fn ($i) => $i['code'] === '1002'))[0];
check('HE 100%: 5h = R$ 100,00', 10000, $he100Item['amount']);

// ═══ Salário líquido (integração da engine) ═════════════════════════════════
echo "Salário líquido (engine completa)\n";
$run = $engine->calculate(['salary_cents' => 520000, 'contract_type' => 'clt']);
check('bruto', 520000, $run['gross']);
check('base INSS', 520000, $run['inss_base']);
check('líquido = 5.200 − INSS 537,60 − IRRF 357,89', 430451, $run['net']);
check('FGTS informado como encargo (não no líquido)', 41600, $run['charges'][0]['amount']);

$vt = $engine->calculate(
    ['salary_cents' => 220000, 'contract_type' => 'clt'],
    ['benefits' => [['type' => 'vt', 'description' => 'VT', 'amount_cents' => 30000,
        'employee_share_percent' => null, 'employee_share_cents' => null]]],
);
$vtItem = array_values(array_filter($vt['items'], fn ($i) => $i['code'] === '2002'))[0];
check('VT: desconto limitado a 6% do salário', 13200, $vtItem['amount']);

$family_run = $engine->calculate(
    ['salary_cents' => 151800, 'contract_type' => 'clt'],
    ['children_under_14' => 2],
);
$famItem = array_values(array_filter($family_run['items'], fn ($i) => $i['code'] === '1300'))[0] ?? null;
check('salário família: 2 filhos dentro do limite', 13008, $famItem['amount'] ?? null);

// ── Resultado ────────────────────────────────────────────────────────────────
echo "\n{$passed} passaram · {$failed} falharam\n";
exit($failed > 0 ? 1 : 0);
