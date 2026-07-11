# PeopleFlow

Plataforma HCM (Departamento Pessoal + Recursos Humanos) **multi-tenant, modular e
orientada a eventos** — projetada para operar de 1 a 10.000 empresas sem refatoração
arquitetural.

> 📐 Comece por aqui: [**Documento de Arquitetura**](./docs/ARCHITECTURE.md) ·
> [Roadmap](./docs/ROADMAP.md) · [ADRs (decisões e justificativas)](./docs/adr/)

## Estrutura do monorepo

```
apps/
  api/            NestJS — API Gateway + Core (IAM, RBAC, tenants, eventos,
                  auditoria, feature flags, workflow engine, AI engine) + Modules
  web/            Next.js 15 — landing page, login e painéis (admin/RH/DP/gestor)
packages/
  database/       Prisma — schema (todas as entidades da plataforma), RLS, seed
  design-system/  Tokens do PeopleFlow Design System (cores, tipografia, motion)
docs/             Arquitetura, roadmap e ADRs
```

## Rodando localmente

Pré-requisitos: Node 20+, pnpm 9+, Docker.

```bash
# 1. Infraestrutura (PostgreSQL + Redis)
docker compose up -d

# 2. Dependências
pnpm install

# 3. Variáveis de ambiente
cp .env.example .env   # ajuste os segredos (openssl rand -base64 48)

# 4. Banco: migrações + RLS + seed (módulos, permissões, planos, tenant demo)
pnpm db:generate && pnpm db:migrate && pnpm db:seed

# 5. Desenvolvimento (API em :3001, web em :3000)
pnpm dev
```

- Documentação da API (OpenAPI): http://localhost:3001/api/docs
- Health checks: `/api/v1/health/live` e `/api/v1/health/ready`

## Pilares da arquitetura

| Pilar | Como |
|---|---|
| Multi-tenancy evolutiva | `tenant_id` + Postgres RLS hoje; schema/banco dedicado por tenant sem reescrita ([ADR-002](./docs/adr/ADR-002-multi-tenancy-evolutiva.md)) |
| Modularidade | Core nunca conhece os Modules; comunicação só por event bus ([ADR-001](./docs/adr/ADR-001-monolito-modular.md), [ADR-004](./docs/adr/ADR-004-event-bus-outbox.md)) |
| Segurança | Argon2id, JWT + refresh rotativo com detecção de reuso, MFA, RBAC/ABAC, auditoria append-only, LGPD by design ([ADR-005](./docs/adr/ADR-005-auth-jwt-rotativo.md)) |
| Workflows por empresa | Motor visual: grafos JSONB interpretados, nós plugáveis ([ADR-006](./docs/adr/ADR-006-workflow-jsonb.md)) |
| IA plugável | AI Engine com agentes registráveis, RAG (pgvector), quotas e logs ([ADR-008](./docs/adr/ADR-008-ai-engine-plugavel.md)) |
| Design próprio | Tokens do PeopleFlow Design System, claro/escuro, motion guidelines ([ADR-007](./docs/adr/ADR-007-design-system-proprio.md)) |

## Testes

```bash
pnpm test   # unitários (Jest) — ex.: rotação/reuso de refresh token
```
