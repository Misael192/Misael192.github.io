<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Database;
use App\Services\AuditService;
use App\Services\Payroll\SpecialPayrollService;

/** Rescisão: simula as verbas, revisa e efetiva (termo + folha + desligamento). */
class TerminationController
{
    private const TYPES = [
        'sem_justa_causa' => 'Dispensa sem justa causa',
        'pedido' => 'Pedido de demissão',
        'acordo' => 'Acordo (art. 484-A CLT)',
        'justa_causa' => 'Dispensa por justa causa',
    ];

    private const NOTICES = [
        'indenizado' => 'Aviso prévio indenizado',
        'trabalhado' => 'Aviso prévio trabalhado',
        'dispensado' => 'Aviso dispensado',
    ];

    public function __construct(private readonly SpecialPayrollService $special = new SpecialPayrollService)
    {
    }

    public function index(): void
    {
        Can::check('payroll:manage');
        $companyId = auth_user()['company_id'];
        $simulation = null;
        $input = ['employee_id' => 0, 'date' => date('Y-m-d'), 'type' => 'sem_justa_causa',
            'notice' => 'indenizado', 'fgts_balance' => '', 'pending_vacation_days' => '0', 'reason' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $input = $this->input();
            $employee = $this->employee($companyId, (int) $input['employee_id']);

            if ($employee === null) {
                flash('error', 'Selecione um colaborador ativo com salário cadastrado.');
                redirect('rescisao.php');
            }

            $fgtsCents = (int) round(((float) str_replace(['.', ','], ['', '.'], $input['fgts_balance'] ?: '0')) * 100);
            $pending = max(0, (int) $input['pending_vacation_days']);

            if (($_POST['action'] ?? '') === 'confirm') {
                [$ok, $message, $payrollId] = $this->special->terminate(
                    $employee, $input['date'], $input['type'], $input['notice'],
                    $fgtsCents, $pending, $input['reason'], auth_user()['id'],
                );
                AuditService::log('payroll.termination', 'employee', $employee['id'], null,
                    ['date' => $input['date'], 'type' => $input['type'], 'result' => $message]);
                flash($ok ? 'success' : 'error', $message);
                redirect($ok ? "holerite.php?id={$payrollId}" : 'rescisao.php');
            }

            // Simulação: calcula e mostra sem gravar nada
            $simulation = $this->special->simulateTermination(
                $employee, $input['date'], $input['type'], $input['notice'], $fgtsCents, $pending,
            );
            $simulation['employee'] = $employee;
        }

        $db = Database::connection();

        $employees = $db->prepare(
            "SELECT e.id, e.full_name FROM employees e
             WHERE e.company_id = :c AND e.status IN ('active', 'vacation', 'on_leave')
               AND e.salary_cents IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM terminations t WHERE t.employee_id = e.id)
             ORDER BY e.full_name",
        );
        $employees->execute(['c' => $companyId]);

        $history = $db->prepare(
            'SELECT t.*, e.full_name, tp.payroll_id, p.net_cents
             FROM terminations t
             JOIN employees e ON e.id = t.employee_id
             LEFT JOIN termination_payroll tp ON tp.termination_id = t.id
             LEFT JOIN payrolls p ON p.id = tp.payroll_id
             WHERE e.company_id = :c ORDER BY t.termination_date DESC',
        );
        $history->execute(['c' => $companyId]);

        view('termination', [
            'employees' => $employees->fetchAll(),
            'history' => $history->fetchAll(),
            'types' => self::TYPES,
            'notices' => self::NOTICES,
            'simulation' => $simulation,
            'input' => $input,
        ]);
    }

    private function input(): array
    {
        $date = (string) ($_POST['date'] ?? '');

        return [
            'employee_id' => (int) ($_POST['employee_id'] ?? 0),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d'),
            'type' => array_key_exists($_POST['type'] ?? '', self::TYPES) ? $_POST['type'] : 'sem_justa_causa',
            'notice' => array_key_exists($_POST['notice'] ?? '', self::NOTICES) ? $_POST['notice'] : 'indenizado',
            'fgts_balance' => trim((string) ($_POST['fgts_balance'] ?? '')),
            'pending_vacation_days' => (string) (int) ($_POST['pending_vacation_days'] ?? 0),
            'reason' => trim((string) ($_POST['reason'] ?? '')),
        ];
    }

    private function employee(int $companyId, int $employeeId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM employees
             WHERE id = :e AND company_id = :c AND salary_cents IS NOT NULL AND status <> 'terminated'",
        );
        $stmt->execute(['e' => $employeeId, 'c' => $companyId]);

        return $stmt->fetch() ?: null;
    }
}
