<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * Autorização por permissão (`recurso:acao`). As permissões efetivas do
 * usuário são carregadas na sessão durante o login (AuthService).
 */
class Can
{
    public static function check(string $permission): void
    {
        Auth::check();

        if (! self::allowed($permission)) {
            http_response_code(403);
            exit('403 — Você não tem permissão para esta ação ('.htmlspecialchars($permission).').');
        }
    }

    public static function allowed(string $permission): bool
    {
        return in_array($permission, auth_user()['permissions'] ?? [], true);
    }
}
