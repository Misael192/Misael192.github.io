<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Health checks para orquestradores e uptime monitoring (§14). */
class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        DB::select('select 1'); // readiness = dependências críticas alcançáveis

        return response()->json(['status' => 'ok', 'checks' => ['database' => 'up']]);
    }
}
