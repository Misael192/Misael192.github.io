# PeopleFlow — Documento de Arquitetura da Plataforma

> **Versão:** 1.0 · **Status:** Aprovado (Fase 1) · **Última atualização:** 2026-07-11
>
> Este documento incorpora todos os requisitos adicionais aprovados na revisão da Fase 1.
> Cada decisão técnica relevante traz uma **justificativa** e as **alternativas consideradas**
> (ver também os ADRs em [`docs/adr/`](./adr/)).

---

## 1. Visão

A PeopleFlow não é apenas um SaaS de RH — é uma **plataforma HCM** (Human Capital Management)
multi-tenant, modular e orientada a eventos, projetada para operar de **1 a 10.000 empresas
sem refatoração arquitetural**.

```
PeopleFlow Platform
├── Core
│   ├── Identity            (usuários, sessões, OAuth, MFA)
│   ├── IAM                 (autenticação, tokens, API keys)
│   ├── RBAC                (papéis, permissões, grupos, ABAC opcional)
│   ├── Organizations       (organizações, empresas, filiais, departamentos, times, cargos, centros de custo)
│   ├── Tenants             (isolamento e resolução de tenant)
│   ├── Billing             (planos, assinaturas, faturas, limites)
│   ├── Notifications       (in-app, e-mail, push)
│   ├── Audit               (trilha de auditoria imutável)
│   ├── Files               (GED: armazenamento, versões, assinaturas)
│   ├── AI                  (AI Engine: chat, prompts, RAG, agentes)
│   ├── Workflow Engine     (motor visual de fluxos por empresa)
│   └── API Gateway         (versionamento, rate-limit, docs OpenAPI)
│
└── Modules                 (ativáveis por empresa via Feature Flags)
    ├── People              (colaboradores, admissão digital, férias, ponto)
    ├── Payroll             (folha, holerites — estrutura preparada)
    ├── Recruitment         (vagas, trabalhe conosco, pipeline Kanban)
    ├── Benefits            (benefícios, convênios, VT/VA, plano de saúde)
    ├── Learning            (treinamentos, cursos, certificados)
    ├── Performance         (metas, avaliações, plano de carreira)
    ├── Documents           (GED por colaborador, e-sign)
    ├── Analytics           (dashboards, turnover, absenteísmo)
    └── Marketplace         (integrações de terceiros — futuro)
```

**Princípio central:** o *Core* nunca conhece os *Modules*. Módulos dependem do Core e
comunicam-se entre si **exclusivamente via barramento de eventos** e contratos públicos.
É isso que permite adicionar, remover ou vender módulos por empresa sem acoplamento.

---

## 2. Topologia de sistemas

```
┌────────────────────────────────────────────────────────────┐
│                        Clientes                            │
│  Landing · Painel Admin · RH · DP · Gestor · Colaborador   │
│              Next.js 15 (Vercel) — SSR/ISR                 │
└───────────────────────────┬────────────────────────────────┘
                            │ HTTPS · /api/v1 · JWT/cookies
┌───────────────────────────▼────────────────────────────────┐
│                   API Gateway (NestJS)                     │
│  versionamento · rate-limit · helmet/CSP · OpenAPI         │
├────────────────────────────────────────────────────────────┤
│   Core Services          │        Modules                  │
│   (IAM, RBAC, Tenants,   │  (People, Recruitment, ...)     │
│    Billing, Audit, AI,   │  carregados como Nest modules   │
│    Workflow, Files)      │  habilitados por feature flag   │
├───────────────┬──────────┴───────────┬─────────────────────┤
│  Event Bus    │   Prisma ORM         │  BullMQ (filas)     │
│ (in-process → │                      │                     │
│  Redis Streams)│                     │                     │
└───────┬───────┴──────────┬───────────┴──────────┬──────────┘
        │                  │                      │
   ┌────▼────┐       ┌─────▼─────┐          ┌─────▼─────┐
   │  Redis  │       │ PostgreSQL │         │  Storage  │
   │ cache · │       │ (RLS por   │         │ Supabase/ │
   │ filas   │       │  tenant)   │         │    S3     │
   └─────────┘       └───────────┘          └───────────┘
```

