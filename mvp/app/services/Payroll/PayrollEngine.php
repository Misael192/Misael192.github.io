<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Motor de folha (Fase 3). PURO: recebe colaborador + contexto (eventos,
 * benefícios, descontos, rubricas) e devolve itens e totais — nenhuma tela
 * faz conta, nenhum SQL aqui dentro. Reutilizável em holerite, férias, 13º,
 * rescisão, eSocial e API.
 *
 * Pipeline: salário base → proventos/eventos → benefícios → descontos
 *           → salário família → INSS → IRRF → FGTS → líquido.
 *
 * Convenções: dinheiro em CENTAVOS (int); divisor de horas = 220 (mês CLT).
 */
final class PayrollEngine
{
    private const HOURS_DIVISOR = 220;

    public function __construct(
        private readonly InssCalculator $inss,
        private readonly IrrfCalculator $irrf,
        private readonly FgtsCalculator $fgts,
        /** @var array<string, array{type: string, name: string, incides_inss: bool, incides_irrf: bool, incides_fgts: bool, formula: ?string}> */
        private readonly array $rubrics,
        /** @var array{limit: int, per_child: int} */
        private readonly array $familyAllowance = ['limit' => 0, 'per_child' => 0],
    ) {
    }

    /**
     * @param array{salary_cents: int, contract_type: string} $employee
     * @param array{
     *   events?: list<array{rubric_code: string, reference: ?float, amount_cents: ?int}>,
     *   benefits?: list<array{type: string, description: ?string, amount_cents: int,
     *                         employee_share_percent: ?float, employee_share_cents: ?int}>,
     *   discounts?: list<array{description: string, amount_cents: int}>,
     *   dependents_irrf?: int, children_under_14?: int
     * } $context
     * @return array{items: list<array>, gross: int, deductions: int, net: int,
     *               inss_base: int, irrf_base: int, fgts_base: int,
     *               charges: list<array{type: string, base: int, rate: float, amount: int}>}
     */
    public function calculate(array $employee, array $context = []): array
    {
        $salary = (int) $employee['salary_cents'];
        $items = [];

        // ── 1. Salário base ─────────────────────────────────────────────────
        $items[] = $this->item('1000', 'Salário Base', 30.0, $salary);

        // ── 2. Eventos variáveis (HE, adicionais, comissões, faltas…) ──────
        foreach ($context['events'] ?? [] as $event) {
            $items[] = $this->resolveEvent($event, $salary);
        }

        // ── 3. Benefícios → desconto do colaborador ────────────────────────
        foreach ($context['benefits'] ?? [] as $benefit) {
            $item = $this->resolveBenefitShare($benefit, $salary);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        // ── 4. Descontos recorrentes (consignado etc.) ─────────────────────
        foreach ($context['discounts'] ?? [] as $discount) {
            $items[] = $this->item('2006', $discount['description'], null, (int) $discount['amount_cents']);
        }

        // ── 5. Bases de incidência (definidas pelas RUBRICAS, não por código)
        $inssBase = $irrfBase = $fgtsBase = 0;
        foreach ($items as $item) {
            if ($item['type'] !== 'earning') {
                continue;
            }
            $rubric = $this->rubrics[$item['code']];
            $signal = $item['code'] === '2005' ? -1 : 1; // faltas reduzem base — tratadas como deduction abaixo
            $inssBase += $rubric['incides_inss'] ? $signal * $item['amount'] : 0;
            $irrfBase += $rubric['incides_irrf'] ? $signal * $item['amount'] : 0;
            $fgtsBase += $rubric['incides_fgts'] ? $signal * $item['amount'] : 0;
        }
        // Faltas (deduction que REDUZ base de incidência)
        foreach ($items as $item) {
            if ($item['code'] === '2005') {
                $inssBase -= $item['amount'];
                $irrfBase -= $item['amount'];
                $fgtsBase -= $item['amount'];
            }
        }

        // ── 6. Salário família (se dentro do limite) ───────────────────────
        $children = (int) ($context['children_under_14'] ?? 0);
        if ($children > 0 && $this->familyAllowance['limit'] > 0 && $inssBase <= $this->familyAllowance['limit']) {
            $items[] = $this->item('1300', "Salário família ({$children})", (float) $children,
                $children * $this->familyAllowance['per_child']);
        }

        // ── 7. INSS ─────────────────────────────────────────────────────────
        $inssValue = $this->inss->calculate($inssBase);
        if ($inssValue > 0) {
            $items[] = $this->item('2000', 'INSS', $this->inss->effectiveRate($inssBase), $inssValue);
        }

        // ── 8. IRRF (menor entre deduções legais e simplificado) ───────────
        $irrfValue = $this->irrf->calculate($irrfBase, $inssValue, (int) ($context['dependents_irrf'] ?? 0));
        if ($irrfValue > 0) {
            $items[] = $this->item('2001', 'IRRF', null, $irrfValue);
        }

        // ── 9. FGTS (encargo patronal — informativo) ───────────────────────
        $fgtsValue = $this->fgts->calculate($fgtsBase, $employee['contract_type'] ?? 'clt');
        $items[] = $this->item('3000', 'FGTS (depósito do mês)', $this->fgts->rateFor($employee['contract_type'] ?? 'clt'), $fgtsValue);

        // ── 10. Totais ──────────────────────────────────────────────────────
        $gross = $deductions = 0;
        foreach ($items as $item) {
            if ($item['type'] === 'earning') {
                $gross += $item['amount'];
            } elseif ($item['type'] === 'deduction') {
                $deductions += $item['amount'];
            }
        }

        return [
            'items' => $items,
            'gross' => $gross,
            'deductions' => $deductions,
            'net' => $gross - $deductions,
            'inss_base' => $inssBase,
            'irrf_base' => $irrfBase,
            'fgts_base' => $fgtsBase,
            'charges' => [
                ['type' => 'fgts', 'base' => $fgtsBase,
                    'rate' => $this->fgts->rateFor($employee['contract_type'] ?? 'clt'), 'amount' => $fgtsValue],
            ],
        ];
    }

    /** Resolve um evento variável pela fórmula da rubrica. */
    private function resolveEvent(array $event, int $salary): array
    {
        $code = $event['rubric_code'];
        $rubric = $this->rubrics[$code] ?? throw new \InvalidArgumentException("Rubrica {$code} desconhecida");
        $reference = $event['reference'] !== null ? (float) $event['reference'] : null;
        $hourly = $salary / self::HOURS_DIVISOR;

        $amount = match ($rubric['formula']) {
            'overtime_50' => (int) round($hourly * 1.5 * $reference),
            'overtime_100' => (int) round($hourly * 2.0 * $reference),
            'night_shift' => (int) round($hourly * 0.2 * $reference),   // adicional 20%
            'hazard_30' => (int) round($salary * 0.30),
            'absence' => (int) round($salary / 30 * $reference),
            default => (int) ($event['amount_cents']
                ?? throw new \InvalidArgumentException("Rubrica {$code} exige valor manual")),
        };

        return $this->item($code, $rubric['name'], $reference, $amount);
    }

    /** Desconto do colaborador em benefício (VT limitado a 6% do salário). */
    private function resolveBenefitShare(array $benefit, int $salary): ?array
    {
        if ($benefit['type'] === 'vt') {
            // CLT/Lei 7.418: desconto = menor entre 6% do salário e o custo do VT
            $share = min((int) round($salary * 0.06), (int) $benefit['amount_cents']);

            return $share > 0 ? $this->item('2002', 'Vale Transporte (6%)', 6.0, $share) : null;
        }

        $share = $benefit['employee_share_cents']
            ?? ($benefit['employee_share_percent'] !== null
                ? (int) round($salary * ((float) $benefit['employee_share_percent'] / 100))
                : 0);

        if ($share <= 0) {
            return null;
        }

        $code = $benefit['type'] === 'saude' ? '2004' : '2003';

        return $this->item($code, $benefit['description'] ?? $this->rubrics[$code]['name'], null, (int) $share);
    }

    private function item(string $code, string $description, ?float $reference, int $amount): array
    {
        return [
            'code' => $code,
            'description' => $description,
            'reference' => $reference,
            'amount' => $amount,
            'type' => $this->rubrics[$code]['type'],
        ];
    }
}
