<?php

declare(strict_types=1);

namespace App\Models;

/** Plano de billing: na prática, um conjunto de módulos + quotas. */
class Plan extends GlobalModel
{
    protected function casts(): array
    {
        return [
            'module_codes' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
