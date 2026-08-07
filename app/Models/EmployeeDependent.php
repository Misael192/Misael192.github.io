<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dependente do colaborador (IRRF e salário-família). */
class EmployeeDependent extends TenantModel
{
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'counts_for_irrf' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
