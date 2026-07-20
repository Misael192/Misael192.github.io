<?php

use Illuminate\Support\Facades\Route;

/**
 * A interface da PeopleFlow vive em public/*.html (HTML5 + Tailwind +
 * Alpine.js), pronta para conversão em Blade. A raiz redireciona para a
 * landing page estática; o fallback cobre links quebrados (404 própria).
 */
Route::get('/', fn () => redirect('/index.html'));

Route::fallback(fn () => response()->file(public_path('404.html'), ['Content-Type' => 'text/html']));
