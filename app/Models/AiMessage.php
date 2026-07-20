<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends TenantModel
{
    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'cost_cents' => 'float',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
