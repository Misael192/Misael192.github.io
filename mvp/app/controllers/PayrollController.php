<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Database;
use App\Services\AuditService;
use App\Services\Payroll\PayrollService;

/**
 * Fechamento de folha: competência → importa ponto/faltas/eventos/benefícios →
 * calcula (engine) → conferência → fecha. Competência fechada é imutável.
 */
class PayrollController
{
    public function __construct(private readonly PayrollService $payroll = new PayrollService)
    {
    }

    public function index(): void
    {
        Can::check('payroll:manage');
        $companyId = auth_user()['company_id'];
        $competency = $this->competency();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            match ($_POST['action'] ?? '') {
                'calculate' => $this->calculate($companyId, $competency),
                'close' => $this->close($companyId, $competency),
                'reopen' => $this->reopen($companyId, $competency),
                'event' => $this->addEvent($companyId, $competency),
                default => null,
            };
            redirect('folha.php?comp='.$competency);
        }

        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM payroll_periods WHERE company_id = :c AND competency = :m');
        $stmt->execute(['c' => $companyId, 'm' => $competency]);
        $period = $stmt->fetch() ?: null;

        $payrolls = [];
        $totals = ['gross' => 0, 'deductions' => 0, 'net' => 0, 'fgts' => 0];
        if ($period) {
            $stmt = $db->prepare(
                'SELECT p.*, e.full_name, e.registration, pos.title AS position_name,
                        (SELECT COALESCE(SUM(amount_cents), 0) FROM social_charges sc
                          WHERE sc.payroll_id = p.id AND sc.type = \'fgts\') AS fgts_cents
                 FROM payrolls p
                 JOIN employees e ON e.id = p.employee_id
                 LEFT JOIN positions pos ON pos.id = e.position_id
                 WHERE p.period_id = :p ORDER BY e.full_name',
            );
            $stmt->execute(['p' => $period['id']]);
            $payrolls = $stmt->fetchAll();
            foreach ($payrolls as $p) {
                $totals['gross'] += (int) $p['gross_cents'];
                $totals['deductions'] += (int) $p['deductions_cents'];
                $totals['net'] += (int) $p['net_cents'];
                $totals['fgts'] += (int) $p['fgts_cents'];
            }
        }

        // Eventos manuais lançados na competência (comissão, bônus…)
        $stmt = $db->prepare(
            'SELECT ev.*, e.full_name, r.name AS rubric_name FROM payroll_events ev
             JOIN employees e ON e.id = ev.employee_id
             JOIN rubrics r ON r.code = ev.rubric_code
             WHERE ev.company_id = :c AND ev.competency = :m ORDER BY ev.id DESC',
        );
        $stmt->execute(['c' => $companyId, 'm' => $competency]);

        view('payroll', [
            'competency' => $competency,
            'period' => $period,
            'payrolls' => $payrolls,
            'totals' => $totals,
            'events' => $stmt->fetchAll(),
            'employees' => $db->query(
                "SELECT id, full_name FROM employees
                 WHERE company_id = {$companyId} AND status IN ('active','vacation') AND salary_cents IS NOT NULL
                 ORDER BY full_name",
            )->fetchAll(),
            'rubrics' => $db->query(
                "SELECT code, name, type FROM rubrics
                 WHERE is_active AND formula IN ('manual', 'overtime_50', 'overtime_100') ORDER BY code",
            )->fetchAll(),
        ]);
    }

