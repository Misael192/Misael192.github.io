-- ═══════════════════════════════════════════════════════════════════════════
-- Row-Level Security (RLS) — defesa em profundidade do multi-tenancy (ADR-002).
-- Aplicado como migração SQL após o `prisma migrate`.
--
-- A aplicação define o tenant no início de cada transação:
--   SET LOCAL app.tenant_id = '<uuid>';
-- Qualquer query que escape do filtro da aplicação retorna zero linhas.
-- ═══════════════════════════════════════════════════════════════════════════

DO $$
DECLARE
  t text;
BEGIN
  -- Todas as tabelas de domínio que possuem tenant_id.
  FOR t IN
    SELECT table_name FROM information_schema.columns
    WHERE column_name = 'tenant_id' AND table_schema = 'public'
  LOOP
    EXECUTE format('ALTER TABLE %I ENABLE ROW LEVEL SECURITY', t);
    EXECUTE format('ALTER TABLE %I FORCE ROW LEVEL SECURITY', t);
    EXECUTE format($f$
      DROP POLICY IF EXISTS tenant_isolation ON %I;
      CREATE POLICY tenant_isolation ON %I
        USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
        WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid);
    $f$, t, t);
  END LOOP;
END $$;

-- Auditoria é append-only: bloqueia UPDATE/DELETE mesmo para o role da aplicação.
CREATE OR REPLACE FUNCTION forbid_mutation() RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'audit_logs é append-only';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS audit_logs_immutable ON audit_logs;
CREATE TRIGGER audit_logs_immutable
  BEFORE UPDATE OR DELETE ON audit_logs
  FOR EACH ROW EXECUTE FUNCTION forbid_mutation();
