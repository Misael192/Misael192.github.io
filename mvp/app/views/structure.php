<?php
$title = 'Estrutura Organizacional';
$active = 'estrutura.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

$card = 'rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900';
$input = 'rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800';
$btn = 'rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700';
$chip = 'rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300';
$money = fn (?string $cents): string => $cents !== null ? 'R$ '.number_format(((int) $cents) / 100, 2, ',', '.') : '—';
?>
      <div class="grid gap-6 xl:grid-cols-2">
        <!-- Filiais -->
        <section class="<?= $card ?>">
          <h2 class="font-bold"><i class="fa-solid fa-location-dot mr-2 text-blue-500" aria-hidden="true"></i>Filiais (<?= count($data['branches']) ?>)</h2>
          <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($data['branches'] as $b): ?><span class="<?= $chip ?>"><?= e($b['name']) ?><?= $b['city'] ? ' · '.e($b['city']) : '' ?></span><?php endforeach; ?>
          </div>
          <form method="post" class="mt-4 flex flex-wrap gap-2">
            <?= csrf_field() ?><input type="hidden" name="type" value="branch">
            <input name="name" required placeholder="Nome da filial" class="<?= $input ?> flex-1">
            <input name="city" placeholder="Cidade" class="<?= $input ?> w-32">
            <input name="state" maxlength="2" placeholder="UF" class="<?= $input ?> w-16">
            <button class="<?= $btn ?>">Adicionar</button>
          </form>
        </section>

        <!-- Departamentos -->
        <section class="<?= $card ?>">
          <h2 class="font-bold"><i class="fa-solid fa-sitemap mr-2 text-blue-500" aria-hidden="true"></i>Departamentos (<?= count($data['departments']) ?>)</h2>
          <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($data['departments'] as $d): ?><span class="<?= $chip ?>"><?= e($d['name']) ?><?= $d['branch'] ? ' · '.e($d['branch']) : '' ?></span><?php endforeach; ?>
          </div>
          <form method="post" class="mt-4 flex flex-wrap gap-2">
            <?= csrf_field() ?><input type="hidden" name="type" value="department">
            <input name="name" required placeholder="Nome do departamento" class="<?= $input ?> flex-1">
            <select name="branch_id" class="<?= $input ?>"><option value="">Filial —</option>
              <?php foreach ($data['branches'] as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select>
            <button class="<?= $btn ?>">Adicionar</button>
          </form>
        </section>

        <!-- Cargos -->
        <section class="<?= $card ?>">
          <h2 class="font-bold"><i class="fa-solid fa-briefcase mr-2 text-blue-500" aria-hidden="true"></i>Cargos (<?= count($data['positions']) ?>)</h2>
          <ul class="mt-3 space-y-1.5 text-sm">
            <?php foreach ($data['positions'] as $p): ?>
              <li class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"><span class="font-medium"><?= e($p['title']) ?></span><span class="text-slate-400"><?= $money($p['base_salary_cents']) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <form method="post" class="mt-4 flex flex-wrap gap-2">
            <?= csrf_field() ?><input type="hidden" name="type" value="position">
            <input name="name" required placeholder="Título do cargo" class="<?= $input ?> flex-1">
            <input name="salary" placeholder="Salário base (R$)" class="<?= $input ?> w-40">
            <button class="<?= $btn ?>">Adicionar</button>
          </form>
        </section>

        <!-- Centros de custo + Escalas -->
        <section class="<?= $card ?>">
          <h2 class="font-bold"><i class="fa-solid fa-coins mr-2 text-blue-500" aria-hidden="true"></i>Centros de custo (<?= count($data['cost_centers']) ?>)</h2>
          <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($data['cost_centers'] as $cc): ?><span class="<?= $chip ?>"><?= e($cc['code']) ?> · <?= e($cc['name']) ?></span><?php endforeach; ?>
          </div>
          <form method="post" class="mt-4 flex flex-wrap gap-2">
            <?= csrf_field() ?><input type="hidden" name="type" value="cost_center">
            <input name="code" required placeholder="Código" class="<?= $input ?> w-28">
            <input name="name" required placeholder="Nome" class="<?= $input ?> flex-1">
            <button class="<?= $btn ?>">Adicionar</button>
          </form>

          <h2 class="mt-8 font-bold"><i class="fa-solid fa-clock mr-2 text-blue-500" aria-hidden="true"></i>Escalas (<?= count($data['work_shifts']) ?>)</h2>
          <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($data['work_shifts'] as $ws): ?><span class="<?= $chip ?>"><?= e($ws['name']) ?> · <?= e((string) $ws['weekly_hours']) ?>h/sem</span><?php endforeach; ?>
          </div>
          <form method="post" class="mt-4 flex flex-wrap gap-2">
            <?= csrf_field() ?><input type="hidden" name="type" value="work_shift">
            <input name="name" required placeholder="Nome (ex.: 5x2 — 08h às 18h)" class="<?= $input ?> flex-1">
            <input name="weekly_hours" type="number" min="1" max="44" value="44" title="Horas semanais" class="<?= $input ?> w-20">
            <input name="daily_hours" type="number" step="0.01" min="1" max="12" value="8" title="Horas diárias" class="<?= $input ?> w-20">
            <button class="<?= $btn ?>">Adicionar</button>
          </form>
        </section>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
