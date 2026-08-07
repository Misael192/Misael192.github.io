<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Resolve o tenant da requisição, na ordem de precedência (ARCHITECTURE.md §4):
 * subdomínio → header X-Tenant-Id. Quando mais de uma fonte está presente,
 * elas DEVEM concordar — divergência é tratada como tentativa cross-tenant.
 *
 * Após resolver, fixa `app.tenant_id` na sessão PostgreSQL, ativando a RLS.
 */
class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $fromHeader = $request->header('X-Tenant-Id');
        $fromSubdomain = $this->subdomain($request->getHost());

        $slug = $fromHeader ?? $fromSubdomain;
        if ($slug === null) {
            throw new BadRequestHttpException('Tenant não identificado (subdomínio ou X-Tenant-Id)');
        }
        if ($fromHeader !== null && $fromSubdomain !== null && $fromHeader !== $fromSubdomain) {
            throw new BadRequestHttpException('Fontes de tenant divergentes');
        }

        /** @var Tenant|null $tenant — lookup fora da RLS (ponto de entrada do isolamento) */
        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->orWhere('id', $slug)
            ->first();

        if ($tenant === null || ! $tenant->is_active) {
            throw new BadRequestHttpException('Tenant inválido ou inativo');
        }

        $this->context->set($tenant);

        // Ativa a RLS: qualquer query fora do escopo retorna zero linhas.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenant->id]);
        }

        return $next($request);
    }

    private function subdomain(string $host): ?string
    {
        // empresa.peopleflow.com.br → "empresa"; localhost não tem subdomínio.
        $parts = explode('.', $host);

        return count($parts) > 2 ? $parts[0] : null;
    }
}
