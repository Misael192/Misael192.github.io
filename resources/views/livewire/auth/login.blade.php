<div class="flex min-h-screen">
    {{-- Painel institucional (desktop) --}}
    <aside class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-blue-700 via-blue-600 to-violet-700 p-12 text-white lg:flex" aria-hidden="true">
        <div class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-white/10 blur-3xl"></div>
        <span class="flex items-center gap-2 text-xl font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-9 w-9 rounded-xl bg-white/15 p-1"> PeopleFlow
        </span>
        <div>
            <h2 class="max-w-md text-4xl font-extrabold leading-tight">A gestão de pessoas da sua empresa, fluindo.</h2>
            <p class="mt-4 max-w-md text-blue-100">DP, RH, IA e Analytics em uma única plataforma multiempresa.</p>
        </div>
        <p class="text-xs text-blue-200">© {{ date('Y') }} PeopleFlow · LGPD by design</p>
    </aside>

    {{-- Formulário --}}
    <main class="flex w-full items-center justify-center p-6 lg:w-1/2">
        <div class="w-full max-w-md">
            <h1 class="text-2xl font-extrabold tracking-tight">Bem-vindo de volta 👋</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Acesse a plataforma da sua empresa.</p>

            <form wire:submit="authenticate" class="mt-8 space-y-4">
                <div>
                    <label for="tenant" class="mb-1.5 block text-sm font-semibold">Empresa</label>
                    <input wire:model="tenant" id="tenant" type="text" required placeholder="demo" autocomplete="organization"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('tenant') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-semibold">E-mail corporativo</label>
                    <input wire:model="email" id="email" type="email" required placeholder="voce@empresa.com.br" autocomplete="email"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-semibold">Senha</label>
                    <input wire:model="password" id="password" type="password" required placeholder="••••••••" autocomplete="current-password"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="w-full rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                    <span wire:loading.remove wire:target="authenticate">Entrar</span>
                    <span wire:loading wire:target="authenticate">Entrando…</span>
                </button>
            </form>
        </div>
    </main>
</div>
