<?php

declare(strict_types=1);

namespace App\Models;

class User extends Model
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        return $this->selectOne(
            'SELECT u.*, r.code AS role_code, r.name AS role_name, c.name AS company_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             JOIN companies c ON c.id = u.company_id
             WHERE u.email = :email AND u.is_active = TRUE
             LIMIT 1',
            ['email' => $email],
        );
    }

    public function allWithRelations(): array
    {
        return $this->select(
            'SELECT u.id, u.name, u.email, u.is_active, u.last_login_at,
                    r.name AS role_name, c.name AS company_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             JOIN companies c ON c.id = u.company_id
             ORDER BY u.created_at DESC',
        );
    }

    public function create(array $data): void
    {
        $this->execute(
            'INSERT INTO users (company_id, role_id, name, email, password)
             VALUES (:company_id, :role_id, :name, :email, :password)',
            $data,
        );
    }

    public function emailExists(int $companyId, string $email): bool
    {
        return $this->selectOne(
            'SELECT 1 FROM users WHERE company_id = :c AND email = :e',
            ['c' => $companyId, 'e' => $email],
        ) !== null;
    }

    public function touchLogin(int $id): void
    {
        $this->execute('UPDATE users SET last_login_at = now() WHERE id = :id', ['id' => $id]);
    }
}
