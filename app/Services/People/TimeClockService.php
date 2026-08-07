<?php

declare(strict_types=1);

namespace App\Services\People;

use App\Models\Employee;
use App\Models\TimeEntry;

/**
 * Registro de ponto (time_entries): cada batida alterna entrada/saída a partir
 * do que já foi batido no dia. O banco de horas (HE) é apurado depois contra a
 * jornada da escala — aqui só registramos a marcação.
 */
class TimeClockService
{
    public function punch(Employee $employee, string $source = 'web'): TimeEntry
    {
        $todayCount = TimeEntry::query()
            ->where('employee_id', $employee->id)
            ->whereDate('recorded_at', now()->toDateString())
            ->count();

        $type = $todayCount % 2 === 0 ? 'clock_in' : 'clock_out';

        return TimeEntry::query()->create([
            'employee_id' => $employee->id,
            'type' => $type,
            'recorded_at' => now(),
            'source' => $source,
            'status' => 'recorded',
        ]);
    }
}
