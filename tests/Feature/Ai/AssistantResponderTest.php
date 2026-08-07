<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Services\Ai\AssistantResponder;
use Database\Seeders\PayrollEngineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Assistente com LLM plugável: a engine é sempre a fonte de verdade; o LLM,
 * quando configurado, redige ancorado nos fatos calculados; sem chave ou com
 * falha, cai no calculado.
 */
class AssistantResponderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PayrollEngineSeeder::class); // tabelas vigentes para a engine
    }

    public function test_sem_chave_usa_o_calculado(): void
    {
        config(['ai.providers.claude.key' => null]);
        Http::fake();

        $answer = (new AssistantResponder)->respond('Salário líquido de R$ 5.200');

        $this->assertSame('calculated', $answer['provider']);
        $this->assertStringContainsString('4.304,51', $answer['content']);
        Http::assertNothingSent();
    }

    public function test_usa_o_llm_ancorado_nos_fatos_quando_configurado(): void
    {
        config(['ai.default' => 'claude', 'ai.providers.claude.key' => 'test-key', 'ai.providers.claude.model' => 'claude-sonnet-5']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Seu líquido é R$ 4.304,51 — explico os descontos abaixo.']],
                'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
            ], 200),
        ]);

        $answer = (new AssistantResponder)->respond('Salário líquido de R$ 5.200');

        $this->assertSame('claude', $answer['provider']);
        $this->assertStringContainsString('4.304,51', $answer['content']);

        // O prompt enviado leva os fatos calculados como âncora (grounding).
        Http::assertSent(function ($request) {
            return str_contains($request['system'] ?? '', '4.304,51')
                && str_contains($request['system'] ?? '', 'EXCLUSIVAMENTE');
        });
    }

    public function test_cai_no_calculado_quando_o_llm_falha(): void
    {
        config(['ai.default' => 'claude', 'ai.providers.claude.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response('erro', 500)]);

        $answer = (new AssistantResponder)->respond('INSS de R$ 3.000');

        $this->assertSame('calculated', $answer['provider']);
        $this->assertStringContainsString('253,41', $answer['content']); // valor da engine
    }
}
