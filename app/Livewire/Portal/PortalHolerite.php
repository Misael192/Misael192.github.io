<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Models\Payroll;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Holerite no portal do colaborador: só o DONO da folha pode ver — qualquer
 * tentativa de acessar a folha de outro colaborador é 403. Reaproveita a view
 * de holerite (itens/encargos), sem exigir a permissão de DP (payroll:read).
 */
#[Layout('layouts.app')]
class PortalHolerite extends Component
{
    public Payroll $payroll;

    public function mount(Payroll $payroll): void
    {
        $employee = auth()->user()->employee;
        abort_unless($employee !== null && $payroll->employee_id === $employee->id, 403);
        $this->payroll = $payroll->load(['items', 'charges', 'employee', 'period']);
    }

    public function render()
    {
        return view('livewire.portal.holerite');
    }
}
