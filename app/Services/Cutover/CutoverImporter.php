<?php

declare(strict_types=1);

namespace App\Services\Cutover;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDependent;
use App\Models\EmploymentContract;
use App\Models\Organization;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\SocialCharge;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * ETL do cutover (ver docs/CUTOVER.md): importa o export portátil de UMA empresa
 * do MVP para o schema multi-tenant da plataforma. Gera UUIDs, fixa o
 * TenantContext (BelongsToTenant preenche tenant_id + RLS), grava PII via
 * Eloquent (casts encrypted) e preserva dinheiro em centavos. Rubricas e
 * tabelas oficiais NÃO entram — são catálogo global já semeado. Idempotente:
 * reexecutar com o mesmo export não duplica (chaves naturais).
 */
class CutoverImporter
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{tenant: string, employees: int, periods: int, payrolls: int}
     */
    public function import(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::query()->firstOrCreate(
                ['slug' => $data['tenant']['slug']],
                ['name' => $data['tenant']['name'] ?? $data['tenant']['slug'], 'is_active' => true],
            );

            // Fixa o escopo no TenantContext compartilhado (o mesmo que o
            // BelongsToTenant lê) — a partir daqui todo write recebe tenant_id (e RLS no pgsql).
            app(TenantContext::class)->set($tenant);

            $organization = Organization::query()->firstOrCreate(['name' => $data['organization']['name']]);
            $company = Company::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'name' => $data['company']['name']],
                ['cnpj' => $data['company']['cnpj'] ?? null],
            );

            $employees = $this->importEmployees($company, $data['employees'] ?? []);
            [$periods, $payrolls] = $this->importPeriods($company, $employees, $data['periods'] ?? []);

            return [
                'tenant' => $tenant->slug,
                'employees' => count($employees),
                'periods' => $periods,
                'payrolls' => $payrolls,
            ];
        });
    }

    /**
     * @return array<string, Employee> matrícula → colaborador
     */
    private function importEmployees(Company $company, array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $employee = Employee::query()->updateOrCreate(
                ['company_id' => $company->id, 'registration_number' => $row['registration_number']],
                [
                    'full_name' => $row['full_name'],
                    'cpf' => $row['cpf'] ?? null, // cast encrypted cifra no destino
                    'birth_date' => $row['birth_date'] ?? null,
                    'status' => $row['status'] ?? Employee::STATUS_ACTIVE,
                    'hired_at' => $row['hired_at'] ?? null,
                ],
            );

            foreach ($row['contracts'] ?? [] as $contract) {
                EmploymentContract::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'start_date' => $contract['start_date']],
                    [
                        'type' => $contract['type'],
                        'salary_cents' => $contract['salary_cents'] ?? null,
                        'weekly_hours' => $contract['weekly_hours'] ?? null,
                        'end_date' => $contract['end_date'] ?? null,
                    ],
                );
            }

            foreach ($row['dependents'] ?? [] as $dependent) {
                EmployeeDependent::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'full_name' => $dependent['full_name']],
                    [
                        'birth_date' => $dependent['birth_date'] ?? null,
                        'relationship' => $dependent['relationship'] ?? null,
                        'counts_for_irrf' => $dependent['counts_for_irrf'] ?? true,
                    ],
                );
            }

            $map[$employee->registration_number] = $employee;
        }

        return $map;
    }

    /**
     * @param  array<string, Employee>  $employees
     * @return array{0: int, 1: int} [períodos, folhas]
     */
    private function importPeriods(Company $company, array $employees, array $rows): array
    {
        $periodCount = 0;
        $payrollCount = 0;

        foreach ($rows as $row) {
            $closed = ($row['status'] ?? 'open') === PayrollPeriod::STATUS_CLOSED;
            $period = PayrollPeriod::query()->updateOrCreate(
                ['company_id' => $company->id, 'competency' => $row['competency']],
                ['status' => $row['status'] ?? PayrollPeriod::STATUS_OPEN, 'closed_at' => $closed ? now() : null],
            );
            $periodCount++;

            foreach ($row['payrolls'] ?? [] as $p) {
                $employee = $employees[$p['registration_number']] ?? null;
                if ($employee === null) {
                    continue; // export inconsistente: folha sem colaborador correspondente
                }

                $payroll = Payroll::query()->updateOrCreate(
                    ['period_id' => $period->id, 'employee_id' => $employee->id, 'kind' => $p['kind'] ?? Payroll::KIND_PAYSLIP],
                    [
                        'gross_cents' => $p['gross_cents'],
                        'deductions_cents' => $p['deductions_cents'],
                        'net_cents' => $p['net_cents'],
                        'inss_base_cents' => $p['inss_base_cents'] ?? 0,
                        'irrf_base_cents' => $p['irrf_base_cents'] ?? 0,
                        'fgts_base_cents' => $p['fgts_base_cents'] ?? 0,
                        'calculated_at' => now(),
                    ],
                );
                $payrollCount++;

                // Reimport substitui itens/encargos (idempotência).
                $payroll->items()->delete();
                foreach ($p['items'] ?? [] as $item) {
                    PayrollItem::query()->create([
                        'payroll_id' => $payroll->id,
                        'rubric_code' => $item['rubric_code'],
                        'description' => $item['description'],
                        'reference' => $item['reference'] ?? null,
                        'amount_cents' => $item['amount_cents'],
                        'type' => $item['type'],
                    ]);
                }

                $payroll->charges()->delete();
                foreach ($p['charges'] ?? [] as $charge) {
                    SocialCharge::query()->create([
                        'payroll_id' => $payroll->id,
                        'type' => $charge['type'],
                        'base_cents' => $charge['base_cents'],
                        'rate' => $charge['rate'],
                        'amount_cents' => $charge['amount_cents'],
                    ]);
                }
            }
        }

        return [$periodCount, $payrollCount];
    }
}
