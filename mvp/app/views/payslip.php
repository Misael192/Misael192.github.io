<?php
/** Holerite (recibo de pagamento) — página limpa, pronta para imprimir/PDF. */
$docTitle = [
    'payslip' => 'Recibo de Pagamento de Salário',
    'vacation' => 'Recibo de Férias',
    'thirteenth_1' => '13º Salário — 1ª Parcela (Adiantamento)',
    'thirteenth_2' => '13º Salário — 2ª Parcela (Final)',
    'termination' => 'Termo de Rescisão — Demonstrativo',
][$payroll['kind']] ?? 'Recibo de Pagamento';
$title = $docTitle.' '.$payroll['competency'].' · '.$payroll['full_name'];
require APP_PATH.'/views/layout/head.php';

$money = fn (?int $cents): string => $cents === null ? '—' : 'R$ '.number_format($cents / 100, 2, ',', '.');
[$year, $month] = explode('-', $payroll['competency']);
$monthLabel = ['01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
    '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'][$month]." de {$year}";

$earnings = array_filter($items, fn ($i) => $i['type'] === 'earning');
$deductions = array_filter($items, fn ($i) => $i['type'] === 'deduction');
$infos = array_filter($items, fn ($i) => $i['type'] === 'info');
$fgtsDeposit = 0;
foreach ($charges as $c) { if ($c['type'] === 'fgts') { $fgtsDeposit = (int) $c['amount_cents']; } }
?>
<body class="bg-slate-100 text-slate-900 antialiased print:bg-white dark:bg-slate-950 dark:text-slate-100">
  <div class="mx-auto max-w-3xl px-4 py-8 print:max-w-none print:p-0">

    <!-- Barra de ações (some na impressão) -->
    <div class="mb-6 flex items-center justify-between print:hidden">
      <a href="folha.php?comp=<?= e($payroll['competency']) ?>" class="text-sm font-semibold text-blue-600 hover:underline">
        <i class="fa-solid fa-arrow-left mr-1.5" aria-hidden="true"></i>Voltar à folha</a>
      <button onclick="window.print()" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">
        <i class="fa-solid fa-print mr-1.5" aria-hidden="true"></i>Imprimir / PDF</button>
    </div>

    <!-- Documento -->
    <div class="rounded-2xl border border-slate-300 bg-white p-8 text-slate-900 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none">
      <header class="flex items-start justify-between border-b-2 border-slate-900 pb-4">
        <div>
          <p class="text-lg font-extrabold tracking-tight"><?= e($payroll['company_name']) ?></p>
          <p class="text-xs text-slate-500">CNPJ <?= e($payroll['cnpj']) ?></p>
        </div>
        <div class="text-right">
          <p class="text-sm font-extrabold uppercase tracking-wide"><?= e($docTitle) ?></p>
          <p class="text-xs text-slate-500">Competência: <strong><?= e($monthLabel) ?></strong>
            <?= $payroll['period_status'] === 'closed' ? '' : ' · <span class="font-bold text-amber-600">PRÉVIA — folha não fechada</span>' ?></p>
        </div>
      </header>

      <section class="mt-4 grid grid-cols-2 gap-x-8 gap-y-1 text-xs sm:grid-cols-4">
        <div><p class="font-bold uppercase text-slate-400">Colaborador</p><p class="font-semibold"><?= e($payroll['full_name']) ?></p></div>
        <div><p class="font-bold uppercase text-slate-400">Matrícula</p><p><?= e($payroll['registration']) ?></p></div>
        <div><p class="font-bold uppercase text-slate-400">Cargo</p><p><?= e($payroll['position_name'] ?? '—') ?> · <?= e($payroll['department_name'] ?? '—') ?></p></div>
        <div><p class="font-bold uppercase text-slate-400">Admissão</p><p><?= br_date($payroll['hired_at']) ?> (<?= strtoupper(e($payroll['contract_type'])) ?>)</p></div>
        <div><p class="font-bold uppercase text-slate-400">CPF</p><p><?= e($payroll['cpf'] ?? '—') ?></p></div>
        <div><p class="font-bold uppercase text-slate-400">PIS</p><p><?= e($payroll['pis'] ?? '—') ?></p></div>
      </section>

      <table class="mt-6 w-full border-collapse text-sm">
        <thead>
          <tr class="border-y-2 border-slate-900 text-left text-[10px] font-bold uppercase tracking-wide">
            <th class="py-2 pr-2">Cód.</th><th class="py-2 pr-2">Descrição</th>
            <th class="py-2 pr-2 text-right">Referência</th>
            <th class="py-2 pr-2 text-right">Proventos</th>
            <th class="py-2 text-right">Descontos</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <?php foreach ($earnings as $i): ?>
            <tr><td class="py-1.5 pr-2 tabular-nums text-slate-500"><?= e($i['rubric_code']) ?></td>
              <td class="py-1.5 pr-2"><?= e($i['description']) ?></td>
              <td class="py-1.5 pr-2 text-right tabular-nums text-slate-500"><?= $i['reference'] !== null ? rtrim(rtrim(number_format((float) $i['reference'], 2, ',', '.'), '0'), ',') : '' ?></td>
              <td class="py-1.5 pr-2 text-right tabular-nums"><?= $money((int) $i['amount_cents']) ?></td>
              <td class="py-1.5 text-right"></td></tr>
          <?php endforeach; ?>
          <?php foreach ($deductions as $i): ?>
            <tr><td class="py-1.5 pr-2 tabular-nums text-slate-500"><?= e($i['rubric_code']) ?></td>
              <td class="py-1.5 pr-2"><?= e($i['description']) ?></td>
              <td class="py-1.5 pr-2 text-right tabular-nums text-slate-500"><?= $i['reference'] !== null ? rtrim(rtrim(number_format((float) $i['reference'], 2, ',', '.'), '0'), ',') : '' ?></td>
              <td class="py-1.5 pr-2 text-right"></td>
              <td class="py-1.5 text-right tabular-nums"><?= $money((int) $i['amount_cents']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="border-t-2 border-slate-900 text-sm font-bold">
            <td colspan="3" class="py-2 pr-2 text-right uppercase text-[10px] tracking-wide">Totais</td>
            <td class="py-2 pr-2 text-right tabular-nums"><?= $money((int) $payroll['gross_cents']) ?></td>
            <td class="py-2 text-right tabular-nums"><?= $money((int) $payroll['deductions_cents']) ?></td>
          </tr>
        </tfoot>
      </table>

      <section class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-[11px] sm:grid-cols-4">
          <div><p class="font-bold uppercase text-slate-400">Base INSS</p><p class="tabular-nums"><?= $money((int) $payroll['inss_base_cents']) ?></p></div>
          <div><p class="font-bold uppercase text-slate-400">Base IRRF</p><p class="tabular-nums"><?= $money((int) $payroll['irrf_base_cents']) ?></p></div>
          <div><p class="font-bold uppercase text-slate-400">Base FGTS</p><p class="tabular-nums"><?= $money((int) $payroll['fgts_base_cents']) ?></p></div>
          <div><p class="font-bold uppercase text-slate-400">FGTS do mês</p><p class="tabular-nums"><?= $money($fgtsDeposit) ?></p></div>
        </div>
        <div class="rounded-xl border-2 border-slate-900 px-5 py-3 text-right">
          <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Valor líquido a receber</p>
          <p class="text-2xl font-extrabold tabular-nums"><?= $money((int) $payroll['net_cents']) ?></p>
        </div>
      </section>

      <footer class="mt-8 grid grid-cols-2 gap-8 border-t border-slate-300 pt-6 text-center text-[11px] text-slate-500">
        <div><div class="mx-auto w-56 border-b border-slate-400 pb-8"></div>Assinatura da empresa</div>
        <div><div class="mx-auto w-56 border-b border-slate-400 pb-8"></div><?= e($payroll['full_name']) ?></div>
      </footer>
      <p class="mt-4 text-center text-[9px] text-slate-400">Gerado pela PeopleFlow em <?= date('d/m/Y H:i') ?> · Documento sem valor fiscal na versão prévia</p>
    </div>
  </div>
</body>
</html>
