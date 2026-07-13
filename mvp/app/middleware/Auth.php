<?php

declare(strict_types=1);

namespace App\Middleware;

/** Protege páginas autenticadas: sem sessão válida → login. */
class Auth
{
    public static function check(): void
    {
        if (auth_user() === null) {
            flash('error', 'Faça login para continuar.');
            redirect('login.php');
        }
    }
}
