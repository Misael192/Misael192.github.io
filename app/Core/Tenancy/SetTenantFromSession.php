<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tenant da UI web: enquanto a API resolve por subdomínio/header (ResolveTenant),
 * as telas Livewire resolvem pelo `tenant_slug` gravado na sessão no login.
 * Roda ANTES do `auth` para que o escopo global encontre o usuário da sessão.
 */
class SetTenantFromSession
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $slug = $request->session()->get('tenant_slug');

        if ($slug !== null) {
            $tenant = Tenant::query()->where('slug', $slug)->first();

            if ($tenant !== null && $tenant->is_active) {
                $this->context->set($tenant);

                // Ativa a RLS igual à API (defesa em profundidade).
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenant->id]);
                }
            }
        }

        return $next($request);
    }
}
