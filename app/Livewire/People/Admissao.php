<?php

declare(strict_types=1);

namespace App\Livewire\People;

use App\Models\AdmissionTask;
use App\Models\Company;
use App\Models\Employee;
use App\Services\People\AdmissionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admissão digital pela UI: lista os colaboradores em admissão com seu
 * checklist clicável; concluir todos os itens ativa o colaborador (sai da
 * lista). RBAC people:employees:update.
 */
#[Layout('layouts.app')]
class Admissao extends Component
{
    public string $companyId = '';

    public string $flash = '';

    public function mount(): void
    {
        $this->companyId = (string) Company::query()->value('id');
    }

    public function alternarItem(string $taskId, AdmissionService $service): void
    {
        $this->authorize('employees:update');

        $task = AdmissionTask::query()
            ->whereHas('employee', fn ($q) => $q->where('company_id', $this->companyId))
            ->findOrFail($taskId);

        $activated = $service->toggle($task);
        $this->flash = $activated
            ? 'Checklist completo — colaborador ativado! 🎉'
            : '';
    }

    public function render()
    {
        $employees = Employee::query()
            ->where('company_id', $this->companyId)
            ->where('status', Employee::STATUS_ADMISSION)
            ->with(['admissionTasks' => fn ($q) => $q->orderBy('position')])
            ->orderBy('full_name')
            ->get();

        return view('livewire.people.admissao', ['employees' => $employees]);
    }
}
