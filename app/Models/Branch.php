<?php

declare(strict_types=1);

namespace App\Models;



class Branch extends TenantModel
{

    protected function casts(): array
    {
        return [
            'address' => 'array',
        ];
    }
}
