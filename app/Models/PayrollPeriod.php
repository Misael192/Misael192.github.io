<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Competência da folha de uma empresa. open → calculated → closed
 * (fechado é imutável; reabrir volta para calculated).
 */
class PayrollPeriod extends TenantModel
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_CLOSED = 'closed';

    protected function casts(): array
    {
        return [
            'calculated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'period_id');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
