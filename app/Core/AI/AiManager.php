<?php

declare(strict_types=1);

namespace App\Core\AI;

use App\Core\AI\Contracts\LlmProvider;
use App\Core\AI\Drivers\ClaudeProvider;
use App\Core\AI\Drivers\GeminiProvider;
use App\Core\AI\Drivers\OllamaProvider;
use App\Core\AI\Drivers\OpenAiProvider;
use Illuminate\Support\Manager;

/**
 * Manager de provedores de IA (padrão Manager do Laravel — mesmo mecanismo
 * de cache/queue/mail). O driver padrão vem de config/ai.php e pode ser
 * sobrescrito por tenant via Settings.
 */
class AiManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('ai.default', 'claude');
    }

    public function createOpenaiDriver(): LlmProvider
    {
        return new OpenAiProvider(
            apiKey: (string) $this->config->get('ai.providers.openai.key'),
            model: (string) $this->config->get('ai.providers.openai.model'),
        );
    }

    public function createGeminiDriver(): LlmProvider
    {
        return new GeminiProvider(
            apiKey: (string) $this->config->get('ai.providers.gemini.key'),
            model: (string) $this->config->get('ai.providers.gemini.model'),
        );
    }

    public function createClaudeDriver(): LlmProvider
    {
        return new ClaudeProvider(
            apiKey: (string) $this->config->get('ai.providers.claude.key'),
            model: (string) $this->config->get('ai.providers.claude.model'),
        );
    }

    public function createOllamaDriver(): LlmProvider
    {
        return new OllamaProvider(
            baseUrl: (string) $this->config->get('ai.providers.ollama.url'),
            model: (string) $this->config->get('ai.providers.ollama.model'),
        );
    }
}