**Monólito modular primeiro, microsserviços depois.**

> **Justificativa:** um monólito modular NestJS com fronteiras rígidas entre módulos entrega
> a mesma modularidade lógica de microsserviços com uma fração do custo operacional
> (um deploy, uma transação de banco, debugging trivial). Como toda comunicação entre módulos
> já passa pelo barramento de eventos e por interfaces públicas, extrair um módulo para um
> serviço separado no futuro é uma mudança de infraestrutura, não de código.
> **Alternativas consideradas:** microsserviços desde o início (rejeitado: complexidade
> operacional injustificável antes de tração); serverless functions (rejeitado: cold starts,
> difícil manter conexões de banco e workers de fila de longa duração).

---

## 3. Stack e justificativas

| Camada | Tecnologia | Justificativa | Alternativas consideradas |
|---|---|---|---|
| Frontend | **Next.js 15 + React + TypeScript** | App Router com RSC reduz JS no cliente; SSR/ISR para landing (SEO) e painéis rápidos; ecossistema maduro; deploy nativo na Vercel. | Remix (menor ecossistema), Vite SPA (perde SSR/SEO), Angular (produtividade menor para design premium) |
| Estilo | **Tailwind CSS + Design System próprio (tokens)** | Tokens próprios garantem identidade e permitem trocar a base de componentes sem reescrever telas; Tailwind dá velocidade com consistência. | CSS Modules (verboso), styled-components (custo de runtime), só Shadcn (acopla identidade visual a terceiros) |
| Componentes | **Shadcn UI como base + camada PeopleFlow** | Shadcn é copiado para o repositório (sem lock-in) e recebe os tokens da PeopleFlow por cima. | MUI/Ant (visual difícil de customizar profundamente), Radix puro (mais trabalho) |
| Animação | **Framer Motion** | Padrão de mercado para micro-interações React; API declarativa; respeita `prefers-reduced-motion`. | CSS puro (limitado para orquestração), GSAP (licença/peso) |
| Backend | **NestJS + TypeScript** | DI nativa viabiliza Clean Architecture e testes; sistema de módulos espelha a modularidade da plataforma; guards/interceptors para RBAC e auditoria transversais; OpenAPI de primeira classe. | Express puro (sem estrutura), Fastify puro (idem — Nest usa Fastify como adapter se preciso), Spring Boot (troca de linguagem, time TS) |
| Banco | **PostgreSQL** | RLS nativa (isolamento por tenant no nível do banco), JSONB para dados flexíveis (workflows, settings), `pgvector` para embeddings de IA, maturidade transacional. | MySQL (sem RLS equivalente), MongoDB (dados de RH são fortemente relacionais) |
| ORM | **Prisma** | Schema declarativo como fonte única de verdade, migrações versionadas, type-safety ponta a ponta com o TS do domínio. | TypeORM (migrações frágeis), Drizzle (promissor, ecossistema menor), SQL puro (produtividade) |
| Cache/Filas | **Redis (+ BullMQ)** | Cache de sessões/flags, rate-limiting distribuído, filas para jobs (notificações, relatórios, eventos de workflow) e Redis Streams como transporte de eventos quando escalar horizontalmente. | RabbitMQ (mais uma peça de infra; Redis já cobre o estágio atual), Kafka (overkill até dezenas de milhares de tenants) |
| Auth | **JWT (access curto) + Refresh Token rotativo** | Stateless para escalar horizontalmente; rotação com detecção de reuso mitiga roubo de refresh token; API Keys para integrações máquina-a-máquina. | Sessões server-side (estado compartilhado), Auth as a Service (custo por MAU e lock-in em identidade — núcleo do negócio) |
| Senhas | **Argon2id** | Vencedor da Password Hashing Competition; resistente a GPU/ASIC; parâmetros de memória ajustáveis. | bcrypt (mais fraco contra hardware moderno), scrypt (menos suporte) |
| Storage | **Supabase Storage (MVP) → Amazon S3 (escala)** | Mesmo contrato interno (`FileStorage` port) para os dois drivers; Supabase acelera o MVP, S3 atende compliance/volume Enterprise. | Somente S3 (setup mais lento no MVP), disco local (não escala, sem durabilidade) |
| Infra | **Docker · Vercel (web) · Railway (API/DB/Redis)** | Deploys independentes de front e back; Railway dá Postgres/Redis gerenciados com custo previsível no início. | AWS completa (complexidade prematura), Render/Fly (equivalentes — Railway escolhido por DX) |
| Observabilidade | **OpenTelemetry + logs estruturados (pino) + Sentry** | OTel é padrão aberto: instrumenta uma vez, exporta para qualquer backend (Grafana, Datadog...). | Instrumentação proprietária (lock-in) |

