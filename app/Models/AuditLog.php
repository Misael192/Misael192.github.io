<?php

declare(strict_types=1);

namespace App\Models;

/** Trilha de auditoria append-only (trigger no PostgreSQL impede mutação). */
class AuditLog extends TenantModel
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }
}
