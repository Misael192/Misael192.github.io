<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Core\AI\AiManager;
use Throwable;

/**
 * Responde no Assistente CLT combinando a engine e um LLM opcional:
 *
 *  1. A engine (CltAssistantService) SEMPRE calcula os fatos com as tabelas
 *     vigentes — é a fonte de verdade de qualquer valor.
 *  2. Se houver um provedor de LLM configurado (config/ai.php), ele redige a
 *     resposta final ANCORADA nesses fatos (não recalcula nem os contradiz).
 *  3. Sem chave configurada, ou se a chamada ao LLM falhar, devolve a resposta
 *     calculada — o produto nunca fica sem responder e nunca inventa número.
 */
class AssistantResponder
{
    public function __construct(
        private readonly CltAssistantService $engine = new CltAssistantService,
    ) {}

    /**
     * @return array{content: string, provider: string}
     */
    public function respond(string $question): array
    {
        $calculated = $this->engine->answer($question);

        $provider = (string) config('ai.default');
        $key = (string) config("ai.providers.{$provider}.key");

        if ($key === '') {
            return ['content' => $calculated, 'provider' => 'calculated'];
        }

        try {
            $result = app(AiManager::class)->driver($provider)->complete(
                $this->groundedSystemPrompt($calculated),
                [['role' => 'user', 'content' => $question]],
            );

            $content = trim((string) ($result['content'] ?? ''));

            return $content !== ''
                ? ['content' => $content, 'provider' => $provider]
                : ['content' => $calculated, 'provider' => 'calculated'];
        } catch (Throwable) {
            // Provedor indisponível/erro → mantém o fallback calculado.
            return ['content' => $calculated, 'provider' => 'calculated'];
        }
    }

    private function groundedSystemPrompt(string $calculated): string
    {
        return (string) config('ai.agents.clt.system_prompt')
            ."\n\nUse EXCLUSIVAMENTE os fatos calculados abaixo como verdade para QUALQUER valor "
            .'monetário, alíquota ou base legal — não recalcule nem os contradiga. Se a pergunta '
            ."pedir um valor que não está nos fatos, peça os dados necessários.\n\n"
            ."FATOS CALCULADOS PELA ENGINE (tabelas vigentes):\n".$calculated;
    }
}
