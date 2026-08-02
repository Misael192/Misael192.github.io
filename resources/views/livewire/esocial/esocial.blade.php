<div class="min-h-screen">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <a href="/painel" wire:navigate class="flex items-center gap-2 font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-8 w-8"> PeopleFlow
        </a>
        <a href="/painel" wire:navigate class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400">← Painel</a>
    </header>

    <main class="mx-auto max-w-5xl p-6">
        <h1 class="text-2xl font-extrabold tracking-tight">eSocial</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Gera S-2200 (admissão) e S-1200 (remuneração da folha fechada) nos leiautes oficiais.</p>

        @if ($flash)
            <div class="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:bg-blue-500/10 dark:text-blue-300">{{ $flash }}</div>
        @endif

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            {{-- S-2200 --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold">S-2200 · Admissão</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Gera o evento para os colaboradores ativos ainda sem admissão enviada.</p>
                <button wire:click="gerarAdmissoes" class="mt-4 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                    <span wire:loading.remove wire:target="gerarAdmissoes">Gerar admissões</span>
                    <span wire:loading wire:target="gerarAdmissoes">Gerando…</span>
                </button>
            </section>

            {{-- S-1200 --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold">S-1200 · Remuneração</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Exige a folha da competência <strong>fechada</strong>.</p>
                <div class="mt-4 flex flex-wrap items-end gap-3">
                    <div>
                        <label for="competency" class="mb-1.5 block text-sm font-semibold">Competência</label>
                        <input wire:model="competency" id="competency" type="month"
                               class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                        @error('competency') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button wire:click="gerarRemuneracao" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                        <span wire:loading.remove wire:target="gerarRemuneracao">Gerar S-1200</span>
                        <span wire:loading wire:target="gerarRemuneracao">Gerando…</span>
                    </button>
                </div>
            </section>
        </div>

        {{-- Lista de eventos --}}
        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Evento</th>
                        <th class="px-5 py-3 font-semibold">Referência</th>
                        <th class="px-5 py-3 font-semibold">Situação</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($events as $event)
                        <tr>
                            <td class="px-5 py-3 font-semibold">{{ $event->event_type }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $event->reference }}{{ $event->employee ? ' · '.$event->employee->full_name : '' }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $event->status }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <button wire:click="verXml('{{ $event->id }}')" class="font-semibold text-blue-600 hover:underline">Ver XML</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-slate-400">Nenhum evento gerado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($selected)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-bold">{{ $selected->event_type }} · {{ $selected->reference }}</h2>
                    <button wire:click="verXml('')" class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400">Fechar</button>
                </div>
                <pre class="overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs leading-relaxed text-slate-100"><code>{{ $selected->xml }}</code></pre>
            </div>
        @endif
    </main>
</div>
