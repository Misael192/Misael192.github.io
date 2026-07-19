# PeopleFlow

**Saia das planilhas.** PeopleFlow é uma plataforma HCM (Departamento Pessoal +
Recursos Humanos) **multiempresa e modular**: comece pelo módulo que resolve o
seu gargalo de hoje e expanda — de 1 a 10.000 empresas.

O repositório tem **duas frentes complementares**:

| Frente | Onde | O que é |
|---|---|---|
| **Produto funcional (MVP)** | [`mvp/`](./mvp/) | PHP 8.4 puro + PostgreSQL. **Fases 1–6 implementadas e testadas**: DP completo, motor de folha (INSS/IRRF/FGTS/férias/13º/rescisão com 37 testes), fechamento + holerite, Assistente CLT, eSocial S-2200/S-1200, Portal do Colaborador e API pública `/api/v1`. Leia o [README do MVP](./mvp/README.md). |
| **Plataforma de escala** | raiz | Laravel 12: multi-tenancy evolutiva (RLS), event bus, workflow engine, AI Engine multi-provedor, billing — alvo da migração do MVP validado. |

> 📐 Comece por aqui: [**Documento de Arquitetura**](./docs/ARCHITECTURE.md) ·
> [Roadmap com status real](./docs/ROADMAP.md) · [ADRs (decisões e justificativas)](./docs/adr/) ·
> [API OpenAPI](./docs/openapi.yaml)

## Rodando o MVP (produto completo)

```bash
cd mvp
createdb peopleflow_mvp
for f in schema seeds fase2 fase3 fase4 fase5 fase6; do psql -d peopleflow_mvp -f database/$f.sql; done
php -S localhost:8091 -t public
php tests/payroll_tests.php   # 37 testes da folha
```

Logins demo: `admin@demo.com` / `password` (admin) · `ana@demo.com` / `password` (portal do colaborador).

## Stack

| Camada | Tecnologias |
|---|---|
| Backend | PHP 8.4 · Laravel 12 · Eloquent · Sanctum · Horizon · Reverb · Octane · Scheduler |
| Frontend | HTML5 · Tailwind CSS · Alpine.js · Livewire/Volt (integração Blade em andamento) |
| Dados | PostgreSQL (RLS por tenant) · Redis (cache, filas, rate limit) |
| Infra | Docker · Nginx · GitHub Actions · Railway · S3/Supabase Storage |
| IA | AI Engine multi-provedor: OpenAI, Gemini, Claude e Ollama (`config/ai.php`) |

## Estrutura

```
app/
  Core/            Fundação da plataforma (nunca depende dos módulos)
    Tenancy/       TenantContext, BelongsToTenant, ResolveTenant (RLS)
    AI/            AiManager (drivers), AiEngine, contratos
    Workflow/      WorkflowEngine + executores de nós plugáveis
    Audit/         Trilha imutável com scrubbing de PII
    FeatureFlags/  Módulos por tenant + flags com rollout
    Identity/      TOTP (MFA) sem dependências
  Events/          Eventos de domínio (employee.created, vacation.approved…)
  Http/Controllers/Api/V1/   API REST versionada
  Models/          Eloquent (UUIDs + escopo global de tenant)
  Services/        Use cases (ex.: RefreshTokenService com rotação)
public/            Interface HTML5 + Tailwind + Alpine (13 páginas, dark/light)
database/          Migrations (todas as entidades), RLS SQL, seeder
docs/              Arquitetura, ADRs, roadmap, OpenAPI
docker/            Nginx + PHP-FPM para produção
```

## Rodando a plataforma Laravel (raiz)

Pré-requisitos: PHP 8.4+, Composer, Node 20+ (assets), Docker (opcional p/ Postgres/Redis).

```bash
composer install
cp .env.example .env && php artisan key:generate

# Banco: sqlite por padrão; para PostgreSQL: docker compose up -d e ajuste o .env
touch database/database.sqlite
php artisan migrate --seed        # módulos, permissões, planos, tenant demo

php artisan serve                 # http://localhost:8000 → landing + app
php artisan test                  # 11 testes (auth, tenancy, workflow)
```

Usuário demo: `admin@demo.com` / `password` (tenant `demo`, header `X-Tenant-Id: demo`).

## Interface (public/)

13 páginas HTML5 prontas para conversão em Blade: landing, login, portal,
dashboard, RH (Kanban de recrutamento), DP (ponto/férias/admissão/eSocial),
colaborador (ponto interativo), gestor (central de aprovações), analytics
(donut/linha/barras/heatmap), assistente IA (chat), administração,
configurações e 404 — modo claro/escuro persistido, assets 100% locais
(`public/assets/vendor`), sem dependência de CDN.

## Pilares da arquitetura

| Pilar | Como |
|---|---|
| Multi-tenancy evolutiva | `tenant_id` + PostgreSQL RLS hoje; schema/banco dedicado sem reescrita ([ADR-002](./docs/adr/ADR-002-multi-tenancy-evolutiva.md)) |
| Modularidade | Core nunca conhece módulos; comunicação via eventos ([ADR-001](./docs/adr/ADR-001-monolito-modular.md), [ADR-004](./docs/adr/ADR-004-event-bus-outbox.md)) |
| Segurança | Argon2id, Sanctum 15 min + refresh rotativo com detecção de reuso, MFA TOTP, RBAC via Gate + ABAC, auditoria append-only, LGPD by design ([ADR-005](./docs/adr/ADR-005-auth-jwt-rotativo.md)) |
| Workflows por empresa | Grafos JSON interpretados, nós plugáveis ([ADR-006](./docs/adr/ADR-006-workflow-jsonb.md)) |
| IA plugável | 4 provedores atrás de um Manager; agentes por configuração ([ADR-008](./docs/adr/ADR-008-ai-engine-plugavel.md)) |
| Stack PHP | Justificativa completa da migração ([ADR-009](./docs/adr/ADR-009-migracao-laravel.md)) |
