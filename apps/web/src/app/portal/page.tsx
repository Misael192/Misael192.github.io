"use client";

import Link from "next/link";
import { useState } from "react";
import { StatusBadge } from "@/components/status-badge";
import { ThemeToggle } from "@/components/theme-toggle";

/**
 * Portal do Colaborador — autoatendimento: ponto, holerites, férias,
 * documentos, benefícios e banco de horas (dados de demonstração).
 */
const PAYSLIPS = [
  { competency: "Junho/2026", net: "R$ 4.812,33", viewed: true },
  { competency: "Maio/2026", net: "R$ 4.812,33", viewed: true },
  { competency: "Abril/2026", net: "R$ 5.104,90", viewed: true },
];

const MY_BENEFITS = ["🍽️ Vale Alimentação", "🏥 Plano de Saúde", "🦷 Odontológico", "🏋️ Academia"];

const PENDING_DOCS = [
  { name: "Acordo de banco de horas 2026.pdf", action: "Assinar" },
  { name: "Comprovante de residência atualizado", action: "Enviar" },
];

export default function EmployeePortalPage() {
  const [punches, setPunches] = useState<string[]>(["08:02", "12:01", "13:00"]);
  const nextLabel = ["Entrada", "Saída almoço", "Volta almoço", "Saída"][punches.length] ?? null;

  function punch() {
    const now = new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
    setPunches((prev) => (prev.length < 4 ? [...prev, now] : prev));
  }

  return (
    <div className="min-h-screen bg-surface-raised">
      <header className="border-b border-line bg-surface">
        <div className="mx-auto flex h-16 max-w-5xl items-center justify-between px-6">
          <Link href="/" className="flex items-center gap-2 font-bold">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white">P</span>
            Portal do Colaborador
          </Link>
          <div className="flex items-center gap-3">
            <ThemeToggle />
            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700 dark:bg-brand-900 dark:text-brand-300">
              AS
            </div>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-5xl space-y-6 px-6 py-8">
        <div>
          <h1 className="text-2xl font-bold">Olá, Ana 👋</h1>
          <p className="mt-1 text-sm text-ink-muted">Sexta-feira, 11 de julho de 2026 · Analista de DP</p>
        </div>

        <div className="grid gap-6 lg:grid-cols-3">
          {/* Registro de ponto */}
          <section className="rounded-xl border border-line bg-surface p-6 lg:col-span-2">
            <div className="flex items-center justify-between">
              <h2 className="font-semibold">Meu ponto de hoje</h2>
              <StatusBadge tone="success">Banco de horas: +2h 30min</StatusBadge>
            </div>
            <div className="mt-5 grid grid-cols-4 gap-3 text-center">
              {["Entrada", "Saída almoço", "Volta almoço", "Saída"].map((label, i) => (
                <div key={label} className="rounded-lg bg-surface-raised p-3">
                  <p className="text-xs text-ink-muted">{label}</p>
                  <p className="mt-1 text-lg font-semibold tabular-nums">{punches[i] ?? "—"}</p>
                </div>
              ))}
            </div>
            {nextLabel ? (
              <button
                onClick={punch}
                className="mt-5 w-full rounded-xl bg-brand-600 py-3.5 font-semibold text-white shadow-lg shadow-brand-600/25 transition-all duration-150 ease-brand hover:bg-brand-700"
              >
                ⏱️ Registrar {nextLabel.toLowerCase()}
              </button>
            ) : (
              <p className="mt-5 text-center text-sm text-ink-muted">Jornada de hoje completa ✅</p>
            )}
          </section>

          {/* Férias */}
          <section className="rounded-xl border border-line bg-surface p-6">
            <h2 className="font-semibold">Minhas férias</h2>
            <p className="mt-3 text-3xl font-bold">22 dias</p>
            <p className="text-xs text-ink-muted">disponíveis · período vence em 04/2027</p>
            <div className="mt-3 text-sm">
              <StatusBadge tone="warning">10–24/08 aguardando gestor</StatusBadge>
            </div>
            <button className="mt-4 w-full rounded-lg border border-line py-2.5 text-sm font-medium text-ink-muted transition-colors hover:border-brand-500 hover:text-brand-600">
              Solicitar férias
            </button>
          </section>
        </div>

        <div className="grid gap-6 lg:grid-cols-3">
          {/* Holerites */}
          <section className="rounded-xl border border-line bg-surface p-6">
            <h2 className="font-semibold">Holerites</h2>
            <ul className="mt-3 divide-y divide-line">
              {PAYSLIPS.map((p) => (
                <li key={p.competency} className="flex items-center justify-between py-2.5 text-sm">
                  <div>
                    <p className="font-medium">{p.competency}</p>
                    <p className="text-xs text-ink-muted">Líquido: {p.net}</p>
                  </div>
                  <button className="text-xs font-medium text-brand-600 hover:underline">Baixar PDF</button>
                </li>
              ))}
            </ul>
          </section>

          {/* Documentos pendentes */}
          <section className="rounded-xl border border-line bg-surface p-6">
            <h2 className="font-semibold">Pendências</h2>
            <ul className="mt-3 space-y-3">
              {PENDING_DOCS.map((d) => (
                <li key={d.name} className="flex items-center justify-between gap-3 rounded-lg bg-surface-raised p-3 text-sm">
                  <span className="min-w-0 truncate">📄 {d.name}</span>
                  <button className="shrink-0 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-brand-700">
                    {d.action}
                  </button>
                </li>
              ))}
            </ul>
            <p className="mt-3 text-xs text-ink-muted">Assinaturas com validade jurídica e trilha de auditoria.</p>
          </section>

          {/* Benefícios e cursos */}
          <section className="rounded-xl border border-line bg-surface p-6">
            <h2 className="font-semibold">Meus benefícios</h2>
            <ul className="mt-3 space-y-2 text-sm">
              {MY_BENEFITS.map((b) => (
                <li key={b} className="rounded-lg bg-surface-raised px-3 py-2">{b}</li>
              ))}
            </ul>
            <div className="mt-4 rounded-lg border border-line p-3 text-sm">
              🎓 <span className="font-medium">LGPD para RH e DP</span>
              <p className="mt-1 text-xs text-ink-muted">Seu curso está 60% concluído — continue de onde parou.</p>
            </div>
          </section>
        </div>
      </main>
    </div>
  );
}
