<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Família de refresh tokens rotativos (ADR-005). Guarda apenas o hash do
 * token ATUAL; reuso de um token antigo revoga a família inteira.
 */
class RefreshSession extends TenantModel
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
