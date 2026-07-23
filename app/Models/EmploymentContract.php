<?php

declare(strict_types=1);

namespace App\Models;

class EmploymentContract extends TenantModel
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
