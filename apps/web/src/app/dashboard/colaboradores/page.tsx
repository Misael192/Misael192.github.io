"use client";

import { useState } from "react";
import { StatusBadge } from "@/components/status-badge";

/**
 * Listagem de colaboradores (dados de demonstração; na Fase 2 esta tela
 * consome GET /api/v1/employees com paginação por cursor).
 */
const EMPLOYEES = [
  { name: "Ana Souza", role: "Analista de DP", dept: "Departamento Pessoal", status: "Ativo", tone: "success" as const, since: "03/2022" },
  { name: "Bruno Ferreira", role: "Desenvolvedor Sênior", dept: "Tecnologia", status: "Férias", tone: "brand" as const, since: "08/2021" },
  { name: "Carlos Lima", role: "Assistente Comercial", dept: "Comercial", status: "Em admissão", tone: "warning" as const, since: "07/2026" },
  { name: "Daniela Rocha", role: "Coordenadora de RH", dept: "Recursos Humanos", status: "Ativo", tone: "success" as const, since: "01/2020" },
  { name: "Eduardo Nunes", role: "Analista Financeiro", dept: "Financeiro", status: "Afastado", tone: "neutral" as const, since: "05/2023" },
  { name: "Fernanda Alves", role: "Designer de Produto", dept: "Tecnologia", status: "Ativo", tone: "success" as const, since: "11/2024" },
  { name: "Gabriel Santos", role: "Vendedor Externo", dept: "Comercial", status: "Ativo", tone: "success" as const, since: "02/2025" },
  { name: "Helena Castro", role: "Gerente Comercial", dept: "Comercial", status: "Ativo", tone: "success" as const, since: "06/2019" },
];

export default function EmployeesPage() {
  const [query, setQuery] = useState("");
  const filtered = EMPLOYEES.filter((e) =>
    `${e.name} ${e.role} ${e.dept}`.toLowerCase().includes(query.toLowerCase()),
  );

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Buscar por nome, cargo ou departamento…"
          className="w-full max-w-sm rounded-lg border border-line bg-surface px-3.5 py-2 text-sm outline-none transition-colors focus:border-brand-500"
        />
        <button className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-700">
          + Nova admissão
        </button>
      </div>

      <div className="overflow-x-auto rounded-xl border border-line bg-surface">
        <table className="w-full text-left text-sm">
          <thead>
            <tr className="border-b border-line text-xs uppercase tracking-wide text-ink-muted">
              <th className="px-5 py-3 font-medium">Colaborador</th>
              <th className="px-5 py-3 font-medium">Departamento</th>
              <th className="px-5 py-3 font-medium">Status</th>
              <th className="px-5 py-3 font-medium">Desde</th>
              <th className="px-5 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {filtered.map((e) => (
              <tr key={e.name} className="transition-colors hover:bg-surface-raised">
                <td className="px-5 py-3.5">
                  <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700 dark:bg-brand-900 dark:text-brand-300">
                      {e.name.split(" ").map((p) => p[0]).slice(0, 2).join("")}
                    </span>
                    <div>
                      <p className="font-medium">{e.name}</p>
                      <p className="text-xs text-ink-muted">{e.role}</p>
                    </div>
                  </div>
                </td>
                <td className="px-5 py-3.5 text-ink-muted">{e.dept}</td>
                <td className="px-5 py-3.5"><StatusBadge tone={e.tone}>{e.status}</StatusBadge></td>
                <td className="px-5 py-3.5 text-ink-muted">{e.since}</td>
                <td className="px-5 py-3.5 text-right">
                  <button className="rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink-muted transition-colors hover:border-brand-500 hover:text-brand-600">
                    Ver perfil
                  </button>
                </td>
              </tr>
            ))}
            {!filtered.length && (
              <tr><td colSpan={5} className="px-5 py-10 text-center text-ink-muted">Nenhum colaborador encontrado.</td></tr>
            )}
          </tbody>
        </table>
      </div>
      <p className="text-xs text-ink-muted">{filtered.length} de {EMPLOYEES.length} colaboradores</p>
    </div>
  );
}
