<?php

declare(strict_types=1);

namespace App\Services\People;

use App\Models\Employee;
use App\Models\TimeBankEntry;

/**
 * Banco de horas: lança créditos (hora-extra) e débitos (compensação) por
 * colaborador. O crédito no mês alimenta a folha mensal (HE 50%, rubrica 1001)
 * — a conta é da engine; aqui só registramos minutos (int, com sinal).
 */
class TimeBankService
{
    public function register(Employee $employee, string $referenceDate, int $minutes, string $reason): TimeBankEntry
    {
        return TimeBankEntry::query()->create([
            'employee_id' => $employee->id,
            'minutes' => $minutes,
            'reason' => $reason,
            'reference_date' => $referenceDate,
        ]);
    }

    /** Saldo do banco de horas (minutos, com sinal) do colaborador. */
    public function balanceMinutes(Employee $employee): int
    {
        return (int) TimeBankEntry::query()
            ->where('employee_id', $employee->id)
            ->sum('minutes');
    }
}
