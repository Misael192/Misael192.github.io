<?php

declare(strict_types=1);

namespace App\Models;



class Payslip extends TenantModel
{

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'viewed_at' => 'datetime',
        ];
    }
}
