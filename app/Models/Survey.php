<?php

declare(strict_types=1);

namespace App\Models;



class Survey extends TenantModel
{

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'is_anonymous' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }
}
