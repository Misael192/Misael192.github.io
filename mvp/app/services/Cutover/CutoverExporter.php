<?php

declare(strict_types=1);

namespace App\Services\Cutover;

use PDO;

/**
 * Exporta uma empresa do MVP no formato portátil consumido pelo comando
 * `cutover:import` da plataforma Laravel (ver docs/CUTOVER.md). Só LÊ o banco
 * (nenhuma escrita) — recebe um PDO para ser testável fora do Postgres.
 *
 * Dinheiro permanece em centavos (int). O mapeamento de colunas MVP → chaves do
 * import: employees.registration → registration_number; o salário do MVP
 * (employees.salary_cents) vira um contrato CLT vigente desde a admissão.
 */
final class CutoverExporter
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @return array<string, mixed> estrutura pronta para o cutover:import
     */
    public function export(int $companyId, string $tenantSlug, ?string $organizationName = null): array
    {
        $company = $this->one('SELECT id, name, cnpj FROM companies WHERE id = ?', [$companyId]);
        if ($company === null) {
            throw new \RuntimeException("Empresa {$companyId} não encontrada.");
        }

        return [
            'tenant' => ['slug' => $tenantSlug, 'name' => $organizationName ?? $company['name']],
            'organization' => ['name' => $organizationName ?? $company['name']],
            'company' => ['name' => $company['name'], 'cnpj' => $company['cnpj']],
            'employees' => $this->employees($companyId),
            'periods' => $this->periods($companyId),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function employees(int $companyId): array
    {
        $rows = $this->all(
            'SELECT id, registration, full_name, cpf, birth_date, status, hired_at, salary_cents
             FROM employees WHERE company_id = ? ORDER BY registration',
            [$companyId],
        );

        return array_map(fn (array $e) => [
            'registration_number' => $e['registration'],
            'full_name' => $e['full_name'],
            'cpf' => $e['cpf'],
            'birth_date' => $e['birth_date'],
            'status' => $e['status'],
            'hired_at' => $e['hired_at'],
            'contracts' => $e['salary_cents'] !== null ? [[
                'type' => 'clt',
                'salary_cents' => (int) $e['salary_cents'],
                'start_date' => $e['hired_at'],
            ]] : [],
            'dependents' => $this->dependents((int) $e['id']),
        ], $rows);
    }

    /** @return list<array<string, mixed>> */
    private function dependents(int $employeeId): array
    {
        $rows = $this->all(
            'SELECT name, birth_date, relationship FROM employee_dependents WHERE employee_id = ? ORDER BY id',
            [$employeeId],
        );

        // No MVP todo dependente cadastrado conta para o IRRF (como a engine usa).
        return array_map(fn (array $d) => [
            'full_name' => $d['name'],
            'birth_date' => $d['birth_date'],
            'relationship' => $d['relationship'],
            'counts_for_irrf' => true,
        ], $rows);
    }

    /** @return list<array<string, mixed>> */
    private function periods(int $companyId): array
    {
        $periods = $this->all(
            'SELECT id, competency, status FROM payroll_periods WHERE company_id = ? ORDER BY competency',
            [$companyId],
        );

        return array_map(fn (array $p) => [
            'competency' => $p['competency'],
            'status' => $p['status'],
            'payrolls' => $this->payrolls((int) $p['id']),
        ], $periods);
    }

    /** @return list<array<string, mixed>> */
    private function payrolls(int $periodId): array
    {
        $rows = $this->all(
            'SELECT p.id, p.kind, p.gross_cents, p.deductions_cents, p.net_cents,
                    p.inss_base_cents, p.irrf_base_cents, p.fgts_base_cents, e.registration
             FROM payrolls p JOIN employees e ON e.id = p.employee_id
             WHERE p.period_id = ? ORDER BY e.registration, p.kind',
            [$periodId],
        );

        return array_map(fn (array $p) => [
            'registration_number' => $p['registration'],
            'kind' => $p['kind'],
            'gross_cents' => (int) $p['gross_cents'],
            'deductions_cents' => (int) $p['deductions_cents'],
            'net_cents' => (int) $p['net_cents'],
            'inss_base_cents' => (int) $p['inss_base_cents'],
            'irrf_base_cents' => (int) $p['irrf_base_cents'],
            'fgts_base_cents' => (int) $p['fgts_base_cents'],
            'items' => $this->items((int) $p['id']),
            'charges' => $this->charges((int) $p['id']),
        ], $rows);
    }

    /** @return list<array<string, mixed>> */
    private function items(int $payrollId): array
    {
        $rows = $this->all(
            'SELECT rubric_code, description, reference, amount_cents, type
             FROM payroll_items WHERE payroll_id = ? ORDER BY rubric_code',
            [$payrollId],
        );

        return array_map(fn (array $i) => [
            'rubric_code' => $i['rubric_code'],
            'description' => $i['description'],
            'reference' => $i['reference'] !== null ? (float) $i['reference'] : null,
            'amount_cents' => (int) $i['amount_cents'],
            'type' => $i['type'],
        ], $rows);
    }

    /** @return list<array<string, mixed>> */
    private function charges(int $payrollId): array
    {
        $rows = $this->all(
            'SELECT type, base_cents, rate, amount_cents FROM social_charges WHERE payroll_id = ? ORDER BY id',
            [$payrollId],
        );

        return array_map(fn (array $c) => [
            'type' => $c['type'],
            'base_cents' => (int) $c['base_cents'],
            'rate' => (float) $c['rate'],
            'amount_cents' => (int) $c['amount_cents'],
        ], $rows);
    }

    /** @return array<string, mixed>|null */
    private function one(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    private function all(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
