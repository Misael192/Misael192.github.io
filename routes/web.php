<?php

use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Payroll\Folha;
use App\Livewire\Payroll\Holerite;
use Illuminate\Support\Facades\Route;

/**
 * Landing e demais páginas de marketing vivem em public/*.html. A aplicação
 * autenticada é Livewire: o login grava o tenant na sessão e as rotas
 * autenticadas o resolvem por ela (tenant.session) antes do auth.
 */
Route::get('/', fn () => redirect('/index.html'));

Route::get('/entrar', Login::class)->name('login');

Route::middleware('auth')->group(function () {
    Route::get('/painel', Dashboard::class)->name('dashboard');

    // Módulo Folha (habilitado por tenant + RBAC nas ações do componente).
    Route::middleware('module:payroll')->group(function () {
        Route::get('/folha', Folha::class)->name('folha');
        Route::get('/folha/holerite/{payroll}', Holerite::class)->name('folha.holerite');
    });
});

Route::fallback(fn () => response()->file(public_path('404.html'), ['Content-Type' => 'text/html']));
