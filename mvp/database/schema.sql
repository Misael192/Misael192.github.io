-- ═══════════════════════════════════════════════════════════════════════════
-- PeopleFlow MVP — Schema PostgreSQL (Fase 1)
-- Tabelas: companies, roles, permissions, users, departments, employees,
-- sessions. Os demais módulos (ponto, férias, documentos, recrutamento,
-- folha) entram nas próximas fases.
--
-- Aplicar com: psql -U peopleflow -d peopleflow_mvp -f database/schema.sql
-- ═══════════════════════════════════════════════════════════════════════════

BEGIN;

DROP TABLE IF EXISTS sessions, employees, departments, users,
                     role_permissions, permissions, roles, companies CASCADE;

-- ── Empresas (multiempresa desde a Fase 1) ───────────────────────────────
CREATE TABLE companies (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(160) NOT NULL,
    trade_name  VARCHAR(160),
    cnpj        VARCHAR(18) UNIQUE,          -- 00.000.000/0000-00
    email       VARCHAR(160),
    phone       VARCHAR(20),
    is_active   BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ── RBAC ─────────────────────────────────────────────────────────────────
CREATE TABLE roles (
    id          SERIAL PRIMARY KEY,
    code        VARCHAR(30) NOT NULL UNIQUE,  -- admin | rh | dp | gestor | colaborador
    name        VARCHAR(60) NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE permissions (
    id          SERIAL PRIMARY KEY,
    code        VARCHAR(60) NOT NULL UNIQUE,  -- recurso:acao (ex.: employees:create)
    description VARCHAR(160) NOT NULL
);

CREATE TABLE role_permissions (
    role_id       INT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id INT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

-- ── Usuários ─────────────────────────────────────────────────────────────
CREATE TABLE users (
    id          SERIAL PRIMARY KEY,
    company_id  INT NOT NULL REFERENCES companies(id),
    role_id     INT NOT NULL REFERENCES roles(id),
    name        VARCHAR(120) NOT NULL,
    email       VARCHAR(160) NOT NULL,
    -- Hash Argon2id gerado por password_hash(PASSWORD_ARGON2ID)
    password    VARCHAR(255) NOT NULL,
    is_active   BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (company_id, email)               -- mesmo e-mail pode existir em outra empresa
);
CREATE INDEX idx_users_company ON users(company_id);

-- ── Estrutura organizacional ─────────────────────────────────────────────
CREATE TABLE departments (
    id          SERIAL PRIMARY KEY,
    company_id  INT NOT NULL REFERENCES companies(id),
    name        VARCHAR(120) NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_departments_company ON departments(company_id);

CREATE TABLE employees (
    id            SERIAL PRIMARY KEY,
    company_id    INT NOT NULL REFERENCES companies(id),
    department_id INT REFERENCES departments(id),
    user_id       INT UNIQUE REFERENCES users(id),   -- login do portal (opcional)
    registration  VARCHAR(30) NOT NULL,              -- matrícula
    full_name     VARCHAR(160) NOT NULL,
    position      VARCHAR(120),
    -- admission | active | vacation | on_leave | terminated
    status        VARCHAR(20) NOT NULL DEFAULT 'active',
    hired_at      DATE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (company_id, registration)
);
CREATE INDEX idx_employees_company_status ON employees(company_id, status);

-- ── Sessões (auditoria de login; a sessão viva é a nativa do PHP) ────────
CREATE TABLE sessions (
    id          SERIAL PRIMARY KEY,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    ip_address  VARCHAR(45),
    user_agent  TEXT,
    logged_in_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    logged_out_at TIMESTAMPTZ
);
CREATE INDEX idx_sessions_user ON sessions(user_id);

COMMIT;
