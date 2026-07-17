<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Database;
use PDO;

/**
 * Fechamento de folha (Etapa 7): orquestra o fluxo
 *   competência → importa ponto (banco de horas) → importa faltas →
 *   eventos manuais → PayrollEngine → persistência → fechamento.
 * A ENGINE calcula; este serviço só monta contexto e persiste.
 */
final class PayrollService
{
    public function __construct(private readonly TaxTableRepository $taxTables = new TaxTableRepository)
    {
    }

    private function db(): PDO
    {
        return Database::connection();
    }

    /** Calcula (ou recalcula) a competência inteira da empresa. */
    public function calculatePeriod(int $companyId, string $competency): array
    {
        $db = $this->db();

        // Engine montada com as tabelas VIGENTES na competência
        $tables = $this->taxTables->forCompetency($competency);
        $engine = new PayrollEngine(
            $this->taxTables->buildInss($tables),
            $this->taxTables->buildIrrf($tables),
            $this->taxTables->buildFgts($tables),
            $this->loadRubrics(),
            $this->taxTables->familyAllowance($tables),
        );

        $db->beginTransaction();
        try {
            // Período (recalcular substitui folhas anteriores; fechado é imutável)
            $stmt = $db->prepare(
                "INSERT INTO payroll_periods (company_id, competency) VALUES (:c, :m)
                 ON CONFLICT (company_id, competency) DO UPDATE SET company_id = EXCLUDED.company_id
                 RETURNING id, status",
            );
            $stmt->execute(['c' => $companyId, 'm' => $competency]);
            $period = $stmt->fetch();
            if ($period['status'] === 'closed') {
                $db->rollBack();

                return [false, 'Competência já fechada — reabra antes de recalcular.'];
            }
            $db->prepare('DELETE FROM payrolls WHERE period_id = :p')->execute(['p' => $period['id']]);

            $employees = $db->prepare(
                "SELECT * FROM employees
                 WHERE company_id = :c AND status IN ('active', 'vacation') AND salary_cents IS NOT NULL",
            );
            $employees->execute(['c' => $companyId]);

            $count = 0;
            foreach ($employees->fetchAll() as $employee) {
                $result = $engine->calculate(
                    ['salary_cents' => (int) $employee['salary_cents'], 'contract_type' => $employee['contract_type']],
                    $this->buildContext((int) $employee['id'], $companyId, $competency),
                );
                $this->persist((int) $period['id'], (int) $employee['id'], $result);
                $count++;
            }

            $db->prepare("UPDATE payroll_periods SET status = 'calculated', calculated_at = now() WHERE id = :id")
               ->execute(['id' => $period['id']]);
            $db->commit();

            return [true, "Folha calculada para {$count} colaborador(es)."];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function closePeriod(int $companyId, string $competency, int $userId): bool
    {
        return $this->db()->prepare(
            "UPDATE payroll_periods SET status = 'closed', closed_at = now(), closed_by = :u
             WHERE company_id = :c AND competency = :m AND status = 'calculated'",
        )->execute(['u' => $userId, 'c' => $companyId, 'm' => $competency]);
    }

    /** Contexto do colaborador: ponto, faltas, eventos, benefícios, descontos. */
    private function buildContext(int $employeeId, int $companyId, string $competency): array
    {
        $db = $this->db();
        [$year, $month] = explode('-', $competency);
        $monthStart = "{$year}-{$month}-01";

        // Importa PONTO: crédito de banco de horas aprovado no mês → HE 50%
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(minutes), 0) FROM overtime_bank
             WHERE employee_id = :e AND minutes > 0
               AND work_date >= :start::date AND work_date < :start::date + interval '1 month'",
        );
        $stmt->execute(['e' => $employeeId, 'start' => $monthStart]);
        $overtimeMinutes = (int) $stmt->fetchColumn();

        $events = [];
        if ($overtimeMinutes > 0) {
            $events[] = ['rubric_code' => '1001', 'reference' => round($overtimeMinutes / 60, 2), 'amount_cents' => null];
        }

        // Importa FALTAS não justificadas do mês
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(date_to - date_from + 1), 0) FROM absences
             WHERE employee_id = :e AND type = 'falta' AND justified = FALSE
               AND date_from >= :start::date AND date_from < :start::date + interval '1 month'",
        );
        $stmt->execute(['e' => $employeeId, 'start' => $monthStart]);
        $absenceDays = (int) $stmt->fetchColumn();
        if ($absenceDays > 0) {
            $events[] = ['rubric_code' => '2005', 'reference' => (float) $absenceDays, 'amount_cents' => null];
        }

        // Eventos manuais da competência (comissões, bônus, adicionais…)
        $stmt = $db->prepare(
            'SELECT rubric_code, reference, amount_cents FROM payroll_events
             WHERE employee_id = :e AND competency = :m',
        );
        $stmt->execute(['e' => $employeeId, 'm' => $competency]);
        $events = array_merge($events, $stmt->fetchAll());

        // Benefícios e descontos recorrentes ativos
        $stmt = $db->prepare(
            'SELECT type, description, amount_cents, employee_share_percent, employee_share_cents
             FROM employee_benefits WHERE employee_id = :e AND is_active',
        );
        $stmt->execute(['e' => $employeeId]);
        $benefits = $stmt->fetchAll();

        $stmt = $db->prepare(
            'SELECT description, amount_cents FROM employee_discounts
             WHERE employee_id = :e AND is_active AND (remaining IS NULL OR remaining > 0)',
        );
        $stmt->execute(['e' => $employeeId]);
        $discounts = $stmt->fetchAll();

        // Dependentes: todos p/ IRRF; < 14 anos p/ salário família
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE birth_date > CURRENT_DATE - interval '14 years') AS under14
             FROM employee_dependents WHERE employee_id = :e",
        );
        $stmt->execute(['e' => $employeeId]);
        $dependents = $stmt->fetch();

        return [
            'events' => $events,
            'benefits' => $benefits,
            'discounts' => $discounts,
            'dependents_irrf' => (int) $dependents['total'],
            'children_under_14' => (int) $dependents['under14'],
        ];
    }

    private function persist(int $periodId, int $employeeId, array $result): void
    {
        $db = $this->db();

        $stmt = $db->prepare(
            'INSERT INTO payrolls (period_id, employee_id, gross_cents, deductions_cents, net_cents,
                                   inss_base_cents, irrf_base_cents, fgts_base_cents)
             VALUES (:p, :e, :gross, :ded, :net, :binss, :birrf, :bfgts) RETURNING id',
        );
        $stmt->execute([
            'p' => $periodId, 'e' => $employeeId,
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
                'ref' => $i['reference'], 'amount' => $i['amount'], 'type' => $i['type']]);
        }

        $charge = $db->prepare(
            'INSERT INTO social_charges (payroll_id, type, base_cents, rate, amount_cents)
             VALUES (:p, :type, :base, :rate, :amount)',
        );
        foreach ($result['charges'] as $c) {
            $charge->execute(['p' => $payrollId, 'type' => $c['type'], 'base' => $c['base'],
                'rate' => $c['rate'], 'amount' => $c['amount']]);
        }
    }

    /** @return array<string, array> mapa código → rubrica (flags/fórmula) */
    private function loadRubrics(): array
    {
        $map = [];
        foreach ($this->db()->query('SELECT * FROM rubrics WHERE is_active')->fetchAll() as $r) {
            $map[$r['code']] = [
                'type' => $r['type'], 'name' => $r['name'], 'formula' => $r['formula'],
                'incides_inss' => (bool) $r['incides_inss'],
                'incides_irrf' => (bool) $r['incides_irrf'],
                'incides_fgts' => (bool) $r['incides_fgts'],
            ];
        }

        return $map;
    }
}
