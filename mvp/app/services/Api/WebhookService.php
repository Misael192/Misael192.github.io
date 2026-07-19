<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Models\Database;

/**
 * Webhooks de saída: POST JSON assinado com HMAC-SHA256 do corpo
 * (cabeçalho X-PeopleFlow-Signature). O despacho NUNCA quebra o fluxo
 * principal — falha vira delivery `failed`, reenviável pela tela de
 * Integrações. Entrega síncrona com timeout curto (MVP; fila depois).
 */
final class WebhookService
{
    public const EVENTS = [
        'employee.created' => 'Colaborador admitido',
        'employee.terminated' => 'Colaborador desligado',
        'vacation.approved' => 'Férias aprovadas',
        'vacation.rejected' => 'Férias rejeitadas',
        'payroll.closed' => 'Folha fechada',
    ];

    /** Notifica todos os endpoints ativos da empresa inscritos no evento. */
    public static function dispatch(int $companyId, string $event, array $data): void
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare('SELECT * FROM webhook_endpoints WHERE company_id = :c AND is_active');
            $stmt->execute(['c' => $companyId]);

            foreach ($stmt->fetchAll() as $endpoint) {
                $subscribed = $endpoint['events'] === null
                    || in_array($event, array_map('trim', explode(',', $endpoint['events'])), true);
                if (! $subscribed) {
                    continue;
                }

                $body = json_encode([
                    'event' => $event,
                    'occurred_at' => date('c'),
                    'data' => $data,
                ], JSON_UNESCAPED_UNICODE);

                [$ok, $code] = self::post($endpoint['url'], $body, trim($endpoint['secret']));

                $db->prepare(
                    'INSERT INTO webhook_deliveries (endpoint_id, event, payload, status, response_code, delivered_at)
                     VALUES (:e, :ev, :p, :s, :code, :at)',
                )->execute([
                    'e' => $endpoint['id'], 'ev' => $event, 'p' => $body,
                    's' => $ok ? 'delivered' : 'failed', 'code' => $code,
                    'at' => $ok ? date('Y-m-d H:i:sP') : null,
                ]);
            }
        } catch (\Throwable) {
            // Webhook jamais derruba a operação de negócio.
        }
    }

    /** Reenvia uma entrega (mesmo corpo, novo POST). @return bool sucesso */
    public static function redeliver(int $deliveryId, int $companyId): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT d.*, e.url, e.secret FROM webhook_deliveries d
             JOIN webhook_endpoints e ON e.id = d.endpoint_id
             WHERE d.id = :id AND e.company_id = :c',
        );
        $stmt->execute(['id' => $deliveryId, 'c' => $companyId]);
        $delivery = $stmt->fetch();
        if ($delivery === false) {
            return false;
        }

        [$ok, $code] = self::post($delivery['url'], $delivery['payload'], trim($delivery['secret']));

        $db->prepare(
            'UPDATE webhook_deliveries SET status = :s, response_code = :code,
                    attempts = attempts + 1, delivered_at = :at WHERE id = :id',
        )->execute(['s' => $ok ? 'delivered' : 'failed', 'code' => $code,
            'at' => $ok ? date('Y-m-d H:i:sP') : $delivery['delivered_at'], 'id' => $deliveryId]);

        return $ok;
    }

    /** @return array{0: bool, 1: ?int} [sucesso (2xx), status HTTP] */
    private static function post(string $url, string $body, string $secret): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_PROXY => '',              // entrega direta ao endpoint do cliente
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: PeopleFlow-Webhooks/1.0',
                'X-PeopleFlow-Signature: sha256='.hash_hmac('sha256', $body, $secret),
            ],
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: null;
        curl_close($ch);

        return [$code !== null && $code >= 200 && $code < 300, $code];
    }
}
