<?php

declare(strict_types=1);

namespace App\Models;

class ApiKey extends TenantModel
{
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
