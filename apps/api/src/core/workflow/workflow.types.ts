/**
 * Tipos do Workflow Engine — o MESMO JSON manipulado pelo editor visual no
 * frontend, sem tradução (ADR-006).
 */

export type WorkflowNodeType =
  | "trigger"    // evento que inicia o fluxo (ex.: vacation.requested)
  | "condition"  // expressão sobre o contexto (ex.: days > 20)
  | "approval"   // aguarda decisão humana (papel ou usuário específico)
  | "document"   // gera documento a partir de template
  | "signature"  // aguarda assinatura eletrônica
  | "notification"
  | "webhook"
  | "ai-agent"   // delega um passo a um agente do AI Engine
  | "end";

export interface WorkflowNode {
  id: string;
  type: WorkflowNodeType;
  /** Configuração específica do tipo (validada pelo executor do tipo). */
  config: Record<string, unknown>;
}

export interface WorkflowEdge {
  from: string;
  to: string;
  /** Rótulo de saída para nós condicionais: "true" | "false" | "approved"… */
  label?: string;
}

export interface WorkflowDefinition {
  nodes: WorkflowNode[];
  edges: WorkflowEdge[];
}

/** Resultado da execução de um nó: para onde ir (label) ou aguardar humano. */
export interface NodeResult {
  status: "completed" | "waiting";
  outputLabel?: string;
  output?: Record<string, unknown>;
}

export interface NodeExecutor {
  readonly type: WorkflowNodeType;
  execute(node: WorkflowNode, context: Record<string, unknown>): Promise<NodeResult>;
}
