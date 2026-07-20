<?php

declare(strict_types=1);

namespace App\Models;



class TimeEntry extends TenantModel
{

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }
}
