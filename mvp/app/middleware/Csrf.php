<?php

declare(strict_types=1);

namespace App\Middleware;

/** Valida o token CSRF de todo POST (campo _token gerado por csrf_field()). */
class Csrf
{
    public static function verify(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $token = $_POST['_token'] ?? '';
        if (! is_string($token) || ! hash_equals(csrf_token(), $token)) {
            http_response_code(419);
            exit('Sessão expirada — recarregue a página e tente novamente.');
        }
    }
}
