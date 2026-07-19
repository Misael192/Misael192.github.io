-- ═══════════════════════════════════════════════════════════════════════════
-- PeopleFlow MVP · Fase 6 — API pública /api/v1
-- Chaves de API por empresa (hash SHA-256, nunca o segredo em claro) com
-- escopos read/write. Idempotente.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS api_keys (
    id           SERIAL PRIMARY KEY,
    company_id   INT NOT NULL REFERENCES companies(id),
    name         VARCHAR(80) NOT NULL,            -- ex.: "Integração Conta Azul"
    key_prefix   VARCHAR(16) NOT NULL,            -- exibição: pfk_a1b2c3d4…
    key_hash     CHAR(64) NOT NULL UNIQUE,        -- sha256 do segredo completo
    scopes       VARCHAR(40) NOT NULL DEFAULT 'read',  -- read | read,write
    is_active    BOOLEAN NOT NULL DEFAULT TRUE,
    last_used_at TIMESTAMPTZ,
    created_by   INT REFERENCES users(id),
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_api_keys_company ON api_keys(company_id);

INSERT INTO permissions (code, description) VALUES ('api:manage', 'Gerenciar chaves da API pública')
ON CONFLICT (code) DO NOTHING;
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.code = 'admin' AND p.code = 'api:manage'
ON CONFLICT DO NOTHING;

-- ── Webhooks de saída ────────────────────────────────────────────────────
-- Integrações recebem POST assinado (HMAC-SHA256) quando algo acontece.
CREATE TABLE IF NOT EXISTS webhook_endpoints (
    id         SERIAL PRIMARY KEY,
    company_id INT NOT NULL REFERENCES companies(id),
    url        VARCHAR(255) NOT NULL,
    secret     CHAR(64) NOT NULL,               -- whsec p/ assinar o corpo
    events     VARCHAR(255),                    -- CSV; NULL = todos
    is_active  BOOLEAN NOT NULL DEFAULT TRUE,
    created_by INT REFERENCES users(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id            SERIAL PRIMARY KEY,
    endpoint_id   INT NOT NULL REFERENCES webhook_endpoints(id) ON DELETE CASCADE,
    event         VARCHAR(60) NOT NULL,
    payload       JSONB NOT NULL,
    status        VARCHAR(12) NOT NULL,         -- delivered | failed
    response_code SMALLINT,
    attempts      SMALLINT NOT NULL DEFAULT 1,
    delivered_at  TIMESTAMPTZ,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_deliveries_endpoint ON webhook_deliveries(endpoint_id, created_at DESC);
