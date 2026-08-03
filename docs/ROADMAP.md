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

## Migração MVP → Laravel 🚧 em andamento (raiz)

Progressiva e fatiada: cada slice porta uma parte já validada do MVP para a
plataforma Laravel (multi-tenancy RLS, testes PHPUnit), sem parar o MVP. Toda a
superfície funcional do MVP já roda na plataforma (suíte 110/110); resta só o
**cutover** — runbook em [CUTOVER.md](./CUTOVER.md).

- [x] **Motor de folha** (`app/Services/Payroll/`): os 7 calculadores puros
      (Inss/Irrf/Fgts/Vacation/Thirteenth/Termination/PayrollEngine) portados
      **verbatim** do MVP (já usavam o namespace `App\Services\Payroll`);
      `rubrics`/`tax_tables` viraram tabelas globais (sem tenant — são leis
      federais, iguais para todo tenant) com `Rubric`/`TaxTable` (GlobalModel)
      e `PayrollEngineSeeder`; `TaxTableRepository` reescrito de PDO cru para
      Eloquent, mesma interface pública. 29 testes novos (25 unit puros
      espelhando as 37 asserções do MVP + 4 feature via banco/seeder) — todos
      os valores batem com os já validados manualmente; suíte completa 40/40
- [x] **Fechamento da folha mensal** (`App\Services\Payroll\PayrollService`):
      tabelas tenant-scoped `payroll_periods`/`payrolls`/`payroll_items`/
      `social_charges` (UUID, RLS automática) com models Eloquent; máquina de
      estados `open → calculated → closed` (fechado é imutável; `reopenPeriod`
      volta para calculada). Salário vem do `employment_contracts` vigente,
      hora-extra do `time_bank_entries` (crédito → HE 50%); permissão
      `payroll:manage`/`payroll:read` para RH/DP. 6 feature tests (totais,
      import de banco de horas, recálculo idempotente, guarda de competência
      fechada, reabertura). Suíte 46/46
- [x] **Folhas especiais** (`App\Services\Payroll\SpecialPayrollService`): 13º
      (1ª/2ª parcela, com desconto do adiantamento e FGTS por diferença),
      recibo de férias (ponte com `vacation_requests`, idempotente) e rescisão
      (termo + desligamento). Tabelas de apoio `employee_dependents` e
      `terminations` (tenant-scoped); folha especial referencia sua origem via
      `payrolls.source_type/source_id` (morphTo). Persistem na mesma estrutura
      com `kind` (thirteenth_1/2, vacation, termination); a mensal nunca as
      toca. 7 feature tests batendo com os calculadores puros. Suíte 53/53
- [x] **API v1 de folha** (`/api/v1/payroll/*`): `PayrollController`
      (calcular/fechar/reabrir competência, holerite) e
      `SpecialPayrollController` (13º, recibo de férias, simular/efetivar
      rescisão) sobre os serviços portados, no mesmo pipeline das demais
      rotas (tenant → auth Sanctum → `module:payroll` → RBAC `payroll:read`/
      `payroll:manage` → auditoria). Módulo payroll habilitado no tenant
      demo. 7 feature tests HTTP ponta a ponta. Suíte 60/60
- [x] **CI da plataforma Laravel**: job `laravel` roda `pint --test` +
      `php artisan test` (SQLite em memória) ao lado do job do MVP; base do
      root normalizada com Pint (`pint.json` exclui `mvp/`, que segue intacto)
- [x] **Fundação da UI (Livewire)**: login web por sessão (tenant no
      formulário → `tenant_slug` na sessão; `SetTenantFromSession` fixa o
      escopo antes do `auth`), shell autenticado e painel com KPIs reais do
      tenant. Layout Blade reaproveita o design system estático
      (`public/assets/peopleflow.css`), sem depender do build Vite. Rotas
      `/entrar` e `/painel`. 7 feature tests (Livewire) + login real validado
      no browser (Playwright, zero erro de JS). Suíte 67/67
