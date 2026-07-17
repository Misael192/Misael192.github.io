<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Férias (CLT arts. 129–145):
 *  - remuneração = salário/30 × dias de gozo + 1/3 constitucional;
 *  - abono pecuniário (venda de até 10 dias) + 1/3 do abono: SEM INSS/IRRF
 *    (verba indenizatória — art. 144 CLT);
 *  - INSS e IRRF incidem só sobre gozo + 1/3.
 */
final class VacationCalculator
{
    public function __construct(
        private readonly InssCalculator $inss,
        private readonly IrrfCalculator $irrf,
    ) {
    }

    /** @return array{items: list<array>, gross: int, inss: int, irrf: int, net: int} */
    public function calculate(int $salaryCents, int $days, int $sellDays = 0, int $dependents = 0): array
    {
        $daily = $salaryCents / 30;

        $vacationPay = (int) round($daily * $days);
        $vacationThird = (int) round($vacationPay / 3);
        $taxableGross = $vacationPay + $vacationThird;

        $allowance = (int) round($daily * $sellDays);
        $allowanceThird = (int) round($allowance / 3);

        $inss = $this->inss->calculate($taxableGross);
        $irrf = $this->irrf->calculate($taxableGross, $inss, $dependents);

        $gross = $taxableGross + $allowance + $allowanceThird;

        $items = [
            ['code' => '1100', 'description' => "Férias ({$days} dias)", 'reference' => $days, 'amount' => $vacationPay, 'type' => 'earning'],
            ['code' => '1100', 'description' => '1/3 constitucional', 'reference' => null, 'amount' => $vacationThird, 'type' => 'earning'],
        ];
        if ($sellDays > 0) {
            $items[] = ['code' => '1100', 'description' => "Abono pecuniário ({$sellDays} dias)", 'reference' => $sellDays, 'amount' => $allowance, 'type' => 'earning'];
            $items[] = ['code' => '1100', 'description' => '1/3 sobre abono', 'reference' => null, 'amount' => $allowanceThird, 'type' => 'earning'];
        }
        if ($inss > 0) {
            $items[] = ['code' => '2000', 'description' => 'INSS sobre férias', 'reference' => null, 'amount' => $inss, 'type' => 'deduction'];
        }
        if ($irrf > 0) {
            $items[] = ['code' => '2001', 'description' => 'IRRF sobre férias', 'reference' => null, 'amount' => $irrf, 'type' => 'deduction'];
        }

        return [
            'items' => $items,
            'gross' => $gross,
            'inss' => $inss,
            'irrf' => $irrf,
            'net' => $gross - $inss - $irrf,
        ];
    }
}
