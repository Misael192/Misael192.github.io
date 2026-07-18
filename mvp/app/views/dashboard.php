<?php
$title = 'Dashboard';
$active = 'dashboard.php';
$totalEmployees = array_sum($statusCounts);
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

$statusMeta = [
    'active' => ['Ativos', 'emerald'],
    'vacation' => ['Em férias', 'blue'],
    'admission' => ['Em admissão', 'amber'],
    'on_leave' => ['Afastados', 'slate'],
];
$badges = [
    'active' => ['Ativo', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'],
    'vacation' => ['Férias', 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400'],
    'admission' => ['Em admissão', 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'],
    'on_leave' => ['Afastado', 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'],
    'terminated' => ['Desligado', 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'],
];
?>
      <!-- KPIs vindos do banco -->
      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
          <div class="flex items-center justify-between text-slate-400"><p class="text-xs font-semibold uppercase tracking-wide">Colaboradores</p><i class="fa-solid fa-users" aria-hidden="true"></i></div>
          <p class="mt-2 text-3xl font-extrabold"><?= $totalEmployees ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
          <div class="flex items-center justify-between text-slate-400"><p class="text-xs font-semibold uppercase tracking-wide">Empresas</p><i class="fa-solid fa-building" aria-hidden="true"></i></div>
          <p class="mt-2 text-3xl font-extrabold"><?= $totalCompanies ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
          <div class="flex items-center justify-between text-slate-400"><p class="text-xs font-semibold uppercase tracking-wide">Usuários</p><i class="fa-solid fa-user-shield" aria-hidden="true"></i></div>
          <p class="mt-2 text-3xl font-extrabold"><?= $totalUsers ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
          <div class="flex items-center justify-between text-slate-400"><p class="text-xs font-semibold uppercase tracking-wide">Em admissão</p><i class="fa-solid fa-user-plus" aria-hidden="true"></i></div>
          <p class="mt-2 text-3xl font-extrabold text-amber-600"><?= $statusCounts['admission'] ?></p>
        </div>
      </section>

      <!-- Pendências: o que precisa de ação agora -->
      <?php $totalPending = array_sum($pending); ?>
      <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900" aria-label="Pendências">
        <div class="flex items-center justify-between">
          <h2 class="font-bold"><i class="fa-solid fa-bell mr-2 text-amber-500" aria-hidden="true"></i>Pendências</h2>
          <?php if ($totalPending > 0): ?>
            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-950 dark:text-amber-400"><?= $totalPending ?></span>
          <?php endif; ?>
        </div>
        <?php if ($totalPending === 0): ?>
          <p class="mt-3 text-sm text-slate-400"><i class="fa-solid fa-circle-check mr-1.5 text-emerald-500" aria-hidden="true"></i>Tudo em dia — nenhuma ação pendente.</p>
        <?php else: ?>
          <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <?php
            $items = [
                ['ferias.php', 'fa-umbrella-beach', $pending['vacations'], 'férias aguardando aprovação'],
                ['ponto.php', 'fa-clock', $pending['timeclock'], 'registros de ponto a aprovar'],
                ['colaboradores.php', 'fa-user-plus', $pending['admissions'], 'admissões com checklist aberto'],
            ];
            foreach ($items as [$href, $icon, $count, $label]): if ($count === 0) { continue; } ?>
              <a href="<?= e($href) ?>" class="group flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 transition-colors hover:border-amber-400 dark:border-amber-900 dark:bg-amber-950/40">
                <i class="fa-solid <?= e($icon) ?> text-amber-500" aria-hidden="true"></i>
                <span class="text-sm"><strong class="tabular-nums"><?= $count ?></strong> <?= e($label) ?></span>
                <i class="fa-solid fa-arrow-right ml-auto text-xs text-amber-400 transition-transform group-hover:translate-x-0.5" aria-hidden="true"></i>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <!-- Distribuição por status -->
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
          <h2 class="font-bold">Quadro por status</h2>
          <ul class="mt-5 space-y-4">
            <?php foreach ($statusMeta as $status => [$label, $color]):
                $count = $statusCounts[$status];
                $pct = $totalEmployees > 0 ? (int) round($count / $totalEmployees * 100) : 0; ?>
              <li>
                <div class="flex items-center justify-between text-sm">
                  <span class="font-semibold"><?= e($label) ?></span>
                  <span class="tabular-nums text-slate-500 dark:text-slate-400"><?= $count ?> · <?= $pct ?>%</span>
                </div>
                <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                  <div class="h-full rounded-full bg-<?= $color ?>-500" style="width: <?= $pct ?>%"></div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>

        <!-- Últimos colaboradores -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
          <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-bold">Últimos colaboradores</h2></div>
          <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <?php foreach ($recentEmployees as $employee): [$label, $classes] = $badges[$employee['status']] ?? $badges['active']; ?>
                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                  <td class="px-5 py-3.5">
                    <p class="font-semibold"><?= e($employee['full_name']) ?></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400"><?= e($employee['position'] ?? '—') ?> · <?= e($employee['department'] ?? 'Sem departamento') ?></p>
                  </td>
                  <td class="px-5 py-3.5 text-xs text-slate-500 dark:text-slate-400">Admissão: <?= br_date($employee['hired_at']) ?></td>
                  <td class="px-5 py-3.5 text-right"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $classes ?>"><?= e($label) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
