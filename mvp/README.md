# PeopleFlow MVP — PHP puro + PostgreSQL

Versão enxuta da PeopleFlow em **PHP 8.3+ sem framework** (MVC próprio), para
aprendizado e prototipagem rápida. Convive com a versão Laravel na raiz do
repositório — as duas compartilham o mesmo design system (PeopleFlow UI).

## Fase 1 (implementada)

- ✅ Landing page institucional
- ✅ Login com sessão segura (Argon2id, `session_regenerate_id`, CSRF, cookies HttpOnly/SameSite)
- ✅ Cadastro de empresas (validação de CNPJ e duplicidade)
- ✅ Cadastro de usuários (empresa + perfil RBAC)
- ✅ Dashboard inicial com KPIs reais do banco
- ✅ Auditoria de logins (tabela `sessions`)
- ✅ Banco: `companies`, `users`, `roles`, `permissions`, `departments`, `employees`, `sessions`

**Próximas fases:** Folha de pagamento (Fase 3), IA/eSocial (Fase 4) — ver roadmap.

## Fase 2 (implementada) — Módulo Departamento Pessoal

- ✅ **Estrutura organizacional**: filiais, departamentos, cargos (CBO/salário base),
  centros de custo e escalas de jornada (`work_shifts`)
- ✅ **Colaborador completo**: dados pessoais (CPF/RG/órgão emissor/nascimento/sexo/
  estado civil/nacionalidade/naturalidade), documentos (PIS/CTPS/título/reservista/CNH),
  contato, endereço, dados bancários + PIX, dependentes, contato de emergência, foto —
  gravado em transação com satélites normalizados
- ✅ **Ficha do colaborador** com abas (dados, admissão, dependentes, históricos)
- ✅ **Admissão digital**: checklist de 8 documentos criado automaticamente
- ✅ **Históricos imutáveis**: salarial e de situação (quem/quando/motivo)
- ✅ **Ponto**: registro manual (entrada/almoço/retorno/saída), aprovação do gestor e
  **banco de horas** calculado contra a jornada da escala
- ✅ **Férias**: período aquisitivo/concessivo por colaborador, validação de saldo
  (CLT arts. 130/134/143), aprovação com débito do período
- ✅ **GED**: categorias, upload com **versionamento automático**, hash SHA-256,
  **assinatura eletrônica** com evidências (IP/user-agent) e download autenticado
- ✅ **Auditoria**: `audit_logs` registra toda ação relevante (quem, quando, IP,
  navegador, valores) — LGPD by design
- ✅ **Permissões**: matriz por perfil (admin/RH/DP/gestor/colaborador) + overrides
  por usuário (`user_permissions`), verificadas via `Can::check()`

Aplicar a Fase 2: `psql -d peopleflow_mvp -f database/fase2.sql`

## Fases 1 e 2 — fechamento 100%

- ✅ **Checklist de admissão clicável**: marcar/desmarcar item a item; ao concluir
  os 8 itens o processo vira `completed` e o colaborador é **ativado
  automaticamente** (com histórico de situação)
- ✅ **Reajuste salarial** pela ficha (aba Históricos): atualiza salário +
  `employee_salary_history` em transação, com auditoria
- ✅ **Mudança de situação** (ativo/férias/afastado/desligado) com motivo,
  histórico e `terminated_at` quando desligado
- ✅ **Benefícios** (`beneficios.php`): atribuir VT/VA/VR/saúde/odonto/seguro/convênio
  por colaborador com desconto em % ou valor fixo; encerrar a qualquer momento —
  benefícios ativos entram automaticamente no cálculo da folha (Fase 3)
- ✅ **Gestão de usuários**: trocar perfil (RBAC) e ativar/desativar acesso direto
  na listagem — com proteção contra auto-lockout e auditoria
- ✅ **Dashboard com pendências reais**: férias aguardando aprovação, pontos a
  aprovar e admissões abertas, cada card com link direto para a ação

## Fase 3 (fundação implementada) — Motor de folha

- ✅ Engine pura em `app/services/Payroll/` (dinheiro em **centavos**, sem float):
  INSS progressivo, IRRF (legal × simplificado, aplica o menor), FGTS, férias,
  13º, rescisão, horas extras (divisor 220), VT ≤ 6%, salário-família
