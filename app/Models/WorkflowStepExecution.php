<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Histórico auditável de cada passo executado (Workflow History). */
class WorkflowStepExecution extends TenantModel
{
    public const CREATED_AT = 'started_at';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }
}
