<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Database;
use App\Models\User;

/**
 * Autenticação com sessão nativa do PHP:
 *  - senha verificada contra hash Argon2id (rehash automático se os
 *    parâmetros de custo mudarem);
 *  - session_regenerate_id() no login (previne session fixation);
 *  - cada login gera uma linha em `sessions` (auditoria).
 */
class AuthService
{
    public function __construct(private readonly User $users = new User)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        // password_verify roda mesmo sem usuário? Não — mas a mensagem de erro
        // é idêntica nos dois casos (evita enumeração de e-mails).
        if ($user === null || ! password_verify($password, $user['password'])) {
            return false;
        }

        if (password_needs_rehash($user['password'], config('auth.hash_algo'), config('auth.hash_options'))) {
            $newHash = password_hash($password, config('auth.hash_algo'), config('auth.hash_options'));
            Database::connection()
                ->prepare('UPDATE users SET password = :p WHERE id = :id')
                ->execute(['p' => $newHash, 'id' => $user['id']]);
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'company_id' => (int) $user['company_id'],
            'company' => $user['company_name'],
            'role' => $user['role_code'],
            'role_name' => $user['role_name'],
        ];

        $this->users->touchLogin((int) $user['id']);

        // Auditoria de acesso
        Database::connection()
            ->prepare('INSERT INTO sessions (user_id, ip_address, user_agent) VALUES (:u, :ip, :ua)')
            ->execute([
                'u' => $user['id'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        $_SESSION['session_log_id'] = (int) Database::connection()->lastInsertId('sessions_id_seq');

        return true;
    }

    public function logout(): void
    {
        if (isset($_SESSION['session_log_id'])) {
            Database::connection()
                ->prepare('UPDATE sessions SET logged_out_at = now() WHERE id = :id')
                ->execute(['id' => $_SESSION['session_log_id']]);
        }

        $_SESSION = [];
        session_destroy();
    }

    public function hashPassword(string $plain): string
    {
        return password_hash($plain, config('auth.hash_algo'), config('auth.hash_options'));
    }
}
