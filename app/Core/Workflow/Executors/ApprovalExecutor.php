<?php

declare(strict_types=1);

namespace App\Core\Workflow\Executors;

use App\Core\Workflow\Contracts\NodeExecutor;

/**
 * Aguarda decisão humana (papel ou usuário definido em config). A instância
 * fica em WAITING; a decisão chega via API e reativa o engine->advance().
 */
class ApprovalExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'approval';
    }

    public function execute(array $node, array $context): array
    {
        return ['status' => 'waiting'];
    }
}
