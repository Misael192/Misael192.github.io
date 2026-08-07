<?php

declare(strict_types=1);

namespace App\Models;

class Subscription extends TenantModel
{
    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }
}
