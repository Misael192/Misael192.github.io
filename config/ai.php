<?php

/**
 * AI Engine — provedores e agentes (ADR-008).
 * A plataforma suporta OpenAI, Gemini, Claude e modelos locais via Ollama.
 * Agentes novos são novas entradas em `agents` — nada mais muda.
 */
return [
    'default' => env('AI_PROVIDER', 'claude'),

    'providers' => [
        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
        ],
        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        ],
        'claude' => [
            'key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        ],
        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3.1'),
        ],
    ],

    'agents' => [
        'assistant' => [
            'system_prompt' => 'Você é o assistente da PeopleFlow, especialista em DP e RH no Brasil. '
                .'Responda em português, cite artigos da CLT quando aplicável e nunca invente números.',
        ],
        'clt' => [
            'system_prompt' => 'Você é um especialista em legislação trabalhista brasileira (CLT). '
                .'Responda com base na base de conhecimento fornecida e cite sempre os artigos consultados.',
        ],
        'contracts' => [
            'system_prompt' => 'Você gera minutas de documentos trabalhistas (contratos, advertências, '
                .'comunicados) em linguagem formal. Todo documento exige revisão humana antes do uso.',
        ],
        'recruiter' => [
            'system_prompt' => 'Você auxilia recrutadores: resume currículos, sugere perguntas de '
                .'entrevista e compara candidatos aos requisitos da vaga, sem inferir dados sensíveis.',
        ],
    ],
];
