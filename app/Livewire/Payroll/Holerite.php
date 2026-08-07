<?php

declare(strict_types=1);

namespace App\Livewire\Payroll;

use App\Models\Payroll;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Holerite: uma folha (payroll) com seus itens (proventos/descontos) e
 * encargos. Serve a folha mensal e as especiais (mesmo `kind`).
 */
#[Layout('layouts.app')]
class Holerite extends Component
{
    public Payroll $payroll;

    public function mount(Payroll $payroll): void
    {
        $this->authorize('payroll:read');
        $this->payroll = $payroll->load(['items', 'charges', 'employee', 'period']);
    }

    public function render()
    {
        return view('livewire.payroll.holerite');
    }
}
