<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\SocialCharge;
use App\Models\Termination;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Folhas especiais — 13º salário, recibo de férias e rescisão (migração de
 * mvp/app/services/Payroll). Persistem na MESMA estrutura da folha mensal
 * (payrolls/payroll_items com `kind` próprio), então o holerite serve para
 * todas; a folha mensal só recalcula kind='payslip' e nunca toca nestas.
 */
class SpecialPayrollService
{
    public function __construct(
        private readonly TaxTableRepository $taxTables = new TaxTableRepository,
    ) {}

    // ── 13º salário ──────────────────────────────────────────────────────────

    /**
     * Calcula a parcela do 13º para toda a empresa.
     *
     * @return array{0: bool, 1: string}
     */
    public function thirteenth(Company $company, int $year, int $installment): array
    {
        $competency = $year.($installment === 1 ? '-11' : '-12');
        $reference = "{$year}-12-20";
        $tables = $this->taxTables->forCompetency($competency);
        $inss = $this->taxTables->buildInss($tables);
        $irrf = $this->taxTables->buildIrrf($tables);
        $fgts = $this->taxTables->buildFgts($tables);
        $thirteenth = new ThirteenthCalculator($inss, $irrf);

        return DB::transaction(function () use ($company, $year, $installment, $competency, $reference, $thirteenth, $fgts) {
            $period = $this->openPeriod($company, $competency);
            if ($period === null) {
                return [false, "Competência {$competency} está fechada — reabra antes."];
            }

            $count = 0;
            foreach ($this->payableEmployees($company) as $employee) {
                $contract = $this->activeContract($employee, $competency);
                if ($contract === null || $contract->salary_cents === null) {
                    continue;
                }
                $months = $thirteenth->months($employee->hired_at->toDateString(), $reference);
                if ($months < 1) {
                    continue;
                }
                $salary = (int) $contract->salary_cents;

                if ($installment === 1) {
                    $calc = $thirteenth->firstInstallment($salary, $months);
                    $items = [['code' => '1200', 'description' => "13º — Adiantamento 1ª parcela ({$months}/12)",
                        'reference' => $months, 'amount' => $calc['net'], 'type' => 'earning']];
                    $result = ['gross' => $calc['gross'], 'deductions' => 0, 'net' => $calc['net'],
                        'inss_base' => 0, 'irrf_base' => 0, 'fgts_base' => $calc['gross']];
                    $fgtsBaseGross = $calc['gross'];
                } else {
                    [$advance, $firstGross] = $this->firstInstallmentValues($company, $employee, $year);
                    $dependents = $this->irrfDependents($employee);
                    $calc = $thirteenth->secondInstallment($salary, $months, $advance, $dependents);
                    $items = [['code' => '1200', 'description' => "13º Salário integral ({$months}/12)",
                        'reference' => $months, 'amount' => $calc['gross'], 'type' => 'earning']];
                    if ($calc['inss'] > 0) {
                        $items[] = ['code' => '2000', 'description' => 'INSS sobre 13º', 'reference' => null, 'amount' => $calc['inss'], 'type' => 'deduction'];
                    }
                    if ($calc['irrf'] > 0) {
                        $items[] = ['code' => '2001', 'description' => 'IRRF sobre 13º', 'reference' => null, 'amount' => $calc['irrf'], 'type' => 'deduction'];
                    }
                    if ($advance > 0) {
                        $items[] = ['code' => '2006', 'description' => 'Adiantamento 13º (1ª parcela)', 'reference' => null, 'amount' => $advance, 'type' => 'deduction'];
                    }
                    $deductions = $calc['inss'] + $calc['irrf'] + $advance;
                    $result = ['gross' => $calc['gross'], 'deductions' => $deductions, 'net' => $calc['net'],
                        'inss_base' => $calc['gross'], 'irrf_base' => $calc['gross'], 'fgts_base' => $calc['gross']];
                    $fgtsBaseGross = $calc['gross'];
                }

                // FGTS: na 1ª deposita sobre o pago; na 2ª deposita a diferença (integral − 1ª)
                $result['fgts_rate'] = $fgts->rateFor($contract->type);
                $deposit = $fgts->calculate($fgtsBaseGross, $contract->type);
                if ($installment === 2) {
                    $deposit = max(0, $deposit - $fgts->calculate($firstGross, $contract->type));
                }
                $result['fgts_deposit'] = $deposit;
                $result['items'] = $items;

                $this->persist($period, $employee, "thirteenth_{$installment}", $result);
                $count++;
            }

            $label = $installment === 1 ? '1ª parcela (adiantamento)' : '2ª parcela (final)';

            return [true, "13º {$label} calculada para {$count} colaborador(es)."];
        });
    }

