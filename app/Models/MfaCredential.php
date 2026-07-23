<?php

declare(strict_types=1);

namespace App\Models;

class MfaCredential extends TenantModel
{
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'recovery_codes' => 'array',
            'verified_at' => 'datetime',
        ];
    }
}
