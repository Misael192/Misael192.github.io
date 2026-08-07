<?php

declare(strict_types=1);

namespace App\Core\FeatureFlags;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Middleware `module:<code>` — bloqueia rotas de módulos não contratados
 * pelo tenant (a resposta é oportunidade de upsell na UI).
 */
class EnsureModuleEnabled
{
    public function __construct(private readonly FeatureFlags $flags) {}

    public function handle(Request $request, Closure $next, string $moduleCode): mixed
    {
        if (! $this->flags->moduleEnabled($moduleCode)) {
            throw new AccessDeniedHttpException(
                "O módulo \"{$moduleCode}\" não está habilitado para esta empresa"
            );
        }

        return $next($request);
    }
}
