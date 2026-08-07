<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eventos eSocial gerados a partir dos dados reais (migração do MVP):
 *  - S-2200: admissão do trabalhador;
 *  - S-1200: remuneração da folha FECHADA da competência.
 * O XML fica gerado, versionado e auditável; a transmissão ao webservice
 * (certificado A1) é a etapa seguinte. Tenant-scoped (RLS no PostgreSQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esocial_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained();
            $table->foreignUuid('employee_id')->nullable()->constrained();
            $table->string('event_type'); // S-2200 | S-1200
            $table->string('reference');  // matrícula (admissão) ou competência (remuneração)
            $table->longText('xml');
            $table->string('status')->default('generated'); // generated | transmitted | rejected
            $table->uuid('created_by_id')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'event_type', 'reference']);
            $table->index(['tenant_id', 'company_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esocial_events');
    }
};