- [x] **Tela de folha + holerite (Livewire)**: `/folha` calcula/fecha/reabre
      a competência pelo `PayrollService` (RBAC `payroll:manage` autorizado na
      ação) e lista as folhas; `/folha/holerite/{payroll}` mostra itens e
      encargos. `SetTenantFromSession` promovido ao grupo `web` global para o
      tenant ser resolvido também no endpoint do Livewire (`/livewire/update`),
      não só nas rotas nomeadas. 5 feature tests + fluxo real validado no
      browser (login → calcular → holerite). Suíte 72/72
- [x] **Telas Livewire das folhas especiais** (`/folha/especiais`):
      `FolhasEspeciais` sobre o `SpecialPayrollService` — 13º (1ª/2ª parcela),
      recibo de férias dos pedidos aprovados (idempotente, aponta o holerite já
      gerado) e rescisão em dois passos (simular verbas → efetivar, com o termo
      e o desligamento). RBAC `payroll:manage` nas ações; saldo do FGTS entra em
      reais e vira centavos só na fronteira (nenhuma tela faz conta). 5 feature
      tests (13º, recibo, simular→efetivar, conversão de centavos, guarda de
      login). Suíte 77/77
- [x] **Tela de colaboradores** (`/colaboradores`): `Colaboradores` sobre o
      `EmployeeService` (novo, em `App\Services\People`) — cadastra colaborador
      + contrato vigente numa transação, lista com salário/situação e ativa quem
      está em admissão. Salário entra em reais e vira centavos na fronteira;
      matrícula única por empresa. RBAC `employees:create`/`employees:update`,
      módulo `people`. Fecha o ciclo ponta a ponta: cadastrar pela tela → rodar
      a folha. 5 feature tests (cadastro+contrato, matrícula duplicada, entra na
      folha com líquido 4.304,51, ativação, guarda de login). Suíte 82/82
- [x] **Tela de férias** (`/ferias`): `Ferias` sobre o `VacationService` (novo)
      — solicita (dias + abono, guardas CLT: gozo ≤ 30, abono ≤ 10 e ≤ dias) e
      decide (aprovar/recusar), disparando os mesmos eventos da API
      (`VacationRequested`/`VacationApproved`) para o Workflow Engine. RBAC
      `vacations:request`/`vacations:approve`. Fecha o ciclo com as folhas
      especiais: pedido aprovado aqui vira recibo lá (líquido 351183, batendo com
      o motor). 5 feature tests. Suíte 87/87
- [x] **Tela de ponto / banco de horas** (`/ponto`): `Ponto` sobre o
      `TimeBankService` (novo) — lança crédito (hora-extra) ou débito
      (compensação) por colaborador (horas → minutos com sinal na fronteira),
      mostra saldo por pessoa e os lançamentos recentes. RBAC
      `time-entries:register`. Fecha o outro insumo da folha mensal: o crédito
      do mês vira HE 50% (rubrica 1001) no cálculo. 4 feature tests (crédito
      600min, débito −120min, HE ponta a ponta com bruto > salário, guarda de
      login). Suíte 91/91
- [x] **Assistente CLT** (`/assistente`): `CltAssistantService` portado
      **verbatim** do MVP para `App\Services\Ai` (já usava `App\Services\Payroll\*`
      — só a normalização Pint de aspas mudou; o MVP segue intacto). Chat
      Livewire que NUNCA responde valor "de cabeça": todo número sai das
      calculadoras com as tabelas vigentes (líquido 5200 = 4.304,51, INSS 3000 =
      253,41) e o texto cita a base legal. Conversa persistida em
      `ai_conversations`/`ai_messages` (provider `calculated`, pronto para plugar
      um LLM). Módulo `ai`. 5 feature tests. Suíte 96/96
