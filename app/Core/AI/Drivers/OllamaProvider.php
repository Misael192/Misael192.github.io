<?php

declare(strict_types=1);

namespace App\Core\AI\Drivers;

use App\Core\AI\Contracts\LlmProvider;
use Illuminate\Support\Facades\Http;

/** Modelos locais (LGPD estrita: nenhum dado sai da infraestrutura). */
class OllamaProvider implements LlmProvider
{
    public function __construct(
        private readonly string $baseUrl = 'http://localhost:11434',
        private readonly string $model = 'llama3.1',
    ) {
    }

    public function complete(string $system, array $messages): array
    {
        $response = Http::timeout(300)->post("{$this->baseUrl}/api/chat", [
            'model' => $this->model,
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ...$messages,
            ],
        ])->throw()->json();

        return [
            'content' => $response['message']['content'] ?? '',
            'input_tokens' => $response['prompt_eval_count'] ?? 0,
            'output_tokens' => $response['eval_count'] ?? 0,
        ];
    }
}