---

## 4. Multi-tenancy — estratégia evolutiva em 3 níveis

Requisito aprovado: o sistema deve suportar **três estratégias sem reescrita**.

```
Nível 1: Tenant por COLUNA  (padrão — todos os planos)
   └── tenant_id em todas as tabelas + Postgres RLS
Nível 2: Tenant por SCHEMA  (planos Business/Enterprise)
   └── um schema Postgres por tenant, mesmo cluster
Nível 3: Tenant por BANCO   (Enterprise dedicado / compliance)
   └── banco (ou cluster) exclusivo por tenant
```

**Como isso é possível sem reescrever nada:** nenhum código de domínio conhece a estratégia.
Todo acesso a dados passa por um **`TenantContext`** (AsyncLocalStorage) e por uma fábrica de
conexão (`PrismaService`) que resolve, por tenant, *onde* e *como* conectar:

```ts
// A única peça que conhece a estratégia do tenant:
interface TenantConnectionResolver {
  resolve(tenant: Tenant): DataSourceRef;
  // COLUMN  → conexão compartilhada + RLS (SET app.tenant_id)
  // SCHEMA  → conexão com search_path = tenant_<slug>
  // DATABASE→ URL de conexão própria (campo Tenant.databaseUrl)
}
```

Regras de implementação obrigatórias desde a Fase 1:

1. **Toda tabela de domínio tem `tenant_id`** (mesmo no nível schema/banco — o custo é nulo e
   permite mover tenants entre níveis com um `pg_dump` filtrado).
2. **RLS habilitada** nas tabelas compartilhadas: `USING (tenant_id = current_setting('app.tenant_id')::uuid)`.
   A aplicação define `app.tenant_id` no início de cada transação. Defesa em profundidade:
   mesmo um bug de query não vaza dados entre tenants.
3. **Resolução do tenant** por subdomínio (`empresa.peopleflow.com.br`), header `X-Tenant-Id`
   (integrações) ou claim do JWT — nessa ordem de precedência, sempre validados entre si.
4. O campo `Tenant.isolationLevel` (`COLUMN | SCHEMA | DATABASE`) e `Tenant.databaseUrl`
   já existem no schema (ver §5), então a migração de nível é uma operação de dados + infra.

> **Justificativa:** começar com coluna+RLS maximiza densidade (custo por tenant ≈ zero) e
> simplicidade de migração de schema (uma migração para todos). Schema/banco dedicado é
> exigência real de clientes Enterprise (compliance, backup próprio, ruído de vizinho).
> Preparar a abstração agora custa ~2 dias; adaptar depois custaria meses.
> **Alternativa rejeitada:** schema-per-tenant desde o início — 10.000 schemas tornam
> migrações e connection pooling um problema operacional sério antes de haver receita.

