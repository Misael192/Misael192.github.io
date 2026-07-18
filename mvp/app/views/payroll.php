<?php
$title = 'Folha de Pagamento';
$active = 'folha.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

$card = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
$input = 'rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800';
$money = fn (int $cents): string => 'R$ '.number_format($cents / 100, 2, ',', '.');

[$year, $month] = explode('-', $competency);
$prev = date('Y-m', mktime(0, 0, 0, (int) $month - 1, 1, (int) $year));
$next = date('Y-m', mktime(0, 0, 0, (int) $month + 1, 1, (int) $year));
$monthLabel = ['01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
    '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'][$month]." de {$year}";

$status = $period['status'] ?? 'open';
$statusBadge = [
    'open' => ['Aberta', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
    'calculated' => ['Calculada — em conferência', 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'],
    'closed' => ['Fechada', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'],
][$status];
?>
      <!-- Competência + fluxo -->
      <section class="<?= $card ?> p-6">
        <div class="flex flex-wrap items-center gap-4">
          <div class="flex items-center gap-2">
            <a href="folha.php?comp=<?= e($prev) ?>" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 hover:border-blue-400 dark:border-slate-700" aria-label="Mês anterior"><i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i></a>
            <div class="text-center">
              <p class="text-lg font-extrabold tracking-tight"><?= e($monthLabel) ?></p>
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Competência <?= e($competency) ?></p>
            </div>
            <a href="folha.php?comp=<?= e($next) ?>" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 hover:border-blue-400 dark:border-slate-700" aria-label="Próximo mês"><i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i></a>
          </div>
          <span class="rounded-full px-3 py-1 text-xs font-bold <?= $statusBadge[1] ?>"><?= $statusBadge[0] ?></span>

          <div class="ml-auto flex flex-wrap gap-2">
            <?php if ($status !== 'closed'): ?>
              <form method="post" action="folha.php?comp=<?= e($competency) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="calculate">
                <button class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">
                  <i class="fa-solid fa-calculator mr-1.5" aria-hidden="true"></i><?= $status === 'calculated' ? 'Recalcular' : 'Calcular folha' ?>
                </button>
              </form>
            <?php endif; ?>
            <?php if ($status === 'calculated'): ?>
              <form method="post" action="folha.php?comp=<?= e($competency) ?>" onsubmit="return confirm('Fechar a competência <?= e($competency) ?>? A folha fica imutável.')">
                <?= csrf_field() ?><input type="hidden" name="action" value="close">
                <button class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/25 hover:bg-emerald-700">
                  <i class="fa-solid fa-lock mr-1.5" aria-hidden="true"></i>Fechar competência
                </button>
              </form>
            <?php elseif ($status === 'closed'): ?>
              <form method="post" action="folha.php?comp=<?= e($competency) ?>" onsubmit="return confirm('Reabrir a competência para ajustes?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="reopen">
                <button class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold hover:border-amber-400 hover:text-amber-600 dark:border-slate-700">
                  <i class="fa-solid fa-lock-open mr-1.5" aria-hidden="true"></i>Reabrir
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
        <ol class="mt-5 flex flex-wrap items-center gap-2 text-xs text-slate-400">
          <?php foreach ([['1. Lançar eventos', true], ['2. Calcular', $status !== 'open'], ['3. Conferir', $status !== 'open'], ['4. Fechar', $status === 'closed']] as [$stepLabel, $done]): ?>
            <li class="flex items-center gap-1.5 rounded-full px-3 py-1 <?= $done ? 'bg-blue-50 font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300' : 'bg-slate-100 dark:bg-slate-800' ?>">
              <?php if ($done): ?><i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i><?php endif; ?><?= e($stepLabel) ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>

      <?php if ($payrolls): ?>
        <!-- Totais da competência -->
        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Totais">
          <?php foreach ([['Total bruto', $totals['gross'], 'fa-sack-dollar', ''], ['Descontos', $totals['deductions'], 'fa-minus', 'text-red-600 dark:text-red-400'],
              ['Total líquido', $totals['net'], 'fa-hand-holding-dollar', 'text-emerald-600 dark:text-emerald-400'], ['FGTS a depositar', $totals['fgts'], 'fa-piggy-bank', 'text-blue-600 dark:text-blue-400']] as [$label, $value, $icon, $cls]): ?>
            <div class="<?= $card ?> p-5">
              <div class="flex items-center justify-between text-slate-400"><p class="text-xs font-semibold uppercase tracking-wide"><?= e($label) ?></p><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i></div>
              <p class="mt-2 text-2xl font-extrabold tabular-nums <?= $cls ?>"><?= $money($value) ?></p>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <!-- Folhas por colaborador -->
        <section class="<?= $card ?> overflow-x-auto xl:col-span-2">
          <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-bold">Folha por colaborador</h2></div>
          <table class="w-full min-w-[680px] text-sm">
            <thead class="border-b border-slate-200 text-left dark:border-slate-800">
              <tr><th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Colaborador</th>
                <th class="px-5 py-3 text-right text-xs font-bold uppercase text-slate-400">Bruto</th>
                <th class="px-5 py-3 text-right text-xs font-bold uppercase text-slate-400">Descontos</th>
                <th class="px-5 py-3 text-right text-xs font-bold uppercase text-slate-400">Líquido</th>
                <th class="px-5 py-3 text-right text-xs font-bold uppercase text-slate-400">FGTS</th>
                <th class="px-5 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <?php foreach ($payrolls as $p): ?>
                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                  <td class="px-5 py-3.5"><p class="font-semibold"><?= e($p['full_name']) ?></p>
                    <p class="text-xs text-slate-400"><?= e($p['position_name'] ?? '—') ?> · mat. <?= e($p['registration']) ?></p></td>
                  <td class="px-5 py-3.5 text-right tabular-nums"><?= $money((int) $p['gross_cents']) ?></td>
                  <td class="px-5 py-3.5 text-right tabular-nums text-red-600 dark:text-red-400">−<?= $money((int) $p['deductions_cents']) ?></td>
                  <td class="px-5 py-3.5 text-right font-bold tabular-nums text-emerald-600 dark:text-emerald-400"><?= $money((int) $p['net_cents']) ?></td>
                  <td class="px-5 py-3.5 text-right tabular-nums text-slate-500 dark:text-slate-400"><?= $money((int) $p['fgts_cents']) ?></td>
                  <td class="px-5 py-3.5 text-right">
                    <a href="holerite.php?id=<?= (int) $p['id'] ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold hover:border-blue-400 hover:text-blue-600 dark:border-slate-700">
                      <i class="fa-solid fa-file-invoice-dollar mr-1" aria-hidden="true"></i>Holerite</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (! $payrolls): ?><tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">
                Nenhum cálculo nesta competência ainda.<br><span class="text-xs">Lance os eventos e clique em <strong>Calcular folha</strong> — ponto, faltas, benefícios e dependentes entram automaticamente.</span></td></tr><?php endif; ?>
            </tbody>
          </table>
        </section>

        <!-- Eventos manuais -->
        <section class="space-y-6">
          <div class="<?= $card ?> h-fit p-6">
            <h2 class="font-bold"><i class="fa-solid fa-plus-circle mr-2 text-blue-500" aria-hidden="true"></i>Lançar evento</h2>
            <p class="mt-1 text-xs text-slate-400">Comissão, bônus, HE avulsa ou desconto pontual desta competência. Referência = horas/dias (a engine calcula) ou informe o valor direto.</p>
            <?php if ($status === 'closed'): ?>
              <p class="mt-4 rounded-xl bg-slate-100 px-4 py-3 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400"><i class="fa-solid fa-lock mr-1.5" aria-hidden="true"></i>Competência fechada — reabra para lançar.</p>
            <?php else: ?>
              <form method="post" action="folha.php?comp=<?= e($competency) ?>" class="mt-4 space-y-3 text-sm">
                <?= csrf_field() ?><input type="hidden" name="action" value="event">
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Colaborador *</span>
                  <select name="employee_id" required class="w-full <?= $input ?>">
                    <?php foreach ($employees as $emp): ?><option value="<?= (int) $emp['id'] ?>"><?= e($emp['full_name']) ?></option><?php endforeach; ?>
                  </select></label>
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Rubrica *</span>
                  <select name="rubric_code" required class="w-full <?= $input ?>">
                    <?php foreach ($rubrics as $r): ?><option value="<?= e($r['code']) ?>"><?= e($r['code']) ?> — <?= e($r['name']) ?><?= $r['type'] === 'deduction' ? ' (desconto)' : '' ?></option><?php endforeach; ?>
                  </select></label>
                <div class="grid grid-cols-2 gap-3">
                  <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Referência (h/dias)</span>
                    <input name="reference" placeholder="10" inputmode="decimal" class="w-full <?= $input ?>"></label>
                  <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">ou valor (R$)</span>
                    <input name="amount" placeholder="500,00" inputmode="decimal" class="w-full <?= $input ?>"></label>
                </div>
                <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Observação</span>
                  <input name="notes" maxlength="160" class="w-full <?= $input ?>"></label>
                <button class="w-full rounded-xl bg-blue-600 py-2.5 font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">Lançar</button>
              </form>
            <?php endif; ?>
          </div>

          <?php if ($events): ?>
            <div class="<?= $card ?> p-6">
              <h2 class="font-bold text-sm">Eventos desta competência</h2>
              <ul class="mt-3 space-y-2 text-xs">
                <?php foreach ($events as $ev): ?>
                  <li class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
                    <span><strong><?= e($ev['full_name']) ?></strong> · <?= e($ev['rubric_name']) ?><?= $ev['notes'] ? ' — '.e($ev['notes']) : '' ?></span>
                    <span class="shrink-0 tabular-nums text-slate-500 dark:text-slate-400">
                      <?= $ev['amount_cents'] !== null ? $money((int) $ev['amount_cents']) : rtrim(rtrim(number_format((float) $ev['reference'], 2, ',', '.'), '0'), ',').'h/d' ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </section>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
