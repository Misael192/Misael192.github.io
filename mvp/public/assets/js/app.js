/* ════════════════════════════════════════════════════════════════════════
   PeopleFlow UI — JavaScript base (ES6, sem jQuery)
   Dark mode, reveal on scroll, contadores, navbar dinâmica, voltar ao topo,
   toasts e filtro de tabelas. Interatividade local (dropdown, modal, tabs,
   drawer, accordion) fica com Alpine.js direto no HTML.
   ════════════════════════════════════════════════════════════════════════ */

/* ── Dark mode (classe .dark no <html>, persistido em localStorage) ────── */
const PF_THEME_KEY = 'pf-theme';

export function pfApplyTheme(theme) {
  document.documentElement.classList.toggle('dark', theme === 'dark');
  localStorage.setItem(PF_THEME_KEY, theme);
}

export function pfToggleTheme() {
  const isDark = document.documentElement.classList.contains('dark');
  pfApplyTheme(isDark ? 'light' : 'dark');
}

// Exposto globalmente para uso em @click do Alpine.
window.pfToggleTheme = pfToggleTheme;

/* ── Reveal on scroll (elementos .pf-reveal) ───────────────────────────── */
function initReveal() {
  const observer = new IntersectionObserver(
    (entries) => entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('pf-visible');
        observer.unobserve(entry.target);
      }
    }),
    { threshold: 0.12 }
  );
  document.querySelectorAll('.pf-reveal').forEach((el) => observer.observe(el));
}

/* ── Contadores animados (data-counter="10000" data-suffix="+") ────────── */
function animateCounter(el) {
  const target = parseFloat(el.dataset.counter);
  const suffix = el.dataset.suffix ?? '';
  const prefix = el.dataset.prefix ?? '';
  const decimals = parseInt(el.dataset.decimals ?? '0', 10);
  const duration = 1600;
  const start = performance.now();

  const step = (now) => {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
    el.textContent = prefix + (target * eased).toLocaleString('pt-BR', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }) + suffix;
    if (progress < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

function initCounters() {
  const observer = new IntersectionObserver(
    (entries) => entries.forEach((entry) => {
      if (entry.isIntersecting) {
        animateCounter(entry.target);
        observer.unobserve(entry.target);
      }
    }),
    { threshold: 0.4 }
  );
  document.querySelectorAll('[data-counter]').forEach((el) => observer.observe(el));
}

/* ── Navbar dinâmica: ganha fundo/sombra ao rolar ──────────────────────── */
function initNavbarScroll() {
  const navbar = document.querySelector('[data-navbar]');
  if (!navbar) return;
  const update = () => navbar.classList.toggle('pf-navbar-scrolled', window.scrollY > 8);
  window.addEventListener('scroll', update, { passive: true });
  update();
}

/* ── Botão "voltar ao topo" ────────────────────────────────────────────── */
function initBackToTop() {
  const button = document.querySelector('[data-back-to-top]');
  if (!button) return;
  const update = () => button.classList.toggle('hidden', window.scrollY < 480);
  window.addEventListener('scroll', update, { passive: true });
  button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  update();
}

/* ── Toast (window.pfToast('Mensagem', 'success' | 'error' | 'info')) ──── */
window.pfToast = function pfToast(message, type = 'info') {
  const container = document.querySelector('[data-toasts]') ?? (() => {
    const div = document.createElement('div');
    div.dataset.toasts = '';
    div.className = 'fixed bottom-6 right-6 z-[100] flex flex-col gap-2';
    div.setAttribute('role', 'status');
    div.setAttribute('aria-live', 'polite');
    document.body.appendChild(div);
    return div;
  })();

  const icons = { success: 'fa-circle-check text-emerald-500', error: 'fa-circle-xmark text-red-500', info: 'fa-circle-info text-blue-500' };
  const toast = document.createElement('div');
  toast.className = 'flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 shadow-lg dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200';
  toast.innerHTML = `<i class="fa-solid ${icons[type] ?? icons.info}" aria-hidden="true"></i><span>${message}</span>`;
  container.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .4s'; }, 3200);
  setTimeout(() => toast.remove(), 3700);
};

/* ── Filtro de tabelas (input[data-table-filter="#idDaTabela"]) ────────── */
function initTableFilters() {
  document.querySelectorAll('[data-table-filter]').forEach((input) => {
    const table = document.querySelector(input.dataset.tableFilter);
    if (!table) return;
    input.addEventListener('input', () => {
      const term = input.value.trim().toLowerCase();
      table.querySelectorAll('tbody tr').forEach((row) => {
        row.classList.toggle('hidden', !row.textContent.toLowerCase().includes(term));
      });
    });
  });
}

/* ── Boot ──────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initReveal();
  initCounters();
  initNavbarScroll();
  initBackToTop();
  initTableFilters();
});
