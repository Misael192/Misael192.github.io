<?php

/**
 * AI Engine (conversas, prompts, knowledge base) e Workflow Engine
 * (templates visuais, instâncias e histórico auditável).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── IA ───────────────────────────────────────────────────────────────
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained();
            $table->string('agent')->default('assistant'); // clt|contracts|recruiter|…
            $table->string('title')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role'); // user | assistant | tool
            $table->text('content');
            $table->json('tool_calls')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_cents', 10, 4)->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('provider')->nullable(); // openai|gemini|claude|ollama
            $table->timestamps();
            $table->index(['tenant_id', 'conversation_id']);
        });

        // Prompt Library versionada por caso de uso, editável por tenant.
        Schema::create('ai_prompt_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable(); // null = template global
            $table->string('code'); // ex.: "generate-warning-letter"
            $table->unsignedSmallInteger('version')->default(1);
            $table->text('content');
            $table->json('variables'); // ex.: ["employeeName", "reason"]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code', 'version']);
        });

        // Knowledge Base para RAG; embedding usa pgvector em produção.
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable(); // null = base global (ex.: CLT)
            $table->string('source'); // clt | policy | upload
            $table->string('title');
            $table->text('chunk');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'source']);
        });

        // Coluna vetorial só em PostgreSQL (extensão pgvector).
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared('CREATE EXTENSION IF NOT EXISTS vector');
            DB::unprepared('ALTER TABLE knowledge_documents ADD COLUMN embedding vector(1536)');
        }

        // ── Workflow Engine ──────────────────────────────────────────────────
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('trigger_event'); // ex.: "vacation.requested"
            $table->unsignedSmallInteger('version')->default(1);
            $table->json('definition'); // grafo {nodes, edges} — o MESMO JSON do editor
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'trigger_event']);
        });

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('template_id')->constrained('workflow_templates');
            $table->string('entity_type'); // ex.: "VacationRequest"
            $table->uuid('entity_id');
            // running | waiting | completed | failed | canceled
            $table->string('status')->default('running');
            $table->string('current_node_id')->nullable();
            $table->json('context'); // variáveis acumuladas na execução
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->index(['tenant_id', 'entity_type', 'entity_id']);
        });

        // Workflow History: cada passo executado, auditável.
        Schema::create('workflow_step_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->string('node_id');
            $table->string('node_type'); // trigger|condition|approval|document|signature|…
            $table->string('status'); // pending | completed | rejected | failed
            $table->uuid('actor_id')->nullable(); // quem agiu (aprovações/assinaturas)
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->index(['tenant_id', 'instance_id']);
        });
    }

    public function down(): void
    {
        foreach (['workflow_step_executions', 'workflow_instances', 'workflow_templates',
            'knowledge_documents', 'ai_prompt_templates', 'ai_messages',
            'ai_conversations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
