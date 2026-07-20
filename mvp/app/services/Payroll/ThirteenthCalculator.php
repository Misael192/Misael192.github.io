<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * 13º salário (Lei 4.090/1962):
 *  - avos: 1/12 por mês com ≥ 15 dias trabalhados;
 *  - 1ª parcela (até 30/11): 50% do proporcional, SEM descontos;
 *  - 2ª parcela (até 20/12): proporcional − INSS − IRRF − 1ª parcela.
 */
final class ThirteenthCalculator
{
    public function __construct(
        private readonly InssCalculator $inss,
        private readonly IrrfCalculator $irrf,
    ) {
    }

    /** Avos entre a admissão (ou 1º/jan) e a data de referência. */
    public function months(string $hiredAt, string $referenceDate): int
    {
        $start = new \DateTimeImmutable(max($hiredAt, substr($referenceDate, 0, 4).'-01-01'));
        $end = new \DateTimeImmutable($referenceDate);
        $months = 0;

        $cursor = $start->modify('first day of this month');
        while ($cursor <= $end) {
            $monthStart = max($start, $cursor);
            $monthEnd = min($end, $cursor->modify('last day of this month'));
            $daysWorked = (int) $monthStart->diff($monthEnd)->days + 1;
            if ($daysWorked >= 15) {
                $months++;
            }
            $cursor = $cursor->modify('+1 month');
        }

        return min(12, $months);
    }

    /** @return array{gross: int, inss: int, irrf: int, advance: int, net: int} */
    public function firstInstallment(int $salaryCents, int $months): array
    {
        $proportional = (int) round($salaryCents * $months / 12);
        $advance = intdiv($proportional, 2);

        return ['gross' => $advance, 'inss' => 0, 'irrf' => 0, 'advance' => 0, 'net' => $advance];
    }

    public function secondInstallment(int $salaryCents, int $months, int $advanceCents, int $dependents = 0): array
    {
        $proportional = (int) round($salaryCents * $months / 12);
        // INSS e IRRF calculados sobre o 13º INTEGRAL (tributação exclusiva)
        $inss = $this->inss->calculate($proportional);
        $irrf = $this->irrf->calculate($proportional, $inss, $dependents);

        return [
            'gross' => $proportional,
            'inss' => $inss,
            'irrf' => $irrf,
            'advance' => $advanceCents,
            'net' => $proportional - $inss - $irrf - $advanceCents,
        ];
    }
}
