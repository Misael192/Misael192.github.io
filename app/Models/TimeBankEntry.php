<?php

declare(strict_types=1);

namespace App\Models;

class TimeBankEntry extends TenantModel
{
    protected function casts(): array
    {
        return [
            'reference_date' => 'date',
        ];
    }
}
