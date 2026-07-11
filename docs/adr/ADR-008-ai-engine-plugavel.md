# ADR-008 — AI Engine independente com agentes plugáveis

**Status:** Aceito · **Data:** 2026-07-11

## Decisão
A IA é um módulo do Core (`core/ai`) com subcamadas: engine, chat, prompt library,
knowledge base, embeddings (pgvector), RAG, agents, memory, tools e logs.
Agentes implementam uma interface (`name`, `systemPrompt`, `tools`) e são registrados em um
registry — novos agentes não alteram o restante da plataforma. O provedor de LLM fica atrás
do port `LlmProvider` (Anthropic como adapter padrão).

## Justificativa
- Requisito aprovado: adicionar agentes futuros sem tocar na plataforma.
- pgvector evita um banco vetorial dedicado enquanto o volume não exigir.
- Logs de toda chamada (tokens, custo, latência, tenant) alimentam Billing (quotas de IA)
  e observabilidade.

## Guard-rails
- IA nunca executa escrita sem confirmação humana; toda tool respeita o RBAC do usuário.
- PII é mascarada antes de sair para o provedor quando o tenant exigir (config LGPD).

## Alternativas consideradas
- **Chamadas de LLM espalhadas pelos módulos** — sem controle de custo, sem auditoria, lock-in.
- **Banco vetorial dedicado (Pinecone/Qdrant)** — mais infra sem necessidade no volume atual.