    /** @return array{0: int, 1: int} [net (adiantamento), gross] da 1ª parcela */
    private function firstInstallmentValues(Company $company, Employee $employee, int $year): array
    {
        $first = Payroll::query()
            ->where('employee_id', $employee->id)
            ->where('kind', 'thirteenth_1')
            ->whereHas('period', fn ($q) => $q->where('company_id', $company->id)->where('competency', "{$year}-11"))
            ->first();

        return [(int) ($first->net_cents ?? 0), (int) ($first->gross_cents ?? 0)];
    }

    // ── Recibo de férias ─────────────────────────────────────────────────────

    /**
     * Gera (ou reaproveita) o recibo de férias de um pedido aprovado.
     *
     * @return array{0: bool, 1: string, 2: ?Payroll}
     */
    public function vacationReceipt(VacationRequest $vacation): array
    {
        if ($vacation->status !== VacationRequest::STATUS_APPROVED) {
            return [false, 'Férias ainda não aprovadas.', null];
        }

        $employee = $vacation->employee;
        $competency = Carbon::parse($vacation->start_date)->format('Y-m');
        $contract = $this->activeContract($employee, $competency);
        if ($contract === null || $contract->salary_cents === null) {
            return [false, 'Colaborador sem contrato/salário vigente.', null];
        }

        // Já existe recibo? Reaproveita (imutável até excluir a folha).
        $existing = Payroll::query()
            ->where('source_type', $vacation->getMorphClass())
            ->where('source_id', $vacation->id)
            ->first();
        if ($existing !== null) {
            return [true, 'Recibo já gerado.', $existing];
        }

        $tables = $this->taxTables->forCompetency($competency);
        $calc = (new VacationCalculator($this->taxTables->buildInss($tables), $this->taxTables->buildIrrf($tables)))
            ->calculate((int) $contract->salary_cents, (int) $vacation->days, (int) $vacation->sell_days, $this->irrfDependents($employee));
        $fgts = $this->taxTables->buildFgts($tables);

        // Base tributável = gozo + 1/3 (abono é indenizatório).
        $taxable = $calc['items'][0]['amount'] + $calc['items'][1]['amount'];

        return DB::transaction(function () use ($vacation, $employee, $competency, $calc, $taxable, $fgts, $contract) {
            $period = $this->openPeriod($employee->company, $competency);
            if ($period === null) {
                return [false, "Competência {$competency} está fechada — reabra antes.", null];
            }

            $payroll = $this->persist($period, $employee, 'vacation', [
                'gross' => $calc['gross'], 'deductions' => $calc['inss'] + $calc['irrf'], 'net' => $calc['net'],
                'inss_base' => $taxable, 'irrf_base' => $taxable, 'fgts_base' => $taxable,
                'fgts_rate' => $fgts->rateFor($contract->type),
                'fgts_deposit' => $fgts->calculate($taxable, $contract->type),
                'items' => $calc['items'],
            ], $vacation);

            return [true, 'Recibo de férias gerado.', $payroll];
        });
    }

    // ── Rescisão ─────────────────────────────────────────────────────────────

    /** Calcula as verbas rescisórias (sem persistir). */
    public function simulateTermination(
        Employee $employee,
        string $date,
        string $type,
        string $notice,
        int $fgtsBalanceCents,
        int $pendingVacationDays,
    ): array {
        $contract = $this->activeContract($employee, substr($date, 0, 7));
        $tables = $this->taxTables->forCompetency(substr($date, 0, 7));
        $inss = $this->taxTables->buildInss($tables);
        $irrf = $this->taxTables->buildIrrf($tables);
        $thirteenth = new ThirteenthCalculator($inss, $irrf);

        return (new TerminationCalculator($inss, $irrf, $thirteenth))->calculate(
            (int) $contract->salary_cents, $employee->hired_at->toDateString(), $date, $type, $notice,
            $fgtsBalanceCents, $pendingVacationDays, $this->irrfDependents($employee),
        );
    }

