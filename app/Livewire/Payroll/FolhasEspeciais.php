<?php

declare(strict_types=1);

namespace App\Livewire\Payroll;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\VacationRequest;
use App\Services\Payroll\SpecialPayrollService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Folhas especiais pela UI: 13º salário (1ª/2ª parcela), recibo de férias de
 * pedidos aprovados e rescisão (simular → efetivar). "Nenhuma tela faz conta" —
 * o componente só orquestra o SpecialPayrollService; a regra vive no serviço.
 */
#[Layout('layouts.app')]
class FolhasEspeciais extends Component
{
    public string $companyId = '';

    // 13º salário
    public int $decimoAno = 0;

    public int $decimoParcela = 1;

    // Rescisão
    public string $rescisaoEmployeeId = '';

    public string $rescisaoData = '';

    public string $rescisaoTipo = 'sem_justa_causa';

    public string $rescisaoAviso = 'indenizado';

    public string $rescisaoSaldoFgts = '0';

    public int $rescisaoDiasFerias = 0;

    public string $rescisaoMotivo = '';

    /** @var array<string, mixed>|null resultado da simulação (não persistido) */
    public ?array $simulacao = null;

    public string $flash = '';

    public function mount(): void
    {
        $this->companyId = (string) Company::query()->value('id');
        $this->decimoAno = (int) now()->format('Y');
        $this->rescisaoData = now()->format('Y-m-d');
    }

    public function calcularDecimo(SpecialPayrollService $service): void
    {
        $this->authorize('payroll:manage');
        $this->validate([
            'decimoAno' => ['required', 'integer', 'min:2000', 'max:2100'],
            'decimoParcela' => ['required', 'integer', 'in:1,2'],
        ]);

        [, $message] = $service->thirteenth($this->company(), $this->decimoAno, $this->decimoParcela);
        $this->flash = $message;
    }

    public function gerarRecibo(string $vacationId, SpecialPayrollService $service): void
    {
        $this->authorize('payroll:manage');
        $vacation = VacationRequest::query()->findOrFail($vacationId);

        [, $message] = $service->vacationReceipt($vacation);
        $this->flash = $message;
    }

    public function simular(SpecialPayrollService $service): void
    {
        $this->authorize('payroll:manage');
        $this->validateRescisao();

        $this->simulacao = $service->simulateTermination(
            $this->employee(),
            $this->rescisaoData,
            $this->rescisaoTipo,
            $this->rescisaoAviso,
            $this->saldoFgtsCents(),
            $this->rescisaoDiasFerias,
        );
        $this->flash = 'Simulação calculada — confira as verbas antes de efetivar.';
    }

    public function efetivar(SpecialPayrollService $service): void
    {
        $this->authorize('payroll:manage');
        $this->validateRescisao();

        [, $message] = $service->terminate(
            $this->employee(),
            $this->rescisaoData,
            $this->rescisaoTipo,
            $this->rescisaoAviso,
            $this->saldoFgtsCents(),
            $this->rescisaoDiasFerias,
            $this->rescisaoMotivo ?: null,
            auth()->user(),
        );

        $this->simulacao = null;
        $this->reset(['rescisaoEmployeeId', 'rescisaoMotivo']);
        $this->flash = $message;
    }

    private function validateRescisao(): void
    {
        $this->validate([
            'rescisaoEmployeeId' => ['required', 'string'],
            'rescisaoData' => ['required', 'date'],
            'rescisaoTipo' => ['required', 'in:sem_justa_causa,justa_causa,pedido,acordo'],
            'rescisaoAviso' => ['required', 'in:trabalhado,indenizado,dispensado'],
            'rescisaoSaldoFgts' => ['required', 'numeric', 'min:0'],
            'rescisaoDiasFerias' => ['required', 'integer', 'min:0'],
        ]);
    }

    /** Converte reais (entrada) para centavos na fronteira — não é conta de folha. */
    private function saldoFgtsCents(): int
    {
        return (int) round(((float) $this->rescisaoSaldoFgts) * 100);
    }

    private function company(): Company
    {
        return Company::query()->findOrFail($this->companyId);
    }

    private function employee(): Employee
    {
        return Employee::query()->findOrFail($this->rescisaoEmployeeId);
    }

    public function render()
    {
        $employees = Employee::query()
            ->where('company_id', $this->companyId)
            ->where('status', Employee::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->get();

        $vacations = VacationRequest::query()
            ->where('status', VacationRequest::STATUS_APPROVED)
            ->whereHas('employee', fn ($q) => $q->where('company_id', $this->companyId))
            ->with('employee')
            ->orderBy('start_date')
            ->get();

        // Pedidos que já viraram recibo (folha kind='vacation' com a origem).
        $comRecibo = Payroll::query()
            ->where('source_type', (new VacationRequest)->getMorphClass())
            ->whereIn('source_id', $vacations->pluck('id'))
            ->pluck('id', 'source_id');

        return view('livewire.payroll.folhas-especiais', [
            'employees' => $employees,
            'vacations' => $vacations,
            'comRecibo' => $comRecibo,
        ]);
    }
}
