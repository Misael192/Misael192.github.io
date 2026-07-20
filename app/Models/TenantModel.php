<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de todo model de domínio multi-tenant:
 *  - UUIDs gerados na aplicação (portável entre PostgreSQL e SQLite de teste);
 *  - escopo global + preenchimento automático de tenant_id (BelongsToTenant);
 *  - mass assignment liberado exceto id (validação acontece nos FormRequests).
 */
abstract class TenantModel extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $guarded = ['id'];
}
