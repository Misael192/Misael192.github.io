"use client";

import { useEffect, useState } from "react";

/** Alterna claro/escuro gravando em localStorage + data-theme no <html>. */
export function ThemeToggle() {
  const [theme, setTheme] = useState<"light" | "dark">("light");

  useEffect(() => {
    const stored = localStorage.getItem("pf-theme");
    const system = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    setTheme((stored as "light" | "dark") ?? system);
  }, []);

  function toggle() {
    const next = theme === "dark" ? "light" : "dark";
    setTheme(next);
    document.documentElement.dataset.theme = next;
    localStorage.setItem("pf-theme", next);
  }

  return (
    <button
      onClick={toggle}
      aria-label="Alternar tema"
      className="rounded-lg border border-line bg-surface-raised px-3 py-2 text-sm text-ink-muted transition-colors duration-150 ease-brand hover:text-ink"
    >
      {theme === "dark" ? "☀️" : "🌙"}
    </button>
  );
}
