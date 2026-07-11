<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Tenancy\TenantContext;
use App\Core\Workflow\WorkflowEngine;
use App\Models\Tenant;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Interpretador do Workflow Engine (ADR-006): condições e nós de espera. */
class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::query()->create(['slug' => 'wf', 'name' => 'WF']);
        app(TenantContext::class)->set($tenant);
    }

    /** Fluxo aprovado na revisão: Solicitação → Condição → Aprovação → Fim. */
    private function template(): WorkflowTemplate
    {
        return WorkflowTemplate::query()->create([
            'name' => 'Aprovação de férias longas',
            'trigger_event' => 'vacation.requested',
            'definition' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'trigger', 'config' => []],
                    ['id' => 'n2', 'type' => 'condition', 'config' => ['field' => 'days', 'operator' => '>', 'value' => 20]],
                    ['id' => 'n3', 'type' => 'approval', 'config' => ['role' => 'MANAGER']],
                    ['id' => 'n4', 'type' => 'end', 'config' => []],
                ],
                'edges' => [
                    ['from' => 'n1', 'to' => 'n2'],
                    ['from' => 'n2', 'to' => 'n3', 'label' => 'true'],
                    ['from' => 'n2', 'to' => 'n4', 'label' => 'false'],
                    ['from' => 'n3', 'to' => 'n4'],
                ],
            ],
        ]);
    }

    public function test_ferias_longas_param_no_no_de_aprovacao(): void
    {
        app(WorkflowEngine::class)->onDomainEvent('vacation.requested', [
            'id' => '11111111-1111-1111-1111-111111111111',
            'days' => 30,
        ]);
        $this->template(); // template criado após o evento não dispara

        $engine = app(WorkflowEngine::class);
        $engine->onDomainEvent('vacation.requested', [
            'id' => '22222222-2222-2222-2222-222222222222',
            'days' => 30,
        ]);

        $instance = WorkflowInstance::query()->latest('started_at')->first();
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);
        $this->assertSame('n3', $instance->current_node_id);
    }

    public function test_ferias_curtas_completam_sem_aprovacao(): void
    {
        $this->template();

        app(WorkflowEngine::class)->onDomainEvent('vacation.requested', [
            'id' => '33333333-3333-3333-3333-333333333333',
            'days' => 10,
        ]);

        $instance = WorkflowInstance::query()->firstOrFail();
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        // Histórico auditável: trigger + condição executados.
        $this->assertSame(['trigger', 'condition'], $instance->steps()->orderBy('started_at')->pluck('node_type')->all());
    }
}
