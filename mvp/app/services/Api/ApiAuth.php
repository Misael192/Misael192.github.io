<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Models\Database;

/**
 * Autenticação da API pública: `Authorization: Bearer pfk_…`.
 * O segredo nunca é armazenado — compara-se o SHA-256. Toda resposta é JSON
 * com envelope {data|error}; erros encerram a requisição imediatamente.
 */
final class ApiAuth
{
    /** Valida o Bearer token e devolve a chave (com company_id). Encerra com 401 se inválido. */
    public static function authenticate(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (! preg_match('/^Bearer\s+(pfk_[A-Za-z0-9]{40})$/', $header, $m)) {
            self::error(401, 'missing_token', 'Envie o cabeçalho Authorization: Bearer pfk_…');
        }

        $stmt = Database::connection()->prepare(
            'SELECT * FROM api_keys WHERE key_hash = :h AND is_active',
        );
        $stmt->execute(['h' => hash('sha256', $m[1])]);
        $key = $stmt->fetch();

        if ($key === false) {
            self::error(401, 'invalid_token', 'Chave de API inválida ou revogada.');
        }

        Database::connection()->prepare('UPDATE api_keys SET last_used_at = now() WHERE id = :id')
            ->execute(['id' => $key['id']]);

        return $key;
    }

    /** Garante o escopo (read | write). Encerra com 403 se faltar. */
    public static function requireScope(array $key, string $scope): void
    {
        if (! in_array($scope, array_map('trim', explode(',', $key['scopes'])), true)) {
            self::error(403, 'insufficient_scope', "Esta chave não tem o escopo \"{$scope}\".");
        }
    }

    /** Gera uma nova chave: [segredo em claro (mostrar UMA vez), prefixo, hash]. */
    public static function generateKey(): array
    {
        $secret = 'pfk_'.substr(bin2hex(random_bytes(30)), 0, 40);

        return [$secret, substr($secret, 0, 12).'…', hash('sha256', $secret)];
    }

    public static function json(mixed $data, int $status = 200, array $meta = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['data' => $data] + ($meta !== [] ? ['meta' => $meta] : []),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function error(int $status, string $code, string $message): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
