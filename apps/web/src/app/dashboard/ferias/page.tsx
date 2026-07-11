import { StatusBadge } from "@/components/status-badge";

/** Gestão de férias: solicitações + calendário da equipe (demonstração). */
const REQUESTS = [
  { name: "Ana Souza", period: "10/08 – 24/08", days: 15, sell: 0, status: "Aguardando aprovação", tone: "warning" as const },
  { name: "Helena Castro", period: "01/09 – 30/09", days: 30, sell: 0, status: "Aguardando aprovação", tone: "warning" as const },
  { name: "Bruno Ferreira", period: "01/07 – 20/07", days: 20, sell: 10, status: "Em andamento", tone: "brand" as const },
  { name: "Eduardo Nunes", period: "12/05 – 26/05", days: 15, sell: 0, status: "Concluídas", tone: "neutral" as const },
];

const ALERTS = [
  { icon: "⚠️", text: "Fernanda Alves: período aquisitivo vence em 45 dias — férias precisam ser agendadas." },
  { icon: "📅", text: "3 colaboradores do Comercial com férias sobrepostas em setembro." },
];

const MONTHS = ["Jul", "Ago", "Set", "Out"];
const CALENDAR = [
  { name: "Bruno Ferreira", bars: [{ m: 0, label: "01–20/07" }] },
  { name: "Ana Souza", bars: [{ m: 1, label: "10–24/08" }] },
  { name: "Helena Castro", bars: [{ m: 2, label: "01–30/09" }] },
];

export default function VacationsPage() {
  return (
    <div className="space-y-6">
      {/* Alertas automáticos */}
      <div className="space-y-2">
        {ALERTS.map((a) => (
          <div key={a.text} className="flex items-center gap-3 rounded-xl border border-line bg-surface px-5 py-3 text-sm">
            <span aria-hidden>{a.icon}</span>
            {a.text}
          </div>
        ))}
      </div>

      <section className="overflow-x-auto rounded-xl border border-line bg-surface">
        <div className="flex items-center justify-between border-b border-line px-6 py-4">
          <h2 className="font-semibold">Solicitações</h2>
          <button className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-700">
            + Solicitar férias
          </button>
        </div>
        <table className="w-full text-left text-sm">
          <thead>
            <tr className="border-b border-line text-xs uppercase tracking-wide text-ink-muted">
              <th className="px-5 py-3 font-medium">Colaborador</th>
              <th className="px-5 py-3 font-medium">Período</th>
              <th className="px-5 py-3 font-medium">Dias</th>
              <th className="px-5 py-3 font-medium">Abono</th>
              <th className="px-5 py-3 font-medium">Status</th>
              <th className="px-5 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {REQUESTS.map((r) => (
              <tr key={r.name} className="transition-colors hover:bg-surface-raised">
                <td className="px-5 py-3.5 font-medium">{r.name}</td>
                <td className="px-5 py-3.5 tabular-nums text-ink-muted">{r.period}</td>
                <td className="px-5 py-3.5 tabular-nums">{r.days}</td>
                <td className="px-5 py-3.5 tabular-nums text-ink-muted">{r.sell ? `${r.sell} dias` : "—"}</td>
                <td className="px-5 py-3.5"><StatusBadge tone={r.tone}>{r.status}</StatusBadge></td>
                <td className="px-5 py-3.5 text-right">
                  {r.tone === "warning" && (
                    <button className="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-brand-700">
                      Aprovar
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>

      {/* Calendário de férias (visão trimestral simplificada) */}
      <section className="rounded-xl border border-line bg-surface p-6">
        <h2 className="font-semibold">Calendário da equipe</h2>
        <div className="mt-4 overflow-x-auto">
          <div className="min-w-[560px]">
            <div className="grid grid-cols-[160px_repeat(4,1fr)] gap-y-3 text-sm">
              <span />
              {MONTHS.map((m) => (
                <span key={m} className="text-center text-xs font-medium uppercase tracking-wide text-ink-muted">{m}</span>
              ))}
              {CALENDAR.map((row) => (
                <div key={row.name} className="contents">
                  <span className="truncate pr-3 font-medium">{row.name}</span>
                  {MONTHS.map((_, mi) => {
                    const bar = row.bars.find((b) => b.m === mi);
                    return (
                      <div key={mi} className="relative h-7 rounded-md bg-surface-raised">
                        {bar && (
                          <span className="absolute inset-y-0 left-1 right-1 flex items-center justify-center rounded-md bg-brand-600 text-[11px] font-medium text-white">
                            {bar.label}
                          </span>
                        )}
                      </div>
                    );
                  })}
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
