<?php

declare(strict_types=1);

namespace App\Core\Workflow\Contracts;

/**
 * Executor de um tipo de nó do workflow. Tipos são plugáveis via registry —
 * novos tipos (webhook, agente de IA…) não alteram o interpretador (ADR-006).
 *
 * Retorno de execute():
 *   ['status' => 'completed', 'outputLabel' => ?string, 'output' => array]
 *   ['status' => 'waiting'] — aguarda ação humana (aprovação, assinatura)
 */
interface NodeExecutor
{
    public function type(): string;

    public function execute(array $node, array $context): array;
}
