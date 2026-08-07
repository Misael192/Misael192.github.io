<div class="min-h-screen">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <a href="/painel" wire:navigate class="flex items-center gap-2 font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-8 w-8"> PeopleFlow
        </a>
        <a href="/painel" wire:navigate class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400">← Painel</a>
    </header>

    <main class="mx-auto flex min-h-[calc(100vh-65px)] max-w-3xl flex-col p-6">
        <h1 class="text-2xl font-extrabold tracking-tight">Assistente CLT</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Calcula com as tabelas vigentes e cita a base legal — nada de valor "de cabeça".</p>

        <div class="mt-5 flex-1 space-y-4 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            @forelse ($messages as $message)
                @if ($message->role === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-[80%] whitespace-pre-line rounded-2xl rounded-br-sm bg-blue-600 px-4 py-2.5 text-sm text-white">{{ $message->content }}</div>
                    </div>
                @else
                    <div class="flex justify-start">
                        <div class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-bl-sm bg-slate-100 px-4 py-2.5 text-sm text-slate-800 dark:bg-slate-800 dark:text-slate-100">{{ $message->content }}</div>
                    </div>
                @endif
            @empty
                <div class="py-10 text-center text-sm text-slate-400">
                    <p class="font-semibold">Pergunte sobre folha e CLT.</p>
                    <p class="mt-2">Ex.: "Salário líquido de R$ 5.200 com 1 dependente" · "INSS de R$ 3.000" · "Férias de 30 dias com salário de R$ 3.000" · "Regras de aviso prévio".</p>
                </div>
            @endforelse
        </div>

        <form wire:submit="enviar" class="mt-4 flex items-end gap-3">
            <div class="flex-1">
                <textarea wire:model="draft" rows="2" placeholder="Pergunte algo sobre folha ou CLT…"
                          class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900"></textarea>
                @error('draft') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                <span wire:loading.remove wire:target="enviar">Enviar</span>
                <span wire:loading wire:target="enviar">…</span>
            </button>
        </form>
    </main>
</div>