---

## 5. Modelo de dados (entidades da arquitetura inicial)

Fonte única de verdade: [`packages/database/prisma/schema.prisma`](../packages/database/prisma/schema.prisma).
Todas as entidades exigidas na aprovação da Fase 1 existem desde a primeira migração:

| Domínio | Entidades |
|---|---|
| Tenancy & Org | `Tenant`, `Organization`, `Company`, `Branch`, `Department`, `Team`, `Position`, `CostCenter` |
| Identity & IAM | `User`, `Session`, `OAuthAccount`, `ApiKey`, `MfaCredential` |
| RBAC | `Role`, `Permission`, `PermissionGroup`, `RolePermission`, `UserRole` |
| Plataforma | `Setting`, `FeatureFlag`, `Module`, `TenantModule`, `Integration`, `WebhookLog`, `Notification` |
| Billing | `Plan`, `Subscription`, `Invoice` |
| IA | `AiConversation`, `AiMessage`, `AiPromptTemplate`, `KnowledgeDocument` (embeddings/pgvector) |
| Workflow | `WorkflowTemplate`, `WorkflowInstance`, `WorkflowStepExecution` (history) |
| Auditoria | `AuditLog` (append-only) |
| People/DP | `Employee`, `EmploymentContract`, `TimeEntry`, `WorkSchedule`, `TimeBankEntry`, `VacationRequest`, `Document`, `DocumentVersion`, `SignatureRequest`, `Payslip` |
| RH | `JobOpening`, `Candidate`, `JobApplication`, `Interview`, `Benefit`, `EmployeeBenefit`, `Goal`, `PerformanceReview`, `Training`, `TrainingEnrollment`, `Survey`, `SurveyResponse`, `Announcement`, `Recognition` |

Convenções:

- **IDs:** UUID v7 (ordenáveis no tempo → índices B-tree eficientes).
- **Soft delete** (`deletedAt`) em entidades de negócio; exclusão física apenas via rotina LGPD.
- **Dados pessoais sensíveis** (CPF, RG, dados bancários, saúde) em colunas criptografadas
  (AES-256-GCM na aplicação) — ver §7.
- **`Setting` e `FeatureFlag`** têm escopo hierárquico: plataforma → tenant → empresa → usuário.

---

## 6. Arquitetura de código (Clean Architecture + SOLID)

Cada módulo NestJS segue o mesmo layout hexagonal:

```
modules/people/
├── domain/          # Entidades, value objects, regras — zero dependências de framework
├── application/     # Use cases (services), ports (interfaces), DTOs, eventos emitidos
├── infrastructure/  # Prisma repositories, adapters (storage, e-mail), consumers de eventos
└── presentation/    # Controllers REST /api/v1, guards, mapeamento HTTP ⇄ DTO
```

Regra de dependência: `presentation → application → domain` e `infrastructure → application`.
O domínio nunca importa Prisma, Nest ou HTTP.

> **Justificativa:** o custo de disciplina em camadas é pago de volta em testabilidade
> (use cases testados sem banco) e na futura extração de módulos para serviços.
> **Alternativa rejeitada:** MVC "fino" (controllers gordos) — colapsa com a quantidade de
> regras trabalhistas (CLT) que o domínio de DP carrega.

---

## 7. Segurança e LGPD (desde a v1)

