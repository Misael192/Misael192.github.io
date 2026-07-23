<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fechamento de folha (migração do MVP — mvp/database/fase3.sql): período,
 * folhas por colaborador, itens (holerite) e encargos patronais. Tudo
 * tenant-scoped (RLS liga automaticamente por terem tenant_id).
 *
 * `kind` distingue folha mensal (payslip) das especiais (13º/férias/
 * rescisão), que virão em fatias seguintes na MESMA estrutura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained();
            $table->string('competency'); // "2026-07"
            $table->string('status')->default('open'); // open|calculated|closed
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->uuid('closed_by_id')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'company_id', 'competency']);
            $table->index(['tenant_id', 'company_id', 'status']);
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained();
            // payslip | vacation | thirteenth_1 | thirteenth_2 | termination
            $table->string('kind')->default('payslip');
            $table->unsignedBigInteger('gross_cents')->default(0);
            $table->unsignedBigInteger('deductions_cents')->default(0);
            $table->unsignedBigInteger('net_cents')->default(0);
            $table->unsignedBigInteger('inss_base_cents')->default(0);
            $table->unsignedBigInteger('irrf_base_cents')->default(0);
            $table->unsignedBigInteger('fgts_base_cents')->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
            $table->unique(['period_id', 'employee_id', 'kind']);
            $table->index(['tenant_id', 'employee_id']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('payroll_id')->constrained()->cascadeOnDelete();
            $table->string('rubric_code');
            $table->string('description');
            $table->decimal('reference', 10, 2)->nullable(); // horas/dias/% no holerite
            $table->bigInteger('amount_cents'); // positivo; type dá o sinal
            $table->string('type'); // earning|deduction|info
            $table->timestamps();
            $table->index('payroll_id');
        });

        // Encargos patronais (não saem do líquido): FGTS, INSS patronal, RAT…
        Schema::create('social_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('payroll_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // fgts|inss_patronal|rat|terceiros
            $table->unsignedBigInteger('base_cents');
            $table->decimal('rate', 5, 2);
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
            $table->index('payroll_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_charges');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('payroll_periods');
    }
};
