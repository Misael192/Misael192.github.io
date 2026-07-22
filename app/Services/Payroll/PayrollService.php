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
use App\Models\TimeBankEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fechamento da folha MENSAL (migração de mvp/app/services/Payroll):
 *   competência → salário do contrato vigente → banco de horas → eventos
 *   manuais → PayrollEngine → persistência → fechamento.
 *
 * A ENGINE calcula; este serviço só monta contexto e persiste. Fechado é
 * imutável (reabra antes de recalcular). Só a folha kind='payslip' é
 * recalculada — as especiais (13º/férias/rescisão) do período ficam
 * intactas quando forem portadas.
 */
class PayrollService
{
    public function __construct(
        private readonly TaxTableRepository $taxTables = new TaxTableRepository,
    ) {}

    /**
     * Calcula (ou recalcula) a folha mensal da empresa na competência.
     *
     * @return array{0: bool, 1: string}
     */
    public function calculatePeriod(Company $company, string $competency): array
    {
        $engine = $this->taxTables->engineFor($competency);

        return DB::transaction(function () use ($company, $competency, $engine) {
            $period = PayrollPeriod::query()->firstOrCreate([
                'company_id' => $company->id,
                'competency' => $competency,
            ]);

            if ($period->isClosed()) {
                return [false, 'Competência já fechada — reabra antes de recalcular.'];
            }

            // Recalcular substitui apenas a folha mensal (itens/encargos via cascade).
            Payroll::query()
                ->where('period_id', $period->id)
                ->where('kind', Payroll::KIND_PAYSLIP)
                ->get()
                ->each->delete();

            $employees = Employee::query()
                ->where('company_id', $company->id)
                ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_VACATION])
                ->get();

            $count = 0;
            foreach ($employees as $employee) {
                $contract = $this->activeContract($employee, $competency);
                if ($contract === null || $contract->salary_cents === null) {
                    continue;
                }

                $result = $engine->calculate(
                    ['salary_cents' => (int) $contract->salary_cents, 'contract_type' => $contract->type],
                    $this->buildContext($employee, $competency),
                );
                $this->persist($period, $employee, $result);
                $count++;
            }

            $period->update([
                'status' => PayrollPeriod::STATUS_CALCULATED,
                'calculated_at' => now(),
            ]);

            return [true, "Folha calculada para {$count} colaborador(es)."];
        });
    }

    /** Fecha a competência (só se estiver calculada). */
    public function closePeriod(Company $company, string $competency, User $user): bool
    {
        $affected = PayrollPeriod::query()
            ->where('company_id', $company->id)
            ->where('competency', $competency)
            ->where('status', PayrollPeriod::STATUS_CALCULATED)
            ->update([
                'status' => PayrollPeriod::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by_id' => $user->id,
            ]);

        return $affected > 0;
    }

    /** Reabre a competência fechada (volta para calculada). */
    public function reopenPeriod(Company $company, string $competency): bool
    {
        $affected = PayrollPeriod::query()
            ->where('company_id', $company->id)
            ->where('competency', $competency)
            ->where('status', PayrollPeriod::STATUS_CLOSED)
            ->update([
                'status' => PayrollPeriod::STATUS_CALCULATED,
                'closed_at' => null,
                'closed_by_id' => null,
            ]);

        return $affected > 0;
    }

    /** Contrato vigente na competência (o mais recente que a cobre). */
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

    /**
     * Contexto da competência: por ora, banco de horas (crédito → HE 50%).
     * Faltas, dependentes e benefícios entram quando suas tabelas forem
     * portadas do MVP.
     */
    private function buildContext(Employee $employee, string $competency): array
    {
        $start = Carbon::parse($competency.'-01');
        $end = $start->copy()->endOfMonth();

        $overtimeMinutes = (int) TimeBankEntry::query()
            ->where('employee_id', $employee->id)
            ->where('minutes', '>', 0)
            ->whereBetween('reference_date', [$start->toDateString(), $end->toDateString()])
            ->sum('minutes');

        $events = [];
        if ($overtimeMinutes > 0) {
            $events[] = [
                'rubric_code' => '1001',
                'reference' => round($overtimeMinutes / 60, 2),
                'amount_cents' => null,
            ];
        }

        return ['events' => $events];
    }

    private function persist(PayrollPeriod $period, Employee $employee, array $result): void
    {
        $payroll = Payroll::query()->create([
            'period_id' => $period->id,
            'employee_id' => $employee->id,
            'kind' => Payroll::KIND_PAYSLIP,
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
                'reference' => $item['reference'],
                'amount_cents' => $item['amount'],
                'type' => $item['type'],
            ]);
        }

        foreach ($result['charges'] as $charge) {
            SocialCharge::query()->create([
                'payroll_id' => $payroll->id,
                'type' => $charge['type'],
                'base_cents' => $charge['base'],
                'rate' => $charge['rate'],
                'amount_cents' => $charge['amount'],
            ]);
        }
    }
}
