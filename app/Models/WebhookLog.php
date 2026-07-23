<?php

declare(strict_types=1);

namespace App\Models;

class WebhookLog extends TenantModel
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
        ];
    }
}