    /**
     * Efetiva a rescisão: termo + folha + desligamento do colaborador.
     *
     * @return array{0: bool, 1: string, 2: ?Payroll}
     */
    public function terminate(
        Employee $employee,
        string $date,
        string $type,
        string $notice,
        int $fgtsBalanceCents,
        int $pendingVacationDays,
        ?string $reason,
        User $user,
    ): array {
        $calc = $this->simulateTermination($employee, $date, $type, $notice, $fgtsBalanceCents, $pendingVacationDays);
        $competency = substr($date, 0, 7);

        return DB::transaction(function () use ($employee, $date, $type, $notice, $reason, $user, $calc, $competency) {
            $period = $this->openPeriod($employee->company, $competency);
            if ($period === null) {
                return [false, "Competência {$competency} está fechada — reabra antes.", null];
            }

            $termination = Termination::query()->create([
                'employee_id' => $employee->id,
                'termination_date' => $date,
                'type' => $type,
                'notice' => $notice,
                'reason' => $reason ?: null,
                'created_by_id' => $user->id,
            ]);

            // Itens → rubricas: INSS/IRRF nas próprias; 13º na 1200; demais na 1400.
            $items = array_map(fn (array $i) => [
                'code' => match (true) {
                    str_starts_with($i['description'], 'INSS') => '2000',
                    str_starts_with($i['description'], 'IRRF') => '2001',
                    str_starts_with($i['description'], '13º') => '1200',
                    default => '1400',
                },
                'description' => $i['description'], 'reference' => null,
                'amount' => $i['amount'], 'type' => $i['type'],
            ], $calc['items']);

            // Base tributável (saldo de salário) para registro.
            $taxable = (int) array_sum(array_map(
                fn (array $i) => $i['type'] === 'earning' && ($i['taxable'] ?? false) ? $i['amount'] : 0,
                $calc['items'],
            ));

            $payroll = $this->persist($period, $employee, 'termination', [
                'gross' => $calc['gross'], 'deductions' => $calc['deductions'], 'net' => $calc['net'],
                'inss_base' => $taxable, 'irrf_base' => $taxable, 'fgts_base' => $taxable,
                'items' => $items,
            ], $termination);

            $employee->update(['status' => Employee::STATUS_TERMINATED, 'terminated_at' => $date]);

            return [true, 'Rescisão efetivada — colaborador desligado e termo gerado.', $payroll];
        });
    }

    // ── Infra compartilhada ──────────────────────────────────────────────────

    /** Garante o período da competência; null se estiver fechado. */
    private function openPeriod(Company $company, string $competency): ?PayrollPeriod
    {
        $period = PayrollPeriod::query()->firstOrCreate([
            'company_id' => $company->id,
            'competency' => $competency,
        ]);

        return $period->isClosed() ? null : $period;
    }

    /** @return Collection<int, Employee> */
    private function payableEmployees(Company $company)
    {
        return Employee::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_VACATION])
            ->get();
    }

    private function activeContract(Employee $employee, string $competency): ?EmploymentContract
    {
        $start = Carbon::parse($competency.'-01');
        $end = $start->copy()->endOfMonth();

        return EmploymentContract::query()
            ->where('employee_id', $employee->id)
            ->whereDate('start_date', '<=', $end)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $start))
            ->orderByDesc('start_date')
            ->first();
    }

    private function irrfDependents(Employee $employee): int
    {
        return $employee->dependents()->where('counts_for_irrf', true)->count();
    }

    /** Substitui (se existir) a folha kind/employee do período e insere a nova. */
    private function persist(PayrollPeriod $period, Employee $employee, string $kind, array $result, ?object $source = null): Payroll
    {
        Payroll::query()
            ->where('period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->where('kind', $kind)
            ->get()
            ->each->delete();

        $payroll = Payroll::query()->create([
            'period_id' => $period->id,
            'employee_id' => $employee->id,
            'kind' => $kind,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->id,
            'gross_cents' => $result['gross'],
            'deductions_cents' => $result['deductions'],
            'net_cents' => $result['net'],
            'inss_base_cents' => $result['inss_base'],
            'irrf_base_cents' => $result['irrf_base'],
            'fgts_base_cents' => $result['fgts_base'],
            'calculated_at' => now(),
        ]);

        foreach ($result['items'] as $item) {
            PayrollItem::query()->create([
                'payroll_id' => $payroll->id,
                'rubric_code' => $item['code'],
                'description' => $item['description'],
                'reference' => $item['reference'] ?? null,
                'amount_cents' => $item['amount'],
                'type' => $item['type'],
            ]);
        }

        if (($result['fgts_deposit'] ?? 0) > 0) {
            SocialCharge::query()->create([
                'payroll_id' => $payroll->id,
                'type' => 'fgts',
                'base_cents' => $result['fgts_base'],
                'rate' => $result['fgts_rate'],
                'amount_cents' => $result['fgts_deposit'],
            ]);
        }

        return $payroll;
    }
}
