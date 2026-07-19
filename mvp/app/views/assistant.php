<?php
$title = 'Assistente CLT';
$active = 'assistente.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

$suggestions = [
    'Salário líquido de R$ 3.500 com 1 dependente',
    'Férias de 20 dias com salário de R$ 3.000',
    'Quantos dias de aviso prévio com 4 anos de casa?',
    'Como funciona a jornada 12x36?',
];
?>
      <div class="mx-auto flex h-[calc(100vh-14rem)] max-w-3xl flex-col" x-data x-init="$refs.end.scrollIntoView()">
        <!-- Cabeçalho -->
        <div class="flex items-center justify-between pb-4">
          <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-600 text-white shadow-lg shadow-violet-600/30">
              <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span>
            <div>
              <h2 class="font-extrabold">Assistente CLT</h2>
              <p class="text-xs text-slate-400">Responde com as tabelas vigentes do sistema e cita a base legal — não é aconselhamento jurídico.</p>
            </div>
          </div>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reset">
            <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold hover:border-violet-400 hover:text-violet-600 dark:border-slate-700">
              <i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Nova conversa</button></form>
        </div>

        <!-- Mensagens -->
        <div class="flex-1 space-y-4 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
          <?php if (! $messages): ?>
            <div class="flex h-full flex-col items-center justify-center gap-4 text-center">
              <p class="text-sm text-slate-400">Pergunte sobre folha, férias, 13º, rescisão, jornada…<br>Os valores saem da mesma engine que calcula a folha oficial.</p>
              <div class="grid gap-2 sm:grid-cols-2">
                <?php foreach ($suggestions as $s): ?>
                  <form method="post"><?= csrf_field() ?>
                    <input type="hidden" name="message" value="<?= e($s) ?>">
                    <button class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-left text-xs text-slate-600 transition-colors hover:border-violet-400 hover:text-violet-700 dark:border-slate-700 dark:text-slate-300"><?= e($s) ?></button>
                  </form>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php foreach ($messages as $m): ?>
            <?php if ($m['role'] === 'user'): ?>
              <div class="flex justify-end">
                <div class="max-w-[85%] rounded-2xl rounded-br-md bg-blue-600 px-4 py-2.5 text-sm text-white"><?= e($m['content']) ?></div>
              </div>
            <?php else: ?>
              <div class="flex gap-3">
                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-violet-600 text-[11px] text-white"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span>
                <div class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-tl-md bg-slate-100 px-4 py-2.5 text-sm leading-relaxed dark:bg-slate-800"><?= e($m['content']) ?></div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
          <div x-ref="end"></div>
        </div>

        <!-- Entrada -->
        <form method="post" class="mt-4 flex gap-2">
          <?= csrf_field() ?>
          <input name="message" required maxlength="500" autofocus autocomplete="off"
                 placeholder="Ex.: INSS de R$ 3.000 · férias de 20 dias com salário de R$ 2.500…"
                 class="flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-violet-500 dark:border-slate-700 dark:bg-slate-900">
          <button class="rounded-2xl bg-violet-600 px-5 font-bold text-white shadow-lg shadow-violet-600/25 transition-colors hover:bg-violet-700" aria-label="Enviar">
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
        </form>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
