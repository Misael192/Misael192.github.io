/**
 * Registry de agentes de IA. Um agente declara nome, prompt de sistema e as
 * tools que pode usar; o engine cuida do resto (modelo, streaming, logs, RAG).
 *
 * Guard-rails da plataforma (ADR-008):
 *  - agentes nunca executam escrita sem confirmação humana;
 *  - toda tool respeita o RBAC do usuário solicitante;
 *  - todo uso é logado em AiMessage (tokens, custo, latência) para quotas.
 */
import { Injectable } from "@nestjs/common";

export interface AgentTool {
  name: string;
  description: string;
  /** JSON Schema dos parâmetros da tool. */
  parameters: Record<string, unknown>;
  execute(args: Record<string, unknown>, userId: string): Promise<unknown>;
}

export interface Agent {
  /** Identificador estável (gravado em AiConversation.agent). */
  name: string;
  description: string;
  systemPrompt: string;
  tools: AgentTool[];
  /** Fontes de RAG habilitadas (ex.: ["clt", "policy"]). */
  knowledgeSources?: string[];
}

@Injectable()
export class AgentRegistry {
  private readonly agents = new Map<string, Agent>();

  register(agent: Agent): void {
    this.agents.set(agent.name, agent);
  }

  get(name: string): Agent {
    const agent = this.agents.get(name);
    if (!agent) throw new Error(`Agente "${name}" não registrado`);
    return agent;
  }

  list(): Agent[] {
    return [...this.agents.values()];
  }
}
