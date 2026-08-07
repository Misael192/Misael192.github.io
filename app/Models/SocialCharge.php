<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Encargo patronal (FGTS, INSS patronal…) — não sai do líquido. */
class SocialCharge extends TenantModel
{
    protected function casts(): array
    {
        return [
            'base_cents' => 'integer',
            'rate' => 'float',
            'amount_cents' => 'integer',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
