<?php

declare(strict_types=1);

namespace App\Core\Workflow\Executors;

use App\Core\Workflow\Contracts\NodeExecutor;

/**
 * Condição declarativa sobre o contexto (sem eval — segurança primeiro):
 * config: { "field": "days", "operator": ">", "value": 20 }
 * Sai pela aresta "true" ou "false".
 */
class ConditionExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'condition';
    }

    public function execute(array $node, array $context): array
    {
        $field = $node['config']['field'] ?? null;
        $operator = $node['config']['operator'] ?? '==';
        $expected = $node['config']['value'] ?? null;
        $actual = data_get($context, $field);

        $result = match ($operator) {
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            '!=' => $actual != $expected,
            default => $actual == $expected,
        };

        return ['status' => 'completed', 'outputLabel' => $result ? 'true' : 'false'];
    }
}
