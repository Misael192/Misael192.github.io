<?php
$title = 'Empresas';
$active = 'empresas.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';
?>
      <div class="grid gap-6 xl:grid-cols-3" x-data="{ novo: false }">
        <!-- Listagem -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
          <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
            <h2 class="font-bold">Empresas cadastradas (<?= count($companies) ?>)</h2>
            <button @click="novo = true; $nextTick(() => $refs.nome.focus())"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition-colors hover:bg-blue-700 xl:hidden">
              <i class="fa-solid fa-plus mr-1.5" aria-hidden="true"></i>Nova
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm">
              <thead class="border-b border-slate-200 text-left dark:border-slate-800">
                <tr>
                  <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Empresa</th>
                  <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">CNPJ</th>
                  <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Usuários</th>
                  <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Colaboradores</th>
                  <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($companies as $company): ?>
                  <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    <td class="px-5 py-3.5">
                      <p class="font-semibold"><?= e($company['name']) ?></p>
                      <?php if ($company['trade_name']): ?><p class="text-xs text-slate-500 dark:text-slate-400"><?= e($company['trade_name']) ?></p><?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 tabular-nums text-slate-500 dark:text-slate-400"><?= e($company['cnpj'] ?? '—') ?></td>
                    <td class="px-5 py-3.5 tabular-nums"><?= (int) $company['users_count'] ?></td>
                    <td class="px-5 py-3.5 tabular-nums"><?= (int) $company['employees_count'] ?></td>
                    <td class="px-5 py-3.5">
                      <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $company['is_active'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' ?>">
                        <?= $company['is_active'] ? 'Ativa' : 'Inativa' ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (! $companies): ?>
                  <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">Nenhuma empresa cadastrada ainda.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Formulário de cadastro -->
        <section class="h-fit rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
          <h2 class="font-bold"><i class="fa-solid fa-plus mr-2 text-blue-500" aria-hidden="true"></i>Cadastrar empresa</h2>
          <form method="post" action="empresas.php" class="mt-4 space-y-4 text-sm">
            <?= csrf_field() ?>
            <label class="block">
              <span class="mb-1.5 block font-semibold">Razão social *</span>
              <input name="name" x-ref="nome" required maxlength="160" placeholder="Empresa Exemplo LTDA"
                     class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800">
            </label>
            <label class="block">
              <span class="mb-1.5 block font-semibold">Nome fantasia</span>
              <input name="trade_name" maxlength="160" placeholder="Exemplo"
                     class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800">
            </label>
            <label class="block">
              <span class="mb-1.5 block font-semibold">CNPJ</span>
              <input name="cnpj" placeholder="00.000.000/0000-00" pattern="\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}"
                     title="Formato: 00.000.000/0000-00"
                     class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800">
            </label>
            <label class="block">
              <span class="mb-1.5 block font-semibold">E-mail</span>
              <input name="email" type="email" maxlength="160" placeholder="contato@empresa.com.br"
                     class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800">
            </label>
            <button type="submit" class="w-full rounded-xl bg-blue-600 py-2.5 font-bold text-white shadow-lg shadow-blue-600/25 transition-colors hover:bg-blue-700">
              Salvar empresa
            </button>
          </form>
        </section>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
