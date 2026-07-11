<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class JobOpening extends TenantModel
{
    use SoftDeletes;
    protected function casts(): array
    {
        return [
            'pipeline' => 'array',
            'is_remote' => 'boolean',
            'is_published' => 'boolean',
        ];
    }
}
