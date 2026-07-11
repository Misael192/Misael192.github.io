/**
 * Badge de status do design system. Estado nunca é comunicado só por cor:
 * o rótulo em texto acompanha sempre (acessibilidade).
 */
const TONES = {
  success: "text-[var(--success)] border-[var(--success)]/30 bg-[var(--success)]/10",
  warning: "text-[var(--warning)] border-[var(--warning)]/30 bg-[var(--warning)]/10",
  danger: "text-[var(--danger)] border-[var(--danger)]/30 bg-[var(--danger)]/10",
  neutral: "text-ink-muted border-line bg-surface-raised",
  brand: "text-brand-600 border-brand-300 bg-brand-50 dark:text-brand-300 dark:border-brand-700 dark:bg-brand-900",
} as const;

export function StatusBadge({ tone, children }: { tone: keyof typeof TONES; children: React.ReactNode }) {
  return (
    <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${TONES[tone]}`}>
      {children}
    </span>
  );
}
