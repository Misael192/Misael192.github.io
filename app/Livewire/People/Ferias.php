<?php

declare(strict_types=1);

namespace App\Livewire\People;

use App\Models\Company;
use App\Models\Employee;
use App\Models\VacationRequest;
use App\Services\People\VacationService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Férias pela UI: solicita (dias + abono, guardas CLT) e decide (aprovar/
 * recusar) pelo VacationService. Aprovado, o pedido vira recibo na tela de
 * folhas especiais. RBAC people:vacations:request/approve.
 */
#[Layout('layouts.app')]
class Ferias extends Component
{
    public string $companyId = '';

    public string $employeeId = '';

    public string $startDate = '';

    public int $days = 30;

    public int $sellDays = 0;

    public string $flash = '';

    public function mount(): void
    {
        $this->companyId = (string) Company::query()->value('id');
        $this->startDate = now()->addWeek()->format('Y-m-d');
    }

    public function solicitar(VacationService $service): void
    {
        $this->authorize('vacations:request');

        $data = $this->validate([
            'employeeId' => ['required', 'string'],
            'startDate' => ['required', 'date'],
            'days' => ['required', 'integer', 'min:1', 'max:30'],
            // Abono pecuniário: no máximo 10 dias e até 1/3 do período (CLT art. 143).
            'sellDays' => ['required', 'integer', 'min:0', 'max:10', 'lte:days'],
        ]);

        $service->request($this->employee($data['employeeId']), $data['startDate'], $data['days'], $data['sellDays']);

        $this->reset(['employeeId', 'sellDays']);
        $this->days = 30;
        $this->flash = 'Solicitação de férias registrada.';
    }

    public function aprovar(string $vacationId, VacationService $service): void
    {
        $this->authorize('vacations:approve');
        [, $message] = $service->approve($this->vacation($vacationId), auth()->user());
        $this->flash = $message;
    }

    public function recusar(string $vacationId, VacationService $service): void
    {
        $this->authorize('vacations:approve');
        [, $message] = $service->reject($this->vacation($vacationId), auth()->user());
        $this->flash = $message;
    }

    private function employee(string $id): Employee
    {
        return Employee::query()->where('company_id', $this->companyId)->findOrFail($id);
    }

    private function vacation(string $id): VacationRequest
    {
        return VacationRequest::query()
            ->whereHas('employee', fn ($q) => $q->where('company_id', $this->companyId))
            ->findOrFail($id);
    }

    public function render()
    {
        $employees = Employee::query()
            ->where('company_id', $this->companyId)
            ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_VACATION])
            ->orderBy('full_name')
            ->get();

        $vacations = VacationRequest::query()
            ->whereHas('employee', fn ($q) => $q->where('company_id', $this->companyId))
            ->with('employee')
            ->orderByDesc('start_date')
            ->get();

        return view('livewire.people.ferias', [
            'employees' => $employees,
            'vacations' => $vacations,
            'statusLabels' => [
                VacationRequest::STATUS_REQUESTED => 'Solicitada',
                VacationRequest::STATUS_APPROVED => 'Aprovada',
                VacationRequest::STATUS_REJECTED => 'Recusada',
            ],
        ]);
    }
}
