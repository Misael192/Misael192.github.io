<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/** FGTS: 8% (2% para aprendiz). Encargo do empregador — não sai do líquido. */
final class FgtsCalculator
{
    public function __construct(
        private readonly float $rate = 8.0,
        private readonly float $apprenticeRate = 2.0,
    ) {
    }

    public function calculate(int $baseCents, string $contractType = 'clt'): int
    {
        $rate = $contractType === 'aprendiz' ? $this->apprenticeRate : $this->rate;

        return (int) round($baseCents * ($rate / 100));
    }

    public function rateFor(string $contractType = 'clt'): float
    {
        return $contractType === 'aprendiz' ? $this->apprenticeRate : $this->rate;
    }
}
