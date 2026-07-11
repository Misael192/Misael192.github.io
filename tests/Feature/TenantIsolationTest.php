<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Isolamento multi-tenant (ADR-002): dados de um tenant jamais aparecem
 * no contexto de outro — nem por acidente do desenvolvedor.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithEmployee(string $slug, string $employeeName): Tenant
    {
        $tenant = Tenant::query()->create(['slug' => $slug, 'name' => ucfirst($slug)]);
        app(TenantContext::class)->set($tenant);

        $organization = Organization::query()->create(['name' => "Org {$slug}"]);
        $company = Company::query()->create([
            'organization_id' => $organization->id,
            'name' => "Empresa {$slug}",
        ]);
        Employee::query()->create([
            'company_id' => $company->id,
            'registration_number' => '0001',
            'full_name' => $employeeName,
        ]);

        return $tenant;
    }

    public function test_escopo_global_isola_dados_entre_tenants(): void
    {
        $tenantA = $this->makeTenantWithEmployee('empresa-a', 'Alice da Empresa A');
        $tenantB = $this->makeTenantWithEmployee('empresa-b', 'Bruno da Empresa B');

        $context = app(TenantContext::class);

        $context->set($tenantA);
        $this->assertSame(['Alice da Empresa A'], Employee::query()->pluck('full_name')->all());

        $context->set($tenantB);
        $this->assertSame(['Bruno da Empresa B'], Employee::query()->pluck('full_name')->all());
    }

    public function test_tenant_id_e_preenchido_automaticamente_na_criacao(): void
    {
        $tenant = $this->makeTenantWithEmployee('empresa-c', 'Carla');

        $employee = Employee::query()->first();
        $this->assertSame($tenant->id, $employee->tenant_id);
    }
}
