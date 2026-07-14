<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Database;

/**
 * Auditoria: toda alteração relevante gera registro imutável em audit_logs —
 * quem, quando, IP, navegador, empresa, valor antigo e novo.
 */
class AuditService
{
    /** Campos que jamais entram na trilha (LGPD). */
    private const SENSITIVE = ['password', 'password_confirmation', '_token'];

    public static function log(
        string $action,
        string $entity,
        string|int|null $entityId = null,
        ?array $old = null,
        ?array $new = null,
    ): void {
        $user = auth_user();

        Database::connection()->prepare(
            'INSERT INTO audit_logs (company_id, user_id, action, entity, entity_id,
                                     old_value, new_value, ip_address, user_agent)
             VALUES (:company, :user, :action, :entity, :entity_id, :old, :new, :ip, :ua)'
        )->execute([
            'company' => $user['company_id'] ?? null,
            'user' => $user['id'] ?? null,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'old' => $old !== null ? json_encode(self::scrub($old), JSON_UNESCAPED_UNICODE) : null,
            'new' => $new !== null ? json_encode(self::scrub($new), JSON_UNESCAPED_UNICODE) : null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }

    private static function scrub(array $data): array
    {
        foreach (self::SENSITIVE as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }
}
