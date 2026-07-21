<?php

namespace Database\Seeders;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed inicial: catálogo de módulos, permissões, planos e um tenant de
 * demonstração com papéis de sistema e usuário admin (admin@demo.com / password).
 */
class DatabaseSeeder extends Seeder
{
    private const MODULES = [
        ['code' => 'people', 'name' => 'Pessoas & DP', 'is_core' => true],
        ['code' => 'documents', 'name' => 'GED — Documentos', 'is_core' => true],
        ['code' => 'payroll', 'name' => 'Folha de Pagamento', 'is_core' => false],
        ['code' => 'recruitment', 'name' => 'Recrutamento & Seleção', 'is_core' => false],
        ['code' => 'benefits', 'name' => 'Benefícios', 'is_core' => false],
        ['code' => 'learning', 'name' => 'Treinamentos', 'is_core' => false],
        ['code' => 'performance', 'name' => 'Desempenho', 'is_core' => false],
        ['code' => 'analytics', 'name' => 'Analytics', 'is_core' => false],
        ['code' => 'ai', 'name' => 'Assistente de IA', 'is_core' => false],
        ['code' => 'marketplace', 'name' => 'Marketplace', 'is_core' => false],
    ];

    private const PERMISSION_GROUPS = [
        'people' => [
            'employees:read', 'employees:create', 'employees:update', 'employees:terminate',
            'vacations:read', 'vacations:request', 'vacations:approve',
            'time-entries:read', 'time-entries:register', 'time-entries:approve',
        ],
        'documents' => ['documents:read', 'documents:upload', 'documents:sign', 'documents:share'],
        'recruitment' => ['jobs:read', 'jobs:manage', 'candidates:read', 'candidates:manage'],
        'benefits' => ['benefits:read', 'benefits:manage'],
        'performance' => ['reviews:read', 'reviews:manage', 'goals:read', 'goals:manage'],
        'learning' => ['trainings:read', 'trainings:manage'],
        'admin' => [
            'users:read', 'users:manage', 'roles:manage', 'settings:manage',
            'billing:manage', 'audit:read', 'integrations:manage', 'workflows:manage',
        ],
        'ai' => ['ai:chat', 'ai:generate-documents'],
    ];

    public function run(): void
    {
        // Motor de folha: rubricas e tabelas oficiais (globais, sem tenant)
        $this->call(PayrollEngineSeeder::class);

        // Catálogo de módulos
        foreach (self::MODULES as $module) {
            Module::query()->updateOrCreate(['code' => $module['code']], $module);
        }

        // Grupos e permissões
        $permissionIds = [];
        foreach (self::PERMISSION_GROUPS as $groupCode => $codes) {
            $group = PermissionGroup::query()->updateOrCreate(
                ['code' => $groupCode],
                ['name' => ucfirst($groupCode)],
            );
            foreach ($codes as $code) {
                $permission = Permission::query()->updateOrCreate(
                    ['code' => $code],
                    ['description' => $code, 'group_id' => $group->id],
                );
                $permissionIds[$code] = $permission->id;
            }
        }

        // Planos (billing preparado desde o MVP)
        $plans = [
            ['code' => 'starter', 'name' => 'Starter', 'price_cents' => 29900,
                'module_codes' => ['people', 'documents'],
                'limits' => ['maxEmployees' => 50, 'maxUsers' => 10, 'aiTokensMonth' => 0, 'storageGb' => 5]],
            ['code' => 'business', 'name' => 'Business', 'price_cents' => 79900,
                'module_codes' => ['people', 'documents', 'recruitment', 'benefits', 'performance', 'learning', 'analytics', 'ai'],
                'limits' => ['maxEmployees' => 300, 'maxUsers' => 50, 'aiTokensMonth' => 500000, 'storageGb' => 50]],
            ['code' => 'enterprise', 'name' => 'Enterprise', 'price_cents' => 0,
                'module_codes' => array_column(self::MODULES, 'code'),
                'limits' => ['maxEmployees' => -1, 'maxUsers' => -1, 'aiTokensMonth' => -1, 'storageGb' => -1]],
        ];
        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['code' => $plan['code']], $plan);
        }

        // Tenant demo + módulos do plano Business habilitados
        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Empresa Demonstração'],
        );

        // Fixa o tenant no contexto para escopos/traits funcionarem no seed.
        app(TenantContext::class)->set($tenant);

        foreach (Module::query()->whereIn('code', $plans[1]['module_codes'])->get() as $module) {
            DB::table('tenant_modules')->updateOrInsert(
                ['tenant_id' => $tenant->id, 'module_id' => $module->id],
                ['is_enabled' => true, 'source' => 'plan', 'created_at' => now(), 'updated_at' => now()],
            );
        }

        // Papéis de sistema
        $systemRoles = [
            'OWNER' => ['name' => 'Proprietário', 'permissions' => '*'],
            'ADMIN' => ['name' => 'Administrador', 'permissions' => '*'],
            'HR' => ['name' => 'RH', 'permissions' => array_merge(
                self::PERMISSION_GROUPS['people'], self::PERMISSION_GROUPS['recruitment'],
                self::PERMISSION_GROUPS['benefits'], self::PERMISSION_GROUPS['performance'],
                self::PERMISSION_GROUPS['learning'], self::PERMISSION_GROUPS['documents'],
                self::PERMISSION_GROUPS['ai'],
            )],
            'DP' => ['name' => 'Departamento Pessoal', 'permissions' => array_merge(
                self::PERMISSION_GROUPS['people'], self::PERMISSION_GROUPS['documents'],
                self::PERMISSION_GROUPS['ai'],
            )],
            'MANAGER' => ['name' => 'Gestor', 'permissions' => [
                'employees:read', 'vacations:read', 'vacations:approve',
                'time-entries:read', 'time-entries:approve', 'documents:read',
                'reviews:manage', 'goals:manage',
            ]],
            'EMPLOYEE' => ['name' => 'Colaborador', 'permissions' => [
                'vacations:request', 'time-entries:register', 'documents:read',
                'documents:upload', 'documents:sign', 'trainings:read',
            ]],
        ];

        $roles = [];
        foreach ($systemRoles as $code => $definition) {
            $role = Role::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                ['name' => $definition['name'], 'is_system' => true],
            );
            $ids = $definition['permissions'] === '*'
                ? array_values($permissionIds)
                : array_values(array_intersect_key($permissionIds, array_flip($definition['permissions'])));
            $role->permissions()->sync($ids);
            $roles[$code] = $role;
        }

        // Organização, empresa e usuário admin de demonstração
        $organization = Organization::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Grupo Demonstração'],
        );
        Company::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'organization_id' => $organization->id, 'name' => 'Empresa Demonstração LTDA'],
        );

        $admin = User::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'admin@demo.com'],
            ['name' => 'Admin Demo', 'password' => 'password'],
        );
        UserRole::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'role_id' => $roles['ADMIN']->id,
        ]);
    }
}
