<?php

declare(strict_types=1);

/**
 * Bootstrap do PeopleFlow MVP.
 * Toda página em public/ começa com: require __DIR__ . '/../app/bootstrap.php';
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH.'/app');
define('STORAGE_PATH', BASE_PATH.'/storage');

// ── Configurações ──────────────────────────────────────────────────────────
$GLOBALS['config'] = [
    'app' => require BASE_PATH.'/config/app.php',
    'database' => require BASE_PATH.'/config/database.php',
    'auth' => require BASE_PATH.'/config/auth.php',
];

date_default_timezone_set($GLOBALS['config']['app']['timezone']);

// ── Erros: visíveis em dev, logados em produção ────────────────────────────
if ($GLOBALS['config']['app']['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', STORAGE_PATH.'/logs/php-error.log');
}

// ── Autoload simples por convenção (App\Controllers\X → app/controllers/X.php)
spl_autoload_register(function (string $class): void {
    $map = [
        'App\\Controllers\\' => APP_PATH.'/controllers/',
        'App\\Models\\' => APP_PATH.'/models/',
        'App\\Middleware\\' => APP_PATH.'/middleware/',
        'App\\Services\\' => APP_PATH.'/services/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir.substr($class, strlen($prefix)).'.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});

require APP_PATH.'/helpers/functions.php';

// ── Sessão segura ──────────────────────────────────────────────────────────
$auth = $GLOBALS['config']['auth'];
session_name($auth['session_name']);
session_set_cookie_params([
    'lifetime' => $auth['session_lifetime'],
    'httponly' => true,                                  // JS não lê o cookie
    'samesite' => 'Lax',                                 // proteção CSRF básica
    'secure' => ! empty($_SERVER['HTTPS']),              // só HTTPS em produção
]);
session_start();
