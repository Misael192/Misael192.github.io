<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\EmployeeCreated;
use App\Events\EmployeeUpdated;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Colaboradores (módulo People). Toda escrita dispara o evento de domínio
 * correspondente — é assim que Benefits, Payroll e o Workflow Engine reagem
 * à vida do colaborador sem acoplamento (ADR-004).
 */
class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::query()
            ->when($request->query('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->cursorPaginate(min((int) $request->query('per_page', 50), 100));

        return response()->json($employees);
    }

    public function show(Employee $employee): JsonResponse
    {
        return response()->json($employee);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'registration_number' => ['required', 'string', 'max:30'],
            'full_name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'position_id' => ['nullable', 'uuid', 'exists:positions,id'],
            'manager_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'hired_at' => ['nullable', 'date'],
        ]);

        $employee = Employee::query()->create(
            [...$data, 'status' => Employee::STATUS_ADMISSION]
        );

        // Dispara a admissão digital: checklist, documentos e workflow do tenant.
        EmployeeCreated::dispatch([
            'id' => $employee->id,
            'company_id' => $employee->company_id,
        ]);

        return response()->json($employee, 201);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'position_id' => ['nullable', 'uuid', 'exists:positions,id'],
            'manager_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'status' => ['sometimes', 'in:admission,active,on_leave,vacation,terminated'],
        ]);

        $employee->update($data);

        EmployeeUpdated::dispatch(['id' => $employee->id]);

        return response()->json($employee);
    }
}
