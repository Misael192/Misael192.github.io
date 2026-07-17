<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * IRRF mensal com as duas sistemáticas legais:
 *  - deduções legais: base = bruto tributável − INSS − (dependentes × dedução);
 *  - desconto simplificado (Lei 14.663/2023): base = bruto − parcela fixa.
 * A retenção aplicada é a MENOR entre as duas (mais benéfica ao trabalhador),
 * como fazem os ERPs de folha.
 */
final class IrrfCalculator
{
    /**
     * @param list<array{up_to: ?int, rate: float, deduction: int}> $brackets em centavos
     * @param int $dependentDeductionCents  dedução por dependente
     * @param int $simplifiedDeductionCents parcela do desconto simplificado
     */
    public function __construct(
        private readonly array $brackets,
        private readonly int $dependentDeductionCents,
        private readonly int $simplifiedDeductionCents,
    ) {
    }

    /** Retenção final (menor entre legal e simplificado). */
    public function calculate(int $taxableGrossCents, int $inssCents, int $dependents = 0): int
    {
        return min(
            $this->calculateLegal($taxableGrossCents, $inssCents, $dependents),
            $this->calculateSimplified($taxableGrossCents),
        );
    }

    public function calculateLegal(int $taxableGrossCents, int $inssCents, int $dependents = 0): int
    {
        $base = $taxableGrossCents - $inssCents - ($dependents * $this->dependentDeductionCents);

        return $this->applyTable($base);
    }

    public function calculateSimplified(int $taxableGrossCents): int
    {
        return $this->applyTable($taxableGrossCents - $this->simplifiedDeductionCents);
    }

    private function applyTable(int $baseCents): int
    {
        if ($baseCents <= 0) {
            return 0;
        }

        foreach ($this->brackets as $bracket) {
            if ($bracket['up_to'] === null || $baseCents <= $bracket['up_to']) {
                $tax = $baseCents * ($bracket['rate'] / 100) - $bracket['deduction'];

                return max(0, (int) round($tax));
            }
        }

        return 0;
    }
}
