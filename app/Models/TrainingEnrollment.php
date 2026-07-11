<?php

declare(strict_types=1);

namespace App\Models;



class TrainingEnrollment extends TenantModel
{

    protected function casts(): array
    {
        return [
            'progress' => 'float',
            'completed_at' => 'datetime',
        ];
    }
}
