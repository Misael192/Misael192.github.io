/**
 * Pipeline Kanban de recrutamento (dados de demonstração; as colunas vêm de
 * JobOpening.pipeline — cada vaga pode ter seu próprio fluxo).
 */
const PIPELINE: { stage: string; cards: { name: string; role: string; score?: number; ai?: string }[] }[] = [
  {
    stage: "Triagem",
    cards: [
      { name: "Juliana Prado", role: "Dev Front-end", ai: "Forte em React e acessibilidade" },
      { name: "Marcos Vieira", role: "Dev Front-end", ai: "5 anos em produto SaaS" },
      { name: "Renata Dias", role: "Analista de DP", ai: "Experiência com eSocial" },
    ],
  },
  {
    stage: "Entrevista",
    cards: [
      { name: "Paulo Cardoso", role: "Dev Front-end", score: 8 },
      { name: "Sofia Martins", role: "Analista de DP", score: 9 },
    ],
  },
  {
    stage: "Avaliação técnica",
    cards: [{ name: "Tiago Costa", role: "Dev Front-end", score: 7 }],
  },
  {
    stage: "Proposta",
    cards: [{ name: "Vanessa Luz", role: "Coordenadora de RH", score: 9 }],
  },
];

export default function RecruitmentPage() {
  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2 text-sm">
          <span className="font-medium">Vaga:</span>
          <select className="rounded-lg border border-line bg-surface px-3 py-2 text-sm outline-none">
            <option>Todas as vagas (3 abertas)</option>
            <option>Desenvolvedor(a) Front-end</option>
            <option>Analista de DP Pleno</option>
            <option>Coordenador(a) de RH</option>
          </select>
        </div>
        <div className="flex gap-2">
          <button className="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink-muted transition-colors hover:text-ink">
            Página Trabalhe Conosco ↗
          </button>
          <button className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-700">
            + Nova vaga
          </button>
        </div>
      </div>

      <div className="overflow-x-auto pb-2">
        <div className="flex min-w-[900px] gap-4">
          {PIPELINE.map((col) => (
            <section key={col.stage} className="w-64 shrink-0 rounded-xl bg-surface-raised p-3">
              <header className="flex items-center justify-between px-1 pb-3">
                <h2 className="text-sm font-semibold">{col.stage}</h2>
                <span className="rounded-full bg-surface px-2 py-0.5 text-xs text-ink-muted">{col.cards.length}</span>
              </header>
              <div className="space-y-2">
                {col.cards.map((c) => (
                  <article
                    key={c.name}
                    className="cursor-grab rounded-lg border border-line bg-surface p-3 shadow-sm transition-all duration-150 ease-brand hover:shadow-md"
                  >
                    <p className="text-sm font-medium">{c.name}</p>
                    <p className="mt-0.5 text-xs text-ink-muted">{c.role}</p>
                    {c.score !== undefined && (
                      <p className="mt-2 text-xs">
                        Avaliação: <span className="font-semibold">{c.score}/10</span>
                      </p>
                    )}
                    {c.ai && (
                      <p className="mt-2 rounded-md bg-surface-raised px-2 py-1.5 text-xs text-ink-muted">
                        🤖 {c.ai}
                      </p>
                    )}
                  </article>
                ))}
              </div>
            </section>
          ))}
        </div>
      </div>
    </div>
  );
}
