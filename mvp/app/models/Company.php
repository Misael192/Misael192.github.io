<?php

declare(strict_types=1);

namespace App\Models;

class Company extends Model
{
    protected string $table = 'companies';

    public function all(): array
    {
        return $this->select(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id) AS users_count,
                    (SELECT COUNT(*) FROM employees e WHERE e.company_id = c.id) AS employees_count
             FROM companies c
             ORDER BY c.created_at DESC',
        );
    }

    public function forSelect(): array
    {
        return $this->select('SELECT id, name FROM companies WHERE is_active = TRUE ORDER BY name');
    }

    public function create(array $data): void
    {
        $this->execute(
            'INSERT INTO companies (name, trade_name, cnpj, email)
             VALUES (:name, :trade_name, :cnpj, :email)',
            $data,
        );
    }

    public function cnpjExists(string $cnpj): bool
    {
        return $this->selectOne('SELECT 1 FROM companies WHERE cnpj = :cnpj', ['cnpj' => $cnpj]) !== null;
    }
}
