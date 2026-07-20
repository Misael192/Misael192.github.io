<?php

declare(strict_types=1);

namespace App\Core\AI\Contracts;

/**
 * Port do provedor de LLM. A plataforma suporta OpenAI, Gemini, Claude e
 * modelos locais via Ollama — trocar de provedor não afeta agentes nem
 * chamadores (o driver é escolhido em config/ai.php).
 */
interface LlmProvider
{
    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     * @return array{content: string, input_tokens: int, output_tokens: int}
     */
    public function complete(string $system, array $messages): array;
}
