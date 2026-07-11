<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Permissão atômica `recurso:ação` — catálogo global, semeado por seeder. */
class Permission extends GlobalModel
{
    public function group(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'group_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}
