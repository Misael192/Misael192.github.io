# Cutover — MVP (`mvp/`) → Plataforma Laravel (raiz)

> **Status:** runbook · **Última atualização:** 2026-08-02
>
> A migração fatiada (ver [ROADMAP](./ROADMAP.md) e [ADR-009](./adr/ADR-009-migracao-laravel.md))
> portou toda a superfície funcional do MVP para a plataforma Laravel. Este documento é o
> **procedimento de virada**: colocar o MVP em somente-leitura e a plataforma como fonte
> única. É uma operação de janela controlada — código + dados + roteamento — com critérios
> de Go/No-Go e rollback.

## 1. Paridade funcional (feito na migração)

Cada fatia foi portada e conferida contra os valores já validados no MVP (dinheiro em
centavos, "nenhuma tela faz conta", multi-tenant com RLS). Suíte da plataforma: **110/110**;
MVP intacto: **37/37**.

| Domínio | MVP | Plataforma Laravel | Conferência |
|---|---|---|---|
| Motor de folha (INSS/IRRF/FGTS/férias/13º/rescisão/HE) | ✅ | ✅ `App\Services\Payroll\*` (verbatim) | 25 unit espelham as 37 asserções |
| Fechamento mensal (open→calculated→closed) | ✅ | ✅ `PayrollService` | líquido(5200)=430451 |
| Folhas especiais (13º/férias/rescisão) | ✅ | ✅ `SpecialPayrollService` | 13º 2ª=100677, férias=351183 |
| API v1 de folha | ✅ | ✅ `Api/V1/*Controller` | 7 HTTP tests |
| Colaboradores / admissão | ✅ | ✅ `/colaboradores` (`EmployeeService`) | contrato 520000 |
| Ponto / banco de horas | ✅ | ✅ `/ponto` (`TimeBankService`) | crédito → HE 1001 |
| Férias | ✅ | ✅ `/ferias` (`VacationService`) | aprovado → recibo |
| Assistente CLT | ✅ | ✅ `/assistente` (verbatim) | 5200→4.304,51 |
| eSocial S-2200/S-1200 | ✅ | ✅ `/esocial` (`EsocialService`) | folha fechada → evtRemun |
| Portal do colaborador | ✅ | ✅ `/portal` (acesso alheio 403) | holerite próprio |
| Webhooks de saída (HMAC) | ✅ | ✅ `/webhooks` (`WebhookDispatcher`) | assinatura conferida |

Itens **fora** do escopo do cutover (roadmap de produto, não bloqueiam a virada):
transmissão eSocial ao webservice (certificado A1), provedor LLM real no Assistente,
conectores prontos (SAP/TOTVS/…), PWA do portal.

## 2. Estratégia de dados

O MVP e a plataforma têm **modelos de dados diferentes** — a virada exige uma migração de
dados (ETL), não um `pg_dump`/`restore` direto:

| Aspecto | MVP | Plataforma |
|---|---|---|
| IDs | inteiros sequenciais | UUID |
| Tenant | implícito (uma base por operação) | explícito `tenant_id` + **RLS** |
| Rubricas / tabelas oficiais | por base | **catálogo global** (sem `tenant_id`), já semeado (`PayrollEngineSeeder`) |
| Dinheiro | centavos (int) | centavos (int) — **sem conversão** |
| PII (cpf/rg/bancários) | texto | **cifrado em repouso** (casts `encrypted`) na escrita |

**Plano ETL (por empresa → um tenant):**

1. Criar o `tenant` + `organization` + `company` de destino na plataforma.
2. Para cada tabela do MVP, inserir na tabela equivalente **gerando UUIDs** e preenchendo
   `tenant_id`; manter um mapa `id_int → uuid` por entidade para reconstruir as FKs.
3. **Não** migrar `rubrics`/`tax_tables` — são catálogo global e já estão semeadas; apenas
   validar que as vigências cobrem as competências históricas a importar.
4. PII entra pelos **models Eloquent** (não `INSERT` cru) para que os casts `encrypted`
   cifrem no destino.
5. Folhas fechadas: importar `payroll_periods`/`payrolls`/`payroll_items`/`social_charges`
   preservando `status='closed'` e os totais — **reconferir** um lote contra o MVP
   (líquido/bruto por holerite) antes do Go.
6. Rodar a suíte e um `migrate:fresh --seed` de referência em staging com uma cópia dos
   dados para validar o ETL ponta a ponta.