| Controle | Implementação |
|---|---|
| **MFA** | TOTP (RFC 6238) + códigos de recuperação; obrigatório configurável por tenant |
| **RBAC** | Permissões `recurso:ação` (ex.: `vacations:approve`), agrupadas em `PermissionGroup`, atribuídas a `Role` por escopo (tenant/empresa/filial) |
| **ABAC** | Condições por atributo quando necessário (ex.: gestor só aprova férias *da própria equipe*) avaliadas por policy handlers sobre o RBAC |
| **JWT** | Access token 15 min, assinado, com `tenantId`, `sub` e versão de permissões |
| **Refresh rotativo** | Cada uso emite novo refresh e invalida o anterior; **reuso detectado revoga a família inteira** de tokens (mitiga roubo) |
| **API Keys** | Hash no banco (nunca em claro), escopos por permissão, expiração e revogação |
| **Rate limit** | Por IP + por tenant + por API key (Redis, sliding window) |
| **CSRF** | Cookies `SameSite=Strict` + double-submit token nas rotas com cookie |
| **CSP / Helmet** | Headers restritivos por padrão no gateway |
| **Criptografia** | TLS em trânsito; AES-256-GCM na aplicação para PII sensível; chaves fora do banco (env/KMS) |
| **Senhas** | Argon2id (`memoryCost=64MB, timeCost=3`) |
| **LGPD by design** | Minimização de dados; consentimento registrado; relatório de dados do titular; anonimização na exclusão; retenção configurável por tenant; DPO log |
| **Auditoria** | `AuditLog` append-only: quem, o quê, quando, de onde (IP/UA), antes/depois — gravado por interceptor global |

---

## 8. Arquitetura de eventos

Barramento interno desde a v1, com contrato estável para evoluir o transporte:

```ts
// application/ports/event-bus.ts — módulos só conhecem esta interface
interface EventBus {
  publish<T extends DomainEvent>(event: T): Promise<void>;
  subscribe<T extends DomainEvent>(type: EventType, handler: Handler<T>): void;
}
```

- **v1 (atual):** transporte in-process (`@nestjs/event-emitter`) + persistência em outbox.
- **v2 (escala):** troca do adapter para Redis Streams/BullMQ — *zero mudança nos módulos*.
- **Padrão outbox:** eventos são gravados na mesma transação do agregado e despachados
  depois — garante que nenhum evento se perde se o processo cair.

Eventos canônicos (nomes versionados, payload documentado em `docs/events/`):
`employee.created` · `employee.updated` · `vacation.requested` · `vacation.approved` ·
`time-entry.registered` · `document.signed` · `payroll.generated` · `payslip.signed` ·
`esocial.event-sent` · `candidate.applied` · `workflow.step.completed` · …

> **Justificativa:** eventos são o que desacopla os módulos (ex.: Benefits reage a
> `employee.created` sem o módulo People saber que Benefits existe) e o que alimentará
> integrações/webhooks e o eSocial. Nascer com o contrato certo evita o retrabalho de
> "espalhar chamadas diretas e depois arrancar".

---

## 9. Workflow Engine (motor visual)

Não há fluxos fixos no código. Cada empresa desenha seus fluxos:

```
WorkflowTemplate (JSONB)             WorkflowInstance
  nodes: [                             templateId, entity (ex.: VacationRequest)
    { type: "trigger",  event: "vacation.requested" }
    { type: "condition", expr: "days > 20" }          currentNodeId, status
    { type: "approval", role: "manager" }             WorkflowStepExecution[]
    { type: "document", template: "aviso-ferias" }      (histórico auditável de cada passo)
    { type: "signature", signers: [...] }
    { type: "end" }
  ]
  edges: [ ... ]
```

- Templates são grafos direcionados validados (sem ciclos não intencionais, um nó inicial).
- O runtime é um interpretador de passos orientado a eventos: cada conclusão de passo emite
  `workflow.step.completed`, que agenda o próximo passo via fila.
- Tipos de nó são **plugáveis** (registry) — novos tipos (ex.: "chamar webhook", "agente de IA")
  não alteram o motor.
- O editor visual (frontend) manipula o mesmo JSON — nada é traduzido.

> **Justificativa:** JSONB + interpretador cobre aprovações/da assinatura com auditoria e é
> simples de versionar por tenant. **Alternativas:** Temporal.io (excelente, mas infra pesada
> e curva de aprendizado para o estágio atual — o contrato do motor permite adotá-lo depois
> como backend de execução); BPMN/Camunda (complexidade de modelagem excessiva para o usuário-alvo).

