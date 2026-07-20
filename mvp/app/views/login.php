<?php $title = 'Entrar'; require APP_PATH.'/views/layout/head.php'; ?>
<body class="flex min-h-screen items-center justify-center bg-slate-50 px-6 antialiased dark:bg-slate-950 dark:text-slate-100"
      x-data="{ show: false }">
  <main class="w-full max-w-md">
    <a href="index.php" class="mb-8 flex items-center justify-center gap-2 text-xl font-bold">
      <img src="assets/img/favicon.svg" alt="" class="h-9 w-9">PeopleFlow
    </a>

    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-xl dark:border-slate-800 dark:bg-slate-900">
      <h1 class="text-xl font-extrabold">Bem-vindo de volta 👋</h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Acesse a plataforma da sua empresa.</p>

      <?php if ($msg = flash('error')): ?>
        <div class="mt-5 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300" role="alert">
          <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i><?= e($msg) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="login.php" class="mt-6 space-y-4">
        <?= csrf_field() ?>
        <label class="block text-sm">
          <span class="mb-1.5 block font-semibold">E-mail corporativo</span>
          <input name="email" type="email" required autocomplete="email" placeholder="voce@empresa.com.br"
                 class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 outline-none transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800">
        </label>
        <label class="block text-sm">
          <span class="mb-1.5 block font-semibold">Senha</span>
          <span class="relative block">
            <input name="password" :type="show ? 'text' : 'password'" required minlength="8" autocomplete="current-password" placeholder="••••••••"
                   class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 pr-11 outline-none transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800">
            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" :aria-label="show ? 'Ocultar senha' : 'Mostrar senha'">
              <i class="fa-regular" :class="show ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
            </button>
          </span>
        </label>
        <button type="submit" class="w-full rounded-xl bg-blue-600 py-3 font-bold text-white shadow-lg shadow-blue-600/25 transition-colors hover:bg-blue-700">
          Entrar <i class="fa-solid fa-arrow-right ml-1 text-sm" aria-hidden="true"></i>
        </button>
      </form>

      <p class="mt-6 rounded-xl bg-slate-50 px-4 py-3 text-center text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400">
        Demo: <strong>admin@demo.com</strong> · senha <strong>password</strong>
      </p>
    </div>
    <p class="mt-6 text-center text-xs text-slate-400"><i class="fa-solid fa-lock mr-1" aria-hidden="true"></i>Senha protegida com Argon2id · sessão com CSRF</p>
  </main>
</body>
</html>
