<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends TenantModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['cnpj' => 'encrypted'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
