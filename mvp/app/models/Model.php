<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Model base: encapsula PDO com prepared statements.
 * Cada model define $table e ganha helpers de consulta seguros.
 */
abstract class Model
{
    protected string $table;

    protected function db(): PDO
    {
        return Database::connection();
    }

    /** SELECT parametrizado; retorna todas as linhas. */
    protected function select(string $sql, array $params = []): array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** SELECT parametrizado; retorna uma linha ou null. */
    protected function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** INSERT/UPDATE/DELETE parametrizado; retorna linhas afetadas. */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function count(): int
    {
        return (int) $this->db()->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
    }
}
