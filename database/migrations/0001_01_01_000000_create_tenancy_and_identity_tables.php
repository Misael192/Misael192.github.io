<?php

/**
 * Tenancy, organização e identidade — a fundação do multi-tenancy (ADR-002).
 *
 * Convenções (valem para todas as migrations):
 *  • Toda tabela de domínio carrega `tenant_id` — mesmo quando o tenant migrar
 *    para schema/banco dedicado, a coluna permanece (permite mover tenants
 *    entre estratégias com export/import filtrado).
 *  • UUIDs são gerados na aplicação (trait HasUuids) para portabilidade
 *    entre PostgreSQL (produção) e SQLite (testes).
 *  • Soft deletes em entidades de negócio; exclusão física só via rotina LGPD.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique(); // subdomínio: <slug>.peopleflow.com.br
            $table->string('name');
            // Estratégia de isolamento: column | schema | database (ADR-002)
            $table->string('isolation_level')->default('column');
            $table->text('database_url')->nullable(); // cifrado; só p/ isolation=database
            $table->string('schema_name')->nullable(); // só p/ isolation=schema
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('document')->nullable(); // CNPJ raiz (cifrado)
            $table->timestamps();
            $table->softDeletes();
            $table->index('tenant_id');
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained();
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->text('cnpj')->nullable(); // cifrado
            $table->timestamps();
            $table->softDeletes();
            $table->index('tenant_id');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained();
            $table->string('name');
            $table->text('cnpj')->nullable(); // cifrado
            $table->json('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'company_id']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained();
            $table->foreignUuid('parent_id')->nullable()->constrained('departments');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'company_id']);
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('department_id')->constrained();
            $table->uuid('manager_id')->nullable(); // FK p/ employees adicionada depois
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'department_id']);
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained();
            $table->string('title');
            $table->text('description')->nullable(); // pode ser gerada pelo AI Engine
            $table->string('cbo_code')->nullable(); // Classificação Brasileira de Ocupações
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'company_id']);
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'company_id', 'code']);
        });

        // ── Identidade ───────────────────────────────────────────────────────
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable(); // Argon2id; null = só OAuth
            $table->string('avatar_url')->nullable();
            $table->string('locale')->default('pt-BR');
            $table->boolean('is_active')->default(true);
            $table->boolean('mfa_enabled')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'email']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Sessões web do Laravel (driver database)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        /**
         * Família de refresh tokens rotativos da API (ADR-005).
         * Guarda apenas o hash do token ATUAL; reuso de token antigo
         * revoga a sessão inteira (detecção de roubo).
         */
        Schema::create('refresh_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('refresh_token_hash');
            $table->unsignedInteger('rotation_counter')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('oauth_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // google | microsoft | ...
            $table->string('provider_user_id');
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
        });

        Schema::create('mfa_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('totp');
            $table->text('secret'); // cifrado (cast encrypted)
            $table->json('recovery_codes')->nullable(); // hashes
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by_id')->constrained('users');
            $table->string('name');
            $table->string('key_hash')->unique();
            $table->string('prefix'); // pf_live_ab12… (identificação na UI)
            $table->json('scopes'); // permissões concedidas
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        foreach (['api_keys', 'mfa_credentials', 'oauth_accounts', 'refresh_sessions', 'sessions',
                  'password_reset_tokens', 'users', 'cost_centers', 'positions', 'teams',
                  'departments', 'branches', 'companies', 'organizations', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
