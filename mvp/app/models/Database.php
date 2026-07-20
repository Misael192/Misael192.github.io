<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Conexão PDO única por request (singleton preguiçoso). */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $cfg = config('database');
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s',
                $cfg['driver'],
                $cfg['host'],
                $cfg['port'],
                $cfg['database'],
            );
            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
        }

        return self::$pdo;
    }
}
