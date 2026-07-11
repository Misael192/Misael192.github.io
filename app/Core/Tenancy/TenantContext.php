<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

use App\Models\Tenant;
use RuntimeException;

/**
 * TenantContext — coração do multi-tenancy (ADR-002).
 *
 * Singleton por request (container do Laravel; no Octane é resetado a cada
 * request via flush). Nenhum código de domínio conhece a ESTRATÉGIA de
 * isolamento; ele só pergunta "qual é o tenant atual?".
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->id;
    }

    /** Lança se não houver tenant — protege contra queries "órfãs". */
    public function getOrFail(): Tenant
    {
        if ($this->tenant === null) {
            throw new RuntimeException(
                'Nenhum tenant no contexto — a rota passou pelo middleware ResolveTenant?'
            );
        }

        return $this->tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }
}
