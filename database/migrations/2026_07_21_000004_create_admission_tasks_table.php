<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admissão digital (migração do MVP): checklist por colaborador em admissão.
 * Concluir todos os itens ativa o colaborador automaticamente. Tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_tasks');
    }
};
