<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Database;

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
        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT ON (type) type, brackets
             FROM tax_tables
             WHERE valid_from <= :d AND (valid_to IS NULL OR valid_to >= :d)
             ORDER BY type, valid_from DESC",
        );
        $stmt->execute(['d' => $date]);

        $tables = [];
        foreach ($stmt->fetchAll() as $row) {
            $tables[$row['type']] = json_decode($row['brackets'], true);
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
}
