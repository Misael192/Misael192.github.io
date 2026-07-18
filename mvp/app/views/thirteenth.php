<?php
$title = '13º Salário';
$active = 'decimo.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

$card = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
$money = fn (int $cents): string => 'R$ '.number_format($cents / 100, 2, ',', '.');
?>
      <!-- Ano -->
      <section class="<?= $card ?> flex flex-wrap items-center gap-4 p-6">
        <div class="flex items-center gap-2">
          <a href="decimo.php?year=<?= $year - 1 ?>" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 hover:border-blue-400 dark:border-slate-700" aria-label="Ano anterior"><i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i></a>
          <p class="w-20 text-center text-lg font-extrabold tracking-tight"><?= $year ?></p>
          <a href="decimo.php?year=<?= $year + 1 ?>" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 hover:border-blue-400 dark:border-slate-700" aria-label="Próximo ano"><i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i></a>
        </div>
        <p class="text-xs text-slate-400">Avos por mês com ≥ 15 dias trabalhados (Lei 4.090/62) · 1ª parcela até 30/11 <strong>sem descontos</strong> · 2ª parcela até 20/12 com INSS/IRRF sobre o integral, descontando o adiantamento.</p>
      </section>

      <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <?php foreach ([1 => ['1ª parcela — Adiantamento', 'Competência '.$year.'-11 · 50% do proporcional, sem descontos'],
                        2 => ['2ª parcela — Final', 'Competência '.$year.'-12 · integral − INSS − IRRF − adiantamento']] as $inst => [$label, $sub]):
            $rows = $calculated[(string) $inst]; ?>
          <section class="<?= $card ?> overflow-x-auto">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-4 dark:border-slate-800">
              <div><h2 class="font-bold"><?= e($label) ?></h2><p class="text-[11px] text-slate-400"><?= e($sub) ?></p></div>
              <form method="post" action="decimo.php?year=<?= $year ?>"
                    <?= $rows ? 'onsubmit="return confirm(\'Recalcular a '.$inst.'ª parcela? Os valores atuais serão substituídos.\')"' : '' ?>>
                <?= csrf_field() ?><input type="hidden" name="installment" value="<?= $inst ?>">
                <button class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">
                  <i class="fa-solid fa-calculator mr-1.5" aria-hidden="true"></i><?= $rows ? 'Recalcular' : 'Calcular' ?>
                </button>
              </form>
            </div>
            <?php if ($rows): ?>
              <table class="w-full min-w-[420px] text-sm">
                <thead class="border-b border-slate-200 text-left dark:border-slate-800">
                  <tr><th class="px-5 py-2.5 text-xs font-bold uppercase text-slate-400">Colaborador</th>
                    <th class="px-5 py-2.5 text-right text-xs font-bold uppercase text-slate-400">Avos</th>
                    <th class="px-5 py-2.5 text-right text-xs font-bold uppercase text-slate-400">Líquido</th>
                    <th class="px-5 py-2.5"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <?php $sum = 0; foreach ($rows as $r): $sum += (int) $r['net_cents']; ?>
                    <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                      <td class="px-5 py-2.5 font-semibold"><?= e($r['full_name']) ?></td>
                      <td class="px-5 py-2.5 text-right tabular-nums"><?= (int) $r['months'] ?>/12</td>
                      <td class="px-5 py-2.5 text-right font-bold tabular-nums text-emerald-600 dark:text-emerald-400"><?= $money((int) $r['net_cents']) ?></td>
                      <td class="px-5 py-2.5 text-right">
                        <a href="holerite.php?id=<?= (int) $r['payroll_id'] ?>" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold hover:border-blue-400 hover:text-blue-600 dark:border-slate-700">Recibo</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="border-t-2 border-slate-200 font-bold dark:border-slate-700">
                  <td class="px-5 py-2.5 text-xs uppercase text-slate-400">Total</td><td></td>
                  <td class="px-5 py-2.5 text-right tabular-nums"><?= $money($sum) ?></td><td></td></tr></tfoot>
              </table>
            <?php else: ?>
              <p class="px-6 py-10 text-center text-sm text-slate-400">Ainda não calculada para <?= $year ?>.</p>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      </div>

      <!-- Elegíveis -->
      <section class="<?= $card ?> mt-6 overflow-x-auto">
        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
          <h2 class="font-bold">Colaboradores elegíveis em <?= $year ?></h2>
          <p class="text-[11px] text-slate-400">Ativos e em férias com salário cadastrado. Avos projetados até 20/12/<?= $year ?>.</p>
        </div>
        <table class="w-full min-w-[560px] text-sm">
          <thead class="border-b border-slate-200 text-left dark:border-slate-800">
            <tr><th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Colaborador</th>
              <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Admissão</th>
              <th class="px-5 py-3 text-right text-xs font-bold uppercase text-slate-400">Salário</th>
              <th class="px-5 py-3 text-right text-xs font-bold uppercase text-slate-400">Avos</th>
              <th class="px-5 py-3 text-right text-xs font-bold uppercase text-slate-400">13º integral previsto</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($eligible as $e): ?>
              <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                <td class="px-5 py-3"><p class="font-semibold"><?= e($e['full_name']) ?></p>
                  <p class="text-xs text-slate-400"><?= e($e['position_name'] ?? '—') ?> · mat. <?= e($e['registration']) ?></p></td>
                <td class="px-5 py-3 tabular-nums text-slate-500 dark:text-slate-400"><?= br_date($e['hired_at']) ?></td>
                <td class="px-5 py-3 text-right tabular-nums"><?= $money((int) $e['salary_cents']) ?></td>
                <td class="px-5 py-3 text-right tabular-nums"><?= (int) $e['months'] ?>/12</td>
                <td class="px-5 py-3 text-right tabular-nums font-semibold"><?= $money((int) round($e['salary_cents'] * $e['months'] / 12)) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (! $eligible): ?><tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">Nenhum colaborador elegível.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </section>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
