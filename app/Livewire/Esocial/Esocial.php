<?php

declare(strict_types=1);

namespace App\Livewire\Esocial;

use App\Models\Company;
use App\Models\EsocialEvent;
use App\Services\Esocial\EsocialService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * eSocial pela UI: gera S-2200 (admissões) e S-1200 (remuneração da folha
 * fechada), lista os eventos e mostra o XML gerado. RBAC payroll:manage.
 * A transmissão ao webservice (certificado A1) é a etapa seguinte.
 */
#[Layout('layouts.app')]
class Esocial extends Component
{
    public string $companyId = '';

    public string $competency = '';

    public string $selectedEventId = '';

    public string $flash = '';

    public function mount(): void
    {
        $this->companyId = (string) Company::query()->value('id');
        $this->competency = now()->subMonthNoOverflow()->format('Y-m');
    }

    public function gerarAdmissoes(EsocialService $service): void
    {
        $this->authorize('payroll:manage');
        [, $message] = $service->generateAdmissions($this->company(), auth()->user());
        $this->flash = $message;
    }

    public function gerarRemuneracao(EsocialService $service): void
    {
        $this->authorize('payroll:manage');
        $this->validate(['competency' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/']]);

        [, $message] = $service->generateRemuneration($this->company(), $this->competency, auth()->user());
        $this->flash = $message;
    }

    public function verXml(string $eventId): void
    {
        $this->selectedEventId = $eventId;
    }

    private function company(): Company
    {
        return Company::query()->findOrFail($this->companyId);
    }

    public function render()
    {
        $events = EsocialEvent::query()
            ->where('company_id', $this->companyId)
            ->with('employee')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $selected = $this->selectedEventId !== ''
            ? $events->firstWhere('id', $this->selectedEventId)
            : null;

        return view('livewire.esocial.esocial', [
            'events' => $events,
            'selected' => $selected,
        ]);
    }
}
