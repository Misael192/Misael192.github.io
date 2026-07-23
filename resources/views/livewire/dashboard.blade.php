<div class="min-h-screen">
    {{-- Topbar --}}
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <span class="flex items-center gap-2 font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-8 w-8"> PeopleFlow
        </span>
        <div class="flex items-center gap-4 text-sm">
            <span class="text-slate-500 dark:text-slate-400">{{ auth()->user()?->name }}</span>
            <button wire:click="logout" class="rounded-lg border border-slate-200 px-3 py-1.5 font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                Sair
            </button>
        </div>
    </header>

    <main class="mx-auto max-w-5xl p-6">
        <h1 class="text-2xl font-extrabold tracking-tight">Painel</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Visão geral da sua empresa.</p>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">Colaboradores ativos</p>
                <p class="mt-2 text-3xl font-extrabold">{{ $employeeCount }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">Competências em aberto</p>
                <p class="mt-2 text-3xl font-extrabold">{{ $openPeriods }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">Férias pendentes</p>
                <p class="mt-2 text-3xl font-extrabold">{{ $pendingVacations }}</p>
            </div>
        </div>
    </main>
</div>
