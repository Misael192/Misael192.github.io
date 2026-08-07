<?php

declare(strict_types=1);

namespace App\Livewire\People;

use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeBankEntry;
use App\Services\People\TimeBankService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ponto / banco de horas pela UI: lança crédito (hora-extra) ou débito
 * (compensação) por colaborador. O crédito no mês alimenta a folha mensal
 * (HE 50%). RBAC people:time-entries:register. Horas → minutos na fronteira.
 */
#[Layout('layouts.app')]
class Ponto extends Component
{
    public string $companyId = '';

    public string $employeeId = '';

    public string $referenceDate = '';

    public string $hours = '';

    public string $kind = 'credito';

    public string $flash = '';

    public function mount(): void
    {
        $this->companyId = (string) Company::query()->value('id');
        $this->referenceDate = now()->format('Y-m-d');
    }

    public function lancar(TimeBankService $service): void
    {
        $this->authorize('time-entries:register');

        $data = $this->validate([
            'employeeId' => ['required', 'string'],
            'referenceDate' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.01', 'max:400'],
            'kind' => ['required', 'in:credito,debito'],
        ]);

        $minutes = (int) round(((float) $data['hours']) * 60);
        if ($data['kind'] === 'debito') {
            $minutes = -$minutes;
        }
        $reason = $data['kind'] === 'credito' ? 'overtime' : 'compensation';

        $service->register($this->employee($data['employeeId']), $data['referenceDate'], $minutes, $reason);

        $this->reset(['employeeId', 'hours']);
        $this->kind = 'credito';
        $this->referenceDate = now()->format('Y-m-d');
        $this->flash = 'Lançamento registrado no banco de horas.';
    }

    private function employee(string $id): Employee
    {
        return Employee::query()->where('company_id', $this->companyId)->findOrFail($id);
    }

    public function render()
    {
        $employees = Employee::query()
            ->where('company_id', $this->companyId)
            ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_VACATION])
            ->orderBy('full_name')
            ->get();

        // Saldo (minutos) por colaborador, num único agregado.
        $balances = TimeBankEntry::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->selectRaw('employee_id, SUM(minutes) as total')
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        $entries = TimeBankEntry::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->orderByDesc('reference_date')
            ->limit(30)
            ->get();

        return view('livewire.people.ponto', [
            'employees' => $employees,
            'balances' => $balances,
            'entries' => $entries,
            'employeeNames' => $employees->pluck('full_name', 'id'),
        ]);
    }
}
