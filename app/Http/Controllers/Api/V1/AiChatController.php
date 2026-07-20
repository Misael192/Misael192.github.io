<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\AI\AiEngine;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Chat com o AI Engine (agentes: assistant, clt, contracts, recruiter). */
class AiChatController extends Controller
{
    public function __construct(private readonly AiEngine $engine)
    {
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:8000'],
            'agent' => ['nullable', 'in:assistant,clt,contracts,recruiter'],
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        $result = $this->engine->sendMessage(
            userId: $request->user()->id,
            content: $data['content'],
            agent: $data['agent'] ?? 'assistant',
            conversationId: $data['conversation_id'] ?? null,
        );

        return response()->json($result);
    }
}
