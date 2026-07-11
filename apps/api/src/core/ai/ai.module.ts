import { Module } from "@nestjs/common";
import { AgentRegistry } from "./agents/agent.registry";
import { AiEngineService } from "./engine/ai-engine.service";

/**
 * AI Engine (ADR-008) — módulo independente do Core.
 * Estrutura completa: engine, chat, prompts, knowledge, embeddings, rag,
 * agents, memory, tools, logs. Agentes novos = novos providers no registry;
 * o restante da plataforma não muda.
 */
@Module({
  providers: [AiEngineService, AgentRegistry],
  exports: [AiEngineService, AgentRegistry],
})
export class AiModule {}
