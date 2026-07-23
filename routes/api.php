<?php

/**
 * API REST da PeopleFlow — versionada por URI desde o dia 1 (/api/v1).
 * Documentação OpenAPI em docs/openapi.yaml.
 *
 * Pipeline de middleware: tenant → auth → permissão (RBAC) → módulo → auditoria.
 */

use App\Http\Controllers\Api\V1\AiChatController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\SpecialPayrollController;
use App\Http\Controllers\Api\V1\VacationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ── Público (sem tenant): health checks ──────────────────────────────────
    Route::get('health/live', [HealthController::class, 'live']);
    Route::get('health/ready', [HealthController::class, 'ready']);

    // ── Autenticação (tenant resolvido, sem usuário ainda) ───────────────────
    Route::middleware(['tenant', 'throttle:20,1'])->group(function () {
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
    });

    // ── Rotas autenticadas ───────────────────────────────────────────────────
    Route::middleware(['tenant', 'auth:sanctum', 'audit'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Módulo People — habilitação por tenant + permissões RBAC por rota.
        Route::middleware('module:people')->group(function () {
            Route::get('employees', [EmployeeController::class, 'index'])
                ->middleware('can:employees:read');
            Route::get('employees/{employee}', [EmployeeController::class, 'show'])
                ->middleware('can:employees:read');
            Route::post('employees', [EmployeeController::class, 'store'])
                ->middleware('can:employees:create');
            Route::patch('employees/{employee}', [EmployeeController::class, 'update'])
                ->middleware('can:employees:update');

            Route::post('vacations', [VacationController::class, 'store'])
                ->middleware('can:vacations:request');
            Route::post('vacations/{vacation}/approve', [VacationController::class, 'approve'])
                ->middleware('can:vacations:approve');
        });

        // Módulo Folha — fechamento mensal, holerite e folhas especiais.
        Route::middleware('module:payroll')->group(function () {
            Route::post('payroll/periods/{company}/calculate', [PayrollController::class, 'calculate'])
                ->middleware('can:payroll:manage');
            Route::post('payroll/periods/{company}/close', [PayrollController::class, 'close'])
                ->middleware('can:payroll:manage');
            Route::post('payroll/periods/{company}/reopen', [PayrollController::class, 'reopen'])
                ->middleware('can:payroll:manage');
            Route::get('payrolls/{payroll}', [PayrollController::class, 'show'])
                ->middleware('can:payroll:read');

            Route::post('payroll/thirteenth/{company}', [SpecialPayrollController::class, 'thirteenth'])
                ->middleware('can:payroll:manage');
            Route::post('payroll/vacations/{vacation}/receipt', [SpecialPayrollController::class, 'vacationReceipt'])
                ->middleware('can:payroll:manage');
            Route::post('payroll/employees/{employee}/termination/simulate', [SpecialPayrollController::class, 'simulateTermination'])
                ->middleware('can:payroll:read');
            Route::post('payroll/employees/{employee}/termination', [SpecialPayrollController::class, 'terminate'])
                ->middleware('can:payroll:manage');
        });

        // AI Engine — módulo comercial próprio.
        Route::post('ai/chat', [AiChatController::class, 'send'])
            ->middleware(['module:ai', 'can:ai:chat', 'throttle:30,1']);
    });
});
