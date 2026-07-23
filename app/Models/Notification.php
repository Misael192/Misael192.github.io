<?php

declare(strict_types=1);

namespace App\Models;

class Notification extends TenantModel
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }
}
