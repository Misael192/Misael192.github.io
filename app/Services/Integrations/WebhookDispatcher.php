<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\Company;
use App\Models\Integration;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;

/**
 * Entrega webhooks de saída assinados (HMAC-SHA256) para as integrações
 * `webhook` ativas do tenant, com cada tentativa rastreada em webhook_logs
 * (código de resposta, tentativas, entregue-em) e reenvio manual.
 */
class WebhookDispatcher
{
    public const SIGNATURE_HEADER = 'X-PeopleFlow-Signature';

    /** Dispara o evento para todas as integrações webhook ativas. @return int entregues (2xx) */
    public function dispatch(Company $company, string $event, array $payload): int
    {
        $integrations = Integration::query()
            ->where('provider', 'webhook')
            ->where('is_active', true)
            ->get();

        $delivered = 0;
        foreach ($integrations as $integration) {
            $log = $this->deliver($integration, $event, $payload);
            if ($log->delivered_at !== null) {
                $delivered++;
            }
        }

        return $delivered;
    }

    /** Reenvia uma entrega anterior usando a mesma integração e payload. */
    public function resend(WebhookLog $log): WebhookLog
    {
        $integration = Integration::query()->findOrFail($log->integration_id);

        return $this->deliver($integration, $log->event, $log->payload, $log);
    }

    private function deliver(Integration $integration, string $event, array $payload, ?WebhookLog $existing = null): WebhookLog
    {
        $config = $integration->config ?? [];
        $url = $config['url'] ?? null;
        $secret = (string) ($config['secret'] ?? '');

        $body = json_encode(['event' => $event, 'data' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        $code = null;
        if ($url !== null) {
            $response = Http::withHeaders([
                self::SIGNATURE_HEADER => $signature,
                'Content-Type' => 'application/json',
            ])->withBody($body, 'application/json')->post($url);

            $code = $response->status();
        }

        $success = $code !== null && $code >= 200 && $code < 300;

        $attributes = [
            'response_code' => $code,
            'attempts' => ($existing?->attempts ?? 0) + 1,
            'delivered_at' => $success ? now() : null,
        ];

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing;
        }

        return WebhookLog::query()->create(array_merge($attributes, [
            'integration_id' => $integration->id,
            'event' => $event,
            'payload' => $payload,
        ]));
    }
}
