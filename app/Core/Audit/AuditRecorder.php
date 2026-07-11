<?php

declare(strict_types=1);

namespace App\Core\Audit;

use App\Models\AuditLog;

/**
 * Auditoria transversal (ARCHITECTURE.md §7). A tabela audit_logs é
 * append-only (trigger no PostgreSQL impede UPDATE/DELETE).
 */
class AuditRecorder
{
    /** Campos que jamais podem aparecer na trilha (LGPD). */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'refresh_token', 'secret',
        'cpf', 'rg', 'bank_info', 'mfa_code', 'token',
    ];

    public function record(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?string $actorId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $ip = null,
        ?string $userAgent = null,
        string $actorType = 'user',
    ): void {
        AuditLog::query()->create([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'before' => $before !== null ? $this->scrub($before) : null,
            'after' => $after !== null ? $this->scrub($after) : null,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    /** Remove recursivamente campos sensíveis antes de persistir. */
    private function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array((string) $key, self::SENSITIVE_KEYS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->scrub($value);
            }
        }

        return $data;
    }
}
