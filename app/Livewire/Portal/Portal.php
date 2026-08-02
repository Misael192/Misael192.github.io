<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\TimeEntry;
use App\Models\VacationRequest;
use App\Services\People\TimeClockService;
use App\Services\People\VacationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Portal do colaborador: o usuário com vínculo (users.employee_id) vê os
 * PRÓPRIOS holerites, bate ponto e pede férias (self-service). Acesso restrito
 * ao próprio colaborador — sem vínculo é 403.
 */
#[Layout('layouts.app')]
class Portal extends Component
{
    public string $employeeId = '';

    public string $startDate = '';

    public int $days = 30;

    public string $flash = '';

    public function mount(): void
    {
        $employee = auth()->user()->employee;
        abort_unless($employee !== null, 403, 'Acesso restrito a colaboradores.');
        $this->employeeId = (string) $employee->id;
        $this->startDate = now()->addWeek()->format('Y-m-d');
    }

    public function baterPonto(TimeClockService $service): void
    {
        $this->authorize('time-entries:register');
        $entry = $service->punch($this->employee());
        $this->flash = ($entry->type === 'clock_in' ? 'Entrada' : 'Saída').' registrada às '.$entry->recorded_at->format('H:i').'.';
    }

    public function solicitarFerias(VacationService $service): void
    {
        $this->authorize('vacations:request');
        $data = $this->validate([
            'startDate' => ['required', 'date'],
            'days' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $service->request($this->employee(), $data['startDate'], $data['days'], 0);
        $this->flash = 'Solicitação de férias enviada para aprovação.';
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return $this->redirect('/entrar', navigate: true);
    }

    private function employee(): Employee
    {
        // Sempre o próprio colaborador — nunca aceita id de fora.
        return Employee::query()->findOrFail($this->employeeId);
    }

    public function render()
    {
        $payrolls = Payroll::query()
            ->where('employee_id', $this->employeeId)
            ->with('period')
            ->get()
            ->sortByDesc(fn ($p) => $p->period?->competency)
            ->values();

        $vacations = VacationRequest::query()
            ->where('employee_id', $this->employeeId)
            ->orderByDesc('start_date')
            ->get();

        $todayPunches = TimeEntry::query()
            ->where('employee_id', $this->employeeId)
            ->whereDate('recorded_at', now()->toDateString())
            ->orderBy('recorded_at')
            ->get();

        return view('livewire.portal.portal', [
            'employee' => $this->employee(),
            'payrolls' => $payrolls,
            'vacations' => $vacations,
            'todayPunches' => $todayPunches,
            'vacationLabels' => [
                VacationRequest::STATUS_REQUESTED => 'Solicitada',
                VacationRequest::STATUS_APPROVED => 'Aprovada',
                VacationRequest::STATUS_REJECTED => 'Recusada',
            ],
        ]);
    }
}
