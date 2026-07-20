<?php
$title = 'Gestão de Documentos';
$active = 'documentos.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

use App\Middleware\Can;

$card = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
$input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800';
$fmtSize = fn (int $bytes): string => $bytes > 1048576 ? round($bytes / 1048576, 1).' MB' : round($bytes / 1024).' KB';
?>
      <div class="grid gap-6 xl:grid-cols-3">
        <!-- Listagem -->
        <section class="<?= $card ?> overflow-x-auto xl:col-span-2">
          <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-bold">Documentos (<?= count($documents) ?>)</h2></div>
          <table class="w-full min-w-[640px] text-sm">
            <thead class="border-b border-slate-200 text-left dark:border-slate-800">
              <tr><th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Documento</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Colaborador</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Versão</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Assinatura</th>
                <th class="px-5 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <?php foreach ($documents as $doc): ?>
                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                  <td class="px-5 py-3.5">
                    <p class="font-semibold">📄 <?= e($doc['name']) ?></p>
                    <p class="text-xs text-slate-400"><?= e($doc['category']) ?> · <?= $fmtSize((int) $doc['size_bytes']) ?> · sha256 <?= e(substr($doc['sha256'], 0, 10)) ?>…</p>
                  </td>
                  <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400"><?= e($doc['employee'] ?? 'Empresa') ?></td>
                  <td class="px-5 py-3.5"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">v<?= (int) $doc['latest_version'] ?></span></td>
                  <td class="px-5 py-3.5">
                    <?php if ((int) $doc['signed_count'] > 0): ?>
                      <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400"><i class="fa-solid fa-signature mr-1" aria-hidden="true"></i>Assinado</span>
                    <?php else: ?>
                      <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Sem assinatura</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-5 py-3.5 text-right">
                    <div class="inline-flex gap-1.5">
                      <a href="download.php?id=<?= (int) $doc['id'] ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold hover:border-blue-400 hover:text-blue-600 dark:border-slate-700">Baixar</a>
                      <?php if (Can::allowed('documents:sign') && (int) $doc['signed_count'] === 0): ?>
                        <form method="post" class="inline">
                          <?= csrf_field() ?><input type="hidden" name="action" value="sign"><input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                          <button class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Assinar</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (! $documents): ?><tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">Nenhum documento ainda.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </section>

        <!-- Upload -->
        <?php if (Can::allowed('documents:manage')): ?>
        <section class="<?= $card ?> h-fit p-6">
          <h2 class="font-bold"><i class="fa-solid fa-cloud-arrow-up mr-2 text-blue-500" aria-hidden="true"></i>Enviar documento</h2>
          <p class="mt-1 text-xs text-slate-400">Mesmo nome + colaborador + categoria = nova versão automática.</p>
          <form method="post" enctype="multipart/form-data" class="mt-4 space-y-3 text-sm">
            <?= csrf_field() ?><input type="hidden" name="action" value="upload">
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Nome *</span>
              <input name="name" required maxlength="160" placeholder="Contrato de trabalho — Ana Souza" class="<?= $input ?>"></label>
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Categoria *</span>
              <select name="category_id" required class="<?= $input ?>">
                <?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?>
              </select></label>
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Colaborador</span>
              <select name="employee_id" class="<?= $input ?>"><option value="">— Documento da empresa</option>
                <?php foreach ($employees as $emp): ?><option value="<?= (int) $emp['id'] ?>"><?= e($emp['full_name']) ?></option><?php endforeach; ?>
              </select></label>
            <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Arquivo * (PDF/JPG/PNG, até 10 MB)</span>
              <input name="file" type="file" required accept="application/pdf,image/jpeg,image/png" class="<?= $input ?> file:mr-2 file:rounded-lg file:border-0 file:bg-blue-50 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-blue-700"></label>
            <button class="w-full rounded-xl bg-blue-600 py-2.5 font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">Enviar</button>
          </form>
        </section>
        <?php endif; ?>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
