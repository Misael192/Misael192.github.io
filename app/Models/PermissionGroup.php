<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionGroup extends GlobalModel
{
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'group_id');
    }
}
