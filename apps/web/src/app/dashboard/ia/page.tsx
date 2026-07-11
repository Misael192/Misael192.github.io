"use client";

import { useState } from "react";

/**
 * Assistente de IA — interface de chat. Nesta fase as respostas são
 * demonstrativas; na Fase 4 esta tela consome POST /api/v1/ai/conversations
 * (AI Engine com agentes, RAG sobre a base CLT e logs de custo por tenant).
 */
const QUICK_ACTIONS = [
  "Quantos dias de férias um colaborador com 1 ano de empresa tem direito?",
  "Gerar uma advertência por atraso recorrente",
  "Criar descrição de cargo para Analista de DP Pleno",
  "Resumir os currículos da vaga de Front-end",
];

const DEMO_REPLY =
  "Pela CLT (art. 130), após cada período aquisitivo de 12 meses o colaborador tem direito a 30 dias de férias, " +
  "podendo haver redução proporcional em caso de faltas injustificadas: até 5 faltas mantêm os 30 dias; de 6 a 14, " +
  "são 24 dias; de 15 a 23, são 18 dias; e de 24 a 32, são 12 dias. Até 10 dias podem ser convertidos em abono " +
  "pecuniário (art. 143). ⚠️ Resposta demonstrativa — na versão final, este agente responde com RAG sobre a base " +
  "CLT curada e cita os artigos consultados.";

interface Message {
  role: "user" | "assistant";
  content: string;
}

export default function AiAssistantPage() {
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState("");

  function send(text: string) {
    if (!text.trim()) return;
    setMessages((prev) => [
      ...prev,
      { role: "user", content: text },
      { role: "assistant", content: DEMO_REPLY },
    ]);
    setInput("");
  }

  return (
    <div className="mx-auto flex h-[calc(100vh-8rem)] max-w-3xl flex-col">
      <div className="flex-1 space-y-4 overflow-y-auto pb-4">
        {messages.length === 0 && (
          <div className="mt-12 text-center">
            <span className="text-4xl" aria-hidden>🤖</span>
            <h2 className="mt-4 text-xl font-semibold">Assistente PeopleFlow</h2>
            <p className="mx-auto mt-2 max-w-md text-sm text-ink-muted">
              Especialista em CLT, geração de documentos e apoio ao recrutamento.
              Toda ação de escrita exige a sua confirmação.
            </p>
            <div className="mt-8 grid gap-2 sm:grid-cols-2">
              {QUICK_ACTIONS.map((q) => (
                <button
                  key={q}
                  onClick={() => send(q)}
                  className="rounded-xl border border-line bg-surface p-4 text-left text-sm text-ink-muted transition-all duration-150 ease-brand hover:border-brand-500 hover:text-ink"
                >
                  {q}
                </button>
              ))}
            </div>
          </div>
        )}

        {messages.map((m, i) => (
          <div key={i} className={`flex ${m.role === "user" ? "justify-end" : "justify-start"}`}>
            <div
              className={`max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed ${
                m.role === "user"
                  ? "bg-brand-600 text-white"
                  : "border border-line bg-surface"
              }`}
            >
              {m.content}
            </div>
          </div>
        ))}
      </div>

      <form
        onSubmit={(e) => {
          e.preventDefault();
          send(input);
        }}
        className="flex gap-2 border-t border-line pt-4"
      >
        <input
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Pergunte sobre CLT, gere documentos, peça relatórios…"
          className="flex-1 rounded-xl border border-line bg-surface px-4 py-3 text-sm outline-none transition-colors focus:border-brand-500"
        />
        <button
          type="submit"
          className="rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-brand-700"
        >
          Enviar
        </button>
      </form>
    </div>
  );
}
