<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant — a raiz do isolamento. Não usa BelongsToTenant (é o ponto de
 * entrada, resolvido pelo middleware antes de qualquer escopo existir).
 */
class Tenant extends Model
{
    use HasUuids;
    use SoftDeletes;

    public const ISOLATION_COLUMN = 'column';

    public const ISOLATION_SCHEMA = 'schema';

    public const ISOLATION_DATABASE = 'database';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'database_url' => 'encrypted',
        ];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'tenant_modules')
            ->withPivot(['is_enabled', 'source', 'expires_at'])
            ->withTimestamps();
    }
}
