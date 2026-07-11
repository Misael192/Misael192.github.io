<?php

declare(strict_types=1);

namespace App\Core\Workflow\Executors;

use App\Core\Workflow\Contracts\NodeExecutor;
use Illuminate\Support\Facades\Http;

/** Chama um endpoint externo com o contexto do fluxo. */
class WebhookExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'webhook';
    }

    public function execute(array $node, array $context): array
    {
        $url = $node['config']['url'] ?? null;

        if ($url !== null) {
            Http::timeout(15)->post($url, $context);
        }

        return ['status' => 'completed'];
    }
}
