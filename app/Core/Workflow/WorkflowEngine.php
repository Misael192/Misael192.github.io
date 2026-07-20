<?php

declare(strict_types=1);

namespace App\Core\Workflow;

use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowTemplate;
use Illuminate\Support\Facades\Log;

/**
 * Interpretador do Workflow Engine (ADR-006).
 *
 * Orientado a eventos: instâncias avançam nó a nó; nós "humanos" (aprovação,
 * assinatura) deixam a instância em WAITING até a ação chegar. Cada passo
 * executado vira uma linha em workflow_step_executions (histórico auditável).
 * O JSON de `definition` é o MESMO manipulado pelo editor visual.
 */
class WorkflowEngine
{
    public function __construct(private readonly NodeExecutorRegistry $registry)
    {
    }

    /** Inicia instâncias de todo template ativo cujo gatilho é este evento. */
    public function onDomainEvent(string $eventName, array $payload): void
    {
        WorkflowTemplate::query()
            ->where('trigger_event', $eventName)
            ->where('is_active', true)
            ->each(fn (WorkflowTemplate $template) => $this->start($template, $eventName, $payload));
    }

    public function start(WorkflowTemplate $template, string $entityType, array $context): void
    {
        $trigger = collect($template->definition['nodes'] ?? [])
            ->firstWhere('type', 'trigger');

        if ($trigger === null) {
            Log::warning("Template {$template->id} sem nó trigger — ignorado");

            return;
        }

        $instance = WorkflowInstance::query()->create([
            'template_id' => $template->id,
            'entity_type' => $entityType,
            'entity_id' => $context['id'] ?? '00000000-0000-0000-0000-000000000000',
            'current_node_id' => $trigger['id'],
            'context' => $context,
        ]);

        $this->advance($instance);
    }

    /** Avança a instância até encontrar um nó WAITING ou o fim do grafo. */
    public function advance(WorkflowInstance $instance): void
    {
        $definition = $instance->template->definition;
        $nodes = collect($definition['nodes'] ?? [])->keyBy('id');
        $currentId = $instance->current_node_id;
        $context = $instance->context;

        while ($currentId !== null && ($node = $nodes->get($currentId)) !== null) {
            if ($node['type'] === 'end') {
                $instance->update([
                    'status' => WorkflowInstance::STATUS_COMPLETED,
                    'current_node_id' => $node['id'],
                    'finished_at' => now(),
                ]);

                return;
            }

            $result = $this->executeStep($instance, $node, $context);

            if (($result['status'] ?? null) === 'waiting') {
                $instance->update([
                    'status' => WorkflowInstance::STATUS_WAITING,
                    'current_node_id' => $node['id'],
                    'context' => $context,
                ]);

                return;
            }

            $context = array_merge($context, $result['output'] ?? []);
            $currentId = $this->nextNode($definition, $node['id'], $result['outputLabel'] ?? null);
        }
    }

    private function executeStep(WorkflowInstance $instance, array $node, array $context): array
    {
        $step = WorkflowStepExecution::query()->create([
            'instance_id' => $instance->id,
            'node_id' => $node['id'],
            'node_type' => $node['type'],
            'status' => 'pending',
            'input' => $context,
        ]);

        $result = $this->registry->get($node['type'])->execute($node, $context);

        $step->update([
            'status' => ($result['status'] ?? null) === 'waiting' ? 'pending' : 'completed',
            'output' => $result['output'] ?? null,
            'finished_at' => ($result['status'] ?? null) === 'waiting' ? null : now(),
        ]);

        return $result;
    }

    /** Resolve a próxima aresta; nós condicionais escolhem pela label. */
    private function nextNode(array $definition, string $fromId, ?string $label): ?string
    {
        $edges = collect($definition['edges'] ?? [])->where('from', $fromId);
        $edge = $label !== null
            ? ($edges->firstWhere('label', $label) ?? $edges->first())
            : $edges->first();

        return $edge['to'] ?? null;
    }
}
