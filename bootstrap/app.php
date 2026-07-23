<?php

use App\Core\Audit\RecordApiMutations;
use App\Core\FeatureFlags\EnsureModuleEnabled;
use App\Core\Tenancy\ResolveTenant;
use App\Core\Tenancy\SetTenantFromSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Aliases usados nas rotas: tenant → módulo → auditoria (ver routes/api.php).
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.session' => SetTenantFromSession::class,
            'module' => EnsureModuleEnabled::class,
            'audit' => RecordApiMutations::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
