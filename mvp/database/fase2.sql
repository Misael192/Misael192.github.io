-- ═══════════════════════════════════════════════════════════════════════════
-- PeopleFlow MVP — FASE 2: Módulo Departamento Pessoal (modelagem completa)
-- Aplicar após schema.sql + seeds.sql:
--   psql -U peopleflow -d peopleflow_mvp -f database/fase2.sql
--
-- Relacionamentos: Company → Branch → Department → Employee, com satélites
-- normalizados (endereço, contato, banco, dependentes, contratos, históricos).
-- Esta base sustenta Folha, eSocial, Benefícios e IA sem mudanças estruturais.
-- ═══════════════════════════════════════════════════════════════════════════

BEGIN;

-- ╔══ 1. ESTRUTURA ORGANIZACIONAL ═════════════════════════════════════════╗

CREATE TABLE IF NOT EXISTS branches (
    id          SERIAL PRIMARY KEY,
    company_id  INT NOT NULL REFERENCES companies(id),
    name        VARCHAR(120) NOT NULL,
    cnpj        VARCHAR(18),
    city        VARCHAR(120),
    state       VARCHAR(2),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE departments ADD COLUMN IF NOT EXISTS branch_id INT REFERENCES branches(id);

CREATE TABLE IF NOT EXISTS positions (
    id          SERIAL PRIMARY KEY,
    company_id  INT NOT NULL REFERENCES companies(id),
    title       VARCHAR(120) NOT NULL,
    cbo_code    VARCHAR(10),                        -- Classificação Brasileira de Ocupações
    base_salary_cents BIGINT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (company_id, title)
);

CREATE TABLE IF NOT EXISTS cost_centers (
    id          SERIAL PRIMARY KEY,
    company_id  INT NOT NULL REFERENCES companies(id),
    code        VARCHAR(20) NOT NULL,
    name        VARCHAR(120) NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (company_id, code)
);

-- Jornadas/escalas (5x2, 6x1, 12x36…)
CREATE TABLE IF NOT EXISTS work_shifts (
    id           SERIAL PRIMARY KEY,
    company_id   INT NOT NULL REFERENCES companies(id),
    name         VARCHAR(80) NOT NULL,
    weekly_hours SMALLINT NOT NULL DEFAULT 44,
    daily_hours  NUMERIC(4,2) NOT NULL DEFAULT 8,   -- base p/ banco de horas
    description  VARCHAR(160),
    UNIQUE (company_id, name)
);

-- ╔══ 2. COLABORADORES: núcleo + satélites ════════════════════════════════╗

ALTER TABLE employees
    -- Dados pessoais
    ADD COLUMN IF NOT EXISTS cpf            VARCHAR(14),
    ADD COLUMN IF NOT EXISTS rg             VARCHAR(20),
    ADD COLUMN IF NOT EXISTS rg_issuer      VARCHAR(20),          -- órgão emissor
    ADD COLUMN IF NOT EXISTS birth_date     DATE,
    ADD COLUMN IF NOT EXISTS gender         VARCHAR(20),
    ADD COLUMN IF NOT EXISTS marital_status VARCHAR(20),
    ADD COLUMN IF NOT EXISTS nationality    VARCHAR(60) DEFAULT 'Brasileira',
    ADD COLUMN IF NOT EXISTS birthplace     VARCHAR(120),         -- naturalidade
    -- Documentos trabalhistas
    ADD COLUMN IF NOT EXISTS pis            VARCHAR(14),
    ADD COLUMN IF NOT EXISTS ctps           VARCHAR(20),
    ADD COLUMN IF NOT EXISTS voter_title    VARCHAR(15),          -- título de eleitor
    ADD COLUMN IF NOT EXISTS reservist      VARCHAR(15),
    ADD COLUMN IF NOT EXISTS cnh            VARCHAR(15),
    -- Vínculo profissional
    ADD COLUMN IF NOT EXISTS branch_id      INT REFERENCES branches(id),
    ADD COLUMN IF NOT EXISTS position_id    INT REFERENCES positions(id),
    ADD COLUMN IF NOT EXISTS cost_center_id INT REFERENCES cost_centers(id),
    ADD COLUMN IF NOT EXISTS work_shift_id  INT REFERENCES work_shifts(id),
    ADD COLUMN IF NOT EXISTS manager_id     INT REFERENCES employees(id),
    ADD COLUMN IF NOT EXISTS contract_type  VARCHAR(20) NOT NULL DEFAULT 'clt',
    ADD COLUMN IF NOT EXISTS salary_cents   BIGINT,
    ADD COLUMN IF NOT EXISTS photo_path     VARCHAR(255),
    ADD COLUMN IF NOT EXISTS terminated_at  DATE;

CREATE UNIQUE INDEX IF NOT EXISTS idx_employees_company_cpf
    ON employees(company_id, cpf) WHERE cpf IS NOT NULL;

CREATE TABLE IF NOT EXISTS employee_addresses (
    id          SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    type        VARCHAR(20) NOT NULL DEFAULT 'residencial',
    zip_code    VARCHAR(9),
    street      VARCHAR(160),
    number      VARCHAR(10),
    district    VARCHAR(120),
    city        VARCHAR(120),
    state       VARCHAR(2),
    complement  VARCHAR(120)
);
CREATE INDEX IF NOT EXISTS idx_addresses_employee ON employee_addresses(employee_id);

CREATE TABLE IF NOT EXISTS employee_contacts (
    id          SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    phone       VARCHAR(20),
    mobile      VARCHAR(20),
    email       VARCHAR(160)
);
CREATE INDEX IF NOT EXISTS idx_contacts_employee ON employee_contacts(employee_id);

CREATE TABLE IF NOT EXISTS employee_bank_accounts (
    id           SERIAL PRIMARY KEY,
    employee_id  INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    bank         VARCHAR(80),
    agency       VARCHAR(10),
    account      VARCHAR(20),
    account_type VARCHAR(15) DEFAULT 'corrente',
    pix_key      VARCHAR(120)
);

CREATE TABLE IF NOT EXISTS employee_dependents (
    id           SERIAL PRIMARY KEY,
    employee_id  INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    name         VARCHAR(160) NOT NULL,
    cpf          VARCHAR(14),
    relationship VARCHAR(30) NOT NULL,             -- filho(a), cônjuge…
    birth_date   DATE
);

CREATE TABLE IF NOT EXISTS employee_emergency_contacts (
    id           SERIAL PRIMARY KEY,
    employee_id  INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    name         VARCHAR(160) NOT NULL,
    relationship VARCHAR(30),
    phone        VARCHAR(20) NOT NULL
);

CREATE TABLE IF NOT EXISTS employee_contracts (
    id            SERIAL PRIMARY KEY,
    employee_id   INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    contract_type VARCHAR(20) NOT NULL,
    start_date    DATE NOT NULL,
    end_date      DATE,
    salary_cents  BIGINT,
    weekly_hours  SMALLINT,
    document_id   INT                              -- contrato assinado no GED
);

-- Históricos: toda mudança salarial/status vira linha imutável
CREATE TABLE IF NOT EXISTS employee_salary_history (
    id                SERIAL PRIMARY KEY,
    employee_id       INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    old_salary_cents  BIGINT,
    new_salary_cents  BIGINT NOT NULL,
    reason            VARCHAR(160),
    changed_by        INT REFERENCES users(id),
    changed_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS employee_status_history (
    id          SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    old_status  VARCHAR(20),
    new_status  VARCHAR(20) NOT NULL,
    reason      VARCHAR(160),
    changed_by  INT REFERENCES users(id),
    changed_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ╔══ 3. GESTÃO DE DOCUMENTOS (GED) ═══════════════════════════════════════╗

CREATE TABLE IF NOT EXISTS document_categories (
    id    SERIAL PRIMARY KEY,
    code  VARCHAR(30) NOT NULL UNIQUE,
    name  VARCHAR(80) NOT NULL
);

CREATE TABLE IF NOT EXISTS documents (
    id          SERIAL PRIMARY KEY,
    company_id  INT NOT NULL REFERENCES companies(id),
    employee_id INT REFERENCES employees(id),
    category_id INT NOT NULL REFERENCES document_categories(id),
    name        VARCHAR(160) NOT NULL,
    status      VARCHAR(15) NOT NULL DEFAULT 'active',   -- active | archived
    created_by  INT REFERENCES users(id),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_documents_employee ON documents(employee_id, category_id);

-- Cada upload é uma versão imutável com hash de integridade
CREATE TABLE IF NOT EXISTS document_versions (
    id          SERIAL PRIMARY KEY,
    document_id INT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    version     SMALLINT NOT NULL,
    file_path   VARCHAR(255) NOT NULL,             -- relativo a storage/uploads
    mime_type   VARCHAR(100) NOT NULL,
    size_bytes  BIGINT NOT NULL,
    sha256      VARCHAR(64) NOT NULL,
    uploaded_by INT REFERENCES users(id),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (document_id, version)
);

-- Assinatura eletrônica: aceite com hash do arquivo + evidências (IP/UA)
CREATE TABLE IF NOT EXISTS document_signatures (
    id          SERIAL PRIMARY KEY,
    version_id  INT NOT NULL REFERENCES document_versions(id) ON DELETE CASCADE,
    user_id     INT NOT NULL REFERENCES users(id),
    status      VARCHAR(10) NOT NULL DEFAULT 'pending',  -- pending | signed
    signed_at   TIMESTAMPTZ,
    file_hash   VARCHAR(64),
    ip_address  VARCHAR(45),
    user_agent  TEXT,
    UNIQUE (version_id, user_id)
);

-- ╔══ 4. DEPARTAMENTO PESSOAL ═════════════════════════════════════════════╗

-- Admissão digital: processo + checklist de documentos
CREATE TABLE IF NOT EXISTS admissions (
    id           SERIAL PRIMARY KEY,
    company_id   INT NOT NULL REFERENCES companies(id),
    employee_id  INT NOT NULL UNIQUE REFERENCES employees(id) ON DELETE CASCADE,
    status       VARCHAR(15) NOT NULL DEFAULT 'in_progress', -- in_progress | completed
    started_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    completed_at TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS admission_items (
    id           SERIAL PRIMARY KEY,
    admission_id INT NOT NULL REFERENCES admissions(id) ON DELETE CASCADE,
    item         VARCHAR(120) NOT NULL,
    is_done      BOOLEAN NOT NULL DEFAULT FALSE,
    document_id  INT REFERENCES documents(id),
    done_at      TIMESTAMPTZ,
    UNIQUE (admission_id, item)
);

-- Férias: período aquisitivo/concessivo + solicitações
CREATE TABLE IF NOT EXISTS vacation_periods (
    id             SERIAL PRIMARY KEY,
    employee_id    INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    acq_start      DATE NOT NULL,                  -- início do período aquisitivo
    acq_end        DATE NOT NULL,                  -- fim (12 meses)
    concessive_end DATE NOT NULL,                  -- limite p/ gozo (CLT art. 134)
    days_entitled  SMALLINT NOT NULL DEFAULT 30,
    days_taken     SMALLINT NOT NULL DEFAULT 0,
    days_sold      SMALLINT NOT NULL DEFAULT 0,
    status         VARCHAR(10) NOT NULL DEFAULT 'open',    -- open | closed
    UNIQUE (employee_id, acq_start)
);

CREATE TABLE IF NOT EXISTS vacations (
    id           SERIAL PRIMARY KEY,
    company_id   INT NOT NULL REFERENCES companies(id),
    employee_id  INT NOT NULL REFERENCES employees(id),
    period_id    INT NOT NULL REFERENCES vacation_periods(id),
    start_date   DATE NOT NULL,
    end_date     DATE NOT NULL,
    days         SMALLINT NOT NULL,
    sell_days    SMALLINT NOT NULL DEFAULT 0,      -- abono (CLT art. 143: máx. 10)
    status       VARCHAR(10) NOT NULL DEFAULT 'requested', -- requested|approved|rejected
    approved_by  INT REFERENCES users(id),
    decided_at   TIMESTAMPTZ,
    notes        VARCHAR(255),
    receipt_document_id INT REFERENCES documents(id),      -- recibo/aviso de férias
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (end_date >= start_date),
    CHECK (sell_days BETWEEN 0 AND 10)
);
CREATE INDEX IF NOT EXISTS idx_vacations_employee ON vacations(employee_id, status);

-- Ponto: registro diário (entrada, almoço, retorno, saída)
CREATE TABLE IF NOT EXISTS time_clock_records (
    id          SERIAL PRIMARY KEY,
    company_id  INT NOT NULL REFERENCES companies(id),
    employee_id INT NOT NULL REFERENCES employees(id),
    work_date   DATE NOT NULL,
    clock_in    TIME,
    lunch_out   TIME,
    lunch_in    TIME,
    clock_out   TIME,
    source      VARCHAR(10) NOT NULL DEFAULT 'manual',
    status      VARCHAR(20) NOT NULL DEFAULT 'recorded',  -- recorded|approved|rejected
    approved_by INT REFERENCES users(id),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (employee_id, work_date)
);

-- Ajustes de ponto: pedido justificado sobre um registro
CREATE TABLE IF NOT EXISTS time_adjustments (
    id            SERIAL PRIMARY KEY,
    record_id     INT NOT NULL REFERENCES time_clock_records(id) ON DELETE CASCADE,
    requested_by  INT NOT NULL REFERENCES users(id),
    justification VARCHAR(255) NOT NULL,
    new_times     JSONB NOT NULL,                  -- {clock_in, lunch_out, lunch_in, clock_out}
    status        VARCHAR(10) NOT NULL DEFAULT 'pending',  -- pending|approved|rejected
    approved_by   INT REFERENCES users(id),
    decided_at    TIMESTAMPTZ
);

-- Banco de horas: lançamentos em minutos (positivo = crédito)
CREATE TABLE IF NOT EXISTS overtime_bank (
    id          SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    work_date   DATE NOT NULL,
    minutes     INT NOT NULL,
    reason      VARCHAR(20) NOT NULL DEFAULT 'overtime',  -- overtime|compensation|adjustment
    created_by  INT REFERENCES users(id),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_overtime_employee ON overtime_bank(employee_id, work_date);

-- Faltas e afastamentos médicos
CREATE TABLE IF NOT EXISTS absences (
    id          SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    date_from   DATE NOT NULL,
    date_to     DATE NOT NULL,
    type        VARCHAR(15) NOT NULL DEFAULT 'falta',     -- falta|abono|folga
    justified   BOOLEAN NOT NULL DEFAULT FALSE,
    reason      VARCHAR(160),
    document_id INT REFERENCES documents(id)
);

CREATE TABLE IF NOT EXISTS medical_leaves (
    id          SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    date_from   DATE NOT NULL,
    date_to     DATE NOT NULL,
    cid         VARCHAR(10),                       -- opcional (dado sensível)
    doctor      VARCHAR(160),
    document_id INT REFERENCES documents(id),      -- atestado no GED
    notes       VARCHAR(255)
);

-- Desligamentos
CREATE TABLE IF NOT EXISTS terminations (
    id               SERIAL PRIMARY KEY,
    employee_id      INT NOT NULL UNIQUE REFERENCES employees(id),
    termination_date DATE NOT NULL,
    type             VARCHAR(30) NOT NULL,  -- sem_justa_causa|justa_causa|pedido|acordo|fim_contrato
    notice           VARCHAR(15),           -- trabalhado|indenizado|dispensado
    reason           VARCHAR(255),
    document_id      INT REFERENCES documents(id),
    created_by       INT REFERENCES users(id),
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ╔══ 5. CONTROLE DE ACESSO E AUDITORIA ═══════════════════════════════════╗

-- Permissões diretas por usuário (sobrepõem o perfil: grant/revoke fino)
CREATE TABLE IF NOT EXISTS user_permissions (
    user_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    permission_id INT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    granted       BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (user_id, permission_id)
);

-- sessions (Fase 1) → user_sessions (nomenclatura definitiva).
-- A sequence do SERIAL não é renomeada junto com a tabela — renomeia à parte.
ALTER TABLE IF EXISTS sessions RENAME TO user_sessions;
ALTER SEQUENCE IF EXISTS sessions_id_seq RENAME TO user_sessions_id_seq;

-- Auditoria: toda alteração relevante gera registro imutável
CREATE TABLE IF NOT EXISTS audit_logs (
    id         SERIAL PRIMARY KEY,
    company_id INT REFERENCES companies(id),
    user_id    INT REFERENCES users(id),
    action     VARCHAR(60) NOT NULL,               -- employee.create, vacation.approve…
    entity     VARCHAR(40) NOT NULL,
    entity_id  VARCHAR(40),
    old_value  JSONB,
    new_value  JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_logs(entity, entity_id);

CREATE TABLE IF NOT EXISTS notifications (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title      VARCHAR(160) NOT NULL,
    body       VARCHAR(255),
    link       VARCHAR(160),
    read_at    TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ╔══ 6. PERMISSÕES DA FASE 2 + MATRIZ POR PERFIL ═════════════════════════╗

INSERT INTO permissions (code, description) VALUES
  ('structure:manage',  'Gerenciar filiais, cargos, centros de custo e escalas'),
  ('documents:read',    'Ver documentos'),
  ('documents:manage',  'Enviar e gerenciar documentos'),
  ('documents:sign',    'Assinar documentos'),
  ('vacations:request', 'Solicitar férias'),
  ('vacations:approve', 'Aprovar férias'),
  ('time:register',     'Registrar ponto'),
  ('time:approve',      'Aprovar ponto e ajustes'),
  ('audit:read',        'Consultar auditoria')
ON CONFLICT (code) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'employees:read','employees:manage','departments:manage','structure:manage',
  'documents:read','documents:manage','documents:sign',
  'vacations:request','vacations:approve','time:register','time:approve')
WHERE r.code IN ('rh','dp')
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'employees:read','documents:read','vacations:approve','time:approve')
WHERE r.code = 'gestor'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'documents:read','documents:sign','vacations:request','time:register')
WHERE r.code = 'colaborador'
ON CONFLICT DO NOTHING;

-- ╔══ 7. SEEDS ════════════════════════════════════════════════════════════╗

INSERT INTO document_categories (code, name) VALUES
  ('holerite', 'Holerite'), ('contrato', 'Contrato'), ('advertencia', 'Advertência'),
  ('atestado', 'Atestado'), ('certificado', 'Certificado'), ('admissao', 'Admissão'),
  ('pessoal', 'Documento pessoal'), ('outro', 'Outro')
ON CONFLICT (code) DO NOTHING;

INSERT INTO branches (company_id, name, city, state)
SELECT c.id, b.name, b.city, b.uf FROM companies c,
  (VALUES ('Matriz — São Paulo', 'São Paulo', 'SP'), ('Filial Nordeste', 'Recife', 'PE')) AS b(name, city, uf)
WHERE c.cnpj = '00.000.000/0001-00'
ON CONFLICT DO NOTHING;

INSERT INTO work_shifts (company_id, name, weekly_hours, daily_hours, description)
SELECT c.id, s.name, s.wh, s.dh, s.descr FROM companies c,
  (VALUES ('5x2 — Comercial', 44::smallint, 8.8, 'Seg a sex, 08h–18h'),
          ('6x1 — Operações', 44::smallint, 7.33, 'Seg a sáb'),
          ('12x36 — Plantão', 36::smallint, 12.0, 'Escala de plantão')) AS s(name, wh, dh, descr)
WHERE c.cnpj = '00.000.000/0001-00'
ON CONFLICT (company_id, name) DO NOTHING;

INSERT INTO cost_centers (company_id, code, name)
SELECT c.id, cc.code, cc.name FROM companies c,
  (VALUES ('ADM-001', 'Administrativo'), ('COM-001', 'Comercial'), ('TEC-001', 'Tecnologia')) AS cc(code, name)
WHERE c.cnpj = '00.000.000/0001-00'
ON CONFLICT (company_id, code) DO NOTHING;

INSERT INTO positions (company_id, title, base_salary_cents)
SELECT c.id, p.title, p.salary FROM companies c,
  (VALUES ('Analista de DP', 480000::bigint), ('Desenvolvedor Sênior', 1450000::bigint),
          ('Assistente Comercial', 280000::bigint), ('Coordenadora de RH', 950000::bigint),
          ('Designer de Produto', 820000::bigint)) AS p(title, salary)
WHERE c.cnpj = '00.000.000/0001-00'
ON CONFLICT (company_id, title) DO NOTHING;

-- Período aquisitivo aberto para cada colaborador existente (a partir da admissão)
INSERT INTO vacation_periods (employee_id, acq_start, acq_end, concessive_end)
SELECT e.id,
       e.hired_at + ((extract(year from age(current_date, e.hired_at)))::int * interval '1 year'),
       e.hired_at + (((extract(year from age(current_date, e.hired_at)))::int + 1) * interval '1 year') - interval '1 day',
       e.hired_at + (((extract(year from age(current_date, e.hired_at)))::int + 2) * interval '1 year') - interval '1 day'
FROM employees e
WHERE e.hired_at IS NOT NULL AND e.status <> 'terminated'
ON CONFLICT (employee_id, acq_start) DO NOTHING;

COMMIT;
