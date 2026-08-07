<?php

/**
 * Módulos People/DP e RH: colaboradores, ponto, férias, GED, holerites,
 * recrutamento, benefícios, desempenho, treinamentos e engajamento.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained();
            $table->foreignUuid('branch_id')->nullable()->constrained();
            $table->foreignUuid('department_id')->nullable()->constrained();
            $table->foreignUuid('team_id')->nullable()->constrained();
            $table->foreignUuid('position_id')->nullable()->constrained();
            $table->foreignUuid('cost_center_id')->nullable()->constrained();
            $table->foreignUuid('user_id')->nullable()->unique()->constrained(); // login do portal
            $table->foreignUuid('manager_id')->nullable()->constrained('employees');

            $table->string('registration_number'); // matrícula
            $table->string('full_name');
            $table->string('social_name')->nullable();
            $table->text('cpf')->nullable(); // cifrado (cast encrypted)
            $table->text('rg')->nullable(); // cifrado
            $table->date('birth_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('personal_email')->nullable();
            $table->json('address')->nullable();
            $table->text('bank_info')->nullable(); // cifrado
            // admission | active | on_leave | vacation | terminated
            $table->string('status')->default('admission');
            $table->date('hired_at')->nullable();
            $table->date('terminated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'company_id', 'registration_number']);
            $table->index(['tenant_id', 'company_id', 'status']);
        });

        // FK adiada: teams.manager_id → employees (dependência circular).
        // SQLite (testes) não suporta ADD CONSTRAINT; a integridade lá é
        // garantida pela aplicação — em produção (PostgreSQL) a FK existe.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::table('teams', function (Blueprint $table) {
                $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
            });
        }

        Schema::create('employment_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained();
            $table->string('type'); // clt | pj | estagio | temporario
            $table->unsignedInteger('salary_cents')->nullable();
            $table->unsignedTinyInteger('weekly_hours')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->uuid('document_id')->nullable(); // contrato assinado no GED
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
        });

        // Escala de trabalho (5x2, 12x36…); `rules` guarda a definição flexível.
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained();
            $table->string('name');
            $table->json('rules');
            $table->timestamps();
            $table->index(['tenant_id', 'company_id']);
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained();
            $table->foreignUuid('schedule_id')->nullable()->constrained('work_schedules');
            $table->string('type'); // clock_in | clock_out | break_start | break_end
            $table->timestamp('recorded_at');
            $table->string('source')->default('web'); // web | mobile
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            // recorded | adjustment_requested | approved | rejected
            $table->string('status')->default('recorded');
            $table->string('adjust_reason')->nullable();
            $table->uuid('approved_by_id')->nullable();
            $table->string('idempotency_key')->nullable()->unique(); // evita duplo registro mobile
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'recorded_at']);
        });

        // Banco de horas: minutos positivos = crédito, negativos = débito.
        Schema::create('time_bank_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained();
            $table->integer('minutes');
            $table->string('reason'); // overtime | compensation | adjustment
            $table->date('reference_date');
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'reference_date']);
        });

        Schema::create('vacation_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days');
            $table->unsignedTinyInteger('sell_days')->default(0); // abono (CLT art. 143: máx. 10)
            // requested|approved|rejected|scheduled|in_progress|completed|canceled
            $table->string('status')->default('requested');
            $table->uuid('approved_by_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'status']);
        });

        // ── GED ──────────────────────────────────────────────────────────────
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->nullable()->constrained();
            $table->string('category'); // contract|admission|medical|policy|payslip|other
            $table->string('name');
            $table->uuid('created_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'employee_id', 'category']);
        });

        // Cada upload gera uma versão imutável no storage (S3/Supabase).
        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('storage_key');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum'); // SHA-256 — integridade e deduplicação
            $table->uuid('uploaded_by_id')->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'version']);
        });

        Schema::create('signature_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained();
            $table->foreignUuid('signer_id')->constrained('users');
            $table->string('status')->default('pending'); // pending|signed|declined|expired
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_hash')->nullable();
            $table->json('evidence')->nullable(); // IP, UA, geo, timestamp confiável
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'signer_id', 'status']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained();
            $table->string('competency'); // "2026-07"
            $table->unsignedInteger('gross_cents')->nullable();
            $table->unsignedInteger('net_cents')->nullable();
            $table->uuid('document_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'employee_id', 'competency']);
        });

        // ── Recrutamento ─────────────────────────────────────────────────────
        Schema::create('job_openings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained();
            $table->string('title');
            $table->text('description');
            $table->string('location')->nullable();
            $table->boolean('is_remote')->default(false);
            $table->boolean('is_published')->default(false); // Trabalhe Conosco
            $table->string('status')->default('draft'); // draft|open|paused|closed
            $table->json('pipeline'); // colunas do Kanban por vaga
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'company_id', 'status']);
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('resume_key')->nullable(); // currículo no storage
            $table->text('resume_summary')->nullable(); // resumo do AI Engine
            $table->string('linkedin_url')->nullable();
            $table->timestamps();
            $table->softDeletes(); // LGPD: candidato pode pedir exclusão
            $table->unique(['tenant_id', 'email']);
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('job_opening_id')->constrained();
            $table->foreignUuid('candidate_id')->constrained();
            $table->string('stage'); // coluna atual do Kanban
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('history'); // trilha: [{stage, at, by, notes}]
            $table->timestamps();
            $table->unique(['job_opening_id', 'candidate_id']);
            $table->index(['tenant_id', 'job_opening_id', 'stage']);
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('job_applications');
            $table->foreignUuid('interviewer_id')->constrained('users');
            $table->timestamp('scheduled_at');
            $table->string('location')->nullable(); // presencial ou link de vídeo
            $table->text('feedback')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'application_id']);
        });

        // ── Benefícios ───────────────────────────────────────────────────────
        Schema::create('benefits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // vt | va | vr | health | dental | other
            $table->string('name');
            $table->string('provider')->nullable(); // convênio/operadora
            $table->unsignedInteger('cost_cents')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('employee_benefits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained();
            $table->foreignUuid('benefit_id')->constrained();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->json('usage')->nullable(); // controle de utilização
            $table->timestamps();
            $table->unique(['employee_id', 'benefit_id', 'start_date']);
            $table->index(['tenant_id', 'employee_id']);
        });

        // ── Desempenho & Learning ────────────────────────────────────────────
        Schema::create('goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('metric')->nullable();
            $table->decimal('target', 12, 2)->nullable();
            $table->decimal('progress', 5, 2)->default(0); // 0–100%
            $table->date('due_date')->nullable();
            $table->string('status')->default('active'); // active|achieved|missed|canceled
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'status']);
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained();
            $table->foreignUuid('reviewer_id')->constrained('users');
            $table->string('cycle'); // "2026-S1"
            $table->string('type')->default('manager'); // manager|self|360|behavioral
            $table->json('competencies'); // [{name, score, comment}]
            $table->decimal('overall_score', 4, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'cycle']);
        });

        Schema::create('trainings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('content_url')->nullable();
            $table->unsignedSmallInteger('duration_min')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('tenant_id');
        });

        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('training_id')->constrained();
            $table->foreignUuid('employee_id')->constrained();
            $table->decimal('progress', 5, 2)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->string('certificate_key')->nullable(); // certificado no storage
            $table->timestamps();
            $table->unique(['training_id', 'employee_id']);
            $table->index(['tenant_id', 'employee_id']);
        });

        // ── Engajamento ──────────────────────────────────────────────────────
        Schema::create('surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // climate | satisfaction | enps
            $table->string('title');
            $table->json('questions');
            $table->boolean('is_anonymous')->default(true); // clima é anônima por padrão
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('survey_id')->constrained();
            $table->uuid('employee_id')->nullable(); // null quando anônima
            $table->json('answers');
            $table->timestamps();
            $table->index(['tenant_id', 'survey_id']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('company_id')->nullable(); // null = todas as empresas do tenant
            $table->foreignUuid('author_id')->constrained('users');
            $table->string('title');
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'company_id', 'published_at']);
        });

        Schema::create('recognitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('from_user_id')->constrained('users');
            $table->foreignUuid('to_employee_id')->constrained('employees');
            $table->text('message');
            $table->string('badge')->nullable(); // ex.: "team-player"
            $table->timestamps();
            $table->index(['tenant_id', 'to_employee_id']);
        });
    }

    public function down(): void
    {
        foreach (['recognitions', 'announcements', 'survey_responses', 'surveys',
            'training_enrollments', 'trainings', 'performance_reviews', 'goals',
            'employee_benefits', 'benefits', 'interviews', 'job_applications',
            'candidates', 'job_openings', 'payslips', 'signature_requests',
            'document_versions', 'documents', 'vacation_requests', 'time_bank_entries',
            'time_entries', 'work_schedules', 'employment_contracts', 'employees'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
