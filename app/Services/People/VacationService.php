<?php

declare(strict_types=1);

namespace App\Services\People;

use App\Events\VacationApproved;
use App\Events\VacationRequested;
use App\Models\Employee;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Support\Carbon;

/**
 * Férias: solicitação e decisão. A regra CLT (dias, abono ≤ 10) fica aqui e na
 * validação da tela; o recibo (cálculo em centavos) é do SpecialPayrollService.
 * Dispara os mesmos eventos da API para o Workflow Engine do tenant reagir.
 */
class VacationService
{
    public function request(Employee $employee, string $startDate, int $days, int $sellDays = 0): VacationRequest
    {
        $start = Carbon::parse($startDate);
        $end = $start->copy()->addDays(max(1, $days) - 1);

        $vacation = VacationRequest::query()->create([
            'employee_id' => $employee->id,
            'start_date' => $start,
            'end_date' => $end,
            'days' => $days,
            'sell_days' => $sellDays,
            'status' => VacationRequest::STATUS_REQUESTED,
        ]);

        VacationRequested::dispatch([
            'id' => $vacation->id,
            'employee_id' => $vacation->employee_id,
            'days' => $vacation->days,
        ]);

        return $vacation;
    }

    /** @return array{0: bool, 1: string} */
    public function approve(VacationRequest $vacation, User $approver): array
    {
        if ($vacation->status !== VacationRequest::STATUS_REQUESTED) {
            return [false, 'Solicitação já decidida.'];
        }

        $vacation->update([
            'status' => VacationRequest::STATUS_APPROVED,
            'approved_by_id' => $approver->id,
            'decided_at' => now(),
        ]);

        VacationApproved::dispatch([
            'id' => $vacation->id,
            'employee_id' => $vacation->employee_id,
        ]);

        return [true, 'Férias aprovadas — recibo disponível nas folhas especiais.'];
    }

    /** @return array{0: bool, 1: string} */
    public function reject(VacationRequest $vacation, User $approver): array
    {
        if ($vacation->status !== VacationRequest::STATUS_REQUESTED) {
            return [false, 'Solicitação já decidida.'];
        }

        $vacation->update([
            'status' => VacationRequest::STATUS_REJECTED,
            'approved_by_id' => $approver->id,
            'decided_at' => now(),
        ]);

        return [true, 'Solicitação recusada.'];
    }
}
