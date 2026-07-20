<?php
$title = 'Meu Espaço';
$active = 'portal.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

$card = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
$money = fn (?int $cents): string => $cents === null ? '—' : 'R$ '.number_format($cents / 100, 2, ',', '.');
$kindLabel = ['payslip' => 'Salário', 'vacation' => 'Férias', 'thirteenth_1' => '13º · 1ª parcela',
    'thirteenth_2' => '13º · 2ª parcela', 'termination' => 'Rescisão'];
?>
      <?php if ($me === null): ?>
        <section class="<?= $card ?> px-6 py-16 text-center text-slate-400">
          <i class="fa-solid fa-link-slash mb-3 text-3xl" aria-hidden="true"></i>
          <p class="text-sm">Seu usuário ainda não está vinculado a uma ficha de colaborador.<br>
          <span class="text-xs">Peça ao RH para vincular seu acesso em <strong>Usuários</strong>.</span></p>
        </section>
      <?php else: ?>

      <!-- Cabeçalho do colaborador -->
      <section class="<?= $card ?> flex flex-wrap items-center gap-5 p-6">
        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-600 text-xl font-bold text-white">
          <?= e(mb_strtoupper(mb_substr($me['full_name'], 0, 2))) ?></span>
        <div class="min-w-0">
          <h2 class="truncate text-lg font-extrabold"><?= e($me['full_name']) ?></h2>
          <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($me['position_name'] ?? '—') ?> · <?= e($me['department_name'] ?? '—') ?></p>
          <p class="text-xs text-slate-400">Mat. <?= e($me['registration']) ?> · Admissão <?= br_date($me['hired_at']) ?> · Escala <?= e($me['shift_name'] ?? '—') ?></p>
        </div>
        <div class="ml-auto grid grid-cols-2 gap-3 text-center sm:grid-cols-3">
          <?php
          $bh = $bankMinutes;
          $bhLabel = ($bh < 0 ? '−' : '+').intdiv(abs($bh), 60).'h'.str_pad((string) (abs($bh) % 60), 2, '0', STR_PAD_LEFT);
          foreach ([['Banco de horas', $bhLabel, $bh < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'],
              ['Saldo de férias', ($vacationBalance['balance'] ?? 0).' dias', ''],
              ['Recibos', (string) count($payslips), '']] as [$l, $v, $cls]): ?>
            <div class="rounded-xl bg-slate-50 px-4 py-2.5 dark:bg-slate-800/60">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400"><?= e($l) ?></p>
              <p class="text-lg font-extrabold tabular-nums <?= $cls ?>"><?= e($v) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <!-- Ponto -->
        <section class="<?= $card ?> p-6">
          <h2 class="font-bold"><i class="fa-solid fa-clock mr-2 text-blue-500" aria-hidden="true"></i>Meu ponto</h2>
          <div class="mt-4 grid grid-cols-4 gap-2 text-center text-xs">
            <?php foreach (['clock_in' => 'Entrada', 'lunch_out' => 'Almoço', 'lunch_in' => 'Retorno', 'clock_out' => 'Saída'] as $f => $l): ?>
              <div class="rounded-xl px-2 py-3 <?= ($today[$f] ?? null) !== null ? 'bg-emerald-50 dark:bg-emerald-950/40' : 'bg-slate-50 dark:bg-slate-800/60' ?>">
                <p class="text-[10px] font-bold uppercase text-slate-400"><?= $l ?></p>
                <p class="mt-1 font-extrabold tabular-nums <?= ($today[$f] ?? null) !== null ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-300 dark:text-slate-600' ?>">
                  <?= ($today[$f] ?? null) !== null ? substr($today[$f], 0, 5) : '—' ?></p>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if (($today['clock_out'] ?? null) === null): ?>
            <form method="post" class="mt-4">
              <?= csrf_field() ?><input type="hidden" name="action" value="clock">
              <button class="w-full rounded-xl bg-blue-600 py-3 font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">
                <i class="fa-solid fa-fingerprint mr-2" aria-hidden="true"></i>Bater ponto</button>
            </form>
          <?php else: ?>
            <p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-center text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
              <i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>Dia completo — boa noite!</p>
          <?php endif; ?>
          <?php if ($recentClock): ?>
            <ul class="mt-4 space-y-1.5 text-xs">
              <?php foreach ($recentClock as $r): ?>
                <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5 dark:bg-slate-800/60">
                  <span class="tabular-nums text-slate-500 dark:text-slate-400"><?= br_date($r['work_date']) ?></span>
                  <span class="tabular-nums"><?= implode(' · ', array_map(fn ($f) => $r[$f] ? substr($r[$f], 0, 5) : '—', ['clock_in', 'lunch_out', 'lunch_in', 'clock_out'])) ?></span>
                  <span class="rounded-full px-2 py-0.5 text-[10px] font-bold <?= $r['status'] === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400' ?>">
                    <?= $r['status'] === 'approved' ? 'OK' : 'Pendente' ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

        <!-- Holerites -->
        <section class="<?= $card ?> overflow-hidden">
          <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-bold"><i class="fa-solid fa-file-invoice-dollar mr-2 text-blue-500" aria-hidden="true"></i>Meus recibos</h2></div>
          <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach (array_slice($payslips, 0, 8) as $p): ?>
              <li>
                <a href="holerite.php?id=<?= (int) $p['id'] ?>" class="flex items-center justify-between gap-3 px-6 py-3 text-sm transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                  <span><span class="font-semibold"><?= e($kindLabel[$p['kind']] ?? $p['kind']) ?></span>
                    <span class="ml-1 text-xs text-slate-400"><?= e($p['competency']) ?><?= $p['period_status'] !== 'closed' ? ' · prévia' : '' ?></span></span>
                  <span class="font-bold tabular-nums text-emerald-600 dark:text-emerald-400"><?= $money((int) $p['net_cents']) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
            <?php if (! $payslips): ?><li class="px-6 py-10 text-center text-sm text-slate-400">Nenhum recibo ainda.</li><?php endif; ?>
          </ul>
        </section>

        <!-- Férias -->
        <section class="<?= $card ?> p-6">
          <h2 class="font-bold"><i class="fa-solid fa-umbrella-beach mr-2 text-blue-500" aria-hidden="true"></i>Minhas férias</h2>
          <?php if ($vacationBalance): ?>
            <p class="mt-2 rounded-xl bg-blue-50 px-4 py-2.5 text-xs text-blue-800 dark:bg-blue-950/50 dark:text-blue-300">
              Período <?= br_date($vacationBalance['acq_start']) ?> – <?= br_date($vacationBalance['acq_end']) ?>:
              <strong><?= (int) $vacationBalance['balance'] ?> dias disponíveis</strong> · gozar até <?= br_date($vacationBalance['concessive_end']) ?></p>
          <?php endif; ?>
          <form method="post" class="mt-3 space-y-2.5 text-sm">
            <?= csrf_field() ?><input type="hidden" name="action" value="vacation">
            <div class="grid grid-cols-2 gap-2.5">
              <label class="block"><span class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Início</span>
                <input name="start_date" type="date" required class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs dark:border-slate-700 dark:bg-slate-800"></label>
              <label class="block"><span class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Fim</span>
                <input name="end_date" type="date" required class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs dark:border-slate-700 dark:bg-slate-800"></label>
            </div>
            <label class="block"><span class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Vender dias (abono, 0–10)</span>
              <input name="sell_days" type="number" min="0" max="10" value="0" class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs dark:border-slate-700 dark:bg-slate-800"></label>
            <button class="w-full rounded-xl bg-blue-600 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">Solicitar férias</button>
          </form>
          <?php if ($vacations): ?>
            <ul class="mt-4 space-y-1.5 text-xs">
              <?php $vBadge = ['requested' => ['Aguardando', 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'],
                  'approved' => ['Aprovada', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'],
                  'rejected' => ['Rejeitada', 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400']];
              foreach ($vacations as $v): [$vl, $vc] = $vBadge[$v['status']]; ?>
                <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5 dark:bg-slate-800/60">
                  <span class="tabular-nums text-slate-500 dark:text-slate-400"><?= br_date($v['start_date']) ?> – <?= br_date($v['end_date']) ?> (<?= (int) $v['days'] ?>d)</span>
                  <span class="rounded-full px-2 py-0.5 text-[10px] font-bold <?= $vc ?>"><?= $vl ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      </div>

      <!-- Documentos -->
      <section class="<?= $card ?> mt-6 overflow-x-auto">
        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-bold"><i class="fa-solid fa-folder-open mr-2 text-blue-500" aria-hidden="true"></i>Meus documentos</h2></div>
        <table class="w-full min-w-[480px] text-sm">
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($documents as $d): ?>
              <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                <td class="px-6 py-3 font-semibold"><?= e($d['name']) ?></td>
                <td class="px-6 py-3 text-xs text-slate-500 dark:text-slate-400"><?= e($d['category'] ?? '—') ?> · v<?= (int) $d['version'] ?></td>
                <td class="px-6 py-3 text-xs tabular-nums text-slate-500 dark:text-slate-400"><?= br_date($d['created_at']) ?></td>
                <td class="px-6 py-3 text-right">
                  <a href="download.php?id=<?= (int) $d['id'] ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold hover:border-blue-400 hover:text-blue-600 dark:border-slate-700">
                    <i class="fa-solid fa-download mr-1" aria-hidden="true"></i>Baixar</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (! $documents): ?><tr><td class="px-6 py-10 text-center text-slate-400">Nenhum documento ainda.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </section>

      <?php endif; ?>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
