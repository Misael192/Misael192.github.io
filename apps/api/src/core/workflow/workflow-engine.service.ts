/**
 * Interpretador do Workflow Engine (ADR-006).
 *
 * Orientado a eventos: instâncias avançam nó a nó; nós "humanos" (aprovação,
 * assinatura) deixam a instância em WAITING até a ação chegar. Cada passo
 * executado vira uma linha em WorkflowStepExecution (histórico auditável).
 */
import { Injectable, Logger, OnModuleInit } from "@nestjs/common";
import { EventBus } from "../events/event-bus";
import { PrismaService } from "../prisma/prisma.service";
import { NodeExecutorRegistry } from "./node-executor.registry";
import type { WorkflowDefinition, WorkflowNode } from "./workflow.types";

@Injectable()
export class WorkflowEngineService implements OnModuleInit {
  private readonly logger = new Logger(WorkflowEngineService.name);

  constructor(
    private readonly prisma: PrismaService,
    private readonly eventBus: EventBus,
    private readonly registry: NodeExecutorRegistry,
  ) {}

  onModuleInit() {
    // Qualquer evento pode ser gatilho de template — a assinatura é dinâmica,
    // resolvida por consulta em WorkflowTemplate.triggerEvent.
    this.eventBus.subscribe("**", (event) => this.onDomainEvent(event.type, event.tenantId, event.payload));
  }

  /** Inicia instâncias para todo template ativo cujo gatilho é este evento. */
  private async onDomainEvent(type: string, tenantId: string, payload: unknown): Promise<void> {
    const templates = await this.prisma.workflowTemplate.findMany({
      where: { tenantId, triggerEvent: type, isActive: true, deletedAt: null },
    });
    for (const template of templates) {
      await this.startInstance(template.id, tenantId, type, payload as Record<string, unknown>);
    }
  }

  async startInstance(
    templateId: string,
    tenantId: string,
    entityType: string,
    context: Record<string, unknown>,
  ): Promise<void> {
    const template = await this.prisma.workflowTemplate.findUniqueOrThrow({ where: { id: templateId } });
    const definition = template.definition as unknown as WorkflowDefinition;
    const trigger = definition.nodes.find((n) => n.type === "trigger");
    if (!trigger) {
      this.logger.warn(`Template ${templateId} sem nó trigger — ignorado`);
      return;
    }

    const instance = await this.prisma.workflowInstance.create({
      data: {
        tenantId,
        templateId,
        entityType,
        entityId: (context.id as string) ?? "00000000-0000-0000-0000-000000000000",
        currentNodeId: trigger.id,
        context: context as object,
      },
    });
    await this.advance(instance.id);
  }

  /** Avança a instância até encontrar um nó WAITING ou o fim do grafo. */
  async advance(instanceId: string): Promise<void> {
    const instance = await this.prisma.workflowInstance.findUniqueOrThrow({
      where: { id: instanceId },
      include: { template: true },
    });
    const definition = instance.template.definition as unknown as WorkflowDefinition;
    let currentId = instance.currentNodeId;
    let context = instance.context as Record<string, unknown>;

    while (currentId) {
      const node = definition.nodes.find((n) => n.id === currentId);
      if (!node) break;

      if (node.type === "end") {
        await this.prisma.workflowInstance.update({
          where: { id: instanceId },
          data: { status: "COMPLETED", finishedAt: new Date(), currentNodeId: node.id },
        });
        await this.eventBus.publish({
          type: "workflow.completed.v1",
          tenantId: instance.tenantId,
          payload: { instanceId },
        });
        return;
      }

      const result = await this.executeStep(instance.tenantId, instanceId, node, context);

      if (result.status === "waiting") {
        await this.prisma.workflowInstance.update({
          where: { id: instanceId },
          data: { status: "WAITING", currentNodeId: node.id, context: context as object },
        });
        return;
      }

      context = { ...context, ...(result.output ?? {}) };
      currentId = this.nextNode(definition, node.id, result.outputLabel);
    }
  }

  private async executeStep(
    tenantId: string,
    instanceId: string,
    node: WorkflowNode,
    context: Record<string, unknown>,
  ) {
    const step = await this.prisma.workflowStepExecution.create({
      data: { tenantId, instanceId, nodeId: node.id, nodeType: node.type, status: "pending", input: context as object },
    });
    const result = await this.registry.get(node.type).execute(node, context);
    await this.prisma.workflowStepExecution.update({
      where: { id: step.id },
      data: {
        status: result.status === "waiting" ? "pending" : "completed",
        output: (result.output ?? null) as object,
        finishedAt: result.status === "waiting" ? null : new Date(),
      },
    });
    await this.eventBus.publish({
      type: "workflow.step.completed.v1",
      tenantId,
      payload: { instanceId, nodeId: node.id, nodeType: node.type },
    });
    return result;
  }

  private nextNode(definition: WorkflowDefinition, fromId: string, label?: string): string | null {
    const edges = definition.edges.filter((e) => e.from === fromId);
    const edge = label ? edges.find((e) => e.label === label) ?? edges[0] : edges[0];
    return edge?.to ?? null;
  }
}
