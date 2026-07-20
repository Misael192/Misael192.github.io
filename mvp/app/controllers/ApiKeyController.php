<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Database;
use App\Services\AuditService;
use App\Services\Api\ApiAuth;
use App\Services\Api\WebhookService;

/** Integrações: chaves da API pública (criar com escopos, revogar, exibir o segredo UMA vez). */
class ApiKeyController
{
    public function index(): void
    {
        Can::check('api:manage');
        $companyId = auth_user()['company_id'];
        $db = Database::connection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            match ($_POST['action'] ?? '') {
                'create' => $this->create($db, $companyId),
                'revoke' => $this->revoke($db, $companyId),
                'webhook_create' => $this->webhookCreate($db, $companyId),
                'webhook_toggle' => $this->webhookToggle($db, $companyId),
                'webhook_resend' => $this->webhookResend($companyId),
                default => null,
            };
            redirect('integracoes.php');
        }

        $keys = $db->prepare(
            'SELECT k.*, u.name AS creator FROM api_keys k
             LEFT JOIN users u ON u.id = k.created_by
             WHERE k.company_id = :c ORDER BY k.created_at DESC',
        );
        $keys->execute(['c' => $companyId]);

        $webhooks = $db->prepare(
            'SELECT * FROM webhook_endpoints WHERE company_id = :c ORDER BY created_at DESC',
        );
        $webhooks->execute(['c' => $companyId]);

        $deliveries = $db->prepare(
            'SELECT d.*, e.url FROM webhook_deliveries d
             JOIN webhook_endpoints e ON e.id = d.endpoint_id
             WHERE e.company_id = :c ORDER BY d.created_at DESC LIMIT 15',
        );
        $deliveries->execute(['c' => $companyId]);

        view('integrations', [
            'keys' => $keys->fetchAll(),
            'newSecret' => $_SESSION['pf_new_api_key'] ?? null,
            'webhooks' => $webhooks->fetchAll(),
            'deliveries' => $deliveries->fetchAll(),
            'webhookEvents' => WebhookService::EVENTS,
            'newWebhookSecret' => $_SESSION['pf_new_webhook_secret'] ?? null,
        ]);
        unset($_SESSION['pf_new_api_key'], $_SESSION['pf_new_webhook_secret']);
    }

    /** Registra um endpoint: URL + eventos; segredo whsec exibido uma vez. */
    private function webhookCreate(\PDO $db, int $companyId): void
    {
        $url = trim((string) ($_POST['url'] ?? ''));
        $events = array_intersect((array) ($_POST['events'] ?? []), array_keys(WebhookService::EVENTS));

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with($url, 'http')) {
            flash('error', 'Informe uma URL http(s) válida.');

            return;
        }

        $secret = bin2hex(random_bytes(32));
        $db->prepare(
            'INSERT INTO webhook_endpoints (company_id, url, secret, events, created_by)
             VALUES (:c, :u, :s, :e, :by)',
        )->execute(['c' => $companyId, 'u' => $url, 's' => $secret,
            'e' => $events === [] ? null : implode(',', $events), 'by' => auth_user()['id']]);

        $_SESSION['pf_new_webhook_secret'] = ['url' => $url, 'secret' => $secret];

        AuditService::log('webhook.create', 'webhook_endpoint', $url, null, ['events' => $events]);
        flash('success', 'Webhook registrado — copie o segredo para validar a assinatura HMAC.');
    }

    private function webhookToggle(\PDO $db, int $companyId): void
    {
        $db->prepare('UPDATE webhook_endpoints SET is_active = NOT is_active WHERE id = :id AND company_id = :c')
           ->execute(['id' => (int) ($_POST['webhook_id'] ?? 0), 'c' => $companyId]);

        AuditService::log('webhook.toggle', 'webhook_endpoint', (int) $_POST['webhook_id']);
        flash('success', 'Webhook atualizado.');
    }

    private function webhookResend(int $companyId): void
    {
        $ok = WebhookService::redeliver((int) ($_POST['delivery_id'] ?? 0), $companyId);
        flash($ok ? 'success' : 'error', $ok ? 'Entrega reenviada com sucesso.' : 'Reenvio falhou — verifique o endpoint.');
    }

    private function create(\PDO $db, int $companyId): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $scopes = ($_POST['scopes'] ?? 'read') === 'read,write' ? 'read,write' : 'read';

        if ($name === '' || mb_strlen($name) > 80) {
            flash('error', 'Dê um nome à chave (ex.: "Integração Conta Azul").');

            return;
        }

        [$secret, $prefix, $hash] = ApiAuth::generateKey();
        $db->prepare(
            'INSERT INTO api_keys (company_id, name, key_prefix, key_hash, scopes, created_by)
             VALUES (:c, :n, :p, :h, :s, :u)',
        )->execute(['c' => $companyId, 'n' => $name, 'p' => $prefix, 'h' => $hash,
            's' => $scopes, 'u' => auth_user()['id']]);

        // O segredo só existe nesta resposta — depois, apenas o hash
        $_SESSION['pf_new_api_key'] = ['name' => $name, 'secret' => $secret];

        AuditService::log('api_key.create', 'api_key', $prefix, null, ['name' => $name, 'scopes' => $scopes]);
        flash('success', "Chave \"{$name}\" criada — copie o segredo agora, ele não será exibido de novo.");
    }

    private function revoke(\PDO $db, int $companyId): void
    {
        $db->prepare('UPDATE api_keys SET is_active = FALSE WHERE id = :id AND company_id = :c')
           ->execute(['id' => (int) ($_POST['key_id'] ?? 0), 'c' => $companyId]);

        AuditService::log('api_key.revoke', 'api_key', (int) $_POST['key_id']);
        flash('success', 'Chave revogada — requisições com ela passam a receber 401.');
    }
}
