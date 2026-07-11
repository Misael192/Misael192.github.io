# ADR-003 — PostgreSQL + Prisma

**Status:** Aceito · **Data:** 2026-07-11

## Decisão
PostgreSQL 16 como único banco transacional; Prisma como ORM e fonte de verdade do schema.

## Justificativa
- **Postgres:** RLS nativa (pilar do multi-tenancy), JSONB (workflows, settings, payloads de
  eventos), `pgvector` (embeddings de IA sem mais um banco), maturidade e disponibilidade
  gerenciada em qualquer nuvem (Railway hoje, RDS amanhã).
- **Prisma:** schema declarativo versionado, migrações reproduzíveis, tipos TS gerados que
  alinham banco ⇄ domínio, DX que acelera um time pequeno.

## Alternativas consideradas
- **MySQL** — sem RLS equivalente; JSONB inferior.
- **MongoDB** — dados de RH/DP são fortemente relacionais e transacionais (folha, ponto).
- **TypeORM** — histórico de migrações frágeis e decorators acoplados às entidades de domínio.
- **Drizzle** — promissor, mas ecossistema/migrações menos maduros na data da decisão.

## Consequências
- Consultas de altíssima performance podem usar `$queryRaw` tipado quando o Prisma limitar.
- RLS exige `SET app.tenant_id` por transação — encapsulado no `PrismaService`, nunca manual.