    /** Holerite individual — DP/RH veem todos; colaborador vê apenas o próprio. */
    public function payslip(): void
    {
        \App\Middleware\Auth::check();
        $companyId = auth_user()['company_id'];

        $stmt = Database::connection()->prepare(
            'SELECT p.*, pp.competency, pp.status AS period_status,
                    e.full_name, e.registration, e.cpf, e.pis, e.hired_at, e.contract_type,
                    pos.title AS position_name, d.name AS department_name,
                    c.name AS company_name, c.cnpj
             FROM payrolls p
             JOIN payroll_periods pp ON pp.id = p.period_id
             JOIN employees e ON e.id = p.employee_id
             LEFT JOIN positions pos ON pos.id = e.position_id
             LEFT JOIN departments d ON d.id = e.department_id
             JOIN companies c ON c.id = pp.company_id
             WHERE p.id = :id AND pp.company_id = :c',
        );
        $stmt->execute(['id' => (int) ($_GET['id'] ?? 0), 'c' => $companyId]);
        $payroll = $stmt->fetch();

        if ($payroll === false) {
            http_response_code(404);
            flash('error', 'Holerite não encontrado.');
            redirect('folha.php');
        }

        if ((int) $payroll['employee_id'] !== (auth_user()['employee_id'] ?? 0)) {
            Can::check('payroll:manage');
        }

        $items = Database::connection()->prepare(
            'SELECT * FROM payroll_items WHERE payroll_id = :p ORDER BY rubric_code',
        );
        $items->execute(['p' => $payroll['id']]);

        $charges = Database::connection()->prepare(
            'SELECT * FROM social_charges WHERE payroll_id = :p',
        );
        $charges->execute(['p' => $payroll['id']]);

        view('payslip', [
            'payroll' => $payroll,
            'items' => $items->fetchAll(),
            'charges' => $charges->fetchAll(),
        ]);
    }

    private function calculate(int $companyId, string $competency): void
    {
        [$success, $message] = $this->payroll->calculatePeriod($companyId, $competency);
        AuditService::log('payroll.calculate', 'payroll_period', $competency, null, ['result' => $message]);
        flash($success ? 'success' : 'error', $message);
    }

    private function close(int $companyId, string $competency): void
    {
        $this->payroll->closePeriod($companyId, $competency, auth_user()['id']);
        AuditService::log('payroll.close', 'payroll_period', $competency);
        flash('success', "Competência {$competency} fechada — folha imutável a partir de agora.");
    }

    private function reopen(int $companyId, string $competency): void
    {
        Database::connection()->prepare(
            "UPDATE payroll_periods SET status = 'calculated', closed_at = NULL, closed_by = NULL
             WHERE company_id = :c AND competency = :m AND status = 'closed'",
        )->execute(['c' => $companyId, 'm' => $competency]);
        AuditService::log('payroll.reopen', 'payroll_period', $competency);
        flash('success', "Competência {$competency} reaberta para ajustes.");
    }

    /** Evento manual (comissão, bônus, HE avulsa, desconto…) para a competência. */
    private function addEvent(int $companyId, string $competency): void
    {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $rubric = (string) ($_POST['rubric_code'] ?? '');
        $reference = $_POST['reference'] !== '' ? (float) str_replace(',', '.', (string) $_POST['reference']) : null;
        $amount = $_POST['amount'] !== ''
            ? (int) round(((float) str_replace(['.', ','], ['', '.'], (string) $_POST['amount'])) * 100)
            : null;

        if ($employeeId < 1 || $rubric === '' || ($reference === null && $amount === null)) {
            flash('error', 'Informe colaborador, rubrica e referência (horas/dias) ou valor.');

            return;
        }

        $check = Database::connection()->prepare('SELECT 1 FROM employees WHERE id = :e AND company_id = :c');
        $check->execute(['e' => $employeeId, 'c' => $companyId]);
        if ($check->fetch() === false) {
            flash('error', 'Colaborador inválido.');

            return;
        }

        Database::connection()->prepare(
            'INSERT INTO payroll_events (company_id, employee_id, competency, rubric_code, reference, amount_cents, notes, created_by)
             VALUES (:c, :e, :m, :r, :ref, :a, :n, :by)',
        )->execute([
            'c' => $companyId, 'e' => $employeeId, 'm' => $competency, 'r' => $rubric,
            'ref' => $reference, 'a' => $amount,
            'n' => trim((string) ($_POST['notes'] ?? '')) ?: null, 'by' => auth_user()['id'],
        ]);

        AuditService::log('payroll.event', 'payroll_event', $employeeId, null,
            ['competency' => $competency, 'rubric' => $rubric, 'reference' => $reference, 'amount_cents' => $amount]);
        flash('success', 'Evento lançado — recalcule a folha para refletir.');
    }

    /** Competência da URL validada; padrão = mês corrente. */
    private function competency(): string
    {
        $comp = (string) ($_GET['comp'] ?? '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $comp) ? $comp : date('Y-m');
    }
}
