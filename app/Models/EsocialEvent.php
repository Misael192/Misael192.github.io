<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EsocialEvent extends TenantModel
{
    public const TYPE_ADMISSION = 'S-2200';

    public const TYPE_REMUNERATION = 'S-1200';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
