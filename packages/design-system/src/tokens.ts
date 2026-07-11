/**
 * PeopleFlow Design System — TOKENS (fonte única de verdade, ADR-007).
 *
 * Estes tokens são exportados como TS (para lógica/JS) e materializados como
 * CSS variables no globals.css do app web. Componentes NUNCA usam valores
 * crus — sempre tokens. Trocar a base de componentes não muda a identidade.
 */

export const color = {
  // Marca — azul-índigo profundo com acento violeta (referências: Stripe/Notion).
  brand: {
    50: "#eef2ff", 100: "#e0e7ff", 200: "#c7d2fe", 300: "#a5b4fc", 400: "#818cf8",
    500: "#6366f1", 600: "#4f46e5", 700: "#4338ca", 800: "#3730a3", 900: "#312e81",
  },
  // Semânticas (mapeadas para light/dark no CSS)
  success: "#10b981",
  warning: "#f59e0b",
  danger: "#ef4444",
  info: "#0ea5e9",
} as const;

/** Escala tipográfica modular (1.250 — major third), base 16px. */
export const typography = {
  fontSans: `'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif`,
  fontMono: `'JetBrains Mono', ui-monospace, monospace`,
  size: {
    xs: "0.75rem", sm: "0.875rem", base: "1rem", lg: "1.125rem",
    xl: "1.25rem", "2xl": "1.563rem", "3xl": "1.953rem", "4xl": "2.441rem", "5xl": "3.052rem",
  },
  weight: { regular: 400, medium: 500, semibold: 600, bold: 700 },
  lineHeight: { tight: 1.2, normal: 1.5, relaxed: 1.7 },
} as const;

/** Espaçamento em grid de 4px — nenhum valor fora da escala. */
export const space = {
  0: "0", 1: "0.25rem", 2: "0.5rem", 3: "0.75rem", 4: "1rem", 5: "1.25rem",
  6: "1.5rem", 8: "2rem", 10: "2.5rem", 12: "3rem", 16: "4rem", 20: "5rem", 24: "6rem",
} as const;

/** Grid de layout: 12 colunas, container máximo 1280px. */
export const grid = {
  columns: 12,
  maxWidth: "80rem",
  gutter: space[6],
  breakpoints: { sm: "640px", md: "768px", lg: "1024px", xl: "1280px", "2xl": "1536px" },
} as const;

export const radius = {
  sm: "0.375rem", md: "0.5rem", lg: "0.75rem", xl: "1rem", full: "9999px",
} as const;

export const shadow = {
  sm: "0 1px 2px 0 rgb(0 0 0 / 0.05)",
  md: "0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.05)",
  lg: "0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.05)",
} as const;

/**
 * Motion guidelines:
 *  - durações: fast (micro-interações), base (transições), slow (entradas de página)
 *  - animar apenas transform/opacity (60fps garantido)
 *  - sempre respeitar prefers-reduced-motion
 */
export const motion = {
  duration: { fast: "150ms", base: "250ms", slow: "400ms" },
  easing: "cubic-bezier(0.2, 0, 0, 1)",
} as const;

export const zIndex = {
  dropdown: 50, sticky: 100, overlay: 200, modal: 300, toast: 400,
} as const;
