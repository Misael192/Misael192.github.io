<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apoio às folhas especiais (13º/férias/rescisão), migração do MVP:
 *  - dependentes (IRRF conta todos; salário-família só < 14 anos);
 *  - rescisões (termo com modalidade/aviso);
 *  - referência polimórfica na folha (payrolls.source_*) ligando a folha
 *    especial ao registro que a originou (pedido de férias, rescisão).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_dependents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->date('birth_date')->nullable();
            $table->string('relationship')->nullable(); // filho|conjuge|outro
            $table->boolean('counts_for_irrf')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
        });

        Schema::create('terminations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained();
            $table->date('termination_date');
            $table->string('type');   // sem_justa_causa|justa_causa|pedido|acordo
            $table->string('notice'); // trabalhado|indenizado|dispensado
            $table->text('reason')->nullable();
            $table->uuid('created_by_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
        });

        // Liga a folha especial ao seu registro de origem (férias/rescisão).
        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('kind');
            $table->uuid('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn(['source_type', 'source_id']);
        });
        Schema::dropIfExists('terminations');
        Schema::dropIfExists('employee_dependents');
    }
};
