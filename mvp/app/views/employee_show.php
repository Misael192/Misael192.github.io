<?php
$title = 'Ficha do colaborador';
$active = 'colaboradores.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

$card = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
$dt = 'text-xs text-slate-400'; $dd = 'font-semibold';
$money = fn (?int $cents): string => $cents !== null ? 'R$ '.number_format($cents / 100, 2, ',', '.') : '—';
$tabOn = 'border-blue-600 font-semibold text-blue-600 dark:text-blue-400';
$tabOff = 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400';
?>
      <div x-data="{ tab: 'dados' }">
        <!-- Cabeçalho da ficha -->
        <section class="<?= $card ?> flex flex-wrap items-center gap-5 p-6">
          <?php if ($emp['photo_path']): ?>
            <img src="foto.php?id=<?= (int) $emp['id'] ?>" alt="Foto de <?= e($emp['full_name']) ?>" class="h-20 w-20 rounded-2xl object-cover">
          <?php else: ?>
            <span class="flex h-20 w-20 items-center justify-center rounded-2xl bg-blue-600 text-2xl font-extrabold text-white"><?= e(mb_strtoupper(mb_substr($emp['full_name'], 0, 2))) ?></span>
          <?php endif; ?>
          <div class="min-w-0 flex-1">
            <h2 class="text-xl font-extrabold"><?= e($emp['full_name']) ?></h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
              Mat. <?= e($emp['registration']) ?> · <?= e($emp['position_title'] ?? 'Sem cargo') ?> · <?= e($emp['department'] ?? 'Sem departamento') ?><?= $emp['branch'] ? ' · '.e($emp['branch']) : '' ?>
            </p>
            <p class="mt-1 text-xs text-slate-400">Gestor: <?= e($emp['manager_name'] ?? '—') ?> · Escala: <?= e($emp['shift_name'] ?? '—') ?> · <?= strtoupper(e($emp['contract_type'])) ?></p>
          </div>
          <div class="text-right">
            <p class="<?= $dt ?>">Salário</p><p class="text-lg font-extrabold"><?= $money($emp['salary_cents'] !== null ? (int) $emp['salary_cents'] : null) ?></p>
            <p class="<?= $dt ?> mt-1">Admissão: <?= br_date($emp['hired_at']) ?></p>
          </div>
        </section>

        <!-- Abas -->
        <div class="mt-6 flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-800" role="tablist">
          <?php foreach (['dados' => 'Dados', 'admissao' => 'Admissão', 'dependentes' => 'Dependentes', 'historico' => 'Históricos'] as $key => $labelTab): ?>
            <button role="tab" :aria-selected="tab === '<?= $key ?>'" @click="tab = '<?= $key ?>'"
                    class="border-b-2 px-4 py-2.5 text-sm transition-colors" :class="tab === '<?= $key ?>' ? '<?= $tabOn ?>' : '<?= $tabOff ?>'"><?= $labelTab ?></button>
          <?php endforeach; ?>
        </div>

        <!-- Dados -->
        <div x-show="tab === 'dados'" class="mt-6 grid gap-6 lg:grid-cols-3">
          <section class="<?= $card ?> p-6">
            <h3 class="font-bold">Pessoais e documentos</h3>
            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
              <div><dt class="<?= $dt ?>">CPF</dt><dd class="<?= $dd ?>"><?= e($emp['cpf'] ?? '—') ?></dd></div>
              <div><dt class="<?= $dt ?>">RG</dt><dd class="<?= $dd ?>"><?= e($emp['rg'] ?? '—') ?><?= $emp['rg_issuer'] ? ' · '.e($emp['rg_issuer']) : '' ?></dd></div>
              <div><dt class="<?= $dt ?>">Nascimento</dt><dd class="<?= $dd ?>"><?= br_date($emp['birth_date']) ?></dd></div>
              <div><dt class="<?= $dt ?>">Estado civil</dt><dd class="<?= $dd ?>"><?= e($emp['marital_status'] ?? '—') ?></dd></div>
              <div><dt class="<?= $dt ?>">Nacionalidade</dt><dd class="<?= $dd ?>"><?= e($emp['nationality'] ?? '—') ?></dd></div>
              <div><dt class="<?= $dt ?>">Naturalidade</dt><dd class="<?= $dd ?>"><?= e($emp['birthplace'] ?? '—') ?></dd></div>
              <div><dt class="<?= $dt ?>">PIS</dt><dd class="<?= $dd ?>"><?= e($emp['pis'] ?? '—') ?></dd></div>
              <div><dt class="<?= $dt ?>">CTPS</dt><dd class="<?= $dd ?>"><?= e($emp['ctps'] ?? '—') ?></dd></div>
              <div><dt class="<?= $dt ?>">Título eleitor</dt><dd class="<?= $dd ?>"><?= e($emp['voter_title'] ?? '—') ?></dd></div>
              <div><dt class="<?= $dt ?>">CNH</dt><dd class="<?= $dd ?>"><?= e($emp['cnh'] ?? '—') ?></dd></div>
            </dl>
          </section>
          <section class="<?= $card ?> p-6">
            <h3 class="font-bold">Contato e endereço</h3>
            <dl class="mt-4 space-y-3 text-sm">
              <div><dt class="<?= $dt ?>">Telefone / Celular</dt><dd class="<?= $dd ?>"><?= e($emp['contact']['phone'] ?? '—') ?> · <?= e($emp['contact']['mobile'] ?? '—') ?></dd></div>
              <div><dt class="<?= $dt ?>">E-mail</dt><dd class="<?= $dd ?>"><?= e($emp['contact']['email'] ?? '—') ?></dd></div>
              <div><dt class="<?= $dt ?>">Endereço</dt><dd class="<?= $dd ?>">
                <?php $a = $emp['address']; echo $a ? e(trim(($a['street'] ?? '').', '.($a['number'] ?? '').' — '.($a['district'] ?? '').', '.($a['city'] ?? '').'/'.($a['state'] ?? '').' · CEP '.($a['zip_code'] ?? ''), ' ,—·')) : '—'; ?>
              </dd></div>
              <?php foreach ($emp['emergency'] as $eme): ?>
                <div><dt class="<?= $dt ?>">Emergência</dt><dd class="<?= $dd ?>"><?= e($eme['name']) ?> (<?= e($eme['relationship'] ?? '—') ?>) · <?= e($eme['phone']) ?></dd></div>
              <?php endforeach; ?>
            </dl>
          </section>
          <section class="<?= $card ?> p-6">
            <h3 class="font-bold">Dados bancários</h3>
            <dl class="mt-4 space-y-3 text-sm">
              <div><dt class="<?= $dt ?>">Banco</dt><dd class="<?= $dd ?>"><?= e($emp['bank']['bank'] ?? '—') ?></dd></div>
              <div><dt class="<?= $dt ?>">Agência / Conta</dt><dd class="<?= $dd ?>"><?= e($emp['bank']['agency'] ?? '—') ?> · <?= e($emp['bank']['account'] ?? '—') ?> (<?= e($emp['bank']['account_type'] ?? '—') ?>)</dd></div>
              <div><dt class="<?= $dt ?>">PIX</dt><dd class="<?= $dd ?>"><?= e($emp['bank']['pix_key'] ?? '—') ?></dd></div>
              <div><dt class="<?= $dt ?>">Centro de custo</dt><dd class="<?= $dd ?>"><?= e($emp['cost_center'] ?? '—') ?></dd></div>
            </dl>
          </section>
        </div>

        <!-- Admissão digital -->
        <div x-show="tab === 'admissao'" x-cloak class="mt-6">
          <section class="<?= $card ?> max-w-xl p-6">
            <div class="flex items-center justify-between">
              <h3 class="font-bold">Checklist de admissão</h3>
              <?php $adm = $emp['admission']; ?>
              <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-400">
                <?= (int) ($adm['done'] ?? 0) ?>/<?= (int) ($adm['total'] ?? 0) ?> itens
              </span>
            </div>
            <ol class="mt-4 space-y-2.5">
              <?php foreach ($emp['admission_items'] as $item): ?>
                <li class="flex items-center gap-3 text-sm">
                  <span class="flex h-6 w-6 items-center justify-center rounded-full <?= $item['is_done'] ? 'bg-emerald-500 text-white' : 'border-2 border-slate-300 dark:border-slate-600' ?>">
                    <?= $item['is_done'] ? '<i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i>' : '' ?>
                  </span>
                  <span class="<?= $item['is_done'] ? 'font-medium' : 'text-slate-500 dark:text-slate-400' ?>"><?= e($item['item']) ?></span>
                </li>
              <?php endforeach; ?>
            </ol>
            <p class="mt-4 text-xs text-slate-400">Anexe os documentos na página <a href="documentos.php" class="font-semibold text-blue-600 hover:underline">Documentos</a> (categoria "Admissão").</p>
          </section>
        </div>

        <!-- Dependentes -->
        <div x-show="tab === 'dependentes'" x-cloak class="mt-6">
          <section class="<?= $card ?> max-w-2xl overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b border-slate-200 text-left dark:border-slate-800">
                <tr><th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Nome</th><th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">CPF</th><th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Parentesco</th><th class="px-5 py-3 text-xs font-bold uppercase text-slate-400">Nascimento</th></tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($emp['dependents'] as $dep): ?>
                  <tr><td class="px-5 py-3 font-semibold"><?= e($dep['name']) ?></td><td class="px-5 py-3"><?= e($dep['cpf'] ?? '—') ?></td><td class="px-5 py-3"><?= e($dep['relationship']) ?></td><td class="px-5 py-3"><?= br_date($dep['birth_date']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (! $emp['dependents']): ?><tr><td colspan="4" class="px-5 py-8 text-center text-slate-400">Nenhum dependente cadastrado.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </section>
        </div>

        <!-- Históricos -->
        <div x-show="tab === 'historico'" x-cloak class="mt-6 grid gap-6 lg:grid-cols-2">
          <section class="<?= $card ?> p-6">
            <h3 class="font-bold">Histórico salarial</h3>
            <ul class="mt-4 space-y-3 text-sm">
              <?php foreach ($emp['salary_history'] as $h): ?>
                <li class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
                  <div><p class="font-semibold"><?= $money((int) $h['new_salary_cents']) ?><?= $h['old_salary_cents'] !== null ? ' <span class="text-xs text-slate-400">(antes '.$money((int) $h['old_salary_cents']).')</span>' : '' ?></p>
                    <p class="text-xs text-slate-400"><?= e($h['reason'] ?? '') ?> · por <?= e($h['changed_by_name'] ?? 'sistema') ?></p></div>
                  <span class="text-xs text-slate-400"><?= date('d/m/Y', strtotime($h['changed_at'])) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
          <section class="<?= $card ?> p-6">
            <h3 class="font-bold">Histórico de situação</h3>
            <ul class="mt-4 space-y-3 text-sm">
              <?php foreach ($emp['status_history'] as $h): ?>
                <li class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
                  <div><p class="font-semibold"><?= e($h['old_status'] ?? 'novo') ?> → <?= e($h['new_status']) ?></p>
                    <p class="text-xs text-slate-400"><?= e($h['reason'] ?? '') ?> · por <?= e($h['changed_by_name'] ?? 'sistema') ?></p></div>
                  <span class="text-xs text-slate-400"><?= date('d/m/Y H:i', strtotime($h['changed_at'])) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        </div>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
