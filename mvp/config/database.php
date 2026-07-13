<?php

declare(strict_types=1);

/**
 * Conexão com o banco (PDO). Padrão: PostgreSQL (decisão do projeto);
 * MySQL 8 é suportado trocando o driver — nenhuma query usa recurso
 * exclusivo de um banco.
 *
 * Sobrescreva via variáveis de ambiente em produção (nunca commite senha real).
 */
return [
    'driver' => getenv('DB_DRIVER') ?: 'pgsql',        // pgsql | mysql
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '5432',             // mysql: 3306
    'database' => getenv('DB_DATABASE') ?: 'peopleflow_mvp',
    'username' => getenv('DB_USERNAME') ?: 'peopleflow',
    'password' => getenv('DB_PASSWORD') ?: 'peopleflow',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,           // prepared statements reais
    ],
];
