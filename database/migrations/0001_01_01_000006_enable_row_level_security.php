<?php

/**
 * Row-Level Security — defesa em profundidade do multi-tenancy (ADR-002).
 *
 * A aplicação define o tenant no início de cada request/transação:
 *   SET app.tenant_id = '<uuid>'   (feito pelo middleware ResolveTenant)
 * Qualquer query que escape do escopo global do Eloquent retorna zero linhas.
 * Apenas PostgreSQL; em SQLite (testes) o escopo global cobre o isolamento.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE t text;
            BEGIN
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
        SQL);
    }

    public function down(): void
    {
        // Políticas são idempotentes; remoção explícita não é necessária.
    }
};
