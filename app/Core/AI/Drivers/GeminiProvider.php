<?php

declare(strict_types=1);

namespace App\Core\AI\Drivers;

use App\Core\AI\Contracts\LlmProvider;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements LlmProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-2.0-flash',
    ) {}

    public function complete(string $system, array $messages): array
    {
        $contents = array_map(fn (array $m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $messages);

        $response = Http::timeout(120)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents' => $contents,
            ]
        )->throw()->json();

        return [
            'content' => $response['candidates'][0]['content']['parts'][0]['text'] ?? '',
            'input_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
            'output_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
        ];
    }
}
