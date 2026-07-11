<?php

declare(strict_types=1);

namespace App\Models;



class JobApplication extends TenantModel
{

    protected function casts(): array
    {
        return [
            'history' => 'array',
        ];
    }
}
