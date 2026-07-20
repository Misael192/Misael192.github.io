-- ═══════════════════════════════════════════════════════════════════════════
-- PeopleFlow MVP — FASE 3: Folha de Pagamento (motor de cálculo)
-- Aplicar após fase2.sql:
--   psql -U peopleflow -d peopleflow_mvp -f database/fase3.sql
--
-- Princípio: NENHUMA tela faz conta. Rubricas parametrizadas + tabelas
-- oficiais com vigência + engine em app/services. Mudou a lei → atualiza
-- tax_tables; mudou a verba → atualiza a rubrica. eSocial-ready.
-- ═══════════════════════════════════════════════════════════════════════════

BEGIN;

-- ╔══ RUBRICAS ════════════════════════════════════════════════════════════╗

CREATE TABLE IF NOT EXISTS rubric_groups (
    id    SERIAL PRIMARY KEY,
    code  VARCHAR(20) NOT NULL UNIQUE,   -- proventos | descontos | encargos | informativas
    name  VARCHAR(80) NOT NULL
);

CREATE TABLE IF NOT EXISTS rubrics (
    id            SERIAL PRIMARY KEY,
    group_id      INT NOT NULL REFERENCES rubric_groups(id),
    code          VARCHAR(10) NOT NULL UNIQUE,   -- 1000, 1001, 2000…
    name          VARCHAR(120) NOT NULL,
    type          VARCHAR(12) NOT NULL,           -- earning | deduction | info
    nature        VARCHAR(40),                    -- eSocial: natureza da rubrica (ex.: 1000 salário)
    incides_inss  BOOLEAN NOT NULL DEFAULT FALSE,
    incides_irrf  BOOLEAN NOT NULL DEFAULT FALSE,
    incides_fgts  BOOLEAN NOT NULL DEFAULT FALSE,
    esocial_code  VARCHAR(10),                    -- código p/ evento S-1010
    formula       VARCHAR(40),                    -- chave interpretada pela engine
    is_active     BOOLEAN NOT NULL DEFAULT TRUE
);

-- ╔══ TABELAS OFICIAIS (com vigência) ═════════════════════════════════════╗
-- Uma linha por tipo+vigência; faixas em JSONB. Mudou o governo → INSERT novo.
CREATE TABLE IF NOT EXISTS tax_tables (
    id          SERIAL PRIMARY KEY,
    type        VARCHAR(20) NOT NULL,   -- inss | irrf | salario_familia | fgts | teto_inss
    valid_from  DATE NOT NULL,
    valid_to    DATE,                   -- null = vigente
    brackets    JSONB NOT NULL,         -- [{up_to, rate, deduction}] ou {value}
    UNIQUE (type, valid_from)
);

-- ╔══ BENEFÍCIOS E DESCONTOS RECORRENTES ══════════════════════════════════╗

