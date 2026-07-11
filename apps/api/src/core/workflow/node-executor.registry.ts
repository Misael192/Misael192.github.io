/**
 * Registry de executores de nó — o mecanismo que torna o motor extensível:
 * novos tipos de nó (ex.: "chamar webhook", "agente de IA") são registrados
 * aqui sem alterar o interpretador.
 */
import { Injectable } from "@nestjs/common";
import type { NodeExecutor, WorkflowNodeType } from "./workflow.types";

@Injectable()
export class NodeExecutorRegistry {
  private readonly executors = new Map<WorkflowNodeType, NodeExecutor>();

  register(executor: NodeExecutor): void {
    this.executors.set(executor.type, executor);
  }

  get(type: WorkflowNodeType): NodeExecutor {
    const executor = this.executors.get(type);
    if (!executor) throw new Error(`Nenhum executor registrado para o nó "${type}"`);
    return executor;
  }
}
