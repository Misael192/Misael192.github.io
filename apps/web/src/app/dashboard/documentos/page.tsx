import { StatusBadge } from "@/components/status-badge";

/** GED — Gestão Eletrônica de Documentos (dados de demonstração). */
const FOLDERS = [
  { icon: "📁", name: "Contratos", count: 248 },
  { icon: "📁", name: "Admissões", count: 96 },
  { icon: "📁", name: "Holerites", count: 2976 },
  { icon: "📁", name: "Atestados", count: 154 },
  { icon: "📁", name: "Políticas internas", count: 12 },
  { icon: "📁", name: "Certificados", count: 87 },
];

const RECENT = [
  { name: "Contrato de trabalho — Carlos Lima.pdf", owner: "Carlos Lima", version: "v2", status: "Aguardando assinatura", tone: "warning" as const, date: "10/07" },
  { name: "Aviso de férias — Ana Souza.pdf", owner: "Ana Souza", version: "v1", status: "Assinado", tone: "success" as const, date: "09/07" },
  { name: "Política de home office 2026.pdf", owner: "Empresa", version: "v4", status: "Publicado", tone: "brand" as const, date: "08/07" },
  { name: "Atestado médico — Eduardo Nunes.pdf", owner: "Eduardo Nunes", version: "v1", status: "Em análise", tone: "neutral" as const, date: "07/07" },
];

export default function DocumentsPage() {
  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-ink-muted">
          Armazenamento em nuvem · versões controladas · permissões por usuário
        </p>
        <button className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-700">
          ↑ Enviar documento
        </button>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {FOLDERS.map((f) => (
          <button
            key={f.name}
            className="flex items-center justify-between rounded-xl border border-line bg-surface p-5 text-left transition-all duration-150 ease-brand hover:-translate-y-0.5 hover:shadow-md"
          >
            <span className="flex items-center gap-3 text-sm font-medium">
              <span aria-hidden className="text-xl">{f.icon}</span>
              {f.name}
            </span>
            <span className="text-xs text-ink-muted">{f.count}</span>
          </button>
        ))}
      </div>

      <section className="overflow-x-auto rounded-xl border border-line bg-surface">
        <div className="border-b border-line px-6 py-4">
          <h2 className="font-semibold">Atividade recente</h2>
        </div>
        <table className="w-full text-left text-sm">
          <thead>
            <tr className="border-b border-line text-xs uppercase tracking-wide text-ink-muted">
              <th className="px-5 py-3 font-medium">Documento</th>
              <th className="px-5 py-3 font-medium">Colaborador</th>
              <th className="px-5 py-3 font-medium">Versão</th>
              <th className="px-5 py-3 font-medium">Status</th>
              <th className="px-5 py-3 font-medium">Data</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {RECENT.map((d) => (
              <tr key={d.name} className="transition-colors hover:bg-surface-raised">
                <td className="px-5 py-3.5 font-medium">📄 {d.name}</td>
                <td className="px-5 py-3.5 text-ink-muted">{d.owner}</td>
                <td className="px-5 py-3.5 text-ink-muted">{d.version}</td>
                <td className="px-5 py-3.5"><StatusBadge tone={d.tone}>{d.status}</StatusBadge></td>
                <td className="px-5 py-3.5 tabular-nums text-ink-muted">{d.date}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </div>
  );
}
