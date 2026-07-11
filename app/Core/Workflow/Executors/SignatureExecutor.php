<?php

declare(strict_types=1);

namespace App\Core\Workflow\Executors;

use App\Core\Workflow\Contracts\NodeExecutor;

/** Aguarda assinatura eletrônica (SignatureRequest) — nó humano. */
class SignatureExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'signature';
    }

    public function execute(array $node, array $context): array
    {
        return ['status' => 'waiting'];
    }
}
