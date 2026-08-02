<?php

declare(strict_types=1);

namespace App\Services\People;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use Illuminate\Support\Facades\DB;

/**
 * Cadastro de colaboradores: cria o colaborador e seu contrato vigente em
 * uma transação (satélites normalizados, como no MVP). Persistência pura —
 * dinheiro em centavos; nenhuma conta de folha vive aqui.
 */
class EmployeeService
{
    /**
     * @param  array{full_name: string, registration_number: string, hired_at: string, type: string, salary_cents: int, weekly_hours?: int|null, status?: string}  $data
     */
    public function register(Company $company, array $data): Employee
    {
        return DB::transaction(function () use ($company, $data) {
            $employee = Employee::query()->create([
                'company_id' => $company->id,
                'registration_number' => $data['registration_number'],
                'full_name' => $data['full_name'],
                'status' => $data['status'] ?? Employee::STATUS_ACTIVE,
                'hired_at' => $data['hired_at'],
            ]);

            EmploymentContract::query()->create([
                'employee_id' => $employee->id,
                'type' => $data['type'],
                'salary_cents' => $data['salary_cents'],
                'weekly_hours' => $data['weekly_hours'] ?? null,
                'start_date' => $data['hired_at'],
            ]);

            return $employee;
        });
    }

    /** Ativa um colaborador em admissão (checklist concluído no MVP). */
    public function activate(Employee $employee): void
    {
        $employee->update(['status' => Employee::STATUS_ACTIVE]);
    }
}
