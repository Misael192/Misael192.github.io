# ADR-005 — JWT + refresh token rotativo + Argon2id

**Status:** Aceito · **Data:** 2026-07-11

## Decisão
- Access token JWT de vida curta (15 min) com `sub`, `tenantId` e versão de permissões.
- Refresh token opaco, armazenado com hash, **rotacionado a cada uso**; reuso de um token
  já rotacionado revoga a família inteira (detecção de roubo).
- Senhas com **Argon2id** (64 MB, timeCost 3). MFA TOTP disponível desde a v1.
- API Keys com hash + escopos para integrações máquina-a-máquina.

## Justificativa
JWT mantém a API stateless (réplicas horizontais sem session store no caminho quente);
a rotação de refresh mitiga o principal risco do modelo (exfiltração de refresh token);
Argon2id é o estado da arte contra ataques com GPU.

## Alternativas consideradas
- **Sessões server-side** — estado compartilhado em todas as réplicas a cada request.
- **Auth externo (Auth0/Clerk/Cognito)** — custo por MAU em 10.000 empresas e lock-in no
  núcleo do negócio (identidade multi-tenant com RBAC próprio).
- **bcrypt** — mais fraco contra hardware moderno que Argon2id.
