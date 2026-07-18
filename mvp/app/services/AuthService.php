<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Database;
use App\Models\User;

/**
 * Autenticação com sessão nativa do PHP:
 *  - senha Argon2id com rehash automático;
 *  - session_regenerate_id() no login (previne session fixation);
 *  - permissões efetivas (perfil + grants/revokes diretos) carregadas na sessão;
 *  - cada login vira uma linha em user_sessions (auditoria de acesso).
 */
class AuthService
{
    public function __construct(private readonly User $users = new User)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        // Mensagem idêntica p/ e-mail inexistente ou senha errada (anti-enumeração).
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
            'employee_id' => isset($user['employee_id']) && $user['employee_id'] !== null ? (int) $user['employee_id'] : null,
            'permissions' => $this->effectivePermissions((int) $user['id'], (int) $user['role_id']),
        ];

        $this->users->touchLogin((int) $user['id']);

        Database::connection()
            ->prepare('INSERT INTO user_sessions (user_id, ip_address, user_agent) VALUES (:u, :ip, :ua)')
            ->execute([
                'u' => $user['id'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        $_SESSION['session_log_id'] = (int) Database::connection()->lastInsertId('user_sessions_id_seq');

        return true;
    }

    /** Permissões do perfil ± overrides diretos em user_permissions. */
    private function effectivePermissions(int $userId, int $roleId): array
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            'SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role'
        );
        $stmt->execute(['role' => $roleId]);
        $permissions = array_column($stmt->fetchAll(), 'code');

        $stmt = $db->prepare(
            'SELECT p.code, up.granted FROM user_permissions up
             JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = :u'
        );
        $stmt->execute(['u' => $userId]);
        foreach ($stmt->fetchAll() as $override) {
            if ($override['granted']) {
                $permissions[] = $override['code'];
            } else {
                $permissions = array_diff($permissions, [$override['code']]);
            }
        }

        return array_values(array_unique($permissions));
    }

    public function logout(): void
    {
        if (isset($_SESSION['session_log_id'])) {
            Database::connection()
                ->prepare('UPDATE user_sessions SET logged_out_at = now() WHERE id = :id')
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
