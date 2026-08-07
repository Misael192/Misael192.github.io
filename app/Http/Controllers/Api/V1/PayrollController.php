<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Payroll;
use App\Services\Payroll\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Folha mensal: fechamento de competência (calcular → fechar → reabrir) e
 * holerite. A conta é do PayrollService/engine — o controller só valida a
 * entrada, delega e traduz o resultado em HTTP (409 quando a competência
 * está fechada e imutável).
 */
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $service) {}

    public function calculate(Request $request, Company $company): JsonResponse
    {
        $competency = $this->validateCompetency($request);
        [$ok, $message] = $this->service->calculatePeriod($company, $competency);

        return response()->json(['message' => $message], $ok ? 200 : 409);
    }

    public function close(Request $request, Company $company): JsonResponse
    {
        $competency = $this->validateCompetency($request);
        $ok = $this->service->closePeriod($company, $competency, $request->user());

        abort_unless($ok, 409, 'Competência não está calculada para fechar.');

        return response()->json(['message' => 'Competência fechada.']);
    }

    public function reopen(Request $request, Company $company): JsonResponse
    {
        $competency = $this->validateCompetency($request);
        $ok = $this->service->reopenPeriod($company, $competency);

        abort_unless($ok, 409, 'Competência não está fechada.');

        return response()->json(['message' => 'Competência reaberta.']);
    }

    /** Holerite: folha do colaborador com itens e encargos. */
    public function show(Payroll $payroll): JsonResponse
    {
        return response()->json($payroll->load(['items', 'charges', 'employee', 'period']));
    }

    private function validateCompetency(Request $request): string
    {
        return $request->validate([
            'competency' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ])['competency'];
    }
}
