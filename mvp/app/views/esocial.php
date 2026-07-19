<?php
$title = 'eSocial';
$active = 'esocial.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

$card = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
$typeBadge = [
    'S-2200' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    'S-1200' => 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
];
?>
      <div class="grid gap-6 xl:grid-cols-3">
        <!-- Gerar eventos -->
        <section class="space-y-6">
          <div class="<?= $card ?> p-6">
            <h2 class="font-bold"><i class="fa-solid fa-user-plus mr-2 text-blue-500" aria-hidden="true"></i>S-2200 · Admissão</h2>
            <p class="mt-1 text-xs text-slate-400">Cadastramento inicial do vínculo, gerado da ficha completa do colaborador (CPF, PIS, cargo/CBO, salário, data de admissão).</p>
            <p class="mt-3 text-sm"><strong class="tabular-nums <?= $pendingAdmissions > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' ?>"><?= $pendingAdmissions ?></strong> colaborador(es) sem evento gerado.</p>
            <form method="post" class="mt-4"><?= csrf_field() ?><input type="hidden" name="action" value="admissions">
              <button class="w-full rounded-xl bg-blue-600 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700" <?= $pendingAdmissions === 0 ? 'disabled' : '' ?>>
                <i class="fa-solid fa-file-code mr-1.5" aria-hidden="true"></i>Gerar S-2200 pendentes</button>
            </form>
          </div>

          <div class="<?= $card ?> p-6">
            <h2 class="font-bold"><i class="fa-solid fa-money-check-dollar mr-2 text-violet-500" aria-hidden="true"></i>S-1200 · Remuneração</h2>
            <p class="mt-1 text-xs text-slate-400">Rubricas e valores da folha da competência — exige folha <strong>fechada</strong> (imutável).</p>
            <?php if ($closedPeriods): ?>
              <form method="post" class="mt-4 space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="remuneration">
                <select name="competency" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                  <?php foreach ($closedPeriods as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
                </select>
                <button class="w-full rounded-xl bg-violet-600 py-2.5 text-sm font-bold text-white shadow-lg shadow-violet-600/25 hover:bg-violet-700">
                  <i class="fa-solid fa-file-code mr-1.5" aria-hidden="true"></i>Gerar S-1200</button>
              </form>
            <?php else: ?>
              <p class="mt-4 rounded-xl bg-slate-100 px-4 py-3 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <i class="fa-solid fa-lock mr-1.5" aria-hidden="true"></i>Nenhuma competência fechada ainda — feche a folha primeiro.</p>
            <?php endif; ?>
          </div>

          <p class="px-2 text-[11px] text-slate-400"><i class="fa-solid fa-circle-info mr-1" aria-hidden="true"></i>
            Os XML seguem os leiautes evtAdmissao/evtRemun (ambiente de testes, tpAmb 2). A transmissão ao webservice com certificado digital A1 é a próxima etapa do roadmap.</p>
        </section>

        <!-- Eventos gerados -->
        <section class="<?= $card ?> overflow-x-auto xl:col-span-2">
          <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-bold">Eventos gerados</h2></div>
          <table class="w-full min-w-[560px] text-sm">
            <thead class="border-b border-slate-200 text-left dark:border-slate-800">
              <tr><th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Evento</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Referência</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Gerado em</th>
                <th class="px-5 py-3 text-right text-xs font-bold uppercase text-slate-400">Tamanho</th>
                <th class="px-5 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <?php foreach ($events as $ev): ?>
                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                  <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-bold <?= $typeBadge[$ev['event_type']] ?? '' ?>"><?= e($ev['event_type']) ?></span></td>
                  <td class="px-5 py-3"><p class="font-semibold"><?= e($ev['full_name'] ?? 'Empresa (todos)') ?></p>
                    <p class="text-xs text-slate-400"><?= e($ev['event_type'] === 'S-1200' ? 'Competência '.$ev['reference'] : 'Matrícula '.$ev['reference']) ?></p></td>
                  <td class="px-5 py-3 text-xs tabular-nums text-slate-500 dark:text-slate-400"><?= date('d/m/Y H:i', strtotime($ev['created_at'])) ?></td>
                  <td class="px-5 py-3 text-right text-xs tabular-nums text-slate-500 dark:text-slate-400"><?= number_format($ev['size'] / 1024, 1, ',', '.') ?> KB</td>
                  <td class="px-5 py-3 text-right">
                    <a href="esocial_xml.php?id=<?= (int) $ev['id'] ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold hover:border-blue-400 hover:text-blue-600 dark:border-slate-700">
                      <i class="fa-solid fa-download mr-1" aria-hidden="true"></i>XML</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (! $events): ?><tr><td colspan="5" class="px-5 py-12 text-center text-slate-400">Nenhum evento gerado ainda.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </section>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
