import { GroupedBars } from "@/components/charts/grouped-bars";
import { TrendLine } from "@/components/charts/trend-line";

/**
 * Dashboard de RH/DP — nesta fase alimentado por dados de demonstração;
 * na Fase 4 os números vêm do módulo Analytics (/api/v1/analytics/*).
 */

const STATS = [
  { label: "Colaboradores", value: "248", delta: "+12 este mês", direction: "up" as const },
  { label: "Novas admissões", value: "14", delta: "+4 vs. mês anterior", direction: "up" as const },
  { label: "Turnover (12m)", value: "8,2%", delta: "−1,1 pp", direction: "down-good" as const },
  { label: "Absenteísmo", value: "2,4%", delta: "+0,3 pp", direction: "up-bad" as const },
];

const HIRES_VS_EXITS = [
  { label: "Fev", a: 9, b: 4 },
  { label: "Mar", a: 12, b: 6 },
  { label: "Abr", a: 8, b: 7 },
  { label: "Mai", a: 15, b: 5 },
  { label: "Jun", a: 11, b: 8 },
  { label: "Jul", a: 14, b: 3 },
];

const ABSENTEEISM = [
  { label: "Fev", value: 2.9 },
  { label: "Mar", value: 2.6 },
  { label: "Abr", value: 3.1 },
  { label: "Mai", value: 2.2 },
  { label: "Jun", value: 2.1 },
  { label: "Jul", value: 2.4 },
];

const PENDING = [
  { icon: "🏖️", text: "Férias de Ana Souza (10–24/08)", tag: "Aprovar" },
  { icon: "⏱️", text: "3 ajustes de ponto da equipe Comercial", tag: "Revisar" },
  { icon: "📋", text: "Admissão de Carlos Lima — 2 documentos", tag: "Pendente" },
  { icon: "✍️", text: "Contrato de estágio aguardando assinatura", tag: "Assinar" },
];

const JOBS = [
  { title: "Analista de DP Pleno", stage: "12 candidatos · Entrevista" },
  { title: "Desenvolvedor(a) Front-end", stage: "34 candidatos · Triagem" },
  { title: "Coordenador(a) de RH", stage: "6 candidatos · Proposta" },
];

function DeltaBadge({ delta, direction }: { delta: string; direction: string }) {
  // Estado nunca é comunicado só por cor: seta + texto acompanham.
  const good = direction === "up" || direction === "down-good";
  const arrow = direction.startsWith("down") ? "↓" : "↑";
  return (
    <span
      className="text-xs font-medium"
      style={{ color: good ? "var(--success)" : "var(--danger)" }}
    >
      {arrow} {delta}
    </span>
  );
}

export default function DashboardPage() {
  return (
    <div className="space-y-6">
      {/* Stat tiles — números em tinta de texto, não em cor de série */}
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {STATS.map((s) => (
          <div key={s.label} className="rounded-xl border border-line bg-surface p-5">
            <p className="text-sm text-ink-muted">{s.label}</p>
            <p className="mt-2 text-3xl font-bold tracking-tight">{s.value}</p>
            <div className="mt-1">
              <DeltaBadge delta={s.delta} direction={s.direction} />
            </div>
          </div>
        ))}
      </div>

      <div className="grid gap-6 xl:grid-cols-2">
        <section className="rounded-xl border border-line bg-surface p-6">
          <h2 className="font-semibold">Admissões × Desligamentos</h2>
          <p className="mb-4 mt-0.5 text-sm text-ink-muted">Últimos 6 meses</p>
          <GroupedBars
            title="Admissões e desligamentos por mês"
            seriesNames={["Admissões", "Desligamentos"]}
            data={HIRES_VS_EXITS}
          />
        </section>

        <section className="rounded-xl border border-line bg-surface p-6">
          <h2 className="font-semibold">Absenteísmo</h2>
          <p className="mb-4 mt-0.5 text-sm text-ink-muted">Percentual mensal · últimos 6 meses</p>
          <TrendLine title="Absenteísmo" data={ABSENTEEISM} unit="%" />
        </section>
      </div>

      <div className="grid gap-6 xl:grid-cols-2">
        <section className="rounded-xl border border-line bg-surface p-6">
          <h2 className="mb-4 font-semibold">Aprovações pendentes</h2>
          <ul className="divide-y divide-line">
            {PENDING.map((p) => (
              <li key={p.text} className="flex items-center justify-between gap-4 py-3">
                <span className="flex items-center gap-3 text-sm">
                  <span aria-hidden>{p.icon}</span>
                  {p.text}
                </span>
                <button className="rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink-muted transition-colors hover:border-brand-500 hover:text-brand-600">
                  {p.tag}
                </button>
              </li>
            ))}
          </ul>
        </section>

        <section className="rounded-xl border border-line bg-surface p-6">
          <h2 className="mb-4 font-semibold">Vagas abertas</h2>
          <ul className="divide-y divide-line">
            {JOBS.map((j) => (
              <li key={j.title} className="py-3">
                <p className="text-sm font-medium">{j.title}</p>
                <p className="mt-0.5 text-xs text-ink-muted">{j.stage}</p>
              </li>
            ))}
          </ul>
          <div className="mt-4 rounded-lg bg-surface-raised p-4 text-sm text-ink-muted">
            🤖 <span className="font-medium text-ink">Assistente IA:</span> 8 currículos novos
            resumidos e classificados para “Desenvolvedor(a) Front-end”.
          </div>
        </section>
      </div>
    </div>
  );
}
