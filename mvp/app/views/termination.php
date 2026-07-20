<?php
$title = 'Rescisão';
$active = 'rescisao.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

$card = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
$field = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800';
$money = fn (int $cents): string => 'R$ '.number_format($cents / 100, 2, ',', '.');
?>
      <div class="grid gap-6 xl:grid-cols-3">
        <!-- Formulário -->
        <section class="<?= $card ?> h-fit p-6">
          <h2 class="font-bold"><i class="fa-solid fa-user-slash mr-2 text-blue-500" aria-hidden="true"></i>Calcular rescisão</h2>
          <p class="mt-1 text-xs text-slate-400">Primeiro <strong>simule</strong> e confira as verbas; só então efetive — o colaborador é desligado e o termo fica registrado.</p>
          <form method="post" class="mt-4 space-y-3 text-sm">
            <?= csrf_field() ?>
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Colaborador *</span>
              <select name="employee_id" required class="<?= $field ?>">
                <option value="">Selecione…</option>
                <?php foreach ($employees as $emp): ?>
                  <option value="<?= (int) $emp['id'] ?>"<?= (int) $input['employee_id'] === (int) $emp['id'] ? ' selected' : '' ?>><?= e($emp['full_name']) ?></option>
                <?php endforeach; ?>
              </select></label>
            <div class="grid grid-cols-2 gap-3">
              <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Data do desligamento *</span>
                <input name="date" type="date" required value="<?= e($input['date']) ?>" class="<?= $field ?>"></label>
              <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Férias vencidas (dias)</span>
                <input name="pending_vacation_days" type="number" min="0" max="30" value="<?= e($input['pending_vacation_days']) ?>" class="<?= $field ?>"></label>
            </div>
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Modalidade *</span>
              <select name="type" class="<?= $field ?>">
                <?php foreach ($types as $key => $label): ?><option value="<?= e($key) ?>"<?= $input['type'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
              </select></label>
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Aviso prévio *</span>
              <select name="notice" class="<?= $field ?>">
                <?php foreach ($notices as $key => $label): ?><option value="<?= e($key) ?>"<?= $input['notice'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
              </select></label>
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Saldo FGTS p/ multa (R$)</span>
              <input name="fgts_balance" placeholder="10.000,00" inputmode="decimal" value="<?= e($input['fgts_balance']) ?>" class="<?= $field ?>"></label>
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Motivo</span>
              <input name="reason" maxlength="255" value="<?= e($input['reason']) ?>" class="<?= $field ?>"></label>
            <button name="action" value="simulate" class="w-full rounded-xl bg-blue-600 py-2.5 font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">
              <i class="fa-solid fa-calculator mr-1.5" aria-hidden="true"></i>Simular verbas</button>
          </form>
        </section>

        <!-- Simulação -->
        <section class="xl:col-span-2 space-y-6">
          <?php if ($simulation !== null): ?>
            <div class="<?= $card ?> overflow-x-auto">
              <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                <div>
                  <h2 class="font-bold">Simulação — <?= e($simulation['employee']['full_name']) ?></h2>
                  <p class="text-[11px] text-slate-400"><?= e($types[$input['type']]) ?> · <?= e($notices[$input['notice']]) ?> · desligamento em <?= br_date($input['date']) ?> · nada foi gravado ainda</p>
                </div>
                <form method="post" onsubmit="return confirm('Efetivar a rescisão? O colaborador será desligado e o termo registrado — a ação fica na auditoria.')">
                  <?= csrf_field() ?>
                  <?php foreach (['employee_id', 'date', 'type', 'notice', 'fgts_balance', 'pending_vacation_days', 'reason'] as $f): ?>
                    <input type="hidden" name="<?= $f ?>" value="<?= e((string) $input[$f]) ?>">
                  <?php endforeach; ?>
                  <button name="action" value="confirm" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-red-600/25 hover:bg-red-700">
                    <i class="fa-solid fa-file-signature mr-1.5" aria-hidden="true"></i>Efetivar rescisão</button>
                </form>
              </div>
              <table class="w-full min-w-[520px] text-sm">
                <thead class="border-b border-slate-200 text-left dark:border-slate-800">
                  <tr><th class="px-6 py-2.5 text-xs font-bold uppercase text-slate-400">Verba</th>
                    <th class="px-6 py-2.5 text-right text-xs font-bold uppercase text-slate-400">Proventos</th>
                    <th class="px-6 py-2.5 text-right text-xs font-bold uppercase text-slate-400">Descontos</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <?php foreach ($simulation['items'] as $i): ?>
                    <tr>
                      <td class="px-6 py-2.5"><?= e($i['description']) ?></td>
                      <td class="px-6 py-2.5 text-right tabular-nums"><?= $i['type'] === 'earning' ? $money($i['amount']) : '' ?></td>
                      <td class="px-6 py-2.5 text-right tabular-nums text-red-600 dark:text-red-400"><?= $i['type'] === 'deduction' ? $money($i['amount']) : '' ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr class="border-t-2 border-slate-200 font-bold dark:border-slate-700">
                    <td class="px-6 py-3 text-xs uppercase text-slate-400">Totais</td>
                    <td class="px-6 py-3 text-right tabular-nums"><?= $money($simulation['gross']) ?></td>
                    <td class="px-6 py-3 text-right tabular-nums text-red-600 dark:text-red-400"><?= $money($simulation['deductions']) ?></td>
                  </tr>
                  <tr>
                    <td class="px-6 pb-4 text-xs font-bold uppercase text-slate-400">Líquido a pagar</td>
                    <td colspan="2" class="px-6 pb-4 text-right text-xl font-extrabold tabular-nums text-emerald-600 dark:text-emerald-400"><?= $money($simulation['net']) ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          <?php else: ?>
            <div class="<?= $card ?> flex flex-col items-center justify-center px-6 py-16 text-center text-slate-400">
              <i class="fa-solid fa-scale-balanced mb-3 text-3xl" aria-hidden="true"></i>
              <p class="text-sm">Preencha o formulário e clique em <strong>Simular verbas</strong>.<br>
              <span class="text-xs">Saldo de salário, aviso prévio (Lei 12.506), férias + 1/3, 13º proporcional e multa do FGTS entram conforme a modalidade.</span></p>
            </div>
          <?php endif; ?>

          <!-- Histórico -->
          <?php if ($history): ?>
            <div class="<?= $card ?> overflow-x-auto">
              <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-bold">Rescisões efetivadas</h2></div>
              <table class="w-full min-w-[520px] text-sm">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <?php foreach ($history as $h): ?>
                    <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                      <td class="px-6 py-3 font-semibold"><?= e($h['full_name']) ?></td>
                      <td class="px-6 py-3 text-slate-500 dark:text-slate-400"><?= e($types[$h['type']] ?? $h['type']) ?></td>
                      <td class="px-6 py-3 tabular-nums text-slate-500 dark:text-slate-400"><?= br_date($h['termination_date']) ?></td>
                      <td class="px-6 py-3 text-right font-bold tabular-nums"><?= $h['net_cents'] !== null ? $money((int) $h['net_cents']) : '—' ?></td>
                      <td class="px-6 py-3 text-right">
                        <?php if ($h['payroll_id']): ?>
                          <a href="holerite.php?id=<?= (int) $h['payroll_id'] ?>" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold hover:border-blue-400 hover:text-blue-600 dark:border-slate-700">Termo</a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
