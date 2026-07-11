import { StatusBadge } from "@/components/status-badge";

/** Benefícios corporativos: catálogo + utilização (demonstração). */
const BENEFITS = [
  { icon: "🚌", name: "Vale Transporte", provider: "VB / RioCard", enrolled: 182, cost: "R$ 38.220/mês", active: true },
  { icon: "🍽️", name: "Vale Alimentação", provider: "Alelo", enrolled: 248, cost: "R$ 173.600/mês", active: true },
  { icon: "🏥", name: "Plano de Saúde", provider: "Unimed — Convênio empresarial", enrolled: 214, cost: "R$ 96.300/mês", active: true },
  { icon: "🦷", name: "Plano Odontológico", provider: "OdontoPrev", enrolled: 167, cost: "R$ 8.350/mês", active: true },
  { icon: "🏋️", name: "Auxílio Academia", provider: "Wellhub", enrolled: 93, cost: "R$ 7.440/mês", active: true },
  { icon: "📚", name: "Auxílio Educação", provider: "Interno", enrolled: 41, cost: "R$ 20.500/mês", active: false },
];

export default function BenefitsPage() {
  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-ink-muted">
          Custo total: <span className="font-semibold text-ink">R$ 344.410/mês</span> · 6 benefícios cadastrados
        </p>
        <button className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-700">
          + Cadastrar benefício
        </button>
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {BENEFITS.map((b) => (
          <section key={b.name} className="rounded-xl border border-line bg-surface p-5">
            <div className="flex items-start justify-between">
              <span aria-hidden className="text-2xl">{b.icon}</span>
              <StatusBadge tone={b.active ? "success" : "neutral"}>{b.active ? "Ativo" : "Suspenso"}</StatusBadge>
            </div>
            <h2 className="mt-3 font-semibold">{b.name}</h2>
            <p className="mt-0.5 text-xs text-ink-muted">{b.provider}</p>
            <dl className="mt-4 flex items-center justify-between border-t border-line pt-3 text-sm">
              <div>
                <dt className="text-xs text-ink-muted">Adesões</dt>
                <dd className="font-semibold">{b.enrolled}</dd>
              </div>
              <div className="text-right">
                <dt className="text-xs text-ink-muted">Custo</dt>
                <dd className="font-semibold">{b.cost}</dd>
              </div>
            </dl>
          </section>
        ))}
      </div>
    </div>
  );
}
