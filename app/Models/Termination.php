<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rescisão contratual (termo). Origem da folha kind='termination'. */
class Termination extends TenantModel
{
    protected function casts(): array
    {
        return [
            'termination_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
