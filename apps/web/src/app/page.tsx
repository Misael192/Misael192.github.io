import Link from "next/link";
import { ThemeToggle } from "@/components/theme-toggle";

const MODULES = [
  { icon: "⏱️", title: "Controle de Ponto", desc: "Registro web e mobile, banco de horas, escalas, geolocalização e aprovação de ajustes." },
  { icon: "📋", title: "Admissão Digital", desc: "Checklist, upload de documentos e assinatura eletrônica — admissão sem papel." },
  { icon: "🏖️", title: "Gestão de Férias", desc: "Solicitação, aprovação pelo gestor, calendário da equipe e alertas automáticos." },
  { icon: "🗂️", title: "GED", desc: "Documentos em nuvem por colaborador, com versões, permissões e assinatura digital." },
  { icon: "🎯", title: "Recrutamento", desc: "Vagas, página Trabalhe Conosco, pipeline Kanban e avaliação de candidatos." },
  { icon: "💜", title: "Engajamento", desc: "Pesquisa de clima, reconhecimento, mural de avisos e indicadores de retenção." },
  { icon: "📈", title: "Desempenho", desc: "Metas, avaliações periódicas, plano de carreira, treinamentos e certificados." },
  { icon: "🤖", title: "Assistente de IA", desc: "Dúvidas de CLT, contratos, advertências, descrições de cargo e resumo de currículos." },
];

const PILLARS = [
  { title: "Multiempresa de verdade", desc: "Cada empresa com seus usuários, permissões e dados isolados — com Row-Level Security no banco." },
  { title: "Módulos sob medida", desc: "Ative só o que cada empresa usa. DP, RH, recrutamento e IA são módulos independentes." },
  { title: "Fluxos do seu jeito", desc: "Motor visual de workflows: aprovações, documentos e assinaturas na ordem que a sua empresa definir." },
  { title: "LGPD by design", desc: "Criptografia AES-256, MFA, trilha de auditoria completa e controle fino de permissões (RBAC)." },
];

export default function LandingPage() {
  return (
    <div className="min-h-screen bg-surface text-ink">
      {/* Header */}
      <header className="sticky top-0 z-50 border-b border-line bg-surface/80 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-6">
          <Link href="/" className="flex items-center gap-2 text-lg font-bold">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white">P</span>
            PeopleFlow
          </Link>
          <nav className="hidden items-center gap-8 text-sm text-ink-muted md:flex">
            <a href="#modulos" className="transition-colors hover:text-ink">Módulos</a>
            <a href="#plataforma" className="transition-colors hover:text-ink">Plataforma</a>
            <a href="#precos" className="transition-colors hover:text-ink">Preços</a>
          </nav>
          <div className="flex items-center gap-3">
            <ThemeToggle />
            <Link
              href="/login"
              className="rounded-lg px-4 py-2 text-sm font-medium text-ink-muted transition-colors hover:text-ink"
            >
              Entrar
            </Link>
            <Link
              href="/login"
              className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all duration-150 ease-brand hover:bg-brand-700"
            >
              Começar grátis
            </Link>
          </div>
        </div>
      </header>

      {/* Hero */}
      <section className="mx-auto max-w-7xl px-6 pb-24 pt-20 text-center">
        <span className="inline-block rounded-full border border-line bg-surface-raised px-4 py-1.5 text-sm text-ink-muted">
          ✨ DP + RH + IA em uma única plataforma
        </span>
        <h1 className="mx-auto mt-6 max-w-3xl text-5xl font-bold leading-tight tracking-tight">
          O RH da sua empresa,{" "}
          <span className="bg-gradient-to-r from-brand-500 to-brand-700 bg-clip-text text-transparent">
            fluindo
          </span>
        </h1>
        <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-ink-muted">
          Ponto, férias, admissão digital, documentos, recrutamento, desempenho e um
          assistente de IA especialista em CLT. Multiempresa, seguro e pronto para crescer
          com você — de 1 a 10.000 empresas.
        </p>
        <div className="mt-10 flex items-center justify-center gap-4">
          <Link
            href="/login"
            className="rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white shadow-lg shadow-brand-600/25 transition-all duration-150 ease-brand hover:bg-brand-700"
          >
            Experimente 14 dias grátis
          </Link>
          <a
            href="#modulos"
            className="rounded-lg border border-line px-6 py-3 font-semibold text-ink transition-colors hover:bg-surface-raised"
          >
            Conhecer os módulos
          </a>
        </div>
      </section>

      {/* Módulos */}
      <section id="modulos" className="border-t border-line bg-surface-raised py-24">
        <div className="mx-auto max-w-7xl px-6">
          <h2 className="text-center text-3xl font-bold">Tudo que DP e RH precisam</h2>
          <p className="mx-auto mt-3 max-w-xl text-center text-ink-muted">
            Módulos independentes — contrate apenas o que sua operação usa hoje e ative o resto quando precisar.
          </p>
          <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {MODULES.map((m) => (
              <div
                key={m.title}
                className="rounded-xl border border-line bg-surface p-6 transition-all duration-250 ease-brand hover:-translate-y-1 hover:shadow-lg"
              >
                <span className="text-2xl">{m.icon}</span>
                <h3 className="mt-4 font-semibold">{m.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-ink-muted">{m.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Plataforma */}
      <section id="plataforma" className="py-24">
        <div className="mx-auto max-w-7xl px-6">
          <h2 className="text-center text-3xl font-bold">Construída como plataforma</h2>
          <div className="mt-14 grid gap-10 md:grid-cols-2">
            {PILLARS.map((p) => (
              <div key={p.title} className="flex gap-4">
                <div className="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-brand-500" />
                <div>
                  <h3 className="font-semibold">{p.title}</h3>
                  <p className="mt-1 leading-relaxed text-ink-muted">{p.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA / Preços */}
      <section id="precos" className="border-t border-line bg-surface-raised py-24 text-center">
        <div className="mx-auto max-w-2xl px-6">
          <h2 className="text-3xl font-bold">Comece hoje, cresça sem trocar de sistema</h2>
          <p className="mt-4 text-ink-muted">
            Planos Starter, Business e Enterprise — do primeiro colaborador ao grupo com
            milhares. Sem taxa de implantação.
          </p>
          <Link
            href="/login"
            className="mt-8 inline-block rounded-lg bg-brand-600 px-8 py-3 font-semibold text-white shadow-lg shadow-brand-600/25 transition-all duration-150 ease-brand hover:bg-brand-700"
          >
            Criar conta gratuita
          </Link>
        </div>
      </section>

      <footer className="border-t border-line py-10 text-center text-sm text-ink-muted">
        © {new Date().getFullYear()} PeopleFlow — Plataforma HCM. LGPD compliant.
      </footer>
    </div>
  );
}