CREATE TABLE IF NOT EXISTS employee_benefits (
    id           SERIAL PRIMARY KEY,
    employee_id  INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    -- vt | va | vr | saude | odonto | seguro_vida | convenio
    type         VARCHAR(20) NOT NULL,
    description  VARCHAR(120),
    amount_cents BIGINT NOT NULL,               -- valor mensal do benefício
    -- desconto do colaborador: percent do salário (VT ≤ 6%) ou fixo
    employee_share_percent NUMERIC(5,2),
    employee_share_cents   BIGINT,
    starts_on    DATE NOT NULL DEFAULT CURRENT_DATE,
    ends_on      DATE,
    is_active    BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS employee_discounts (
    id           SERIAL PRIMARY KEY,
    employee_id  INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    description  VARCHAR(120) NOT NULL,          -- ex.: empréstimo consignado
    amount_cents BIGINT NOT NULL,
    installments SMALLINT,                       -- null = recorrente
    remaining    SMALLINT,
    is_active    BOOLEAN NOT NULL DEFAULT TRUE
);

-- ╔══ EVENTOS VARIÁVEIS DA COMPETÊNCIA ════════════════════════════════════╗
-- HE, faltas, atestados, comissões, bônus… lançados por competência.
CREATE TABLE IF NOT EXISTS payroll_events (
    id           SERIAL PRIMARY KEY,
    company_id   INT NOT NULL REFERENCES companies(id),
    employee_id  INT NOT NULL REFERENCES employees(id),
    competency   CHAR(7) NOT NULL,               -- 'YYYY-MM'
    rubric_code  VARCHAR(10) NOT NULL REFERENCES rubrics(code),
    reference    NUMERIC(10,2),                  -- horas, dias, % …
    amount_cents BIGINT,                         -- null = engine calcula pela fórmula
    notes        VARCHAR(160),
    created_by   INT REFERENCES users(id),
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_events_comp ON payroll_events(company_id, competency, employee_id);

-- ╔══ FOLHA ═══════════════════════════════════════════════════════════════╗

CREATE TABLE IF NOT EXISTS payroll_periods (
    id          SERIAL PRIMARY KEY,
    company_id  INT NOT NULL REFERENCES companies(id),
    competency  CHAR(7) NOT NULL,                -- 'YYYY-MM'
    status      VARCHAR(12) NOT NULL DEFAULT 'open',  -- open | calculated | closed
    calculated_at TIMESTAMPTZ,
    closed_at   TIMESTAMPTZ,
    closed_by   INT REFERENCES users(id),
    UNIQUE (company_id, competency)
);

CREATE TABLE IF NOT EXISTS payrolls (
    id              SERIAL PRIMARY KEY,
    period_id       INT NOT NULL REFERENCES payroll_periods(id) ON DELETE CASCADE,
    employee_id     INT NOT NULL REFERENCES employees(id),
    -- payslip | vacation | thirteenth_1 | thirteenth_2 | termination
    kind            VARCHAR(15) NOT NULL DEFAULT 'payslip',
    gross_cents     BIGINT NOT NULL DEFAULT 0,   -- total de proventos
    deductions_cents BIGINT NOT NULL DEFAULT 0,
    net_cents       BIGINT NOT NULL DEFAULT 0,
    inss_base_cents BIGINT NOT NULL DEFAULT 0,
    irrf_base_cents BIGINT NOT NULL DEFAULT 0,
    fgts_base_cents BIGINT NOT NULL DEFAULT 0,
    calculated_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (period_id, employee_id, kind)
);

CREATE TABLE IF NOT EXISTS payroll_items (
    id           SERIAL PRIMARY KEY,
    payroll_id   INT NOT NULL REFERENCES payrolls(id) ON DELETE CASCADE,
    rubric_code  VARCHAR(10) NOT NULL REFERENCES rubrics(code),
    description  VARCHAR(120) NOT NULL,
    reference    NUMERIC(10,2),                  -- horas/dias/% mostrados no holerite
    amount_cents BIGINT NOT NULL,                -- positivo; type da rubrica dá o sinal
    type         VARCHAR(12) NOT NULL            -- earning | deduction | info
);
CREATE INDEX IF NOT EXISTS idx_items_payroll ON payroll_items(payroll_id);

-- Encargos patronais (não saem do líquido): FGTS, INSS patronal, RAT…
CREATE TABLE IF NOT EXISTS social_charges (
    id           SERIAL PRIMARY KEY,
    payroll_id   INT NOT NULL REFERENCES payrolls(id) ON DELETE CASCADE,
    type         VARCHAR(20) NOT NULL,           -- fgts | inss_patronal | rat | terceiros
    base_cents   BIGINT NOT NULL,
    rate         NUMERIC(5,2) NOT NULL,
    amount_cents BIGINT NOT NULL
);

-- ╔══ FOLHAS ESPECIAIS (13º, férias, rescisão) ════════════════════════════╗

CREATE TABLE IF NOT EXISTS thirteenth_salary (
    id           SERIAL PRIMARY KEY,
    employee_id  INT NOT NULL REFERENCES employees(id),
    year         SMALLINT NOT NULL,
    installment  SMALLINT NOT NULL,              -- 1 (adiantamento) | 2 (final)
    months       SMALLINT NOT NULL,              -- avos (≥15 dias = 1 avo)
    payroll_id   INT REFERENCES payrolls(id),
    UNIQUE (employee_id, year, installment)
);

CREATE TABLE IF NOT EXISTS vacation_payroll (
    id           SERIAL PRIMARY KEY,
    vacation_id  INT NOT NULL UNIQUE REFERENCES vacations(id),
    payroll_id   INT REFERENCES payrolls(id)
);

CREATE TABLE IF NOT EXISTS termination_payroll (
    id             SERIAL PRIMARY KEY,
    termination_id INT NOT NULL UNIQUE REFERENCES terminations(id),
    payroll_id     INT REFERENCES payrolls(id)
);

-- ╔══ SEEDS: GRUPOS E RUBRICAS ════════════════════════════════════════════╗

INSERT INTO rubric_groups (code, name) VALUES
  ('proventos', 'Proventos'), ('descontos', 'Descontos'),
  ('encargos', 'Encargos patronais'), ('informativas', 'Informativas')
ON CONFLICT (code) DO NOTHING;

INSERT INTO rubrics (group_id, code, name, type, nature, incides_inss, incides_irrf, incides_fgts, formula) VALUES
  -- Proventos
  ((SELECT id FROM rubric_groups WHERE code='proventos'), '1000', 'Salário Base',            'earning', '1000', TRUE,  TRUE,  TRUE,  'base_salary'),
  ((SELECT id FROM rubric_groups WHERE code='proventos'), '1001', 'Hora Extra 50%',          'earning', '1003', TRUE,  TRUE,  TRUE,  'overtime_50'),
  ((SELECT id FROM rubric_groups WHERE code='proventos'), '1002', 'Hora Extra 100%',         'earning', '1004', TRUE,  TRUE,  TRUE,  'overtime_100'),
  ((SELECT id FROM rubric_groups WHERE code='proventos'), '1003', 'Adicional Noturno',       'earning', '1005', TRUE,  TRUE,  TRUE,  'night_shift'),
  ((SELECT id FROM rubric_groups WHERE code='proventos'), '1004', 'Periculosidade',          'earning', '1202', TRUE,  TRUE,  TRUE,  'hazard_30'),
  ((SELECT id FROM rubric_groups WHERE code='proventos'), '1005', 'Insalubridade',           'earning', '1201', TRUE,  TRUE,  TRUE,  'unhealthy'),
  ((SELECT id FROM rubric_groups WHERE code='proventos'), '1006', 'Comissões',               'earning', '1009', TRUE,  TRUE,  TRUE,  'manual'),
  ((SELECT id FROM rubric_groups WHERE code='proventos'), '1007', 'Bônus/Premiação',         'earning', '1010', TRUE,  TRUE,  TRUE,  'manual'),
  ((SELECT id FROM rubric_groups WHERE code='proventos'), '1100', 'Férias + 1/3',            'earning', '1300', TRUE,  TRUE,  TRUE,  'vacation'),
  ((SELECT id FROM rubric_groups WHERE code='proventos'), '1200', '13º Salário',             'earning', '1350', TRUE,  TRUE,  TRUE,  'thirteenth'),
  ((SELECT id FROM rubric_groups WHERE code='proventos'), '1300', 'Salário Família',         'earning', '1409', FALSE, FALSE, FALSE, 'family_allowance'),
  -- Descontos
  ((SELECT id FROM rubric_groups WHERE code='descontos'), '2000', 'INSS',                    'deduction', '9201', FALSE, FALSE, FALSE, 'inss'),
  ((SELECT id FROM rubric_groups WHERE code='descontos'), '2001', 'IRRF',                    'deduction', '9203', FALSE, FALSE, FALSE, 'irrf'),
  ((SELECT id FROM rubric_groups WHERE code='descontos'), '2002', 'Vale Transporte (6%)',    'deduction', '9216', FALSE, FALSE, FALSE, 'vt_discount'),
  ((SELECT id FROM rubric_groups WHERE code='descontos'), '2003', 'Vale Refeição/Alimentação','deduction', '9217', FALSE, FALSE, FALSE, 'benefit_share'),
  ((SELECT id FROM rubric_groups WHERE code='descontos'), '2004', 'Plano de Saúde',          'deduction', '9219', FALSE, FALSE, FALSE, 'benefit_share'),
  ((SELECT id FROM rubric_groups WHERE code='descontos'), '2005', 'Faltas',                  'deduction', '9200', FALSE, FALSE, FALSE, 'absence'),
  ((SELECT id FROM rubric_groups WHERE code='descontos'), '2006', 'Desconto diverso',        'deduction', '9299', FALSE, FALSE, FALSE, 'manual'),
  -- Encargos (informativos no holerite; não saem do líquido)
  ((SELECT id FROM rubric_groups WHERE code='encargos'),  '3000', 'FGTS (8%)',               'info', 'FGTS', FALSE, FALSE, FALSE, 'fgts')
ON CONFLICT (code) DO NOTHING;

-- ╔══ SEEDS: TABELAS OFICIAIS (vigência 2025-05 →, usadas em 2026) ════════╗
-- Fonte: tabelas progressivas INSS/IRRF vigentes (Lei 14.848/2024, IN RFB).
-- Para reajustes futuros: INSERT nova linha com valid_from novo.

INSERT INTO tax_tables (type, valid_from, brackets) VALUES
  ('inss', '2025-01-01', '[
     {"up_to": 151800, "rate": 7.5},
     {"up_to": 279388, "rate": 9.0},
     {"up_to": 419083, "rate": 12.0},
     {"up_to": 815741, "rate": 14.0}
   ]'),
  ('teto_inss', '2025-01-01', '{"value": 815741}'),
  ('irrf', '2025-05-01', '{
     "brackets": [
       {"up_to": 242880, "rate": 0,    "deduction": 0},
       {"up_to": 282665, "rate": 7.5,  "deduction": 18216},
       {"up_to": 375105, "rate": 15.0, "deduction": 39416},
       {"up_to": 466468, "rate": 22.5, "deduction": 67549},
       {"up_to": null,   "rate": 27.5, "deduction": 90873}
     ],
     "dependent_deduction": 18959,
     "simplified_deduction": 60720
   }'),
  ('salario_familia', '2025-01-01', '{"limit": 190604, "per_child": 6504}'),
  ('fgts', '2020-01-01', '{"rate": 8.0, "apprentice_rate": 2.0}')
ON CONFLICT (type, valid_from) DO NOTHING;

COMMIT;

-- Benefícios (Fase 2 — conclusão): permissão de gestão
INSERT INTO permissions (code, description) VALUES ('benefits:manage', 'Gerenciar benefícios dos colaboradores')
ON CONFLICT (code) DO NOTHING;
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'benefits:manage'
WHERE r.code IN ('admin', 'rh', 'dp') ON CONFLICT DO NOTHING;

-- Permissão do módulo de folha (fechamento/holerite) — idempotente
INSERT INTO permissions (code, description) VALUES ('payroll:manage', 'Calcular e fechar a folha de pagamento')
ON CONFLICT (code) DO NOTHING;
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.code IN ('admin', 'rh', 'dp') AND p.code = 'payroll:manage'
ON CONFLICT DO NOTHING;

-- Rubrica para verbas rescisórias (indenizatórias — sem incidências) — idempotente
INSERT INTO rubrics (group_id, code, name, type, nature, incides_inss, incides_irrf, incides_fgts, formula)
SELECT (SELECT id FROM rubric_groups WHERE code='proventos'), '1400', 'Verbas Rescisórias', 'earning', '1400', FALSE, FALSE, FALSE, 'termination_item'
WHERE NOT EXISTS (SELECT 1 FROM rubrics WHERE code = '1400');

-- Recalcular uma folha especial substitui a payroll: os vínculos acompanham — idempotente
ALTER TABLE thirteenth_salary DROP CONSTRAINT IF EXISTS thirteenth_salary_payroll_id_fkey;
ALTER TABLE thirteenth_salary ADD CONSTRAINT thirteenth_salary_payroll_id_fkey
    FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE CASCADE;
ALTER TABLE vacation_payroll DROP CONSTRAINT IF EXISTS vacation_payroll_payroll_id_fkey;
ALTER TABLE vacation_payroll ADD CONSTRAINT vacation_payroll_payroll_id_fkey
    FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE CASCADE;
ALTER TABLE termination_payroll DROP CONSTRAINT IF EXISTS termination_payroll_payroll_id_fkey;
ALTER TABLE termination_payroll ADD CONSTRAINT termination_payroll_payroll_id_fkey
    FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE CASCADE;

-- Seeds demo: salários dos colaboradores de exemplo (só se ainda não têm) —
-- sem isso uma instalação limpa calcularia a folha para 0 colaboradores.
UPDATE employees e SET salary_cents = v.cents
FROM (VALUES ('00001', 420000), ('00002', 780000), ('00004', 550000), ('00005', 510000))
     AS v(registration, cents), companies c
WHERE c.cnpj = '00.000.000/0001-00' AND e.company_id = c.id
  AND e.registration = v.registration AND e.salary_cents IS NULL;
