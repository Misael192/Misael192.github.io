<?php

declare(strict_types=1);

namespace App\Services\Cutover;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Tenant;

/**
 * Reconferência do cutover (Go/No-Go, ver docs/CUTOVER.md): compara um tenant
 * JÁ IMPORTADO contra o export de origem — contagens e os totais de cada folha
 * (bruto/líquido). Qualquer divergência é reportada para travar o Go.
 */
class CutoverVerifier
{
    /**
     * @param  array<string, mixed>  $export
     * @return array{ok: bool, checked: int, issues: list<string>}
     */
    public function verify(array $export, Tenant $tenant): array
    {
        app(TenantContext::class)->set($tenant);

        $issues = [];
        $company = Company::query()->where('name', $export['company']['name'])->first();
        if ($company === null) {
            return ['ok' => false, 'checked' => 0, 'issues' => ["Empresa '{$export['company']['name']}' não encontrada no tenant '{$tenant->slug}'."]];
        }

        // Contagens (export × importado).
        $expectedEmployees = count($export['employees'] ?? []);
        $actualEmployees = Employee::query()->where('company_id', $company->id)->count();
        if ($expectedEmployees !== $actualEmployees) {
            $issues[] = "Colaboradores: export {$expectedEmployees} × importado {$actualEmployees}.";
        }

        $checked = 0;
        foreach ($export['periods'] ?? [] as $period) {
            $importedPeriod = PayrollPeriod::query()
                ->where('company_id', $company->id)
                ->where('competency', $period['competency'])
                ->first();

            if ($importedPeriod === null) {
                $issues[] = "Competência {$period['competency']} não foi importada.";

                continue;
            }

            if (($period['status'] ?? null) !== $importedPeriod->status) {
                $issues[] = "Competência {$period['competency']}: status export '{$period['status']}' × importado '{$importedPeriod->status}'.";
            }

            foreach ($period['payrolls'] ?? [] as $p) {
                $checked++;
                $payroll = Payroll::query()
                    ->where('period_id', $importedPeriod->id)
                    ->where('kind', $p['kind'] ?? Payroll::KIND_PAYSLIP)
                    ->whereHas('employee', fn ($q) => $q->where('registration_number', $p['registration_number']))
                    ->first();

                if ($payroll === null) {
                    $issues[] = "Competência {$period['competency']}: folha de {$p['registration_number']} não importada.";

                    continue;
                }

                foreach (['gross_cents', 'net_cents'] as $field) {
                    if ((int) $p[$field] !== (int) $payroll->{$field}) {
                        $issues[] = "Competência {$period['competency']} / {$p['registration_number']}: {$field} "
                            ."export {$p[$field]} × importado {$payroll->{$field}}.";
                    }
                }
            }
        }

        return ['ok' => $issues === [], 'checked' => $checked, 'issues' => $issues];
    }
}
