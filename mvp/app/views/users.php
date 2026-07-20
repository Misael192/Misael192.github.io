<?php
$title = 'Usuários';
$active = 'usuarios.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

use App\Middleware\Can;

$canManage = Can::allowed('users:manage');
$me = auth_user()['id'];
?>
      <div class="grid gap-6 xl:grid-cols-3">
        <!-- Listagem -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
          <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
            <h2 class="font-bold">Usuários (<?= count($users) ?>)</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm">
              <thead class="border-b border-slate-200 text-left dark:border-slate-800">
                <tr>
                  <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Usuário</th>
                  <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Empresa</th>
                  <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Perfil</th>
                  <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Último acesso</th>
                  <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Acesso</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($users as $user): ?>
                  <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    <td class="px-5 py-3.5">
                      <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                          <?= e(mb_strtoupper(mb_substr($user['name'], 0, 2))) ?>
                        </span>
                        <div>
                          <p class="font-semibold"><?= e($user['name']) ?></p>
                          <p class="text-xs text-slate-500 dark:text-slate-400"><?= e($user['email']) ?></p>
                        </div>
                      </div>
                    </td>
                    <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400"><?= e($user['company_name']) ?></td>
                    <td class="px-5 py-3.5">
                      <?php if ($canManage && $user['id'] !== $me): ?>
                        <form method="post" class="inline">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="role">
                          <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                          <select name="role_id" onchange="this.form.submit()" aria-label="Perfil de <?= e($user['name']) ?>"
                                  class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold dark:border-slate-700 dark:bg-slate-800">
                            <?php foreach ($roles as $role): ?>
                              <option value="<?= (int) $role['id'] ?>"<?= (int) $role['id'] === (int) $user['role_id'] ? ' selected' : '' ?>><?= e($role['name']) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </form>
                      <?php else: ?>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"><?= e($user['role_name']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-500 dark:text-slate-400"><?= $user['last_login_at'] ? date('d/m/Y H:i', strtotime($user['last_login_at'])) : 'nunca' ?></td>
                    <td class="px-5 py-3.5">
                      <?php if ($canManage && $user['id'] !== $me): ?>
                        <form method="post" class="inline">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="toggle">
                          <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                          <button class="rounded-full px-2.5 py-0.5 text-xs font-semibold transition-colors <?= $user['is_active']
                              ? 'bg-emerald-100 text-emerald-700 hover:bg-red-100 hover:text-red-700 dark:bg-emerald-950 dark:text-emerald-400'
                              : 'bg-red-100 text-red-700 hover:bg-emerald-100 hover:text-emerald-700 dark:bg-red-950 dark:text-red-400' ?>"
                                  title="<?= $user['is_active'] ? 'Clique para desativar' : 'Clique para reativar' ?>">
                            <?= $user['is_active'] ? 'Ativo' : 'Inativo' ?>
                          </button>
                        </form>
                      <?php else: ?>
                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">Ativo</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Formulário -->
        <section class="h-fit rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
          <h2 class="font-bold"><i class="fa-solid fa-user-plus mr-2 text-blue-500" aria-hidden="true"></i>Criar usuário</h2>
          <form method="post" action="usuarios.php" class="mt-4 space-y-4 text-sm">
            <?= csrf_field() ?>
            <label class="block">
              <span class="mb-1.5 block font-semibold">Nome *</span>
              <input name="name" required maxlength="120"
                     class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800">
            </label>
            <label class="block">
              <span class="mb-1.5 block font-semibold">E-mail *</span>
              <input name="email" type="email" required maxlength="160"
                     class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800">
            </label>
            <label class="block">
              <span class="mb-1.5 block font-semibold">Senha * <span class="font-normal text-slate-400">(mín. 8)</span></span>
              <input name="password" type="password" required minlength="8"
                     class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800">
            </label>
            <label class="block">
              <span class="mb-1.5 block font-semibold">Empresa *</span>
              <select name="company_id" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800">
                <?php foreach ($companies as $company): ?>
                  <option value="<?= (int) $company['id'] ?>"><?= e($company['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="block">
              <span class="mb-1.5 block font-semibold">Perfil *</span>
              <select name="role_id" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800">
                <?php foreach ($roles as $role): ?>
                  <option value="<?= (int) $role['id'] ?>"><?= e($role['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <button type="submit" class="w-full rounded-xl bg-blue-600 py-2.5 font-bold text-white shadow-lg shadow-blue-600/25 transition-colors hover:bg-blue-700">
              Criar usuário
            </button>
          </form>
        </section>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
