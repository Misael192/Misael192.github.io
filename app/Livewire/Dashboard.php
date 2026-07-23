<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\VacationRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Painel autenticado (shell da plataforma web). KPIs reais do tenant atual —
 * o SetTenantFromSession já fixou o escopo, então as contagens são isoladas.
 */
#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function logout()
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return $this->redirect('/entrar', navigate: true);
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'employeeCount' => Employee::query()->where('status', Employee::STATUS_ACTIVE)->count(),
            'openPeriods' => PayrollPeriod::query()->where('status', '!=', PayrollPeriod::STATUS_CLOSED)->count(),
            'pendingVacations' => VacationRequest::query()->where('status', VacationRequest::STATUS_REQUESTED)->count(),
        ]);
    }
}
