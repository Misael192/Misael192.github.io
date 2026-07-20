<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base dos models de catálogo da PLATAFORMA (sem tenant): permissões,
 * módulos, planos, feature flags. São compartilhados por todos os tenants.
 */
abstract class GlobalModel extends Model
{
    use HasUuids;

    protected $guarded = ['id'];
}
