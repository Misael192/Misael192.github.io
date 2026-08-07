<?php

declare(strict_types=1);

namespace App\Livewire\People;

use App\Models\Company;
use App\Models\Employee;
use App\Services\People\EmployeeService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cadastro de colaboradores pela UI: lista os colaboradores da empresa e
 * cadastra um novo (colaborador + contrato vigente) pelo EmployeeService.
 * Alimenta as telas de folha com dados reais. RBAC people:employees:*.
 */
#[Layout('layouts.app')]
class Colaboradores extends Component
{
    public string $companyId = '';

    public string $fullName = '';

    public string $registrationNumber = '';

    public string $hiredAt = '';

    public string $type = 'clt';

    public string $salary = '';

    public string $weeklyHours = '';

    public string $initialStatus = 'active';

    public string $flash = '';

    public function mount(): void
    {
        $this->companyId = (string) Company::query()->value('id');
        $this->hiredAt = now()->format('Y-m-d');
    }

    public function cadastrar(EmployeeService $service): void
    {
        $this->authorize('employees:create');

        $data = $this->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'registrationNumber' => [
                'required', 'string', 'max:60',
                Rule::unique('employees', 'registration_number')
                    ->where(fn ($q) => $q->where('company_id', $this->companyId)),
            ],
            'hiredAt' => ['required', 'date'],
            'type' => ['required', 'in:clt,pj,estagio,temporario'],
            'salary' => ['required', 'numeric', 'min:0'],
            'weeklyHours' => ['nullable', 'integer', 'min:1', 'max:60'],
            'initialStatus' => ['required', 'in:active,admission'],
        ]);

        $service->register($this->company(), [
            'full_name' => $data['fullName'],
            'registration_number' => $data['registrationNumber'],
            'hired_at' => $data['hiredAt'],
            'type' => $data['type'],
            'salary_cents' => (int) round(((float) $data['salary']) * 100),
            'weekly_hours' => $data['weeklyHours'] !== '' ? (int) $data['weeklyHours'] : null,
            'status' => $data['initialStatus'],
        ]);

        $this->reset(['fullName', 'registrationNumber', 'salary', 'weeklyHours']);
        $this->type = 'clt';
        $this->initialStatus = 'active';
        $this->hiredAt = now()->format('Y-m-d');
        $this->flash = $data['initialStatus'] === 'admission'
            ? 'Colaborador cadastrado em admissão — checklist criado.'
            : 'Colaborador cadastrado.';
    }

    public function ativar(string $employeeId, EmployeeService $service): void
    {
        $this->authorize('employees:update');
        $employee = Employee::query()->where('company_id', $this->companyId)->findOrFail($employeeId);
        $service->activate($employee);
        $this->flash = 'Colaborador ativado.';
    }

    private function company(): Company
    {
        return Company::query()->findOrFail($this->companyId);
    }

    public function render()
    {
        $employees = Employee::query()
            ->where('company_id', $this->companyId)
            ->with(['contracts' => fn ($q) => $q->orderByDesc('start_date')])
            ->orderBy('full_name')
            ->get();

        return view('livewire.people.colaboradores', [
            'employees' => $employees,
            'statusLabels' => [
                Employee::STATUS_ADMISSION => 'Admissão',
                Employee::STATUS_ACTIVE => 'Ativo',
                Employee::STATUS_ON_LEAVE => 'Afastado',
                Employee::STATUS_VACATION => 'Férias',
                Employee::STATUS_TERMINATED => 'Desligado',
            ],
        ]);
    }
}