- ✅ Rubricas parametrizadas com incidências (`rubrics`) e **tabelas oficiais com
  vigência** (`tax_tables`) — mudou a lei, muda-se a tabela, nunca o código
- ✅ `PayrollService` importa banco de horas, faltas, eventos, benefícios e
  descontos do período
- ✅ **37 testes automatizados**: `php tests/payroll_tests.php`
- ✅ **Fechamento de competência** (`folha.php`): navegar por mês, lançar
  eventos manuais (comissão/bônus/HE/desconto), calcular a empresa inteira,
  conferir totais (bruto/descontos/líquido/FGTS) e **fechar** — competência
  fechada é imutável (com reabertura auditada)
- ✅ **Holerite** (`holerite.php`): recibo de pagamento printável (Imprimir/PDF
  do navegador) com rubricas, referências, bases INSS/IRRF/FGTS, FGTS do mês e
  líquido em destaque; marca **PRÉVIA** enquanto a folha não é fechada
- ✅ **13º salário** (`decimo.php`): elegíveis com avos projetados (≥15 dias =
  1 avo), 1ª parcela sem descontos (competência nov) e 2ª com INSS/IRRF sobre o
  integral menos o adiantamento (dez); recibo próprio por parcela
- ✅ **Recibo de férias**: botão nas férias aprovadas gera a folha kind=vacation
  (gozo + 1/3 tributados; abono indenizatório) e abre o recibo
- ✅ **Rescisão** (`rescisao.php`): simula as verbas (saldo, aviso Lei 12.506,
  férias vencidas/proporcionais + 1/3, 13º proporcional, multa FGTS 40%/20%)
  sem gravar nada; efetivar gera o termo, desliga o colaborador (histórico +
  auditoria) e abre o demonstrativo
- ✅ Folhas especiais convivem com a mensal no mesmo período (`payrolls.kind`);
  o recálculo mensal substitui apenas `kind=payslip`

Aplicar a Fase 3: `psql -d peopleflow_mvp -f database/fase3.sql`

## Fase 4 (implementada) — Assistente CLT + eSocial

- ✅ **Assistente CLT** (`assistente.php`): chat disponível a todo usuário
  (inclusive colaborador). Respostas numéricas saem das **calculadoras da
  folha com as tabelas vigentes do banco** — nunca valores fixos no texto:
  salário líquido, INSS, IRRF (legal × simplificado), FGTS, férias, 13º e
  horas extras. Base de conhecimento CLT com artigos: aviso prévio (com conta
  por anos de casa), rescisão por modalidade, banco de horas, jornada/12x36,
  adicional noturno, insalubridade/periculosidade, licenças, experiência,
  VT e salário-família. Conversas persistidas (`ai_conversations/ai_messages`);
  arquitetura pronta para plugar um provedor LLM externo na mesma interface
- ✅ **eSocial** (`esocial.php`, permissão `esocial:manage`):
  **S-2200** (admissão) gerado da ficha do colaborador — quem está sem
  CPF/salário aparece como pendência de cadastro; **S-1200** (remuneração)
  gerado das rubricas da folha **fechada** da competência. XML nos leiautes
  evtAdmissao/evtRemun (tpAmb 2), download por evento, regeneração idempotente
  e auditoria. Transmissão via webservice (certificado A1) = próxima etapa

Aplicar a Fase 4: `psql -d peopleflow_mvp -f database/fase4.sql`

## Fase 6 (implementada) — API pública /api/v1

- ✅ **Chaves de API** (`integracoes.php`, permissão `api:manage`): criação com
  escopos (`read` | `read,write`), segredo `pfk_…` exibido **uma única vez**
  (armazenamos só o SHA-256), prefixo para identificação, último uso e
  revogação imediata (→ 401)
- ✅ **Endpoints REST** em `/api/v1/index.php` (PATH_INFO — funciona em Apache
  e `php -S` sem rewrite), envelope `{data, meta} | {error}`:
  `GET /me`, `GET /employees[?status=]`, `GET /employees/{id}`,
  `GET /payrolls?competency=YYYY-MM`, `GET /vacations[?status=]`,
  `GET /openapi` (spec pública) e `POST /payroll-events` (escopo write) —
  integrações (CRM/ERP) lançam comissões/bônus direto na competência aberta;
  competência fechada responde 409
