<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Models\Integration;
use App\Models\WebhookLog;
use App\Services\Integrations\WebhookDispatcher;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Webhooks de saída pela UI: cadastra endpoints (url + segredo), ativa/desativa
 * e acompanha as entregas (código, tentativas) com reenvio. As entregas
 * assinadas (HMAC) saem do WebhookDispatcher — aqui só configuramos e vemos.
 */
#[Layout('layouts.app')]
class Webhooks extends Component
{
    public string $name = '';

    public string $url = '';

    public string $secret = '';

    public string $flash = '';

    public function mount(): void
    {
        $this->secret = Str::random(32);
    }

    public function cadastrar(): void
    {
        $this->authorize('integrations:manage');
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:500'],
            'secret' => ['required', 'string', 'min:16', 'max:120'],
        ]);

        Integration::query()->create([
            'provider' => 'webhook',
            'name' => $data['name'],
            'config' => ['url' => $data['url'], 'secret' => $data['secret']],
            'is_active' => true,
        ]);

        $this->reset(['name', 'url']);
        $this->secret = Str::random(32);
        $this->flash = 'Webhook cadastrado.';
    }

    public function alternar(string $integrationId): void
    {
        $this->authorize('integrations:manage');
        $integration = Integration::query()->where('provider', 'webhook')->findOrFail($integrationId);
        $integration->update(['is_active' => ! $integration->is_active]);
        $this->flash = $integration->is_active ? 'Webhook ativado.' : 'Webhook desativado.';
    }

    public function reenviar(string $logId, WebhookDispatcher $dispatcher): void
    {
        $this->authorize('integrations:manage');
        $log = WebhookLog::query()->findOrFail($logId);
        $result = $dispatcher->resend($log);
        $this->flash = $result->delivered_at !== null
            ? 'Reenviado com sucesso.'
            : 'Reenvio falhou (código '.($result->response_code ?? '—').').';
    }

    public function render()
    {
        $webhooks = Integration::query()
            ->where('provider', 'webhook')
            ->orderByDesc('created_at')
            ->get();

        $logs = WebhookLog::query()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('livewire.integrations.webhooks', [
            'webhooks' => $webhooks,
            'logs' => $logs,
        ]);
    }
}
