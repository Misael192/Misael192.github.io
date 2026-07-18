<?php
/** Shell do app: sidebar + topbar. Recebe $title e $active (arquivo atual). */
$user = auth_user();
$nav = [
    ['dashboard.php', 'fa-chart-pie', 'Dashboard'],
    ['colaboradores.php', 'fa-id-badge', 'Colaboradores'],
    ['ponto.php', 'fa-clock', 'Ponto'],
    ['ferias.php', 'fa-umbrella-beach', 'Férias'],
    ['documentos.php', 'fa-folder-open', 'Documentos'],
    ['beneficios.php', 'fa-gift', 'Benefícios'],
    ['estrutura.php', 'fa-sitemap', 'Estrutura'],
    ['empresas.php', 'fa-building', 'Empresas'],
    ['usuarios.php', 'fa-users', 'Usuários'],
];
?>
<body class="bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100"
      x-data="{ mobileNav: false }">
<div class="flex min-h-screen">

  <!-- Sidebar -->
  <aside class="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 lg:flex">
    <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-4 dark:border-slate-800">
      <img src="assets/img/favicon.svg" alt="" class="h-8 w-8" width="32" height="32">
      <span class="text-lg font-bold">PeopleFlow <span class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">MVP</span></span>
    </div>
    <nav class="flex-1 space-y-1 p-3" aria-label="Menu principal">
      <?php foreach ($nav as [$href, $icon, $label]): ?>
        <a href="<?= e($href) ?>"<?= ($active ?? '') === $href ? ' aria-current="page"' : '' ?>
           class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors <?= ($active ?? '') === $href
               ? 'bg-blue-50 font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
               : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>">
          <i class="fa-solid <?= e($icon) ?> w-4 text-center" aria-hidden="true"></i><?= e($label) ?>
        </a>
      <?php endforeach; ?>
      <p class="px-3 pt-4 text-[10px] font-bold uppercase tracking-wide text-slate-400">Folha</p>
      <?php foreach ([['folha.php', 'fa-money-check-dollar', 'Folha de pagamento'],
                      ['decimo.php', 'fa-gifts', '13º salário'],
                      ['rescisao.php', 'fa-file-signature', 'Rescisão']] as [$href, $icon, $label]): ?>
        <a href="<?= e($href) ?>"<?= ($active ?? '') === $href ? ' aria-current="page"' : '' ?>
           class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors <?= ($active ?? '') === $href
               ? 'bg-blue-50 font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
               : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>">
          <i class="fa-solid <?= e($icon) ?> w-4 text-center" aria-hidden="true"></i><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="border-t border-slate-200 p-4 text-xs text-slate-400 dark:border-slate-800">
      <?= e($user['company'] ?? '') ?>
    </div>
  </aside>

  <div class="flex min-w-0 flex-1 flex-col">
    <!-- Topbar -->
    <header class="pf-glass sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 px-4 dark:border-slate-800 sm:px-6">
      <span class="font-bold lg:hidden">PeopleFlow</span>
      <div class="ml-auto flex items-center gap-2">
        <button onclick="pfToggleTheme()" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800" aria-label="Alternar tema">
          <i class="fa-solid fa-moon pf-show-light" aria-hidden="true"></i>
          <i class="fa-solid fa-sun pf-show-dark" aria-hidden="true"></i>
        </button>
        <div class="flex items-center gap-2 rounded-xl px-2 py-1.5 text-sm">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">
            <?= e(mb_strtoupper(mb_substr($user['name'] ?? '?', 0, 2))) ?>
          </span>
          <span class="hidden font-semibold sm:block"><?= e($user['name'] ?? '') ?></span>
          <span class="hidden rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400 sm:block"><?= e($user['role_name'] ?? '') ?></span>
        </div>
        <a href="logout.php" class="flex h-9 w-9 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40" aria-label="Sair" title="Sair">
          <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
        </a>
      </div>
    </header>

    <div class="px-4 pt-6 sm:px-6">
      <h1 class="text-2xl font-extrabold tracking-tight"><?= e($title ?? '') ?></h1>
    </div>

    <main class="flex-1 px-4 py-6 sm:px-6">
      <?php if ($msg = flash('success')): ?>
        <div class="mb-5 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300" role="status">
          <i class="fa-solid fa-circle-check" aria-hidden="true"></i><?= e($msg) ?>
        </div>
      <?php endif; ?>
      <?php if ($msg = flash('error')): ?>
        <div class="mb-5 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300" role="alert">
          <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i><?= e($msg) ?>
        </div>
      <?php endif; ?>