- ✅ Escopo por empresa em toda consulta; auditoria na gestão de chaves
- ✅ **Webhooks de saída**: endpoints por empresa com segredo `whsec` (exibido
  uma vez) e filtro de eventos (`employee.created/terminated`,
  `vacation.approved/rejected`, `payroll.closed`); POST JSON assinado com
  **HMAC-SHA256** no cabeçalho `X-PeopleFlow-Signature` (validado
  criptograficamente nos testes); entregas registradas com status/código/
  tentativas, pausa de endpoint e **reenvio manual** — o despacho nunca
  derruba a operação de negócio

Aplicar a Fase 6: `psql -d peopleflow_mvp -f database/fase6.sql`

## Fase 5 (implementada) — Portal do Colaborador

- ✅ **Vínculo login ↔ ficha**: `users.employee_id` liga o acesso ao colaborador;
  o portal entrega somente o que é do próprio vínculo (sem RBAC — escopo por
  `employee_id` da sessão)
- ✅ **Meu espaço** (`portal.php`): cabeçalho com banco de horas, saldo de férias
  e recibos; **bater ponto** (entrada → almoço → retorno → saída, fonte
  `portal`, cai na aprovação do gestor); últimos 7 dias de marcações
- ✅ **Meus recibos**: salário, férias, 13º e rescisão — o holerite valida que o
  recibo é do próprio colaborador (alheio → 403, DP/RH seguem vendo tudo)
- ✅ **Minhas férias**: saldo do período aquisitivo aberto, solicitação
  self-service com a mesma validação CLT do RH, acompanhamento de status
- ✅ **Meus documentos**: lista + download apenas dos próprios (`documents:read`
  foi revogado do perfil colaborador — o GED completo é do DP/RH)
- ✅ Login de colaborador cai direto no portal; sidebar esconde as telas
  administrativas
- Demo: **ana@demo.com** / **password** (perfil colaborador, vinculada à Ana)

Aplicar a Fase 5: `psql -d peopleflow_mvp -f database/fase5.sql`

## Estrutura

```
mvp/
├── public/            # Document root (Apache/XAMPP aponta aqui)
│   ├── index.php      # Landing (logado → dashboard)
│   ├── login.php · logout.php · dashboard.php · empresas.php · usuarios.php
│   └── assets/        # PeopleFlow UI (Tailwind/Alpine/FontAwesome/Inter locais)
├── app/
│   ├── bootstrap.php  # Autoload, config, sessão — incluído por toda página
│   ├── controllers/   # AuthController, DashboardController, CompanyController, UserController
│   ├── models/        # Database (PDO), Model base, User, Company, Role, Employee
│   ├── middleware/    # Auth (login obrigatório), Csrf (valida POSTs)
│   ├── services/      # AuthService (login/logout/hash)
│   ├── helpers/       # e(), view(), flash(), csrf_field()…
│   └── views/         # Templates PHP (layout + páginas)
├── config/            # app.php, database.php, auth.php
├── database/          # schema.sql e seeds.sql (PostgreSQL)
└── storage/           # logs, uploads, temp
```

## Como rodar

```bash
# 1. Banco (PostgreSQL; MySQL 8 suportado trocando DB_DRIVER/porta no config)
createdb peopleflow_mvp
psql -d peopleflow_mvp -f database/schema.sql
psql -d peopleflow_mvp -f database/seeds.sql

# 2. Servidor de desenvolvimento
php -S localhost:8091 -t public
# XAMPP/Laragon: aponte o virtual host para mvp/public
```

Login demo: **admin@demo.com** / **password**

## Segurança implementada

| Item | Como |
|---|---|
| Senhas | `password_hash` com **Argon2id** + rehash automático |
| Sessão | `session_regenerate_id` no login, cookie HttpOnly + SameSite=Lax |
| CSRF | Token por sessão validado em todo POST (`Csrf::verify`) |
| SQL Injection | 100% prepared statements (PDO, `EMULATE_PREPARES=false`) |
| XSS | Helper `e()` em toda saída das views |
| Enumeração | Mensagem de erro idêntica para e-mail ou senha errados |
