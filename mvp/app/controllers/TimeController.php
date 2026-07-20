<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Employee;
use App\Models\TimeClock;
use App\Services\AuditService;

class TimeController
{
    public function __construct(private readonly TimeClock $clock = new TimeClock)
    {
    }

    public function index(): void
    {
        Can::check('time:register');
        $companyId = auth_user()['company_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            match ($_POST['action'] ?? 'register') {
                'approve' => $this->approve($companyId),
                default => $this->register($companyId),
            };
        }

        view('timesheet', [
            'records' => $this->clock->listForCompany($companyId),
            'balances' => $this->clock->bankBalances($companyId),
            'employees' => (new Employee)->listForCompany($companyId),
            'clock' => $this->clock,
        ]);
    }

    private function register(int $companyId): void
    {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $date = (string) ($_POST['work_date'] ?? '');

        if ($employeeId < 1 || ! $date) {
            flash('error', 'Informe colaborador e data.');
            redirect('ponto.php');
        }

        [$ok, $message] = $this->clock->register($companyId, $employeeId, $date, [
            'clock_in' => $_POST['clock_in'] ?? null,
            'lunch_out' => $_POST['lunch_out'] ?? null,
            'lunch_in' => $_POST['lunch_in'] ?? null,
            'clock_out' => $_POST['clock_out'] ?? null,
        ]);
        flash($ok ? 'success' : 'error', $message);
        if ($ok) {
            AuditService::log('time.register', 'time_clock_record', $employeeId, null,
                ['date' => $date, 'in' => $_POST['clock_in'] ?? null, 'out' => $_POST['clock_out'] ?? null]);
        }
        redirect('ponto.php');
    }

    private function approve(int $companyId): void
    {
        Can::check('time:approve');
        $id = (int) ($_POST['record_id'] ?? 0);

        if ($this->clock->approve($id, $companyId, auth_user()['id'])) {
            AuditService::log('time.approve', 'time_clock_record', $id,
                ['status' => 'recorded'], ['status' => 'approved']);
            flash('success', 'Dia aprovado — banco de horas atualizado.');
        } else {
            flash('error', 'Registro não encontrado ou já aprovado.');
        }
        redirect('ponto.php');
    }
}
