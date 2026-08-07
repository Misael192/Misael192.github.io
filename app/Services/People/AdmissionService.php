<?php

declare(strict_types=1);

namespace App\Services\People;

use App\Models\AdmissionTask;
use App\Models\Employee;

/**
 * Admissão digital: checklist padrão por colaborador em admissão; concluir
 * todos os itens ativa o colaborador automaticamente (migração do MVP).
 */
class AdmissionService
{
    /** Checklist padrão de documentos/etapas da admissão (CLT). */
    public const CHECKLIST = [
        'CPF', 'RG', 'CTPS', 'PIS/PASEP', 'Comprovante de residência',
        'Exame admissional (ASO)', 'Contrato assinado', 'Foto 3x4',
    ];

    /** Cria o checklist do colaborador (idempotente — não duplica). */
    public function startFor(Employee $employee): void
    {
        if (AdmissionTask::query()->where('employee_id', $employee->id)->exists()) {
            return;
        }

        foreach (self::CHECKLIST as $position => $label) {
            AdmissionTask::query()->create([
                'employee_id' => $employee->id,
                'label' => $label,
                'position' => $position,
            ]);
        }
    }

    /**
     * Marca/desmarca um item; se todos concluírem e o colaborador estiver em
     * admissão, ativa-o. Retorna true se ativou o colaborador nesta chamada.
     */
    public function toggle(AdmissionTask $task): bool
    {
        $done = ! $task->is_done;
        $task->update(['is_done' => $done, 'done_at' => $done ? now() : null]);

        $allDone = ! AdmissionTask::query()
            ->where('employee_id', $task->employee_id)
            ->where('is_done', false)
            ->exists();

        $employee = $task->employee;
        if ($allDone && $employee !== null && $employee->status === Employee::STATUS_ADMISSION) {
            $employee->update(['status' => Employee::STATUS_ACTIVE]);

            return true;
        }

        return false;
    }
}
