<?php

/**
 * RBAC, feature flags, módulos comerciais, billing, integrações e auditoria.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── RBAC ─────────────────────────────────────────────────────────────
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // ex.: "people", "recruitment"
            $table->string('name');
            $table->timestamps();
        });

        // Catálogo global de permissões `recurso:ação` (semeado por seeder).
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // ex.: "vacations:approve"
            $table->string('description');
            $table->foreignUuid('group_id')->nullable()->constrained('permission_groups');
            $table->timestamps();
        });

        // Papéis por tenant; papéis de sistema não podem ser removidos pela UI.
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code'); // OWNER | ADMIN | HR | DP | MANAGER | EMPLOYEE | custom
            $table->string('name');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // Atribuição com escopo opcional (empresa/filial) — base para ABAC.
        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained();
            $table->foreignUuid('branch_id')->nullable()->constrained();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
        });

        // ── Plataforma ───────────────────────────────────────────────────────
        // Escopo hierárquico: plataforma → tenant → empresa → usuário.
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable(); // null = configuração de plataforma
            $table->uuid('company_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('key');
            $table->json('value');
            $table->timestamps();
            $table->unique(['tenant_id', 'company_id', 'user_id', 'key']);
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('description')->nullable();
            $table->boolean('enabled_globally')->default(false);
            $table->unsignedTinyInteger('rollout_percentage')->default(0);
            $table->json('enabled_tenant_ids')->nullable();
            $table->json('disabled_tenant_ids')->nullable();
            $table->timestamps();
        });

        // Catálogo de módulos comerciais (people, payroll, recruitment…).
        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_core')->default(false);
            $table->timestamps();
        });

        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->string('source')->default('plan'); // plan | trial | manual
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->primary(['tenant_id', 'module_id']);
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // slack | esocial | webhook | ...
            $table->string('name');
            $table->text('config'); // cifrado (cast encrypted:json)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('tenant_id');
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('integration_id')->constrained();
            $table->string('event');
            $table->json('payload');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'integration_id', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // ex.: "vacation.approved"
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->string('channel')->default('in_app'); // in_app | email | push
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'read_at']);
        });

        // ── Billing (estrutura desde o MVP: plano = módulos + quotas) ────────
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // starter | business | enterprise
            $table->string('name');
            $table->unsignedInteger('price_cents'); // mensal, em centavos (BRL)
            $table->json('module_codes');
            $table->json('limits'); // { maxEmployees, maxUsers, aiTokensMonth, storageGb }
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained();
            $table->string('status')->default('trialing'); // trialing|active|past_due|canceled
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->boolean('cancel_at_period_end')->default(false);
            $table->string('external_id')->nullable(); // id no gateway (Stripe)
            $table->string('coupon_code')->nullable();
            $table->timestamps();
            $table->index('tenant_id');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->constrained();
            $table->unsignedInteger('amount_cents');
            $table->string('status')->default('open'); // open|paid|void|uncollectible
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('external_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'subscription_id']);
        });

        // ── Auditoria (append-only — trigger no PostgreSQL impede mutação) ───
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('actor_id')->nullable();
            $table->string('actor_type')->default('user'); // user | api_key | system
            $table->string('action'); // ex.: "employee.update"
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'entity_type', 'entity_id']);
        });

        // Auditoria é imutável: bloqueia UPDATE/DELETE no nível do banco (Postgres).
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION forbid_audit_mutation() RETURNS trigger AS $$
                BEGIN RAISE EXCEPTION 'audit_logs é append-only'; END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER audit_logs_immutable
                  BEFORE UPDATE OR DELETE ON audit_logs
                  FOR EACH ROW EXECUTE FUNCTION forbid_audit_mutation();
            SQL);
        }
    }

    public function down(): void
    {
        foreach (['audit_logs', 'invoices', 'subscriptions', 'plans', 'notifications',
                  'webhook_logs', 'integrations', 'tenant_modules', 'modules', 'feature_flags',
                  'settings', 'user_roles', 'permission_role', 'roles', 'permissions',
                  'permission_groups'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
