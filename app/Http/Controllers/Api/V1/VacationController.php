<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\VacationApproved;
use App\Events\VacationRequested;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\VacationRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Férias. A aprovação demonstra o padrão ABAC sobre RBAC: o middleware
 * `can:vacations:approve` já garantiu a PERMISSÃO; aqui verificamos o
 * ATRIBUTO — o aprovador gerencia este colaborador?
 */
class VacationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            // Abono pecuniário: no máximo 10 dias (CLT, art. 143).
            'sell_days' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        $vacation = VacationRequest::query()->create([
            'employee_id' => $data['employee_id'],
            'start_date' => $start,
            'end_date' => $end,
            'days' => $start->diffInDays($end) + 1,
            'sell_days' => $data['sell_days'] ?? 0,
        ]);

        // O Workflow Engine do tenant decide o que acontece a partir daqui.
        VacationRequested::dispatch([
            'id' => $vacation->id,
            'employee_id' => $vacation->employee_id,
            'days' => $vacation->days,
        ]);

        return response()->json($vacation, 201);
    }

    public function approve(Request $request, VacationRequest $vacation): JsonResponse
    {
        abort_if($vacation->status !== VacationRequest::STATUS_REQUESTED, 409, 'Solicitação já decidida');

        $approver = $request->user();

        // ABAC: aprovador precisa ser gestor do colaborador — ou admin/RH/DP.
        $isManager = Employee::query()
            ->whereKey($vacation->employee_id)
            ->whereHas('manager', fn ($q) => $q->where('user_id', $approver->id))
            ->exists();

        $isAdmin = $approver->userRoles()
            ->whereHas('role', fn ($q) => $q->whereIn('code', ['OWNER', 'ADMIN', 'HR', 'DP']))
            ->exists();

        if (! $isManager && ! $isAdmin) {
            throw new AuthorizationException('Você só pode aprovar férias da sua própria equipe');
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

        return response()->json($vacation);
    }
}
