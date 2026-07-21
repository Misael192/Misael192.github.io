<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * INSS progressivo (Lei 14.331/2022 em diante): cada faixa tributa apenas
 * a parcela que lhe cabe; acima do teto, contribui-se pelo teto.
 *
 * Calculadora PURA: recebe as faixas no construtor (vindas de tax_tables),
 * não conhece banco nem telas. Tudo em CENTAVOS (int) — nunca float em dinheiro.
 */
final class InssCalculator
{
    /**
     * @param  list<array{up_to: int, rate: float}>  $brackets  faixas em centavos, ordenadas
     * @param  int  $ceilingCents  teto previdenciário
     */
    public function __construct(
        private readonly array $brackets,
        private readonly int $ceilingCents,
    ) {}

    public function calculate(int $baseCents): int
    {
        $base = min($baseCents, $this->ceilingCents);
        $total = 0.0;
        $previousLimit = 0;

        foreach ($this->brackets as $bracket) {
            if ($base <= $previousLimit) {
                break;
            }
            $taxableInBracket = min($base, $bracket['up_to']) - $previousLimit;
            $total += $taxableInBracket * ($bracket['rate'] / 100);
            $previousLimit = $bracket['up_to'];
        }

        return (int) round($total);
    }

    /** Alíquota efetiva (para exibição no holerite). */
    public function effectiveRate(int $baseCents): float
    {
        return $baseCents > 0 ? round($this->calculate($baseCents) / $baseCents * 100, 2) : 0.0;
    }
}
