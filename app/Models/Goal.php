<?php

declare(strict_types=1);

namespace App\Models;



class Goal extends TenantModel
{

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'progress' => 'float',
        ];
    }
}
