<?php

declare(strict_types=1);

namespace App\Livewire\Payroll;

use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Fechamento da folha mensal pela UI: escolhe a competência, dispara o
 * cálculo (PayrollService/engine) e fecha/reabre. "Nenhuma tela faz conta" —
 * o componente só orquestra e mostra; a regra vive no serviço.
 */
#[Layout('layouts.app')]
class Folha extends Component
{
    public string $companyId = '';

    public string $competency = '';

    public string $flash = '';

    public function mount(): void
    {
        $this->companyId = (string) Company::query()->value('id');
        $this->competency = now()->format('Y-m');
    }

    public function calcular(PayrollService $service): void
    {
        $this->authorize('payroll:manage');
        $this->validateCompetency();

        [$ok, $message] = $service->calculatePeriod($this->company(), $this->competency);
        $this->flash = $message;
    }

    public function fechar(PayrollService $service): void
    {
        $this->authorize('payroll:manage');
        $ok = $service->closePeriod($this->company(), $this->competency, auth()->user());
        $this->flash = $ok ? 'Competência fechada.' : 'Só é possível fechar uma competência já calculada.';
    }

    public function reabrir(PayrollService $service): void
    {
        $this->authorize('payroll:manage');
        $ok = $service->reopenPeriod($this->company(), $this->competency);
        $this->flash = $ok ? 'Competência reaberta.' : 'Só é possível reabrir uma competência fechada.';
    }

    private function company(): Company
    {
        return Company::query()->findOrFail($this->companyId);
    }

    private function validateCompetency(): void
    {
        $this->validate(['competency' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/']]);
    }

    public function render()
    {
        $period = PayrollPeriod::query()
            ->where('company_id', $this->companyId)
            ->where('competency', $this->competency)
            ->first();

        $payrolls = $period
            ? Payroll::query()
                ->where('period_id', $period->id)
                ->where('kind', Payroll::KIND_PAYSLIP)
                ->with('employee')
                ->get()
            : collect();

        return view('livewire.payroll.folha', [
            'period' => $period,
            'payrolls' => $payrolls,
        ]);
    }
}
