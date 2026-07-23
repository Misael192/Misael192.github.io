<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Rubric;
use App\Models\TaxTable;

/**
 * Carrega as tabelas oficiais vigentes na competência e monta as
 * calculadoras. Único ponto de contato entre o banco e a engine.
 */
final class TaxTableRepository
{
    /** @return array<string, array> brackets por tipo, vigentes na competência */
    public function forCompetency(string $competency): array
    {
        $date = $competency.'-01';

        $rows = TaxTable::query()
            ->where('valid_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date))
            ->orderByDesc('valid_from')
            ->get(['type', 'brackets']);

        $tables = [];
        foreach ($rows as $row) {
            $tables[$row->type] ??= $row->brackets;
        }

        return $tables;
    }

    public function buildInss(array $tables): InssCalculator
    {
        return new InssCalculator(
            array_map(fn (array $b) => ['up_to' => (int) $b['up_to'], 'rate' => (float) $b['rate']], $tables['inss']),
            (int) $tables['teto_inss']['value'],
        );
    }

    public function buildIrrf(array $tables): IrrfCalculator
    {
        $t = $tables['irrf'];

        return new IrrfCalculator(
            array_map(fn (array $b) => [
                'up_to' => $b['up_to'] !== null ? (int) $b['up_to'] : null,
                'rate' => (float) $b['rate'],
                'deduction' => (int) $b['deduction'],
            ], $t['brackets']),
            (int) $t['dependent_deduction'],
            (int) $t['simplified_deduction'],
        );
    }

    public function buildFgts(array $tables): FgtsCalculator
    {
        return new FgtsCalculator((float) $tables['fgts']['rate'], (float) $tables['fgts']['apprentice_rate']);
    }

    /** @return array{limit: int, per_child: int} */
    public function familyAllowance(array $tables): array
    {
        return [
            'limit' => (int) ($tables['salario_familia']['limit'] ?? 0),
            'per_child' => (int) ($tables['salario_familia']['per_child'] ?? 0),
        ];
    }

    /** @return array<string, array{type: string, name: string, incides_inss: bool, incides_irrf: bool, incides_fgts: bool, formula: ?string}> */
    public function rubricsMap(): array
    {
        $map = [];
        foreach (Rubric::query()->where('is_active', true)->get() as $rubric) {
            $map[$rubric->code] = [
                'type' => $rubric->type,
                'name' => $rubric->name,
                'incides_inss' => $rubric->incides_inss,
                'incides_irrf' => $rubric->incides_irrf,
                'incides_fgts' => $rubric->incides_fgts,
                'formula' => $rubric->formula,
            ];
        }

        return $map;
    }

    /** Monta a engine já pronta para a competência informada. */
    public function engineFor(string $competency): PayrollEngine
    {
        $tables = $this->forCompetency($competency);

        return new PayrollEngine(
            $this->buildInss($tables),
            $this->buildIrrf($tables),
            $this->buildFgts($tables),
            $this->rubricsMap(),
            $this->familyAllowance($tables),
        );
    }
}
