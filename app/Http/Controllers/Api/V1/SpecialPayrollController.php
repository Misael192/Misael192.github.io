<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\VacationRequest;
use App\Services\Payroll\SpecialPayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Folhas especiais: 13º salário, recibo de férias e rescisão. Mesma regra
 * de ouro — o SpecialPayrollService/engine calcula, aqui só validamos e
 * traduzimos para HTTP. Persistem na mesma estrutura da folha mensal.
 */
class SpecialPayrollController extends Controller
{
    public function __construct(private readonly SpecialPayrollService $service) {}

    public function thirteenth(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'installment' => ['required', 'integer', 'in:1,2'],
        ]);

        [$ok, $message] = $this->service->thirteenth($company, $data['year'], $data['installment']);

        return response()->json(['message' => $message], $ok ? 200 : 409);
    }

    public function vacationReceipt(VacationRequest $vacation): JsonResponse
    {
        [$ok, $message, $payroll] = $this->service->vacationReceipt($vacation);

        return response()->json(['message' => $message, 'payroll' => $payroll], $ok ? 200 : 409);
    }

    /** Simulação (não persiste) — verbas rescisórias por modalidade. */
    public function simulateTermination(Request $request, Employee $employee): JsonResponse
    {
        $data = $this->validateTermination($request);

        return response()->json($this->service->simulateTermination(
            $employee, $data['date'], $data['type'], $data['notice'],
            $data['fgts_balance_cents'], $data['pending_vacation_days'],
        ));
    }

    /** Efetiva: termo + folha + desligamento do colaborador. */
    public function terminate(Request $request, Employee $employee): JsonResponse
    {
        $data = $this->validateTermination($request, withReason: true);

        [$ok, $message, $payroll] = $this->service->terminate(
            $employee, $data['date'], $data['type'], $data['notice'],
            $data['fgts_balance_cents'], $data['pending_vacation_days'],
            $data['reason'] ?? null, $request->user(),
        );

        return response()->json(['message' => $message, 'payroll' => $payroll], $ok ? 201 : 409);
    }

    private function validateTermination(Request $request, bool $withReason = false): array
    {
        $rules = [
            'date' => ['required', 'date'],
            'type' => ['required', 'in:sem_justa_causa,justa_causa,pedido,acordo'],
            'notice' => ['required', 'in:trabalhado,indenizado,dispensado'],
            'fgts_balance_cents' => ['nullable', 'integer', 'min:0'],
            'pending_vacation_days' => ['nullable', 'integer', 'min:0'],
        ];
        if ($withReason) {
            $rules['reason'] = ['nullable', 'string', 'max:500'];
        }

        $data = $request->validate($rules);
        $data['fgts_balance_cents'] ??= 0;
        $data['pending_vacation_days'] ??= 0;

        return $data;
    }
}
