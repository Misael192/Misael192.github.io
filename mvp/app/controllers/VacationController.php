<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Employee;
use App\Models\Vacation;
use App\Services\AuditService;

class VacationController
{
    public function __construct(private readonly Vacation $vacations = new Vacation)
    {
    }

    public function index(): void
    {
        Can::check('vacations:request');
        $companyId = auth_user()['company_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            match ($_POST['action'] ?? 'request') {
                'approve', 'reject' => $this->decide($companyId),
                'receipt' => $this->receipt($companyId),
                default => $this->request($companyId),
            };
        }

        view('vacations', [
            'vacations' => $this->vacations->listForCompany($companyId),
            'employees' => (new Employee)->listForCompany($companyId),
        ]);
    }

    private function request(int $companyId): void
    {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $start = (string) ($_POST['start_date'] ?? '');
        $end = (string) ($_POST['end_date'] ?? '');
        $sell = min(10, max(0, (int) ($_POST['sell_days'] ?? 0)));

        if ($employeeId < 1 || ! $start || ! $end || $end < $start) {
            flash('error', 'Informe colaborador e um período válido.');
            redirect('ferias.php');
        }

        [$ok, $message] = $this->vacations->request($companyId, $employeeId, $start, $end, $sell);
        flash($ok ? 'success' : 'error', $message);
        if ($ok) {
            AuditService::log('vacation.request', 'vacation', $employeeId, null,
                ['start' => $start, 'end' => $end, 'sell_days' => $sell]);
        }
        redirect('ferias.php');
    }

    /** Gera (ou abre) o recibo de férias — folha kind=vacation + holerite. */
    private function receipt(int $companyId): void
    {
        Can::check('payroll:manage');
        $vacationId = (int) ($_POST['vacation_id'] ?? 0);

        [$ok, $message, $payrollId] = (new \App\Services\Payroll\SpecialPayrollService)
            ->vacationReceipt($vacationId, $companyId);
        if (! $ok) {
            flash('error', $message);
            redirect('ferias.php');
        }

        AuditService::log('vacation.receipt', 'vacation', $vacationId, null, ['payroll_id' => $payrollId]);
        redirect("holerite.php?id={$payrollId}");
    }

    private function decide(int $companyId): void
    {
        Can::check('vacations:approve');
        $id = (int) ($_POST['vacation_id'] ?? 0);
        $approve = ($_POST['action'] ?? '') === 'approve';

        if ($this->vacations->decide($id, $companyId, $approve, auth_user()['id'])) {
            AuditService::log($approve ? 'vacation.approve' : 'vacation.reject', 'vacation', $id,
                ['status' => 'requested'], ['status' => $approve ? 'approved' : 'rejected']);
            \App\Services\Api\WebhookService::dispatch($companyId,
                $approve ? 'vacation.approved' : 'vacation.rejected', ['vacation_id' => $id]);
            flash('success', $approve ? 'Férias aprovadas — saldo do período atualizado.' : 'Solicitação rejeitada.');
        } else {
            flash('error', 'Solicitação não encontrada ou já decidida.');
        }
        redirect('ferias.php');
    }
}
