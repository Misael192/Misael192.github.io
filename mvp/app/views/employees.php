<?php
$title = 'Colaboradores';
$active = 'colaboradores.php';
require APP_PATH.'/views/layout/head.php';
require APP_PATH.'/views/layout/app_start.php';

$card = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
$input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-800';
$label = 'block text-sm'; $span = 'mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400';
$statusBadge = [
    'active' => ['Ativo', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'],
    'vacation' => ['Férias', 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400'],
    'admission' => ['Em admissão', 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'],
    'on_leave' => ['Afastado', 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'],
    'terminated' => ['Desligado', 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400'],
];
?>
      <div x-data="{ novo: false }">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <label class="relative">
            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
            <input data-table-filter="#tabela-colab" type="search" placeholder="Filtrar colaboradores…" aria-label="Filtrar"
                   class="w-72 rounded-xl border border-slate-200 bg-white py-2 pl-8 pr-3 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-900">
          </label>
          <button @click="novo = !novo" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">
            <i class="fa-solid fa-user-plus mr-1.5" aria-hidden="true"></i><span x-text="novo ? 'Fechar formulário' : 'Nova admissão'"></span>
          </button>
        </div>

        <!-- ══ Formulário completo de admissão ══ -->
        <form x-show="novo" x-collapse method="post" action="colaboradores.php" enctype="multipart/form-data"
              class="<?= $card ?> mb-6 space-y-6 p-6">
          <?= csrf_field() ?>

          <fieldset>
            <legend class="font-bold"><i class="fa-solid fa-user mr-2 text-blue-500" aria-hidden="true"></i>Dados pessoais</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <label class="<?= $label ?> lg:col-span-2"><span class="<?= $span ?>">Nome completo *</span><input name="full_name" required class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">CPF</span><input name="cpf" placeholder="000.000.000-00" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">RG</span><input name="rg" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Órgão emissor</span><input name="rg_issuer" placeholder="SSP/SP" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Nascimento</span><input name="birth_date" type="date" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Sexo</span>
                <select name="gender" class="<?= $input ?>"><option value="">—</option><option>Feminino</option><option>Masculino</option><option>Outro</option></select></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Estado civil</span>
                <select name="marital_status" class="<?= $input ?>"><option value="">—</option><option value="solteiro">Solteiro(a)</option><option value="casado">Casado(a)</option><option value="divorciado">Divorciado(a)</option><option value="viuvo">Viúvo(a)</option><option value="uniao_estavel">União estável</option></select></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Nacionalidade</span><input name="nationality" value="Brasileira" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Naturalidade</span><input name="birthplace" placeholder="São Paulo/SP" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Foto (JPG/PNG)</span><input name="photo" type="file" accept="image/jpeg,image/png" class="<?= $input ?> file:mr-2 file:rounded-lg file:border-0 file:bg-blue-50 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-blue-700"></label>
            </div>
          </fieldset>

          <fieldset>
            <legend class="font-bold"><i class="fa-solid fa-id-card mr-2 text-blue-500" aria-hidden="true"></i>Documentos</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
              <label class="<?= $label ?>"><span class="<?= $span ?>">PIS/PASEP</span><input name="pis" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">CTPS</span><input name="ctps" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Título de eleitor</span><input name="voter_title" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Reservista</span><input name="reservist" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">CNH</span><input name="cnh" class="<?= $input ?>"></label>
            </div>
          </fieldset>

          <fieldset>
            <legend class="font-bold"><i class="fa-solid fa-phone mr-2 text-blue-500" aria-hidden="true"></i>Contato e endereço</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <label class="<?= $label ?>"><span class="<?= $span ?>">Telefone</span><input name="phone" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Celular</span><input name="mobile" class="<?= $input ?>"></label>
              <label class="<?= $label ?> lg:col-span-2"><span class="<?= $span ?>">E-mail</span><input name="email" type="email" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">CEP</span><input name="zip_code" placeholder="00000-000" class="<?= $input ?>"></label>
              <label class="<?= $label ?> lg:col-span-2"><span class="<?= $span ?>">Rua</span><input name="street" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Número</span><input name="number" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Bairro</span><input name="district" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Cidade</span><input name="city" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">UF</span><input name="state" maxlength="2" placeholder="SP" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Complemento</span><input name="complement" class="<?= $input ?>"></label>
            </div>
          </fieldset>

          <fieldset>
            <legend class="font-bold"><i class="fa-solid fa-briefcase mr-2 text-blue-500" aria-hidden="true"></i>Dados profissionais</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <label class="<?= $label ?>"><span class="<?= $span ?>">Matrícula *</span><input name="registration" required class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Filial</span>
                <select name="branch_id" class="<?= $input ?>"><option value="">—</option>
                  <?php foreach ($structure['branches'] as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Departamento</span>
                <select name="department_id" class="<?= $input ?>"><option value="">—</option>
                  <?php foreach ($structure['departments'] as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Cargo</span>
                <select name="position_id" class="<?= $input ?>"><option value="">—</option>
                  <?php foreach ($structure['positions'] as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach; ?></select></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Centro de custo</span>
                <select name="cost_center_id" class="<?= $input ?>"><option value="">—</option>
                  <?php foreach ($structure['cost_centers'] as $cc): ?><option value="<?= (int) $cc['id'] ?>"><?= e($cc['code'].' · '.$cc['name']) ?></option><?php endforeach; ?></select></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Escala/Jornada</span>
                <select name="work_shift_id" class="<?= $input ?>"><option value="">—</option>
                  <?php foreach ($structure['work_shifts'] as $ws): ?><option value="<?= (int) $ws['id'] ?>"><?= e($ws['name']) ?></option><?php endforeach; ?></select></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Gestor</span>
                <select name="manager_id" class="<?= $input ?>"><option value="">—</option>
                  <?php foreach ($managers as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['full_name']) ?></option><?php endforeach; ?></select></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Tipo de contrato</span>
                <select name="contract_type" class="<?= $input ?>"><option value="clt">CLT</option><option value="pj">PJ</option><option value="estagio">Estágio</option><option value="temporario">Temporário</option><option value="aprendiz">Aprendiz</option></select></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Salário (R$)</span><input name="salary" placeholder="3.500,00" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Admissão *</span><input name="hired_at" type="date" required class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Situação</span>
                <select name="status" class="<?= $input ?>"><option value="admission">Em admissão</option><option value="active">Ativo</option></select></label>
            </div>
          </fieldset>

          <fieldset>
            <legend class="font-bold"><i class="fa-solid fa-building-columns mr-2 text-blue-500" aria-hidden="true"></i>Dados bancários</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
              <label class="<?= $label ?>"><span class="<?= $span ?>">Banco</span><input name="bank" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Agência</span><input name="agency" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Conta</span><input name="account" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Tipo</span>
                <select name="account_type" class="<?= $input ?>"><option value="corrente">Corrente</option><option value="poupanca">Poupança</option><option value="salario">Salário</option></select></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Chave PIX</span><input name="pix_key" class="<?= $input ?>"></label>
            </div>
          </fieldset>

          <fieldset>
            <legend class="font-bold"><i class="fa-solid fa-people-roof mr-2 text-blue-500" aria-hidden="true"></i>Dependente e contato de emergência <span class="text-xs font-normal text-slate-400">(opcionais)</span></legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <label class="<?= $label ?>"><span class="<?= $span ?>">Dependente — nome</span><input name="dep_name" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">CPF</span><input name="dep_cpf" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Parentesco</span>
                <select name="dep_relationship" class="<?= $input ?>"><option value="filho(a)">Filho(a)</option><option value="conjuge">Cônjuge</option><option value="enteado(a)">Enteado(a)</option><option value="outro">Outro</option></select></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Nascimento</span><input name="dep_birth_date" type="date" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Emergência — nome</span><input name="emg_name" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Parentesco</span><input name="emg_relationship" class="<?= $input ?>"></label>
              <label class="<?= $label ?>"><span class="<?= $span ?>">Telefone</span><input name="emg_phone" class="<?= $input ?>"></label>
            </div>
          </fieldset>

          <button type="submit" class="rounded-xl bg-blue-600 px-6 py-3 font-bold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700">
            <i class="fa-solid fa-check mr-1.5" aria-hidden="true"></i>Admitir colaborador
          </button>
        </form>

        <!-- ══ Listagem ══ -->
        <div class="<?= $card ?> overflow-x-auto">
          <table id="tabela-colab" class="w-full min-w-[640px] text-sm">
            <thead class="border-b border-slate-200 text-left dark:border-slate-800">
              <tr>
                <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Colaborador</th>
                <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Lotação</th>
                <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Admissão</th>
                <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Status</th>
                <th class="px-5 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <?php foreach ($employees as $emp): [$sl, $sc] = $statusBadge[$emp['status']] ?? $statusBadge['active']; ?>
                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                  <td class="px-5 py-3.5">
                    <div class="flex items-center gap-3">
                      <?php if ($emp['photo_path']): ?>
                        <img src="foto.php?id=<?= (int) $emp['id'] ?>" alt="" class="h-9 w-9 rounded-full object-cover">
                      <?php else: ?>
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300"><?= e(mb_strtoupper(mb_substr($emp['full_name'], 0, 2))) ?></span>
                      <?php endif; ?>
                      <div><p class="font-semibold"><?= e($emp['full_name']) ?></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Mat. <?= e($emp['registration']) ?> · <?= e($emp['position_title'] ?? '—') ?></p></div>
                    </div>
                  </td>
                  <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400"><?= e($emp['department'] ?? '—') ?><?= $emp['branch'] ? ' · '.e($emp['branch']) : '' ?></td>
                  <td class="px-5 py-3.5 tabular-nums text-slate-500 dark:text-slate-400"><?= br_date($emp['hired_at']) ?></td>
                  <td class="px-5 py-3.5"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $sc ?>"><?= $sl ?></span></td>
                  <td class="px-5 py-3.5 text-right">
                    <a href="colaborador.php?id=<?= (int) $emp['id'] ?>" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold hover:border-blue-400 hover:text-blue-600 dark:border-slate-700">Ficha completa</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

<?php require APP_PATH.'/views/layout/app_end.php'; ?>
