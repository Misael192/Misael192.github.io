<div class="min-h-screen">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <a href="/painel" wire:navigate class="flex items-center gap-2 font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-8 w-8"> PeopleFlow
        </a>
        <a href="/folha" wire:navigate class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400">← Folha</a>
    </header>

    <main class="mx-auto max-w-2xl p-6">
        @include('partials.holerite-card')
    </main>
</div>
