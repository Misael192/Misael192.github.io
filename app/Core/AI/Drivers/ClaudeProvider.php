<?php

declare(strict_types=1);

namespace App\Core\AI\Drivers;

use App\Core\AI\Contracts\LlmProvider;
use Illuminate\Support\Facades\Http;

class ClaudeProvider implements LlmProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-5',
    ) {
    }

    public function complete(string $system, array $messages): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 4096,
            'system' => $system,
            'messages' => $messages,
        ])->throw()->json();

        return [
            'content' => $response['content'][0]['text'] ?? '',
            'input_tokens' => $response['usage']['input_tokens'] ?? 0,
            'output_tokens' => $response['usage']['output_tokens'] ?? 0,
        ];
    }
}
