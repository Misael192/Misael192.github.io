<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Linha do holerite (provento/desconto/informativa). */
class PayrollItem extends TenantModel
{
    protected function casts(): array
    {
        return [
            'reference' => 'float',
            'amount_cents' => 'integer',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
