<?php

declare(strict_types=1);

namespace App\Models;

class WorkSchedule extends TenantModel
{
    protected function casts(): array
    {
        return [
            'rules' => 'array',
        ];
    }
}
