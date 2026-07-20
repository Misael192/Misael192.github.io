<?php
$title = 'Gestão de Férias';
$active = 'ferias.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

use App\Middleware\Can;

$card = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
$input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800';
$statusBadge = [
    'requested' => ['Aguardando', 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'],
    'approved' => ['Aprovada', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'],
    'rejected' => ['Rejeitada', 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400'],
];
?>
      <div class="grid gap-6 xl:grid-cols-3">
        <!-- Solicitações -->
        <section class="<?= $card ?> overflow-x-auto xl:col-span-2">
          <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-bold">Solicitações</h2></div>
          <table class="w-full min-w-[640px] text-sm">
            <thead class="border-b border-slate-200 text-left dark:border-slate-800">
              <tr><th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Colaborador</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Período</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Dias</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Status</th>
                <th class="px-5 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <?php foreach ($vacations as $v): [$sl, $sc] = $statusBadge[$v['status']]; ?>
                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                  <td class="px-5 py-3.5 font-semibold"><?= e($v['full_name']) ?></td>
                  <td class="px-5 py-3.5 tabular-nums text-slate-500 dark:text-slate-400"><?= br_date($v['start_date']) ?> – <?= br_date($v['end_date']) ?></td>
                  <td class="px-5 py-3.5 tabular-nums"><?= (int) $v['days'] ?><?= $v['sell_days'] ? ' <span class="text-xs text-slate-400">(+'.(int) $v['sell_days'].' abono)</span>' : '' ?></td>
                  <td class="px-5 py-3.5"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $sc ?>"><?= $sl ?></span>
                    <?= $v['approver'] ? '<p class="mt-0.5 text-[10px] text-slate-400">por '.e($v['approver']).'</p>' : '' ?></td>
                  <td class="px-5 py-3.5 text-right">
                    <?php if ($v['status'] === 'requested' && Can::allowed('vacations:approve')): ?>
                      <form method="post" class="inline-flex gap-1.5">
                        <?= csrf_field() ?><input type="hidden" name="vacation_id" value="<?= (int) $v['id'] ?>">
                        <button name="action" value="approve" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Aprovar</button>
                        <button name="action" value="reject" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold hover:border-red-400 hover:text-red-600 dark:border-slate-700">Rejeitar</button>
                      </form>
                    <?php elseif ($v['status'] === 'approved' && Can::allowed('payroll:manage')): ?>
                      <form method="post" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="vacation_id" value="<?= (int) $v['id'] ?>">
                        <button name="action" value="receipt" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold hover:border-blue-400 hover:text-blue-600 dark:border-slate-700">
                          <i class="fa-solid fa-file-invoice-dollar mr-1" aria-hidden="true"></i>Recibo</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (! $vacations): ?><tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">Nenhuma solicitação ainda.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </section>

        <!-- Nova solicitação -->
        <section class="<?= $card ?> h-fit p-6">
          <h2 class="font-bold"><i class="fa-solid fa-umbrella-beach mr-2 text-blue-500" aria-hidden="true"></i>Solicitar férias</h2>
          <p class="mt-1 text-xs text-slate-400">O saldo é validado contra o período aquisitivo aberto do colaborador (CLT arts. 130/134/143).</p>
          <form method="post" class="mt-4 space-y-3 text-sm">
            <?= csrf_field() ?><input type="hidden" name="action" value="request">
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Colaborador *</span>
              <select name="employee_id" required class="<?= $input ?>">
                <?php foreach ($employees as $emp): ?><option value="<?= (int) $emp['id'] ?>"><?= e($emp['full_name']) ?></option><?php endforeach; ?>
              </select></label>
            <div class="grid grid-cols-2 gap-3">
              <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Início *</span><input name="start_date" type="date" required class="<?= $input ?>"></label>
              <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Fim *</span><input name="end_date" type="date" required class="<?= $input ?>"></label>
            </div>
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Abono pecuniário (0–10 dias)</span>
              <input name="sell_days" type="number" min="0" max="10" value="0" class="<?= $input ?>"></label>
            <button class="w-full rounded-xl bg-blue-600 py-2.5 font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">Enviar solicitação</button>
          </form>
        </section>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
