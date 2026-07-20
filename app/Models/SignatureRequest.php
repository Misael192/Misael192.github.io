<?php

declare(strict_types=1);

namespace App\Models;



class SignatureRequest extends TenantModel
{

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'signed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
