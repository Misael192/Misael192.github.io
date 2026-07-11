# ADR-001 — Monólito modular antes de microsserviços

**Status:** Aceito · **Data:** 2026-07-11

## Contexto
A PeopleFlow precisa de modularidade real (módulos vendáveis por empresa) e de um caminho
para escalar a 10.000 tenants, mas nasce com um time pequeno e sem tráfego.

## Decisão
Um único deploy NestJS organizado como **monólito modular**: cada módulo é um Nest module
com camadas hexagonais próprias, comunicação entre módulos apenas via event bus e interfaces
públicas, banco compartilhado com RLS.

## Justificativa
- Um deploy, uma transação, debugging e onboarding triviais.
- As fronteiras (eventos + ports) são exatamente as que microsserviços exigiriam — a
  extração futura é mudança de infraestrutura, não de código.
- Microsserviços prematuros multiplicam custo operacional (observabilidade distribuída,
  sagas, versionamento de contratos em rede) sem nenhum ganho no estágio atual.

## Alternativas consideradas
- **Microsserviços desde o início** — rejeitado: complexidade sem demanda.
- **Serverless** — rejeitado: cold start, conexões de banco, workers de longa duração.

## Consequências
- Import direto entre módulos é proibido (lint rule + revisão).
- Quando um módulo precisar de escala independente, extrai-se trocando o adapter do
  event bus (in-process → Redis Streams) e movendo o módulo para outro deploy.
