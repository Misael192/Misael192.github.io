<?php

declare(strict_types=1);

namespace App\Core\FeatureFlags;

use App\Core\Tenancy\TenantContext;
use App\Models\FeatureFlag;
use Illuminate\Support\Facades\DB;

/**
 * Feature flags e habilitação de módulos por tenant (ARCHITECTURE.md §11).
 * Um plano é, na prática, um conjunto de módulos + quotas — a verificação
 * de módulo consulta tenant_modules (alimentada pelo Billing).
 */
class FeatureFlags
{
    public function __construct(private readonly TenantContext $context) {}

    /** Módulo comercial habilitado para o tenant atual? (people, recruitment…) */
    public function moduleEnabled(string $moduleCode): bool
    {
        $tenantId = $this->context->getOrFail()->id;

        return DB::table('tenant_modules')
            ->join('modules', 'modules.id', '=', 'tenant_modules.module_id')
            ->where('tenant_modules.tenant_id', $tenantId)
            ->where('tenant_modules.is_enabled', true)
            ->where('modules.code', $moduleCode)
            ->where(fn ($q) => $q->whereNull('tenant_modules.expires_at')
                ->orWhere('tenant_modules.expires_at', '>', now()))
            ->exists();
    }

    /** Flag granular: override por tenant → global → rollout percentual. */
    public function flagEnabled(string $key): bool
    {
        $tenantId = $this->context->getOrFail()->id;
        $flag = FeatureFlag::query()->where('key', $key)->first();

        if ($flag === null) {
            return false;
        }
        if (in_array($tenantId, $flag->disabled_tenant_ids ?? [], true)) {
            return false;
        }
        if (in_array($tenantId, $flag->enabled_tenant_ids ?? [], true)) {
            return true;
        }
        if ($flag->enabled_globally) {
            return true;
        }
        if ($flag->rollout_percentage > 0) {
            // Hash determinístico: o mesmo tenant cai sempre no mesmo bucket.
            $bucket = hexdec(substr(sha1($tenantId), 0, 8)) % 100;

            return $bucket < $flag->rollout_percentage;
        }

        return false;
    }
}
