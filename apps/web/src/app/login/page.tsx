"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

/**
 * Login com e-mail/senha (+ campo MFA quando exigido pela API).
 * O fluxo real chama POST /api/v1/auth/login; nesta fase o dashboard usa
 * dados de demonstração, então o submit navega direto.
 */
export default function LoginPage() {
  const router = useRouter();
  const [needsMfa, setNeedsMfa] = useState(false);
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setLoading(true);
    const form = new FormData(e.currentTarget);
    try {
      const res = await fetch("/api/v1/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-Tenant-Id": "demo" },
        body: JSON.stringify({
          email: form.get("email"),
          password: form.get("password"),
          mfaCode: form.get("mfaCode") || undefined,
        }),
      });
      if (res.status === 401) {
        const body = await res.json().catch(() => null);
        if (body?.message?.includes("MFA")) {
          setNeedsMfa(true);
          return;
        }
      }
      // Demo: segue para o dashboard mesmo sem API local rodando.
      router.push("/dashboard");
    } catch {
      router.push("/dashboard");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-surface-raised px-6">
      <div className="w-full max-w-md">
        <Link href="/" className="mb-8 flex items-center justify-center gap-2 text-lg font-bold">
          <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-white">P</span>
          PeopleFlow
        </Link>

        <div className="rounded-xl border border-line bg-surface p-8 shadow-lg">
          <h1 className="text-xl font-semibold">Entrar na sua conta</h1>
          <p className="mt-1 text-sm text-ink-muted">Acesse o painel da sua empresa</p>

          <form onSubmit={onSubmit} className="mt-6 space-y-4">
            <div>
              <label htmlFor="email" className="mb-1.5 block text-sm font-medium">E-mail corporativo</label>
              <input
                id="email" name="email" type="email" required
                placeholder="voce@empresa.com.br"
                className="w-full rounded-lg border border-line bg-surface px-3.5 py-2.5 text-sm outline-none transition-colors focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
              />
            </div>
            <div>
              <label htmlFor="password" className="mb-1.5 block text-sm font-medium">Senha</label>
              <input
                id="password" name="password" type="password" required minLength={8}
                placeholder="••••••••"
                className="w-full rounded-lg border border-line bg-surface px-3.5 py-2.5 text-sm outline-none transition-colors focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
              />
            </div>
            {needsMfa && (
              <div>
                <label htmlFor="mfaCode" className="mb-1.5 block text-sm font-medium">Código do autenticador (MFA)</label>
                <input
                  id="mfaCode" name="mfaCode" inputMode="numeric" pattern="\d{6}" maxLength={6}
                  placeholder="000000"
                  className="w-full rounded-lg border border-line bg-surface px-3.5 py-2.5 text-center text-lg tracking-[0.5em] outline-none focus:border-brand-500"
                />
              </div>
            )}
            <button
              type="submit"
              disabled={loading}
              className="w-full rounded-lg bg-brand-600 py-2.5 font-semibold text-white transition-colors duration-150 ease-brand hover:bg-brand-700 disabled:opacity-60"
            >
              {loading ? "Entrando…" : "Entrar"}
            </button>
          </form>

          <p className="mt-6 text-center text-sm text-ink-muted">
            Esqueceu a senha? <a href="#" className="font-medium text-brand-600 hover:underline">Recuperar acesso</a>
          </p>
        </div>

        <p className="mt-6 text-center text-xs text-ink-muted">
          Protegido por MFA, criptografia AES-256 e auditoria completa · LGPD
        </p>
      </div>
    </div>
  );
}
