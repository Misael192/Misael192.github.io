<?php

use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

/**
 * Landing e demais páginas de marketing vivem em public/*.html. A aplicação
 * autenticada é Livewire: o login grava o tenant na sessão e as rotas
 * autenticadas o resolvem por ela (tenant.session) antes do auth.
 */
Route::get('/', fn () => redirect('/index.html'));

Route::get('/entrar', Login::class)->name('login');

Route::middleware(['tenant.session', 'auth'])->group(function () {
    Route::get('/painel', Dashboard::class)->name('dashboard');
});

Route::fallback(fn () => response()->file(public_path('404.html'), ['Content-Type' => 'text/html']));
