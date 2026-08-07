<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de folha (migração do MVP — mvp/database/fase3.sql): rubricas e
 * tabelas oficiais (INSS/IRRF/FGTS/salário-família). São lei federal,
 * iguais para todo tenant — por isso SEM tenant_id, como permissions/
 * modules/plans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type'); // earning|deduction|info
            $table->string('group')->nullable(); // proventos|descontos|encargos|informativas
            $table->string('nature')->nullable(); // natureza eSocial (ex.: 1000 salário)
            $table->boolean('incides_inss')->default(false);
            $table->boolean('incides_irrf')->default(false);
            $table->boolean('incides_fgts')->default(false);
            $table->string('esocial_code')->nullable();
            $table->string('formula')->nullable(); // chave interpretada pela engine
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // inss|teto_inss|irrf|salario_familia|fgts
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->json('brackets');
            $table->timestamps();
            $table->unique(['type', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_tables');
        Schema::dropIfExists('rubrics');
    }
};
