<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Folha de um colaborador numa competência. `kind` separa a mensal
 * (payslip) das especiais (13º/férias/rescisão) — mesma estrutura serve
 * a todos os holerites.
 */
class Payroll extends TenantModel
{
    public const KIND_PAYSLIP = 'payslip';

    protected function casts(): array
    {
        return [
            'calculated_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(SocialCharge::class);
    }
}
