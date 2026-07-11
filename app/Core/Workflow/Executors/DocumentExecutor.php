<?php

declare(strict_types=1);

namespace App\Core\Workflow\Executors;

use App\Core\Workflow\Contracts\NodeExecutor;

/**
 * Gera documento a partir de template (config.template). A geração real
 * (GED + AI Engine) entra na Fase 2; o contrato do nó é definitivo.
 */
class DocumentExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'document';
    }

    public function execute(array $node, array $context): array
    {
        return ['status' => 'completed', 'output' => ['document_template' => $node['config']['template'] ?? null]];
    }
}
