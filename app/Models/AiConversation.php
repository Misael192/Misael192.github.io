<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiConversation extends TenantModel
{
    use SoftDeletes;

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }
}
