<?php

declare(strict_types=1);

namespace App\Livewire\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\CltAssistantService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Assistente CLT pela UI: chat que calcula com a engine da folha (tabelas
 * vigentes) e cita a base legal. Conversa persistida em ai_conversations/
 * ai_messages. Provedor 'calculated' — pronto para plugar um LLM depois.
 */
#[Layout('layouts.app')]
class Assistente extends Component
{
    public string $conversationId = '';

    public string $draft = '';

    public function mount(): void
    {
        $conversation = AiConversation::query()->firstOrCreate(
            ['user_id' => auth()->id(), 'agent' => 'clt'],
            ['title' => 'Assistente CLT'],
        );

        $this->conversationId = (string) $conversation->id;
    }

    public function enviar(CltAssistantService $assistant): void
    {
        $data = $this->validate(['draft' => ['required', 'string', 'max:2000']]);

        AiMessage::query()->create([
            'conversation_id' => $this->conversationId,
            'role' => 'user',
            'content' => $data['draft'],
        ]);

        AiMessage::query()->create([
            'conversation_id' => $this->conversationId,
            'role' => 'assistant',
            'content' => $assistant->answer($data['draft']),
            'provider' => 'calculated',
        ]);

        $this->draft = '';
    }

    public function render()
    {
        $messages = AiMessage::query()
            ->where('conversation_id', $this->conversationId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('livewire.ai.assistente', ['messages' => $messages]);
    }
}