> O ETL já é um comando Artisan idempotente: **`php artisan cutover:import {export.json}`**
> (`App\Services\Cutover\CutoverImporter`). Ele consome um **export portátil JSON por empresa**
> (tenant/organization/company + employees[contracts,dependents] + periods[payrolls[items,charges]]),
> gera UUIDs, fixa o `TenantContext`, cifra PII via Eloquent, preserva centavos/fechamentos e
> **não** toca o catálogo global. Falta ligar a **origem** (gerar o JSON a partir da base do MVP)
> e **ensaiar em staging** com dados mascarados. O ensaio usa
> **`cutover:import {export.json} --dry-run`**, que conta o que seria importado e aponta
> inconsistências (ex.: folha referenciando matrícula ausente) **sem gravar nada** — falha
> com código de erro se houver inconsistência, para travar um Go sobre export quebrado.
> Exemplo de payload nos testes `tests/Feature/Cutover/CutoverImportTest.php`.

## 3. Colocar o MVP em somente-leitura

Ponto de inserção único e não-invasivo: **`mvp/app/bootstrap.php`** (incluído por todas as
páginas de `mvp/public/*.php`). Guarda dirigida por ambiente, para não alterar a lógica:

```php
// Bloqueia escritas durante o cutover sem tocar nos controllers.
if (($_ENV['PEOPLEFLOW_READONLY'] ?? '') === '1'
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(423); // Locked
    exit('Sistema em migração — somente leitura. Acesse a nova plataforma.');
}
```

- Ativa com `PEOPLEFLOW_READONLY=1` **só na janela de cutover**.
- GET continua servindo (consulta ao histórico); qualquer POST/PUT/DELETE responde 423.
- A API do MVP (`/api/v1`) deve seguir a mesma guarda (o mesmo bootstrap cobre).

> Esta edição pertence à **janela de cutover** — é o único momento em que o MVP deixa de ser
> mantido byte-a-byte. Aplicar via PR próprio, revisável, e reverter no rollback.

## 4. Sequência da virada (janela controlada)

1. **Anúncio** (D-2): comunicar janela e congelamento aos usuários.
2. **Freeze de escrita** no MVP: `PEOPLEFLOW_READONLY=1` (§3).
3. **Sync final**: rodar o ETL (`cutover:import`) para capturar o delta desde o último ensaio.
4. **Smoke test** na plataforma (checklist §5) apontando para os dados migrados.
5. **Flip de roteamento**: DNS/reverse-proxy passa o tráfego para a plataforma Laravel;
   manter o MVP acessível em um host interno (`legado.`) só-leitura para conferência.
6. **Habilitar escrita** na plataforma (já é o padrão; garantir filas/Horizon e webhooks ativos).
7. **Monitorar** (§6) pelas primeiras horas.

## 5. Go / No-Go (checklist)

- [ ] Suíte da plataforma **verde** no commit a promover (`pint --test` + `php artisan test`).
- [ ] ETL ensaiado em staging **sem divergência** num lote de holerites (bruto/líquido).
- [ ] `migrate:fresh --seed` limpo; tabelas globais sem `tenant_id`, tenant-tables com `tenant_id`.
- [ ] Login por tenant, folha (calcular/fechar), holerite, portal (403 alheio) validados no destino.
- [ ] eSocial S-1200 de uma competência fechada gera XML válido.
- [ ] Webhook de `payroll.closed` entregue e assinatura conferida (endpoint de teste).
- [ ] Backup **imediatamente antes** do flip (base da plataforma + base do MVP).
- [ ] Plano de rollback (§7) testado.

**No-Go** se qualquer divergência de valor de folha, falha de RLS (tenant vê dado de outro),
ou ETL incompleto.

## 6. Monitoramento pós-virada

- Erros 5xx e latência (primeiras 24–48h).
- Fila/Horizon: jobs falhos, retries de webhook (`webhook_logs.attempts`).
- Auditoria: mutações registradas por tenant.
- Conferência de negócio: um fechamento real por um cliente-piloto antes de generalizar.

## 7. Rollback

Enquanto o MVP estiver íntegro e só-leitura, o rollback é barato:

1. Reverter o flip de roteamento para o MVP.
2. `PEOPLEFLOW_READONLY=0` (reabre escrita no MVP).
3. Comunicar; investigar a causa na plataforma sem pressão de janela.

O rollback é seguro **enquanto não houver escrita nova relevante na plataforma**. Definir um
**ponto de não-retorno** explícito (ex.: primeiro fechamento de folha real na plataforma);
depois dele, o caminho é corrigir-para-frente, não voltar.

## 8. Descomissionamento

Após **N dias** (sugerido: um ciclo de folha completo + fechamento) estável:

1. Exportar/arquivar a base do MVP (retenção legal — eSocial/holerites).
2. Congelar o host legado (só-leitura interno) por mais um período.
3. Remover `mvp/` do deploy ativo (o histórico fica no git).
4. Atualizar ROADMAP/ADR marcando a migração **concluída**.
