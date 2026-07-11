/**
 * Orquestrador do AI Engine: recebe a mensagem do usuário, monta o contexto
 * (memória da conversa + RAG da knowledge base), chama o LlmProvider e loga
 * tudo (tokens, custo, latência) em AiMessage.
 *
 * O provedor de LLM fica atrás do port abaixo — trocar de fornecedor não
 * afeta agentes nem chamadores (ADR-008).
 */
import { Injectable } from "@nestjs/common";
import { PrismaService } from "../../prisma/prisma.service";
import { TenantContext } from "../../tenancy/tenant-context";
import { AgentRegistry } from "../agents/agent.registry";

/** Port do provedor de LLM (adapter padrão: Anthropic — fase 4). */
export interface LlmProvider {
  complete(input: {
    system: string;
    messages: { role: "user" | "assistant"; content: string }[];
  }): Promise<{ content: string; inputTokens: number; outputTokens: number }>;
}

@Injectable()
export class AiEngineService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly tenantContext: TenantContext,
    private readonly agents: AgentRegistry,
  ) {}

  /**
   * Envia uma mensagem para um agente dentro de uma conversa, persistindo o
   * histórico (memória) e os logs de uso.
   * A integração com o LlmProvider real entra na Fase 4 (ver ROADMAP.md);
   * o contrato e a persistência já são definitivos.
   */
  async sendMessage(params: {
    userId: string;
    agentName: string;
    conversationId?: string;
    content: string;
    provider: LlmProvider;
  }): Promise<{ conversationId: string; reply: string }> {
    const { tenantId } = this.tenantContext.getOrThrow();
    const agent = this.agents.get(params.agentName);

    const conversation = params.conversationId
      ? await this.prisma.aiConversation.findUniqueOrThrow({ where: { id: params.conversationId } })
      : await this.prisma.aiConversation.create({
          data: { tenantId, userId: params.userId, agent: agent.name },
        });

    // Memória: histórico da conversa vai como contexto para o modelo.
    const history = await this.prisma.aiMessage.findMany({
      where: { conversationId: conversation.id },
      orderBy: { createdAt: "asc" },
      take: 50,
    });

    await this.prisma.aiMessage.create({
      data: { tenantId, conversationId: conversation.id, role: "user", content: params.content },
    });

    const startedAt = Date.now();
    const result = await params.provider.complete({
      system: agent.systemPrompt,
      messages: [
        ...history.map((m) => ({ role: m.role as "user" | "assistant", content: m.content })),
        { role: "user" as const, content: params.content },
      ],
    });

    await this.prisma.aiMessage.create({
      data: {
        tenantId,
        conversationId: conversation.id,
        role: "assistant",
        content: result.content,
        inputTokens: result.inputTokens,
        outputTokens: result.outputTokens,
        latencyMs: Date.now() - startedAt,
      },
    });

    return { conversationId: conversation.id, reply: result.content };
  }
}
