<?php

declare(strict_types=1);

namespace App\Models;



class Setting extends TenantModel
{

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
