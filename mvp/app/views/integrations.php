<?php
$title = 'Integrações & API';
$active = 'integracoes.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

$card = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
?>
      <?php if ($newSecret !== null): ?>
        <section class="mb-6 rounded-2xl border-2 border-amber-300 bg-amber-50 p-6 dark:border-amber-700 dark:bg-amber-950/40" x-data>
          <h2 class="font-bold text-amber-800 dark:text-amber-300"><i class="fa-solid fa-key mr-2" aria-hidden="true"></i>Segredo da chave "<?= e($newSecret['name']) ?>" — copie AGORA</h2>
          <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Por segurança guardamos apenas o hash: este valor não será exibido novamente.</p>
          <div class="mt-3 flex gap-2">
            <code x-ref="secret" class="flex-1 overflow-x-auto rounded-xl bg-white px-4 py-3 font-mono text-sm dark:bg-slate-900"><?= e($newSecret['secret']) ?></code>
            <button @click="navigator.clipboard.writeText($refs.secret.textContent.trim()); $el.innerHTML='Copiado!'"
                    class="shrink-0 rounded-xl bg-amber-600 px-4 text-sm font-bold text-white hover:bg-amber-700">Copiar</button>
          </div>
        </section>
      <?php endif; ?>

      <div class="grid gap-6 xl:grid-cols-3">
        <!-- Chaves -->
        <section class="<?= $card ?> overflow-x-auto xl:col-span-2">
          <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-bold">Chaves de API</h2></div>
          <table class="w-full min-w-[560px] text-sm">
            <thead class="border-b border-slate-200 text-left dark:border-slate-800">
              <tr><th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Nome</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Chave</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Escopos</th>
                <th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Último uso</th>
                <th class="px-5 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <?php foreach ($keys as $k): ?>
                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60 <?= $k['is_active'] ? '' : 'opacity-50' ?>">
                  <td class="px-5 py-3"><p class="font-semibold"><?= e($k['name']) ?></p>
                    <p class="text-[11px] text-slate-400">por <?= e($k['creator'] ?? '—') ?> em <?= br_date($k['created_at']) ?></p></td>
                  <td class="px-5 py-3 font-mono text-xs text-slate-500 dark:text-slate-400"><?= e($k['key_prefix']) ?></td>
                  <td class="px-5 py-3">
                    <?php foreach (explode(',', $k['scopes']) as $s): ?>
                      <span class="mr-1 rounded-full px-2 py-0.5 text-[10px] font-bold <?= trim($s) === 'write' ? 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' ?>"><?= e(trim($s)) ?></span>
                    <?php endforeach; ?>
                  </td>
                  <td class="px-5 py-3 text-xs tabular-nums text-slate-500 dark:text-slate-400"><?= $k['last_used_at'] ? date('d/m/Y H:i', strtotime($k['last_used_at'])) : 'nunca' ?></td>
                  <td class="px-5 py-3 text-right">
                    <?php if ($k['is_active']): ?>
                      <form method="post" class="inline" onsubmit="return confirm('Revogar esta chave? Integrações que a usam param de funcionar.')">
                        <?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="key_id" value="<?= (int) $k['id'] ?>">
                        <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold hover:border-red-400 hover:text-red-600 dark:border-slate-700">Revogar</button>
                      </form>
                    <?php else: ?>
                      <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-950 dark:text-red-400">Revogada</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (! $keys): ?><tr><td colspan="5" class="px-5 py-12 text-center text-slate-400">Nenhuma chave ainda — crie a primeira ao lado.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </section>

        <!-- Criar + docs -->
        <section class="space-y-6">
          <div class="<?= $card ?> p-6">
            <h2 class="font-bold"><i class="fa-solid fa-plus mr-2 text-blue-500" aria-hidden="true"></i>Nova chave</h2>
            <form method="post" class="mt-4 space-y-3 text-sm">
              <?= csrf_field() ?><input type="hidden" name="action" value="create">
              <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Nome *</span>
                <input name="name" required maxlength="80" placeholder="Integração Conta Azul"
                       class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800"></label>
              <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-500">Escopos</span>
                <select name="scopes" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                  <option value="read">read — somente leitura</option>
                  <option value="read,write">read,write — leitura + lançar eventos de folha</option>
                </select></label>
              <button class="w-full rounded-xl bg-blue-600 py-2.5 font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">Criar chave</button>
            </form>
          </div>

          <div class="<?= $card ?> p-6 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100"><i class="fa-solid fa-book mr-2 text-blue-500" aria-hidden="true"></i>Como usar</h2>
            <p class="mt-2">Base: <code class="rounded bg-slate-100 px-1 py-0.5 font-mono dark:bg-slate-800">/api/v1/index.php</code> · autentique com <code class="rounded bg-slate-100 px-1 py-0.5 font-mono dark:bg-slate-800">Authorization: Bearer pfk_…</code></p>
            <ul class="mt-3 space-y-1.5 font-mono">
              <li>GET /me</li>
              <li>GET /employees?status=active</li>
              <li>GET /employees/{id}</li>
              <li>GET /payrolls?competency=2026-07</li>
              <li>GET /vacations?status=requested</li>
              <li class="text-violet-600 dark:text-violet-400">POST /payroll-events <span class="font-sans">(write)</span></li>
              <li>GET /openapi <span class="font-sans">(spec, sem chave)</span></li>
            </ul>
            <p class="mt-3">Ex.: lançar comissão vinda do CRM:</p>
            <pre class="mt-1 overflow-x-auto rounded-xl bg-slate-100 p-3 font-mono text-[10px] leading-relaxed dark:bg-slate-800">curl -X POST …/api/v1/index.php/payroll-events \
 -H "Authorization: Bearer pfk_…" \
 -d '{"employee_id":1,"competency":"2026-08",
      "rubric_code":"1006","amount_cents":80000}'</pre>
          </div>
        </section>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
