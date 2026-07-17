<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Rescisão — verbas principais por modalidade:
 *  - saldo de salário (dias trabalhados no mês);
 *  - aviso prévio indenizado (Lei 12.506: 30 dias + 3/ano, máx. 90);
 *  - férias vencidas + 1/3 e proporcionais + 1/3 (indenizatórias: sem INSS/IRRF);
 *  - 13º proporcional (tributado);
 *  - multa FGTS: 40% sem justa causa · 20% acordo (art. 484-A CLT).
 * Simplificação documentada do MVP: não projeta o aviso nos avos.
 */
final class TerminationCalculator
{
    public function __construct(
        private readonly InssCalculator $inss,
        private readonly IrrfCalculator $irrf,
        private readonly ThirteenthCalculator $thirteenth,
    ) {
    }

    /** @return array{items: list<array>, gross: int, deductions: int, net: int, fgts_fine: int} */
    public function calculate(
        int $salaryCents,
        string $hiredAt,
        string $terminationDate,
        string $type,                 // sem_justa_causa | justa_causa | pedido | acordo
        string $notice,               // trabalhado | indenizado | dispensado
        int $fgtsBalanceCents = 0,
        int $pendingVacationDays = 0, // férias vencidas não gozadas
        int $dependents = 0,
    ): array {
        $items = [];
        $daily = $salaryCents / 30;

        // 1. Saldo de salário
        $daysWorked = (int) substr($terminationDate, 8, 2);
        $balance = (int) round($daily * $daysWorked);
        $items[] = ['description' => "Saldo de salário ({$daysWorked} dias)", 'amount' => $balance, 'type' => 'earning', 'taxable' => true];

        // 2. Aviso prévio indenizado (não devido em pedido/justa causa)
        $noticeValue = 0;
        if ($notice === 'indenizado' && in_array($type, ['sem_justa_causa', 'acordo'], true)) {
            $years = (int) ((new \DateTimeImmutable($hiredAt))->diff(new \DateTimeImmutable($terminationDate))->y);
            $noticeDays = min(90, 30 + 3 * $years);
            $noticeValue = (int) round($daily * $noticeDays);
            if ($type === 'acordo') {
                $noticeValue = intdiv($noticeValue, 2); // acordo: metade do aviso
            }
            $items[] = ['description' => "Aviso prévio indenizado ({$noticeDays} dias)", 'amount' => $noticeValue, 'type' => 'earning', 'taxable' => false];
        }

        // 3. Férias vencidas + 1/3 (indenizatórias)
        if ($pendingVacationDays > 0 && $type !== 'justa_causa') {
            $vac = (int) round($daily * $pendingVacationDays);
            $items[] = ['description' => "Férias vencidas ({$pendingVacationDays} dias) + 1/3", 'amount' => $vac + (int) round($vac / 3), 'type' => 'earning', 'taxable' => false];
        }

        // 4. Férias proporcionais + 1/3 (avos do período aquisitivo corrente)
        if ($type !== 'justa_causa') {
            $avos = $this->thirteenth->months($hiredAt, $terminationDate) ?: 0;
            // avos de férias contam do aniversário de admissão; simplificação: mesmos avos
            $propVacation = (int) round($salaryCents * $avos / 12);
            if ($propVacation > 0) {
                $items[] = ['description' => "Férias proporcionais ({$avos}/12) + 1/3", 'amount' => $propVacation + (int) round($propVacation / 3), 'type' => 'earning', 'taxable' => false];
            }
        }

        // 5. 13º proporcional (tributado por fora, exclusivo)
        $thirteenthNet = 0;
        if ($type !== 'justa_causa') {
            $avos13 = $this->thirteenth->months($hiredAt, $terminationDate);
            $calc13 = $this->thirteenth->secondInstallment($salaryCents, $avos13, 0, $dependents);
            $items[] = ['description' => "13º proporcional ({$avos13}/12)", 'amount' => $calc13['gross'], 'type' => 'earning', 'taxable' => false];
            if ($calc13['inss'] > 0) {
                $items[] = ['description' => 'INSS sobre 13º', 'amount' => $calc13['inss'], 'type' => 'deduction', 'taxable' => false];
            }
            if ($calc13['irrf'] > 0) {
                $items[] = ['description' => 'IRRF sobre 13º', 'amount' => $calc13['irrf'], 'type' => 'deduction', 'taxable' => false];
            }
        }

        // 6. INSS/IRRF sobre a parte salarial tributável (saldo de salário)
        $taxableBase = array_sum(array_map(
            fn (array $i) => $i['type'] === 'earning' && $i['taxable'] ? $i['amount'] : 0,
            $items,
        ));
        $inss = $this->inss->calculate($taxableBase);
        if ($inss > 0) {
            $items[] = ['description' => 'INSS', 'amount' => $inss, 'type' => 'deduction', 'taxable' => false];
        }
        $irrf = $this->irrf->calculate($taxableBase, $inss, $dependents);
        if ($irrf > 0) {
            $items[] = ['description' => 'IRRF', 'amount' => $irrf, 'type' => 'deduction', 'taxable' => false];
        }

        // 7. Multa do FGTS
        $fine = match ($type) {
            'sem_justa_causa' => (int) round($fgtsBalanceCents * 0.40),
            'acordo' => (int) round($fgtsBalanceCents * 0.20),
            default => 0,
        };
        if ($fine > 0) {
            $items[] = ['description' => 'Multa FGTS ('.($type === 'acordo' ? '20%' : '40%').')', 'amount' => $fine, 'type' => 'earning', 'taxable' => false];
        }

        $gross = array_sum(array_map(fn (array $i) => $i['type'] === 'earning' ? $i['amount'] : 0, $items));
        $deductions = array_sum(array_map(fn (array $i) => $i['type'] === 'deduction' ? $i['amount'] : 0, $items));

        return [
            'items' => $items,
            'gross' => $gross,
            'deductions' => $deductions,
            'net' => $gross - $deductions,
            'fgts_fine' => $fine,
        ];
    }
}
