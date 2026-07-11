import { StatusBadge } from "@/components/status-badge";

/** Desenvolvimento e performance: metas, ciclos e treinamentos (demonstração). */
const GOALS = [
  { owner: "Time Comercial", title: "Aumentar receita recorrente em 20%", progress: 68 },
  { owner: "Daniela Rocha", title: "Implantar pesquisa de clima trimestral", progress: 100 },
  { owner: "Time de Tecnologia", title: "Reduzir tempo de deploy para < 15 min", progress: 45 },
  { owner: "Ana Souza", title: "Certificação em eSocial avançado", progress: 30 },
];

const REVIEWS = [
  { cycle: "2026 · S1", type: "Avaliação 360°", status: "Em andamento", tone: "brand" as const, done: "182/248 respostas" },
  { cycle: "2025 · S2", type: "Avaliação de gestor", status: "Concluído", tone: "success" as const, done: "241/243 respostas" },
];

const TRAININGS = [
  { title: "LGPD para RH e DP", duration: "2h", enrolled: 214, completion: 87 },
  { title: "Liderança para novos gestores", duration: "6h", enrolled: 32, completion: 54 },
  { title: "Segurança da informação (obrigatório)", duration: "1h", enrolled: 248, completion: 96 },
];

export default function PerformancePage() {
  return (
    <div className="space-y-6">
      <section className="rounded-xl border border-line bg-surface p-6">
        <h2 className="font-semibold">Metas ativas</h2>
        <ul className="mt-4 space-y-4">
          {GOALS.map((g) => (
            <li key={g.title}>
              <div className="flex items-center justify-between gap-4 text-sm">
                <div>
                  <p className="font-medium">{g.title}</p>
                  <p className="text-xs text-ink-muted">{g.owner}</p>
                </div>
                <span className="shrink-0 font-semibold tabular-nums">{g.progress}%</span>
              </div>
              {/* Barra de progresso: valor também em texto, nunca só visual */}
              <div className="mt-2 h-2 overflow-hidden rounded-full bg-surface-raised" role="presentation">
                <div className="h-full rounded-full bg-brand-600" style={{ width: `${g.progress}%` }} />
              </div>
            </li>
          ))}
        </ul>
      </section>

      <div className="grid gap-6 xl:grid-cols-2">
        <section className="rounded-xl border border-line bg-surface p-6">
          <h2 className="font-semibold">Ciclos de avaliação</h2>
          <ul className="mt-4 divide-y divide-line">
            {REVIEWS.map((r) => (
              <li key={r.cycle} className="flex items-center justify-between gap-3 py-3">
                <div>
                  <p className="text-sm font-medium">{r.cycle} — {r.type}</p>
                  <p className="text-xs text-ink-muted">{r.done}</p>
                </div>
                <StatusBadge tone={r.tone}>{r.status}</StatusBadge>
              </li>
            ))}
          </ul>
        </section>

        <section className="rounded-xl border border-line bg-surface p-6">
          <h2 className="font-semibold">Treinamentos</h2>
          <ul className="mt-4 divide-y divide-line">
            {TRAININGS.map((t) => (
              <li key={t.title} className="flex items-center justify-between gap-3 py-3">
                <div>
                  <p className="text-sm font-medium">{t.title}</p>
                  <p className="text-xs text-ink-muted">{t.duration} · {t.enrolled} inscritos</p>
                </div>
                <span className="text-sm font-semibold tabular-nums">{t.completion}% concluído</span>
              </li>
            ))}
          </ul>
        </section>
      </div>
    </div>
  );
}
