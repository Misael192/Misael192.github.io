# ADR-009 — Migração da stack para PHP 8.4 / Laravel 12

**Status:** Aceito · **Data:** 2026-07-13 · **Substitui parcialmente:** ADR-003 (Prisma→Eloquent)

## Contexto
A fundação inicial foi construída em TypeScript (NestJS + Next.js + Prisma, monorepo pnpm).
Decisão do produto: concentrar a plataforma em um único ecossistema para reduzir a
superfície operacional e de manutenção.

## Decisão
Migrar 100% do projeto para **PHP 8.4 + Laravel 12**:

| Antes (TS) | Depois (PHP) |
|---|---|
| NestJS (API) | Laravel 12 (API + web) |
| Next.js/React | HTML5 + Tailwind + Alpine.js → Blade/Livewire 3 + Volt |
| Prisma | Eloquent + migrations |
| pnpm + turbo (monorepo) | Composer + npm (aplicação única) |
| JWT manual + Argon2 lib | Sanctum (access 15 min) + refresh rotativo próprio + Argon2id nativo |
| EventEmitter2 + outbox | Laravel Events + Queue/Horizon (durabilidade via filas Redis) |
| BullMQ | Laravel Queue + Horizon |
| WebSockets (futuro) | Laravel Reverb |
| Node runtime | PHP-FPM hoje, Octane (RoadRunner/Swoole) para alto tráfego |

## Justificativa
- **Um ecossistema só:** elimina Node.js, NestJS, Next.js, pnpm e Prisma do dia a dia —
  um único deploy, um único gerenciador de dependências, um time com uma linguagem.
- **Laravel cobre o Core inteiro com peças de primeira classe:** auth (Sanctum/Policies/Gates),
  filas (Horizon), WebSockets (Reverb), scheduler, cache, storage S3, criptografia
  (casts `encrypted`), hashing Argon2id — tudo que antes exigia bibliotecas avulsas.
- **Multi-tenancy preservada:** o desenho evolutivo (ADR-002) não dependia de linguagem —
  `TenantContext` (container scoped), trait `BelongsToTenant` (escopo global) e
  `set_config('app.tenant_id')` para RLS reproduzem exatamente o modelo anterior.
- **Livewire/Alpine entrega UX de SPA sem SPA:** menos JavaScript para manter, SSR nativo.

## Alternativas consideradas
- **Manter NestJS/Next** — rejeitado pelo custo de manter dois ecossistemas.
- **Symfony** — excelente, porém mais verboso para o mesmo resultado; ecossistema de
  pacotes SaaS (Horizon, Sanctum, Octane) menos integrado.
- **Laravel + Inertia/React** — reintroduziria o ecossistema Node no front; Livewire
  mantém tudo em PHP/Blade.

## Consequências
- Os ADRs 001, 002, 004, 005, 006, 007 e 008 permanecem válidos — as decisões eram de
  arquitetura, não de linguagem; apenas as implementações citadas mudam (ver tabela).
- O outbox explícito do ADR-004 é substituído por listeners enfileirados (Horizon):
  a durabilidade vem da fila Redis; handlers continuam obrigatoriamente idempotentes.
- A interface HTML5 em `public/` é o contrato visual; a conversão para Blade é mecânica
  (shell compartilhado → `layouts/app.blade.php`).
