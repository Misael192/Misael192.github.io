<?php

declare(strict_types=1);

namespace App\Models;



class Integration extends TenantModel
{

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:json',
            'is_active' => 'boolean',
        ];
    }
}
