<?php

declare(strict_types=1);

namespace App\Core\Audit;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Toda mutação HTTP bem-sucedida (POST/PUT/PATCH/DELETE) gera uma linha
 * em audit_logs — quem, o quê, quando, de onde. Auditoria nunca derruba
 * a requisição do usuário: falhas são logadas, não propagadas.
 */
class RecordApiMutations
{
    private const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly TenantContext $context,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if (
            in_array($request->method(), self::MUTATING, true)
            && $this->context->get() !== null
            && $response instanceof Response
            && $response->isSuccessful()
        ) {
            try {
                // /api/v1/<recurso>/… → entityType = <recurso>
                $segments = $request->segments();
                $this->audit->record(
                    action: $request->method().' '.$request->path(),
                    entityType: $segments[2] ?? 'unknown',
                    actorId: $request->user()?->id,
                    after: $request->except(['password', 'mfa_code']),
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                );
            } catch (\Throwable $e) {
                Log::error('Falha ao gravar auditoria', ['exception' => $e]);
            }
        }

        return $response;
    }
}
