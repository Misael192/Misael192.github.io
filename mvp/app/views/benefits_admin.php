<?php
$title = 'Benefícios';
$active = 'beneficios.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

$card = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
$input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800';
$money = fn (?int $cents): string => $cents === null ? '—' : 'R$ '.number_format($cents / 100, 2, ',', '.');
?>
      <div class="grid gap-6 xl:grid-cols-3">
        <!-- Benefícios ativos -->
        <section class="<?= $card ?> overflow-x-auto xl:col-span-2">
          <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-bold">Benefícios por colaborador</h2></div>
          <table class="w-full min-w-[720px] text-sm">
            <thead class="border-b border-slate-200 text-left dark:border-slate-800">
              <tr><th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Colaborador</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Benefício</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Valor</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Parte do colaborador</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Status</th>
                <th class="px-5 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <?php foreach ($benefits as $b): ?>
                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                  <td class="px-5 py-3.5 font-semibold"><?= e($b['full_name']) ?></td>
                  <td class="px-5 py-3.5"><?= e($types[$b['type']] ?? $b['type']) ?></td>
                  <td class="px-5 py-3.5 tabular-nums"><?= $money((int) $b['amount_cents']) ?></td>
                  <td class="px-5 py-3.5 tabular-nums text-slate-500 dark:text-slate-400">
                    <?php if ($b['employee_share_percent'] !== null): ?>
                      <?= rtrim(rtrim(number_format((float) $b['employee_share_percent'], 2, ',', '.'), '0'), ',') ?>%
                    <?php elseif ($b['employee_share_cents'] !== null): ?>
                      <?= $money((int) $b['employee_share_cents']) ?>
                    <?php else: ?>
                      <span class="text-xs">regra padrão da rubrica</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-5 py-3.5">
                    <?php if ($b['is_active']): ?>
                      <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">Ativo</span>
                    <?php else: ?>
                      <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Encerrado<?= $b['ends_on'] ? ' em '.br_date($b['ends_on']) : '' ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="px-5 py-3.5 text-right">
                    <?php if ($b['is_active']): ?>
                      <form method="post" onsubmit="return confirm('Encerrar este benefício? Ele deixa de entrar nas próximas folhas.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="deactivate">
                        <input type="hidden" name="benefit_id" value="<?= (int) $b['id'] ?>">
                        <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold hover:border-red-400 hover:text-red-600 dark:border-slate-700">Encerrar</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (! $benefits): ?><tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">Nenhum benefício atribuído ainda.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </section>

        <!-- Atribuir benefício -->
        <section class="<?= $card ?> h-fit p-6">
          <h2 class="font-bold"><i class="fa-solid fa-gift mr-2 text-blue-500" aria-hidden="true"></i>Atribuir benefício</h2>
          <p class="mt-1 text-xs text-slate-400">Benefícios ativos entram automaticamente no cálculo da folha (ex.: VT desconta no máximo 6% do salário, por lei).</p>
          <form method="post" class="mt-4 space-y-3 text-sm">
            <?= csrf_field() ?><input type="hidden" name="action" value="assign">
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Colaborador *</span>
              <select name="employee_id" required class="<?= $input ?>">
                <?php foreach ($employees as $emp): ?><option value="<?= (int) $emp['id'] ?>"><?= e($emp['full_name']) ?></option><?php endforeach; ?>
              </select></label>
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Tipo *</span>
              <select name="type" required class="<?= $input ?>">
                <?php foreach ($types as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
              </select></label>
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Valor mensal (R$) *</span>
              <input name="amount" required placeholder="300,00" inputmode="decimal" class="<?= $input ?>"></label>
            <div class="grid grid-cols-2 gap-3">
              <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Desconto: %</span>
                <input name="share_percent" type="number" step="0.01" min="0" max="100" placeholder="6" class="<?= $input ?>"></label>
              <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">ou valor fixo (R$)</span>
                <input name="share_fixed" placeholder="50,00" inputmode="decimal" class="<?= $input ?>"></label>
            </div>
            <p class="text-[11px] text-slate-400">Deixe os dois vazios para usar a regra padrão da rubrica (ex.: VT = 6% limitado ao custo).</p>
            <button class="w-full rounded-xl bg-blue-600 py-2.5 font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">Atribuir</button>
          </form>
        </section>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
