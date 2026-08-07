<?php

declare(strict_types=1);

namespace App\Core\AI\Drivers;

use App\Core\AI\Contracts\LlmProvider;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements LlmProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o',
    ) {}

    public function complete(string $system, array $messages): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ...$messages,
                ],
            ])->throw()->json();

        return [
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'input_tokens' => $response['usage']['prompt_tokens'] ?? 0,
            'output_tokens' => $response['usage']['completion_tokens'] ?? 0,
        ];
    }
}
