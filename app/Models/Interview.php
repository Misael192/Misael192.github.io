<?php

declare(strict_types=1);

namespace App\Models;

class Interview extends TenantModel
{
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }
}
