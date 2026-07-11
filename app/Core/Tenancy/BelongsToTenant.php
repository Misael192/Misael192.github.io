<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait aplicada a TODO model de domínio:
 *  1. escopo global filtra por tenant_id do contexto (isolamento na aplicação);
 *  2. preenche tenant_id automaticamente na criação;
 *  3. em PostgreSQL, a RLS é a segunda camada — mesmo um query builder cru
 *     sem o escopo retorna zero linhas de outros tenants.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = app(TenantContext::class)->id();
            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app(TenantContext::class)->getOrFail()->id;
            }
        });
    }
}
