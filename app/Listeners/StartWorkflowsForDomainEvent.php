<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Workflow\WorkflowEngine;
use App\Events\DomainEvent;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Conecta o Event Bus ao Workflow Engine: qualquer evento de domínio pode
 * ser gatilho de um template desenhado pelo tenant. Enfileirado (Horizon)
 * para não bloquear a requisição; idempotência garantida pelo engine.
 */
class StartWorkflowsForDomainEvent implements ShouldQueue
{
    public function __construct(private readonly WorkflowEngine $engine) {}

    public function handle(DomainEvent $event): void
    {
        $this->engine->onDomainEvent($event::name(), $event->payload);
    }
}
