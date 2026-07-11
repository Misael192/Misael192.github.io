# ADR-002 — Multi-tenancy evolutiva (coluna → schema → banco)

**Status:** Aceito · **Data:** 2026-07-11

## Contexto
Requisito aprovado: suportar três estratégias de isolamento para que clientes Enterprise
migrem sem reescrita — coluna, schema e banco dedicado.

## Decisão
- Padrão: **tenant por coluna** (`tenant_id` em toda tabela) + **Postgres RLS** como defesa
  em profundidade.
- `Tenant.isolationLevel ∈ {COLUMN, SCHEMA, DATABASE}` e `Tenant.databaseUrl` existem desde
  a primeira migração.
- Todo acesso a dados passa por `TenantContext` (AsyncLocalStorage) + `TenantConnectionResolver`,
  a única peça que sabe onde cada tenant mora.
- `tenant_id` permanece nas tabelas mesmo em schema/banco dedicado, para permitir mover
  tenants entre níveis com export/import filtrado.

## Justificativa
Coluna+RLS maximiza densidade e mantém uma migração única de schema para todos os tenants;
RLS garante que nem um bug de query vaza dados. A abstração de conexão custa dias agora e
evita meses de retrabalho quando o primeiro Enterprise exigir isolamento físico.

## Alternativas consideradas
- **Schema-per-tenant desde o início** — rejeitado: milhares de schemas quebram pooling e
  tornam cada migração uma operação O(n tenants).
- **Banco por tenant sempre** — rejeitado: custo fixo por tenant inviabiliza planos de entrada.
