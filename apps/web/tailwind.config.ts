import type { Config } from "tailwindcss";

/**
 * Tailwind consome os tokens do design system via CSS variables (globals.css).
 * Cores semânticas mudam com o tema; componentes nunca usam hex cru.
 */
const config: Config = {
  darkMode: ["class", '[data-theme="dark"]'],
  content: ["./src/**/*.{ts,tsx}"],
  theme: {
    extend: {
      colors: {
        brand: {
          50: "var(--brand-50)", 100: "var(--brand-100)", 200: "var(--brand-200)",
          300: "var(--brand-300)", 400: "var(--brand-400)", 500: "var(--brand-500)",
          600: "var(--brand-600)", 700: "var(--brand-700)", 800: "var(--brand-800)",
          900: "var(--brand-900)",
        },
        surface: "var(--surface)",
        "surface-raised": "var(--surface-raised)",
        ink: "var(--ink)",
        "ink-muted": "var(--ink-muted)",
        line: "var(--line)",
        success: "var(--success)",
        warning: "var(--warning)",
        danger: "var(--danger)",
      },
      fontFamily: {
        sans: ["Inter", "ui-sans-serif", "system-ui", "sans-serif"],
      },
      borderRadius: {
        md: "0.5rem", lg: "0.75rem", xl: "1rem",
      },
      transitionTimingFunction: {
        brand: "cubic-bezier(0.2, 0, 0, 1)",
      },
    },
  },
  plugins: [],
};

export default config;
