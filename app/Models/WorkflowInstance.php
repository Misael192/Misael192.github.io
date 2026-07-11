<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInstance extends TenantModel
{
    public const CREATED_AT = 'started_at';

    public const UPDATED_AT = null;

    public const STATUS_RUNNING = 'running';

    public const STATUS_WAITING = 'waiting'; // aguardando ação humana

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'template_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStepExecution::class, 'instance_id');
    }
}
