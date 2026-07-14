<?php
$title = 'Controle de Ponto';
$active = 'ponto.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

use App\Middleware\Can;

$card = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
$input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800';
$fmtMin = function (int $minutes): string {
    $sign = $minutes < 0 ? '−' : '+';
    $abs = abs($minutes);

    return sprintf('%s%dh %02dmin', $sign, intdiv($abs, 60), $abs % 60);
};
?>
      <div class="grid gap-6 xl:grid-cols-3">
        <!-- Registros -->
        <section class="<?= $card ?> overflow-x-auto xl:col-span-2">
          <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-bold">Registros recentes</h2></div>
          <table class="w-full min-w-[680px] text-sm">
            <thead class="border-b border-slate-200 text-left dark:border-slate-800">
              <tr><th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Colaborador</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Data</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Entrada · Almoço · Saída</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Trabalhado</th>
                <th class="px-5 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <?php foreach ($records as $r): $worked = $clock->workedMinutes($r); ?>
                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                  <td class="px-5 py-3.5 font-semibold"><?= e($r['full_name']) ?></td>
                  <td class="px-5 py-3.5 tabular-nums"><?= br_date($r['work_date']) ?></td>
                  <td class="px-5 py-3.5 tabular-nums text-slate-500 dark:text-slate-400">
                    <?= substr((string) $r['clock_in'], 0, 5) ?: '—' ?> · <?= substr((string) $r['lunch_out'], 0, 5) ?: '—' ?>–<?= substr((string) $r['lunch_in'], 0, 5) ?: '—' ?> · <?= substr((string) $r['clock_out'], 0, 5) ?: '—' ?>
                  </td>
                  <td class="px-5 py-3.5 tabular-nums"><?= $worked !== null ? sprintf('%dh %02dmin', intdiv($worked, 60), $worked % 60) : '—' ?></td>
                  <td class="px-5 py-3.5 text-right">
                    <?php if ($r['status'] === 'recorded' && Can::allowed('time:approve')): ?>
                      <form method="post" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="record_id" value="<?= (int) $r['id'] ?>">
                        <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Aprovar</button>
                      </form>
                    <?php elseif ($r['status'] === 'approved'): ?>
                      <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">Aprovado</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (! $records): ?><tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">Nenhum registro ainda.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </section>

        <div class="space-y-6">
          <!-- Registro manual -->
          <section class="<?= $card ?> p-6">
            <h2 class="font-bold"><i class="fa-solid fa-fingerprint mr-2 text-blue-500" aria-hidden="true"></i>Registro manual</h2>
            <form method="post" class="mt-4 space-y-3 text-sm">
              <?= csrf_field() ?><input type="hidden" name="action" value="register">
              <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Colaborador *</span>
                <select name="employee_id" required class="<?= $input ?>">
                  <?php foreach ($employees as $emp): ?><option value="<?= (int) $emp['id'] ?>"><?= e($emp['full_name']) ?></option><?php endforeach; ?>
                </select></label>
              <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Data *</span>
                <input name="work_date" type="date" required value="<?= date('Y-m-d') ?>" class="<?= $input ?>"></label>
              <div class="grid grid-cols-2 gap-3">
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Entrada</span><input name="clock_in" type="time" class="<?= $input ?>"></label>
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Saída almoço</span><input name="lunch_out" type="time" class="<?= $input ?>"></label>
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Retorno</span><input name="lunch_in" type="time" class="<?= $input ?>"></label>
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Saída</span><input name="clock_out" type="time" class="<?= $input ?>"></label>
              </div>
              <button class="w-full rounded-xl bg-blue-600 py-2.5 font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">Registrar</button>
            </form>
          </section>

          <!-- Banco de horas -->
          <section class="<?= $card ?> p-6">
            <h2 class="font-bold"><i class="fa-solid fa-hourglass-half mr-2 text-blue-500" aria-hidden="true"></i>Banco de horas</h2>
            <p class="mt-1 text-xs text-slate-400">Crédito/débito lançado na aprovação do dia (vs. jornada da escala).</p>
            <ul class="mt-3 space-y-2 text-sm">
              <?php foreach ($balances as $b): $bal = (int) $b['balance']; ?>
                <li class="flex items-center justify-between rounded-xl bg-slate-50 px-3.5 py-2.5 dark:bg-slate-800">
                  <span class="font-medium"><?= e($b['full_name']) ?></span>
                  <span class="font-bold tabular-nums <?= $bal >= 0 ? 'text-emerald-600' : 'text-red-500' ?>"><?= $fmtMin($bal) ?></span>
                </li>
              <?php endforeach; ?>
              <?php if (! $balances): ?><li class="py-4 text-center text-slate-400">Sem lançamentos ainda.</li><?php endif; ?>
            </ul>
          </section>
        </div>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
