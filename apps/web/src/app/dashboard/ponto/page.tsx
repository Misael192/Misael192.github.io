import { StatusBadge } from "@/components/status-badge";

/** Controle de ponto do dia + pedidos de ajuste (dados de demonstração). */
const TODAY = [
  { name: "Ana Souza", in: "08:02", lunchOut: "12:01", lunchIn: "13:00", out: "—", balance: "+2h 30min", source: "📱 Mobile" },
  { name: "Bruno Ferreira", in: "—", lunchOut: "—", lunchIn: "—", out: "—", balance: "+8h 15min", source: "🏖️ Férias" },
  { name: "Daniela Rocha", in: "07:55", lunchOut: "12:10", lunchIn: "13:08", out: "—", balance: "−1h 05min", source: "💻 Web" },
  { name: "Fernanda Alves", in: "09:00", lunchOut: "12:30", lunchIn: "13:30", out: "—", balance: "+0h 45min", source: "📱 Mobile" },
  { name: "Gabriel Santos", in: "08:15", lunchOut: "—", lunchIn: "—", out: "—", balance: "+4h 20min", source: "📍 Geo" },
];

const ADJUSTMENTS = [
  { name: "Gabriel Santos", date: "09/07", reason: "Esqueci de registrar a saída — visita a cliente", requested: "18:10" },
  { name: "Carlos Lima", date: "08/07", reason: "Sistema fora do ar no início do expediente", requested: "08:00" },
];

export default function TimeTrackingPage() {
  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-3">
        <div className="rounded-xl border border-line bg-surface p-5">
          <p className="text-sm text-ink-muted">Presentes agora</p>
          <p className="mt-2 text-3xl font-bold">231<span className="text-base font-normal text-ink-muted"> / 248</span></p>
        </div>
        <div className="rounded-xl border border-line bg-surface p-5">
          <p className="text-sm text-ink-muted">Banco de horas (saldo geral)</p>
          <p className="mt-2 text-3xl font-bold">+412h</p>
        </div>
        <div className="rounded-xl border border-line bg-surface p-5">
          <p className="text-sm text-ink-muted">Ajustes pendentes</p>
          <p className="mt-2 text-3xl font-bold">{ADJUSTMENTS.length}</p>
        </div>
      </div>

      <section className="overflow-x-auto rounded-xl border border-line bg-surface">
        <div className="border-b border-line px-6 py-4">
          <h2 className="font-semibold">Registros de hoje</h2>
        </div>
        <table className="w-full text-left text-sm">
          <thead>
            <tr className="border-b border-line text-xs uppercase tracking-wide text-ink-muted">
              <th className="px-5 py-3 font-medium">Colaborador</th>
              <th className="px-5 py-3 font-medium">Entrada</th>
              <th className="px-5 py-3 font-medium">Almoço</th>
              <th className="px-5 py-3 font-medium">Saída</th>
              <th className="px-5 py-3 font-medium">Banco de horas</th>
              <th className="px-5 py-3 font-medium">Origem</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {TODAY.map((r) => (
              <tr key={r.name} className="transition-colors hover:bg-surface-raised">
                <td className="px-5 py-3.5 font-medium">{r.name}</td>
                <td className="px-5 py-3.5 tabular-nums">{r.in}</td>
                <td className="px-5 py-3.5 tabular-nums text-ink-muted">{r.lunchOut} – {r.lunchIn}</td>
                <td className="px-5 py-3.5 tabular-nums">{r.out}</td>
                <td className="px-5 py-3.5 tabular-nums">
                  <StatusBadge tone={r.balance.startsWith("+") ? "success" : "warning"}>{r.balance}</StatusBadge>
                </td>
                <td className="px-5 py-3.5 text-ink-muted">{r.source}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>

      <section className="rounded-xl border border-line bg-surface">
        <div className="border-b border-line px-6 py-4">
          <h2 className="font-semibold">Aprovação de ajustes</h2>
        </div>
        <ul className="divide-y divide-line">
          {ADJUSTMENTS.map((a) => (
            <li key={`${a.name}-${a.date}`} className="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
              <div>
                <p className="text-sm font-medium">{a.name} · {a.date} às {a.requested}</p>
                <p className="mt-0.5 text-xs text-ink-muted">{a.reason}</p>
              </div>
              <div className="flex gap-2">
                <button className="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-brand-700">Aprovar</button>
                <button className="rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink-muted transition-colors hover:text-ink">Rejeitar</button>
              </div>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
