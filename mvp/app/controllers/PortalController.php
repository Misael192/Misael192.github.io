<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Auth;
use App\Middleware\Csrf;
use App\Models\Database;
use App\Models\Vacation;
use App\Services\AuditService;

/**
 * Portal do Colaborador — autosserviço 100% restrito ao próprio vínculo:
 * bater ponto, holerites, férias (saldo + solicitação) e documentos.
 * Nenhuma permissão de RBAC é exigida: o escopo é o employee_id da sessão.
 */
class PortalController
{
    public function index(): void
    {
        Auth::check();
        $employeeId = auth_user()['employee_id'];

        if ($employeeId === null) {
            view('portal', ['me' => null]);

            return;
        }

        $db = Database::connection();
        $me = $this->me($db, $employeeId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            match ($_POST['action'] ?? '') {
                'clock' => $this->clock($db, $me),
                'vacation' => $this->requestVacation($me),
                default => null,
            };
            redirect('portal.php');
        }

        view('portal', [
            'me' => $me,
            'today' => $this->today($db, $employeeId),
            'recentClock' => $this->recentClock($db, $employeeId),
            'bankMinutes' => $this->bankMinutes($db, $employeeId),
            'payslips' => $this->payslips($db, $employeeId),
            'vacations' => $this->vacations($db, $employeeId),
            'vacationBalance' => $this->vacationBalance($db, $employeeId),
            'documents' => $this->documents($db, $employeeId),
        ]);
    }

    private function me(\PDO $db, int $employeeId): array
    {
        $stmt = $db->prepare(
            'SELECT e.*, pos.title AS position_name, d.name AS department_name,
                    ws.name AS shift_name, c.name AS company_name
             FROM employees e
             LEFT JOIN positions pos ON pos.id = e.position_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN work_shifts ws ON ws.id = e.work_shift_id
             JOIN companies c ON c.id = e.company_id
             WHERE e.id = :e',
        );
        $stmt->execute(['e' => $employeeId]);

        return $stmt->fetch();
    }

    /** Bate o ponto: preenche a próxima marcação vazia do dia (entrada→almoço→retorno→saída). */
    private function clock(\PDO $db, array $me): void
    {
        $today = $this->today($db, (int) $me['id']);
        $now = date('H:i:s');

        if ($today === null) {
            $db->prepare(
                "INSERT INTO time_clock_records (company_id, employee_id, work_date, clock_in, source)
                 VALUES (:c, :e, CURRENT_DATE, :t, 'portal')",
            )->execute(['c' => $me['company_id'], 'e' => $me['id'], 't' => $now]);
            $mark = 'Entrada';
        } else {
            $next = null;
            foreach (['lunch_out' => 'Saída almoço', 'lunch_in' => 'Retorno almoço', 'clock_out' => 'Saída'] as $field => $label) {
                if ($today[$field] === null) {
                    $next = [$field, $label];
                    break;
                }
            }
            if ($next === null) {
                flash('error', 'As 4 marcações de hoje já foram registradas.');

                return;
            }
            $db->prepare("UPDATE time_clock_records SET {$next[0]} = :t WHERE id = :id")
               ->execute(['t' => $now, 'id' => $today['id']]);
            $mark = $next[1];
        }

        AuditService::log('timeclock.portal', 'time_clock_record', $me['id'], null,
            ['mark' => $mark, 'time' => $now]);
        flash('success', "{$mark} registrada às ".substr($now, 0, 5).'.');
    }

    /** Solicita férias para si — a validação de saldo/período é a mesma do RH. */
    private function requestVacation(array $me): void
    {
        $start = (string) ($_POST['start_date'] ?? '');
        $end = (string) ($_POST['end_date'] ?? '');
        $sell = min(10, max(0, (int) ($_POST['sell_days'] ?? 0)));

        if (! $start || ! $end || $end < $start) {
            flash('error', 'Informe um período válido.');

            return;
        }

        [$ok, $message] = (new Vacation)->request((int) $me['company_id'], (int) $me['id'], $start, $end, $sell);
        flash($ok ? 'success' : 'error', $message);
        if ($ok) {
            AuditService::log('vacation.request', 'vacation', $me['id'], null,
                ['start' => $start, 'end' => $end, 'sell_days' => $sell, 'via' => 'portal']);
        }
    }

    private function today(\PDO $db, int $employeeId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM time_clock_records WHERE employee_id = :e AND work_date = CURRENT_DATE');
        $stmt->execute(['e' => $employeeId]);

        return $stmt->fetch() ?: null;
    }

    private function recentClock(\PDO $db, int $employeeId): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM time_clock_records WHERE employee_id = :e ORDER BY work_date DESC LIMIT 7',
        );
        $stmt->execute(['e' => $employeeId]);

        return $stmt->fetchAll();
    }

    private function bankMinutes(\PDO $db, int $employeeId): int
    {
        $stmt = $db->prepare('SELECT COALESCE(SUM(minutes), 0) FROM overtime_bank WHERE employee_id = :e');
        $stmt->execute(['e' => $employeeId]);

        return (int) $stmt->fetchColumn();
    }

    private function payslips(\PDO $db, int $employeeId): array
    {
        $stmt = $db->prepare(
            'SELECT p.id, p.kind, p.gross_cents, p.net_cents, pp.competency, pp.status AS period_status
             FROM payrolls p JOIN payroll_periods pp ON pp.id = p.period_id
             WHERE p.employee_id = :e
             ORDER BY pp.competency DESC, p.kind LIMIT 24',
        );
        $stmt->execute(['e' => $employeeId]);

        return $stmt->fetchAll();
    }

    private function vacations(\PDO $db, int $employeeId): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM vacations WHERE employee_id = :e ORDER BY start_date DESC LIMIT 10',
        );
        $stmt->execute(['e' => $employeeId]);

        return $stmt->fetchAll();
    }

    private function vacationBalance(\PDO $db, int $employeeId): ?array
    {
        $stmt = $db->prepare(
            "SELECT *, days_entitled - days_taken - days_sold AS balance
             FROM vacation_periods
             WHERE employee_id = :e AND status = 'open'
             ORDER BY acq_start LIMIT 1",
        );
        $stmt->execute(['e' => $employeeId]);

        return $stmt->fetch() ?: null;
    }

    private function documents(\PDO $db, int $employeeId): array
    {
        $stmt = $db->prepare(
            'SELECT d.id, d.name, c.name AS category, d.created_at,
                    (SELECT MAX(version) FROM document_versions v WHERE v.document_id = d.id) AS version
             FROM documents d
             LEFT JOIN document_categories c ON c.id = d.category_id
             WHERE d.employee_id = :e
             ORDER BY d.created_at DESC LIMIT 20',
        );
        $stmt->execute(['e' => $employeeId]);

        return $stmt->fetchAll();
    }
}
