# PeopleFlow — Roadmap

Duas frentes convivem no repositório:

- **`mvp/` — produto funcional** em PHP puro + PostgreSQL (MVC próprio), onde as
  fases abaixo foram implementadas e testadas ponta a ponta (Playwright + testes
  de unidade da folha);
- **raiz — plataforma Laravel 12** (multi-tenancy com RLS, event bus, workflow,
  AI Engine multi-provedor, billing), alvo da migração quando o produto validar.

## Fase 1 — Fundação ✅ concluída (mvp/)
- [x] Login seguro (Argon2id, CSRF, sessão auditada em `user_sessions`)
- [x] Empresas (CNPJ validado) e usuários com perfis RBAC + overrides por usuário
- [x] Gestão de perfis: trocar papel e ativar/desativar acesso (anti auto-lockout)
- [x] Dashboard com KPIs reais e pendências acionáveis (férias/ponto/admissões)

## Fase 2 — Departamento Pessoal ✅ concluída (mvp/)
- [x] Estrutura organizacional: filiais, departamentos, cargos (CBO), centros de custo, escalas
- [x] Colaborador completo (documentos, endereço, bancários/PIX, dependentes, emergência, foto)
      gravado em transação com satélites normalizados
- [x] Admissão digital com checklist clicável → ativação automática do colaborador
- [x] Ponto com aprovação e banco de horas contra a jornada da escala
- [x] Férias: período aquisitivo/concessivo, saldo CLT, aprovação com débito
- [x] GED: versionamento automático, SHA-256, assinatura eletrônica, download gated
- [x] Históricos imutáveis (salário/situação) e auditoria completa (quem/quando/IP/valores)

## Fase 3 — Folha de pagamento ✅ concluída (mvp/)
- [x] Engine pura em centavos: INSS progressivo, IRRF legal × simplificado (menor),
      FGTS, férias, 13º, rescisão, HE (divisor 220), VT ≤ 6%, salário-família — 37 testes
- [x] Rubricas parametrizadas (incidências/fórmula) e tabelas oficiais com vigência
- [x] Fechamento de competência: eventos → calcular → conferir → fechar (imutável) → reabrir
- [x] Holerite printável por tipo; folhas especiais: 13º (1ª/2ª), recibo de férias,
      rescisão com simulação → termo

## Fase 4 — IA e eSocial ✅ concluída (mvp/)
- [x] Assistente CLT: chat que calcula com a engine + tabelas vigentes e cita base
      legal; conversas persistidas; interface pronta para provedor LLM externo
- [x] eSocial: S-2200 (admissão) e S-1200 (remuneração de folha fechada), XML nos
      leiautes, download e pendências de cadastro apontadas
- [ ] Transmissão ao webservice eSocial (certificado A1)
- [ ] Workflow visual de aprovações · OCR de documentos

## Fase 5 — Portal do Colaborador ✅ concluída (mvp/)
- [x] Vínculo `users.employee_id`; login de colaborador cai direto no portal
- [x] Bater ponto, recibos próprios, férias self-service com validação CLT,
      documentos próprios — acesso alheio bloqueado (403) e testado
- [ ] PWA/app móvel

## Fase 6 — API pública e integrações ✅ concluída (mvp/)
- [x] `/api/v1` com Bearer `pfk_…` (SHA-256), escopos read/write, envelope JSON,
      OpenAPI pública; POST /payroll-events para integrações lançarem comissões
- [x] Tela de chaves: criar/copiar uma única vez/revogar, auditado
- [x] Webhooks de saída assinados (HMAC-SHA256) com entregas rastreadas e reenvio
- [ ] Conectores prontos (SAP, TOTVS, Conta Azul, Omie, Nibo, Domínio)

## Próximos passos
1. Transmissão eSocial (certificado A1) e S-1210/S-2299 (pagamentos/desligamento)
2. Provedor LLM real no Assistente (Claude API) mantendo o fallback calculado
3. Migração progressiva do MVP validado para a plataforma Laravel da raiz
   (multi-tenancy RLS, filas, billing) — ver [ARCHITECTURE.md](./ARCHITECTURE.md) e ADRs