---

## 10. Módulo de IA (AI Engine independente)

```
core/ai/
├── engine/            # Orquestração: seleção de modelo, streaming, fallback
├── chat/              # Conversas e mensagens (AiConversation/AiMessage)
├── prompts/           # Prompt Library versionada (AiPromptTemplate)
├── knowledge/         # Knowledge Base por tenant (documentos, políticas, CLT)
├── embeddings/        # pgvector: indexação e busca semântica
├── rag/               # Retrieval Augmented Generation sobre a knowledge base
├── agents/            # Registry de agentes (CLT, contratos, recrutamento, relatórios)
├── memory/            # Memória por conversa e por usuário
├── tools/             # Tools tipadas que agentes podem invocar (ex.: buscarColaborador)
└── logs/              # Toda chamada logada: tokens, custo, latência, tenant
```

- **Agentes são plugins:** implementam `interface Agent { name; systemPrompt; tools; }` e são
  registrados no registry — adicionar um agente novo não toca o restante da plataforma.
- Casos de uso da v1: dúvidas sobre CLT (RAG sobre base curada), geração de advertências,
  contratos, descrições de cargo, comunicados, resumo de currículos, relatórios.
- **Guard-rails:** IA nunca executa ação de escrita sem confirmação humana; tudo passa por
  RBAC do usuário que pediu; custos limitados por tenant (Billing → limites).
- Provedor por trás de um port `LlmProvider` (Anthropic como padrão) — troca de fornecedor
  não afeta agentes.

---

## 11. Feature Flags e modularização comercial

- `Module` (catálogo: `people`, `payroll`, `recruitment`, …) × `TenantModule` (habilitação
  por tenant, com origem: plano, trial, override manual).
- `FeatureFlag` para features granulares dentro de módulos (rollout %, por plano, por tenant).
- Enforcement em **três camadas**: guard no gateway (`@RequireModule('recruitment')`),
  navegação no frontend (menus somem), e verificação nos use cases (defesa em profundidade).

```
Empresa A: ✅ RH  ✅ DP  ❌ Payroll  ❌ IA
Empresa B: ✅ todos os módulos
```

## 12. Billing (estrutura desde o MVP)

`Plan` (limites: usuários, colaboradores, módulos, tokens de IA) → `Subscription`
(trial, ativa, past_due, cancelada; upgrade/downgrade com proration) → `Invoice`.
Gateway de pagamento fica atrás de um port `PaymentProvider` (Stripe como primeiro adapter,
quando a cobrança for ativada). Limites de plano são consultados pelos mesmos guards de
feature flag — um plano é, na prática, um conjunto de flags + quotas.

## 13. API

- **Versionada por URI desde o dia 1:** `/api/v1/...` (Nest `enableVersioning`). `v2` poderá
  coexistir com `v1` durante janelas de depreciação documentadas.
- **OpenAPI 3.1** gerada do código (decorators) e publicada em `/api/docs`.
- Erros no formato RFC 7807 (`application/problem+json`); paginação por cursor;
  idempotency keys em POSTs críticos (ponto, pagamento).

## 14. Observabilidade

- **OpenTelemetry** (traces + métricas) instrumentado no bootstrap — exportável para
  qualquer backend OTLP.
- **Health checks:** `/health/live` e `/health/ready` (banco, Redis, storage).
- **Logs estruturados** (pino, JSON) com `traceId`, `tenantId`, `userId` em todo log.
- **Distributed tracing** preparado para o dia em que módulos virarem serviços.
- **Error tracking:** Sentry (front e back), com scrubbing de PII antes do envio.
- **Métricas de negócio** expostas: logins, eventos publicados, jobs processados, latência p95.

## 15. Design System PeopleFlow

