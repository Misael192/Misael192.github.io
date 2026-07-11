<?php

declare(strict_types=1);

namespace App\Core\Workflow\Executors;

use App\Core\Workflow\Contracts\NodeExecutor;

/** Nó inicial: o evento já aconteceu, apenas segue adiante. */
class TriggerExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'trigger';
    }

    public function execute(array $node, array $context): array
    {
        return ['status' => 'completed'];
    }
}
