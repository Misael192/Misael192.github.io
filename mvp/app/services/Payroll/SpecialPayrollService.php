<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Database;
use PDO;

/**
 * Folhas especiais — 13º salário, recibo de férias e rescisão.
 * Persiste na MESMA estrutura da folha mensal (payrolls/payroll_items com
 * `kind` próprio), então o holerite serve para todas; a folha mensal só
 * recalcula kind='payslip' e nunca toca nestes registros.
 */
final class SpecialPayrollService
{
    public function __construct(private readonly TaxTableRepository $taxTables = new TaxTableRepository)
    {
    }

    private function db(): PDO
    {
        return Database::connection();
    }

    /** @return array{0: InssCalculator, 1: IrrfCalculator, 2: FgtsCalculator} */
    private function calculators(string $competency): array
    {
        $tables = $this->taxTables->forCompetency($competency);

        return [
            $this->taxTables->buildInss($tables),
            $this->taxTables->buildIrrf($tables),
            $this->taxTables->buildFgts($tables),
        ];
    }

    /** Garante o período da competência e o devolve; null se estiver fechado. */
    private function openPeriod(int $companyId, string $competency): ?array
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO payroll_periods (company_id, competency) VALUES (:c, :m)
             ON CONFLICT (company_id, competency) DO UPDATE SET company_id = EXCLUDED.company_id
             RETURNING id, status',
        );
        $stmt->execute(['c' => $companyId, 'm' => $competency]);
        $period = $stmt->fetch();

        return $period['status'] === 'closed' ? null : $period;
    }

    /** Substitui (se existir) a folha kind/employee do período e insere a nova. */
    private function persistPayroll(int $periodId, int $employeeId, string $kind, array $result): int
    {
        $db = $this->db();
        $db->prepare('DELETE FROM payrolls WHERE period_id = :p AND employee_id = :e AND kind = :k')
           ->execute(['p' => $periodId, 'e' => $employeeId, 'k' => $kind]);

        $stmt = $db->prepare(
            'INSERT INTO payrolls (period_id, employee_id, kind, gross_cents, deductions_cents, net_cents,
                                   inss_base_cents, irrf_base_cents, fgts_base_cents)
             VALUES (:p, :e, :k, :gross, :ded, :net, :binss, :birrf, :bfgts) RETURNING id',
        );
        $stmt->execute([
            'p' => $periodId, 'e' => $employeeId, 'k' => $kind,
            'gross' => $result['gross'], 'ded' => $result['deductions'], 'net' => $result['net'],
            'binss' => $result['inss_base'], 'birrf' => $result['irrf_base'], 'bfgts' => $result['fgts_base'],
        ]);
        $payrollId = (int) $stmt->fetchColumn();

        $item = $db->prepare(
            'INSERT INTO payroll_items (payroll_id, rubric_code, description, reference, amount_cents, type)
             VALUES (:p, :code, :descr, :ref, :amount, :type)',
        );
        foreach ($result['items'] as $i) {
            $item->execute(['p' => $payrollId, 'code' => $i['code'], 'descr' => $i['description'],
                'ref' => $i['reference'] ?? null, 'amount' => $i['amount'], 'type' => $i['type']]);
        }

        if (($result['fgts_deposit'] ?? 0) > 0) {
            $db->prepare(
                'INSERT INTO social_charges (payroll_id, type, base_cents, rate, amount_cents)
                 VALUES (:p, :t, :b, :r, :a)',
            )->execute(['p' => $payrollId, 't' => 'fgts', 'b' => $result['fgts_base'],
                'r' => $result['fgts_rate'], 'a' => $result['fgts_deposit']]);
        }

        return $payrollId;
    }

    // ── 13º salário ──────────────────────────────────────────────────────────

    /** Calcula a parcela do 13º para toda a empresa. @return array{0: bool, 1: string} */
    public function thirteenth(int $companyId, int $year, int $installment): array
    {
        $competency = $year.($installment === 1 ? '-11' : '-12');
        $reference = "{$year}-12-20";
        [$inss, $irrf, $fgts] = $this->calculators($competency);
        $thirteenth = new ThirteenthCalculator($inss, $irrf);

        $db = $this->db();
        $db->beginTransaction();
        try {
            $period = $this->openPeriod($companyId, $competency);
            if ($period === null) {
                $db->rollBack();

                return [false, "Competência {$competency} está fechada — reabra antes."];
            }

            $employees = $db->prepare(
                "SELECT e.*, (SELECT COUNT(*) FROM employee_dependents d WHERE d.employee_id = e.id) AS dependents
                 FROM employees e
                 WHERE e.company_id = :c AND e.status IN ('active', 'vacation') AND e.salary_cents IS NOT NULL",
            );
            $employees->execute(['c' => $companyId]);

            $count = 0;
            foreach ($employees->fetchAll() as $employee) {
                $months = $thirteenth->months($employee['hired_at'], $reference);
                if ($months < 1) {
                    continue;
                }
                $salary = (int) $employee['salary_cents'];

                if ($installment === 1) {
                    $calc = $thirteenth->firstInstallment($salary, $months);
                    $items = [['code' => '1200', 'description' => "13º — Adiantamento 1ª parcela ({$months}/12)",
                        'reference' => $months, 'amount' => $calc['net'], 'type' => 'earning']];
                    $result = ['gross' => $calc['gross'], 'deductions' => 0, 'net' => $calc['net'],
                        'inss_base' => 0, 'irrf_base' => 0, 'fgts_base' => $calc['gross']];
                } else {
                    $advance = $this->firstInstallmentNet((int) $employee['id'], $year);
                    $calc = $thirteenth->secondInstallment($salary, $months, $advance, (int) $employee['dependents']);
                    $items = [['code' => '1200', 'description' => "13º Salário integral ({$months}/12)",
                        'reference' => $months, 'amount' => $calc['gross'], 'type' => 'earning']];
                    if ($calc['inss'] > 0) {
                        $items[] = ['code' => '2000', 'description' => 'INSS sobre 13º', 'reference' => null, 'amount' => $calc['inss'], 'type' => 'deduction'];
                    }
                    if ($calc['irrf'] > 0) {
                        $items[] = ['code' => '2001', 'description' => 'IRRF sobre 13º', 'reference' => null, 'amount' => $calc['irrf'], 'type' => 'deduction'];
                    }
                    if ($advance > 0) {
                        $items[] = ['code' => '2006', 'description' => 'Adiantamento 13º (1ª parcela)', 'reference' => null, 'amount' => $advance, 'type' => 'deduction'];
                    }
                    $deductions = $calc['inss'] + $calc['irrf'] + $advance;
                    $result = ['gross' => $calc['gross'], 'deductions' => $deductions, 'net' => $calc['net'],
                        'inss_base' => $calc['gross'], 'irrf_base' => $calc['gross'], 'fgts_base' => $calc['gross']];
                }

                // FGTS incide sobre o valor pago em cada parcela
                $result['fgts_rate'] = $employee['contract_type'] === 'aprendiz' ? 2.0 : 8.0;
                $result['fgts_deposit'] = $fgts->calculate($installment === 1 ? $result['gross'] : $result['gross'], $employee['contract_type']);
                if ($installment === 2) { // na 2ª deposita-se a diferença (integral − 1ª)
                    $result['fgts_deposit'] = max(0, $result['fgts_deposit'] - $fgts->calculate($this->firstInstallmentGross((int) $employee['id'], $year), $employee['contract_type']));
                }
                $result['items'] = $items;

                $payrollId = $this->persistPayroll((int) $period['id'], (int) $employee['id'], "thirteenth_{$installment}", $result);

                $db->prepare(
                    'INSERT INTO thirteenth_salary (employee_id, year, installment, months, payroll_id)
                     VALUES (:e, :y, :i, :m, :p)
                     ON CONFLICT (employee_id, year, installment) DO UPDATE SET months = :m, payroll_id = :p',
                )->execute(['e' => $employee['id'], 'y' => $year, 'i' => $installment, 'm' => $months, 'p' => $payrollId]);
                $count++;
            }

            $db->commit();

            $label = $installment === 1 ? '1ª parcela (adiantamento)' : '2ª parcela (final)';

            return [true, "13º {$label} calculada para {$count} colaborador(es)."];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function firstInstallmentNet(int $employeeId, int $year): int
    {
        return $this->firstInstallmentColumn($employeeId, $year, 'net_cents');
    }

    private function firstInstallmentGross(int $employeeId, int $year): int
    {
        return $this->firstInstallmentColumn($employeeId, $year, 'gross_cents');
    }

    private function firstInstallmentColumn(int $employeeId, int $year, string $column): int
    {
        $stmt = $this->db()->prepare(
            "SELECT p.{$column} FROM thirteenth_salary t
             JOIN payrolls p ON p.id = t.payroll_id
             WHERE t.employee_id = :e AND t.year = :y AND t.installment = 1",
        );
        $stmt->execute(['e' => $employeeId, 'y' => $year]);

        return (int) $stmt->fetchColumn();
    }

    // ── Recibo de férias ─────────────────────────────────────────────────────

    /** Gera (ou reaproveita) o recibo de férias de uma solicitação aprovada. @return array{0: bool, 1: string, 2: ?int} */
    public function vacationReceipt(int $vacationId, int $companyId): array
    {
        $db = $this->db();

        $stmt = $db->prepare(
            "SELECT v.*, e.salary_cents, e.contract_type,
                    (SELECT COUNT(*) FROM employee_dependents d WHERE d.employee_id = e.id) AS dependents
             FROM vacations v JOIN employees e ON e.id = v.employee_id
             WHERE v.id = :id AND v.company_id = :c AND v.status = 'approved'",
        );
        $stmt->execute(['id' => $vacationId, 'c' => $companyId]);
        $vacation = $stmt->fetch();
        if ($vacation === false) {
            return [false, 'Férias não encontradas ou ainda não aprovadas.', null];
        }
        if ($vacation['salary_cents'] === null) {
            return [false, 'Colaborador sem salário cadastrado.', null];
        }

        // Já existe recibo? Reaproveita (imutável até excluir a folha)
        $stmt = $db->prepare('SELECT payroll_id FROM vacation_payroll WHERE vacation_id = :v');
        $stmt->execute(['v' => $vacationId]);
        if (($existing = $stmt->fetchColumn()) !== false) {
            return [true, 'Recibo já gerado.', (int) $existing];
        }

        $competency = substr($vacation['start_date'], 0, 7);
        [$inssCalc, $irrfCalc, $fgtsCalc] = $this->calculators($competency);
        $calc = (new VacationCalculator($inssCalc, $irrfCalc))
            ->calculate((int) $vacation['salary_cents'], (int) $vacation['days'], (int) $vacation['sell_days'], (int) $vacation['dependents']);

        // Base tributável = gozo + 1/3 (abono é indenizatório)
        $taxable = $calc['items'][0]['amount'] + $calc['items'][1]['amount'];

        $db->beginTransaction();
        try {
            $period = $this->openPeriod($companyId, $competency);
            if ($period === null) {
                $db->rollBack();

                return [false, "Competência {$competency} está fechada — reabra antes.", null];
            }

            $payrollId = $this->persistPayroll((int) $period['id'], (int) $vacation['employee_id'], 'vacation', [
                'gross' => $calc['gross'], 'deductions' => $calc['inss'] + $calc['irrf'], 'net' => $calc['net'],
                'inss_base' => $taxable, 'irrf_base' => $taxable, 'fgts_base' => $taxable,
                'fgts_rate' => $vacation['contract_type'] === 'aprendiz' ? 2.0 : 8.0,
                'fgts_deposit' => $fgtsCalc->calculate($taxable, $vacation['contract_type']),
                'items' => $calc['items'],
            ]);
            $db->prepare('INSERT INTO vacation_payroll (vacation_id, payroll_id) VALUES (:v, :p)')
               ->execute(['v' => $vacationId, 'p' => $payrollId]);
            $db->commit();

            return [true, 'Recibo de férias gerado.', $payrollId];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── Rescisão ─────────────────────────────────────────────────────────────

    /** Calcula as verbas rescisórias (sem persistir). */
    public function simulateTermination(array $employee, string $date, string $type, string $notice,
        int $fgtsBalanceCents, int $pendingVacationDays): array
    {
        [$inss, $irrf] = $this->calculators(substr($date, 0, 7));
        $thirteenth = new ThirteenthCalculator($inss, $irrf);

        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM employee_dependents WHERE employee_id = :e');
        $stmt->execute(['e' => $employee['id']]);

        return (new TerminationCalculator($inss, $irrf, $thirteenth))->calculate(
            (int) $employee['salary_cents'], $employee['hired_at'], $date, $type, $notice,
            $fgtsBalanceCents, $pendingVacationDays, (int) $stmt->fetchColumn(),
        );
    }

    /** Efetiva a rescisão: termo + folha + desligamento do colaborador. @return array{0: bool, 1: string, 2: ?int} */
    public function terminate(array $employee, string $date, string $type, string $notice,
        int $fgtsBalanceCents, int $pendingVacationDays, string $reason, int $userId): array
    {
        $calc = $this->simulateTermination($employee, $date, $type, $notice, $fgtsBalanceCents, $pendingVacationDays);
        $competency = substr($date, 0, 7);
        $db = $this->db();

        $db->beginTransaction();
        try {
            $period = $this->openPeriod((int) $employee['company_id'], $competency);
            if ($period === null) {
                $db->rollBack();

                return [false, "Competência {$competency} está fechada — reabra antes.", null];
            }

            $stmt = $db->prepare(
                'INSERT INTO terminations (employee_id, termination_date, type, notice, reason, created_by)
                 VALUES (:e, :d, :t, :n, :r, :by) RETURNING id',
            );
            $stmt->execute(['e' => $employee['id'], 'd' => $date, 't' => $type, 'n' => $notice,
                'r' => $reason ?: null, 'by' => $userId]);
            $terminationId = (int) $stmt->fetchColumn();

            // Itens → rubricas: INSS/IRRF nas próprias; 13º na 1200; demais verbas na 1400
            $items = array_map(fn (array $i) => [
                'code' => match (true) {
                    str_starts_with($i['description'], 'INSS') => '2000',
                    str_starts_with($i['description'], 'IRRF') => '2001',
                    str_starts_with($i['description'], '13º') => '1200',
                    default => '1400',
                },
                'description' => $i['description'], 'reference' => null,
                'amount' => $i['amount'], 'type' => $i['type'],
            ], $calc['items']);

            // Base tributável (saldo de salário) para registro
            $taxable = (int) array_sum(array_map(
                fn (array $i) => $i['type'] === 'earning' && ($i['taxable'] ?? false) ? $i['amount'] : 0,
                $calc['items'],
            ));

            $payrollId = $this->persistPayroll((int) $period['id'], (int) $employee['id'], 'termination', [
                'gross' => $calc['gross'], 'deductions' => $calc['deductions'], 'net' => $calc['net'],
                'inss_base' => $taxable, 'irrf_base' => $taxable, 'fgts_base' => $taxable,
                'items' => $items,
            ]);
            $db->prepare('INSERT INTO termination_payroll (termination_id, payroll_id) VALUES (:t, :p)')
               ->execute(['t' => $terminationId, 'p' => $payrollId]);

            $db->prepare('UPDATE employees SET status = :s, terminated_at = :d WHERE id = :e')
               ->execute(['s' => 'terminated', 'd' => $date, 'e' => $employee['id']]);
            $db->prepare('INSERT INTO employee_status_history (employee_id, old_status, new_status, reason, changed_by)
                          VALUES (:e, :old, :new, :r, :by)')
               ->execute(['e' => $employee['id'], 'old' => $employee['status'], 'new' => 'terminated',
                   'r' => 'Rescisão: '.$type, 'by' => $userId]);

            $db->commit();

            return [true, 'Rescisão efetivada — colaborador desligado e termo gerado.', $payrollId];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
