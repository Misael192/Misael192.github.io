-- ═══════════════════════════════════════════════════════════════════════════
-- PeopleFlow MVP · Fase 4 — Assistente CLT + eSocial
-- Assistente: conversas persistidas por usuário; respostas calculadas pela
-- MESMA engine da folha (tabelas vigentes, nunca valores fixos em texto).
-- eSocial: eventos gerados dos dados reais (S-2200 admissão, S-1200 remuneração).
-- Idempotente.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS ai_conversations (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title      VARCHAR(120),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS ai_messages (
    id              SERIAL PRIMARY KEY,
    conversation_id INT NOT NULL REFERENCES ai_conversations(id) ON DELETE CASCADE,
    role            VARCHAR(10) NOT NULL,        -- user | assistant
    content         TEXT NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_ai_messages_conv ON ai_messages(conversation_id);

-- Eventos eSocial gerados (XML pronto para transmissão via webservice)
CREATE TABLE IF NOT EXISTS esocial_events (
    id          SERIAL PRIMARY KEY,
    company_id  INT NOT NULL REFERENCES companies(id),
    employee_id INT REFERENCES employees(id),
    event_type  VARCHAR(10) NOT NULL,            -- S-2200 | S-1200
    reference   VARCHAR(30) NOT NULL,            -- matrícula (S-2200) ou competência (S-1200)
    status      VARCHAR(12) NOT NULL DEFAULT 'generated', -- generated | transmitted
    xml         TEXT NOT NULL,
    created_by  INT REFERENCES users(id),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (company_id, event_type, reference)
);

INSERT INTO permissions (code, description) VALUES ('esocial:manage', 'Gerar e transmitir eventos eSocial')
ON CONFLICT (code) DO NOTHING;
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.code IN ('admin', 'dp') AND p.code = 'esocial:manage'
ON CONFLICT DO NOTHING;
