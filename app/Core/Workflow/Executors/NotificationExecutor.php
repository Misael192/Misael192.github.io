<?php

declare(strict_types=1);

namespace App\Core\Workflow\Executors;

use App\Core\Workflow\Contracts\NodeExecutor;
use App\Models\Notification;

/** Notifica um usuário do tenant (in-app; e-mail/push via queue na Fase 2). */
class NotificationExecutor implements NodeExecutor
{
    public function type(): string
    {
        return 'notification';
    }

    public function execute(array $node, array $context): array
    {
        $userId = $node['config']['user_id'] ?? ($context['user_id'] ?? null);

        if ($userId !== null) {
            Notification::query()->create([
                'user_id' => $userId,
                'type' => $node['config']['type'] ?? 'workflow',
                'title' => $node['config']['title'] ?? 'Atualização de fluxo',
                'body' => $node['config']['body'] ?? null,
                'data' => $context,
            ]);
        }

        return ['status' => 'completed'];
    }
}
