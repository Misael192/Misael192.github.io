<?php

declare(strict_types=1);

namespace App\Core\Workflow;

use App\Core\Workflow\Contracts\NodeExecutor;
use InvalidArgumentException;

/** Registry de executores — o mecanismo que torna o motor extensível. */
class NodeExecutorRegistry
{
    /** @var array<string, NodeExecutor> */
    private array $executors = [];

    public function register(NodeExecutor $executor): void
    {
        $this->executors[$executor->type()] = $executor;
    }

    public function get(string $type): NodeExecutor
    {
        return $this->executors[$type]
            ?? throw new InvalidArgumentException("Nenhum executor registrado para o nó \"{$type}\"");
    }
}
