<?php

declare(strict_types=1);

namespace App\Models;

class FeatureFlag extends GlobalModel
{
    protected function casts(): array
    {
        return [
            'enabled_globally' => 'boolean',
            'enabled_tenant_ids' => 'array',
            'disabled_tenant_ids' => 'array',
        ];
    }
}
