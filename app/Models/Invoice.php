<?php

declare(strict_types=1);

namespace App\Models;

class Invoice extends TenantModel
{
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }
}
