<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Database;
use App\Services\AuditService;
use App\Services\Api\ApiAuth;

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

        view('integrations', [
            'keys' => $keys->fetchAll(),
            'newSecret' => $_SESSION['pf_new_api_key'] ?? null,
        ]);
        unset($_SESSION['pf_new_api_key']);
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
