<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToTenant;
    use HasApiTokens;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed', // driver argon2id (config/hashing + HASH_DRIVER)
            'is_active' => 'boolean',
            'mfa_enabled' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * Permissões efetivas (união de todos os papéis do usuário), memoizadas
     * por request. Na fase de réplicas o cache migra para Redis com
     * invalidação pelo evento role.updated.
     */
    public function permissionCodes(): array
    {
        return $this->permissionCache ??= Permission::query()
            ->whereHas('roles.userRoles', fn ($q) => $q->where('user_id', $this->id))
            ->pluck('code')
            ->all();
    }
}
