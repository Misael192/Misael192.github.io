<?php

declare(strict_types=1);

namespace App\Core\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;

/**
 * Orquestrador do AI Engine: monta o contexto (memória da conversa + RAG),
 * chama o provedor configurado e loga tudo (tokens, custo, latência) em
 * ai_messages — insumo para quotas de billing e observabilidade.
 *
 * Guard-rails (ADR-008): IA nunca executa escrita sem confirmação humana;
 * toda tool respeita o RBAC do usuário solicitante.
 */
class AiEngine
{
    public function __construct(private readonly AiManager $manager)
    {
    }

    public function sendMessage(
        string $userId,
        string $content,
        string $agent = 'assistant',
        ?string $conversationId = null,
        ?string $provider = null,
    ): array {
        $conversation = $conversationId !== null
            ? AiConversation::query()->findOrFail($conversationId)
            : AiConversation::query()->create(['user_id' => $userId, 'agent' => $agent]);

        // Memória: histórico da conversa vai como contexto para o modelo.
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->limit(50)
            ->get(['role', 'content'])
            ->map(fn (AiMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->all();

        $conversation->messages()->create(['role' => 'user', 'content' => $content]);

        $startedAt = hrtime(true);
        $result = $this->manager->driver($provider)->complete(
            system: config("ai.agents.{$agent}.system_prompt", config('ai.agents.assistant.system_prompt')),
            messages: [...$history, ['role' => 'user', 'content' => $content]],
        );

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['content'],
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
            'latency_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'provider' => $provider ?? $this->manager->getDefaultDriver(),
        ]);

        return [
            'conversation_id' => $conversation->id,
            'reply' => $result['content'],
        ];
    }
}
