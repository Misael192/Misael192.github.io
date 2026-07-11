import Link from "next/link";
import { ThemeToggle } from "@/components/theme-toggle";

const NAV = [
  { href: "/dashboard", label: "Dashboard", icon: "📊" },
  { href: "#", label: "Colaboradores", icon: "👥" },
  { href: "#", label: "Ponto", icon: "⏱️" },
  { href: "#", label: "Férias", icon: "🏖️" },
  { href: "#", label: "Documentos", icon: "🗂️" },
  { href: "#", label: "Recrutamento", icon: "🎯" },
  { href: "#", label: "Benefícios", icon: "🎁" },
  { href: "#", label: "Desempenho", icon: "📈" },
  { href: "#", label: "Assistente IA", icon: "🤖" },
  { href: "#", label: "Configurações", icon: "⚙️" },
];

/** Shell dos painéis (admin/RH/DP/gestor): sidebar fixa + header. */
export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen bg-surface-raised">
      <aside className="hidden w-60 shrink-0 flex-col border-r border-line bg-surface lg:flex">
        <Link href="/" className="flex h-16 items-center gap-2 border-b border-line px-5 font-bold">
          <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white">P</span>
          PeopleFlow
        </Link>
        <nav className="flex-1 space-y-1 p-3">
          {NAV.map((item) => (
            <Link
              key={item.label}
              href={item.href}
              className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors duration-150 ease-brand ${
                item.href === "/dashboard"
                  ? "bg-brand-50 font-medium text-brand-700 dark:bg-brand-900 dark:text-brand-300"
                  : "text-ink-muted hover:bg-surface-raised hover:text-ink"
              }`}
            >
              <span aria-hidden>{item.icon}</span>
              {item.label}
            </Link>
          ))}
        </nav>
        <div className="border-t border-line p-4 text-xs text-ink-muted">
          Empresa Demonstração · Plano Business
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-16 items-center justify-between border-b border-line bg-surface px-6">
          <h1 className="font-semibold">Visão geral</h1>
          <div className="flex items-center gap-3">
            <ThemeToggle />
            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700 dark:bg-brand-900 dark:text-brand-300">
              MA
            </div>
          </div>
        </header>
        <main className="flex-1 p-6">{children}</main>
      </div>
    </div>
  );
}