Pacote próprio [`packages/design-system`](../packages/design-system) — Shadcn é a base de
implementação, mas a identidade é da PeopleFlow:

- **Tokens** (fonte única em `tokens.ts` → CSS variables): cores semânticas (light/dark),
  tipografia (escala modular), espaçamento (grid de 4px), raios, sombras, z-index.
- **Grid:** 12 colunas, container máx. 1280px, breakpoints sm/md/lg/xl/2xl.
- **Componentes:** primeiro os primitivos (Button, Card, Input, Badge, Table, Dialog…),
  depois compostos de domínio (EmployeeCard, ApprovalTimeline, KanbanBoard).
- **Motion guidelines:** durações 150/250/400ms, easing `cubic-bezier(0.2, 0, 0, 1)`,
  animar apenas `transform`/`opacity`, respeitar `prefers-reduced-motion`.
- **Modo claro/escuro** via `data-theme` + `prefers-color-scheme`, tokens espelhados.

## 16. Testes e qualidade

- **Unitários** (Jest/Vitest): domínio e use cases — sem banco, rápidos, rodam no pre-commit.
- **Integração:** repositórios e RLS contra Postgres em Docker (testcontainers).
- **E2E:** fluxos críticos (login+MFA, registrar ponto, solicitar/aprovar férias) com Playwright.
- **CI (GitHub Actions):** lint → typecheck → testes → build em todo PR.
- Cobertura mínima de 80% no Core (IAM, RBAC, tenancy — onde bug é incidente de segurança).

## 17. Escalabilidade — 1 → 10.000 empresas

| Estágio | O que muda | O que NÃO muda |
|---|---|---|
| 1–100 empresas | 1 instância API + Postgres + Redis (Railway) | — |
| 100–1.000 | Réplicas horizontais da API (stateless por design), read replicas, PgBouncer, eventos migram para Redis Streams | Código dos módulos |
| 1.000–10.000 | Tenants Enterprise migram para schema/banco dedicado; workers de fila separados; módulos quentes extraídos para serviços (o event bus já é a costura) | Contratos, domínio, frontend |

Tudo que impediria essa progressão foi proibido por design: estado em memória de processo,
joins entre dados de tenants, chamadas diretas entre módulos, dependência de disco local.

---

## Apêndice A — Estrutura do repositório

```
peopleflow/
├── apps/
│   ├── api/          # NestJS — gateway + core + modules
│   └── web/          # Next.js 15 — landing + painéis + portais
├── packages/
│   ├── database/     # Prisma schema, migrações, seed
│   ├── design-system/# Tokens e componentes PeopleFlow
│   └── contracts/    # DTOs/eventos compartilhados (tipos TS)
├── docs/
│   ├── ARCHITECTURE.md   (este documento)
│   ├── ROADMAP.md
│   └── adr/              (decisões registradas)
├── docker-compose.yml
└── turbo.json
```

## Apêndice B — Índice de ADRs

| ADR | Decisão |
|---|---|
| [001](./adr/ADR-001-monolito-modular.md) | Monólito modular antes de microsserviços |
| [002](./adr/ADR-002-multi-tenancy-evolutiva.md) | Multi-tenancy evolutiva (coluna → schema → banco) |
| [003](./adr/ADR-003-postgres-prisma.md) | PostgreSQL + Prisma |
| [004](./adr/ADR-004-event-bus-outbox.md) | Event bus interno com padrão outbox |
| [005](./adr/ADR-005-auth-jwt-rotativo.md) | JWT + refresh rotativo + Argon2id |
| [006](./adr/ADR-006-workflow-jsonb.md) | Workflow engine JSONB interpretado |
| [007](./adr/ADR-007-design-system-proprio.md) | Design system próprio sobre Shadcn |
| [008](./adr/ADR-008-ai-engine-plugavel.md) | AI Engine independente com agentes plugáveis |