- [x] **eSocial** (`/esocial`): `EsocialService` (reescrito de PDO cru para
      Eloquent) gera **S-2200** (admissão) e **S-1200** (remuneração da folha
      **fechada**) nos leiautes evtAdmissao/evtRemun; tabela tenant-scoped
      `esocial_events` (UUID, XML versionado por `company/tipo/referência`,
      auditável). Aponta pendências de cadastro (sem CPF/salário) e mostra o XML
      na tela. RBAC `payroll:manage`. 5 feature tests (S-2200 com vrSalFx
      5200.00; pendência sem CPF; S-1200 barra folha não-fechada; S-1200 da
      folha fechada com rubricas; guarda de login). Suíte 101/101
- [ ] Admissão digital (checklist) — UI
- [x] **Portal do colaborador** (`/portal`): o usuário com vínculo
      (`users.employee_id`) cai direto no portal (o login redireciona
      colaborador → `/portal`, gestão → `/painel`). Vê os PRÓPRIOS holerites,
      bate ponto (`TimeClockService`, alterna entrada/saída) e pede férias
      self-service (`VacationService`, vira solicitação para aprovação).
      **Acesso alheio bloqueado (403)**: `/portal/holerite/{payroll}` só abre
      para o dono da folha; usuário sem vínculo não entra. Holerite reaproveita
      um parcial compartilhado com a folha de DP. RBAC `time-entries:register`/
      `vacations:request`. 6 feature tests. Suíte 106/106
- [x] **Webhooks de folha** (`/webhooks`): `WebhookDispatcher` (novo, em
      `App\Services\Integrations`) entrega webhooks de saída **assinados
      (HMAC-SHA256, header `X-PeopleFlow-Signature`)** para as integrações
      `webhook` ativas do tenant, rastreando cada tentativa em `webhook_logs`
      (código, tentativas, entregue-em) com **reenvio**. O `PayrollService`
      dispara `payroll.closed` (com totais da competência) ao fechar a folha —
      no-op sem integração, então a folha segue funcionando sem webhook. Tela
      cadastra endpoints (url+segredo), ativa/desativa e mostra as entregas.
      RBAC `integrations:manage`. 4 feature tests (cadastro; fechar entrega com
      assinatura conferida contra o corpo; reenvio de entrega que falhou 500→200
      incrementando tentativas; guarda de login). Suíte 110/110
- [~] **Cutover**: runbook pronto ([CUTOVER.md](./CUTOVER.md)) — paridade
      funcional, estratégia de ETL, guarda de somente-leitura no MVP
      (`PEOPLEFLOW_READONLY` no `mvp/app/bootstrap.php`), sequência da virada,
      Go/No-Go, rollback e descomissionamento. O **ETL já tem código**:
      `cutover:import {export.json}` (`CutoverImporter`) importa o export
      portátil de uma empresa para o schema multi-tenant — gera UUIDs, fixa o
      `TenantContext` (tenant_id + RLS), cifra PII via Eloquent, preserva os
      centavos e os fechamentos, e **não duplica** o catálogo global; idempotente
      (reexecutável para deltas). 5 feature tests (valores/fechamento, PII
      cifrada em repouso, catálogo intacto, idempotência, comando Artisan).
      Suíte 115/115. A execução da virada segue operacional (ensaiar em staging
      com dados mascarados + janela controlada), a cargo da operação.

## Próximos passos
1. **Executar o cutover** (janela controlada): escrever o comando ETL
   `cutover:import`, ensaiar em staging com dados mascarados e seguir o
   [runbook](./CUTOVER.md) — Go/No-Go, freeze só-leitura, flip, monitoramento.
2. Transmissão eSocial (certificado A1) e S-1210/S-2299 (pagamentos/desligamento)
3. Provedor LLM real no Assistente (Claude API) mantendo o fallback calculado
4. Admissão digital (checklist clicável) na plataforma; conectores prontos
   (SAP/TOTVS/Conta Azul/…) sobre os webhooks já assinados
