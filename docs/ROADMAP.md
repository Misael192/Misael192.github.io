# PeopleFlow — Roadmap

## Fase 1 — Fundação da Plataforma ✅ (aprovada, em execução)
- [x] Documento de arquitetura com justificativas e ADRs
- [x] Monorepo (pnpm + turbo) com apps `api`/`web` e packages `database`/`design-system`/`contracts`
- [x] Schema Prisma com todas as entidades da arquitetura inicial (tenancy, IAM, RBAC,
      billing, workflow, IA, auditoria, DP/RH)
- [x] Core API: auth (JWT + refresh rotativo + Argon2id), TenantContext, RBAC guards,
      event bus com outbox, auditoria, feature flags, health checks, OpenAPI `/api/v1`
- [x] Design system (tokens light/dark) + landing page + login + shell do dashboard
- [ ] Migrações aplicadas + seed (tenant demo, papéis padrão, catálogo de módulos)
- [ ] CI (lint, typecheck, testes) — workflow incluído, ativar branch protection

## Fase 2 — DP essencial
- Admissão digital (cadastro, documentos, checklist, assinatura)
- Controle de ponto (web/mobile, banco de horas, aprovação de ajustes, geolocalização)
- Gestão de férias (solicitação, aprovação via workflow engine, calendário, alertas)
- GED (versões, permissões, compartilhamento seguro)
- Portal do colaborador e portal do gestor (fluxos acima ponta a ponta)

## Fase 3 — RH
- Recrutamento e seleção (vagas, trabalhe conosco, pipeline Kanban, entrevistas)
- Benefícios (catálogo, VT/VA, plano de saúde, relatórios)
- Desempenho (metas, avaliações, PDI) e treinamentos (cursos, certificados)
- Engajamento (clima, reconhecimento, mural, comunicação interna)

## Fase 4 — Plataforma comercial
- Billing ativo (Stripe), planos self-service, limites e upgrade/downgrade
- Workflow engine com editor visual completo
- AI Engine: assistente CLT (RAG), geração de documentos, resumo de currículos
- Dashboards de analytics (turnover, absenteísmo, headcount)

## Fase 5 — Escala e ecossistema
- Multi-tenancy nível 2/3 (schema/banco dedicado) para Enterprise
- Eventos em Redis Streams, workers dedicados, read replicas
- Webhooks públicos, API keys self-service, Marketplace de integrações
- Preparação eSocial / folha (Payroll)
