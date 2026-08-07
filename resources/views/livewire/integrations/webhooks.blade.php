<div class="min-h-screen">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <a href="/painel" wire:navigate class="flex items-center gap-2 font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-8 w-8"> PeopleFlow
        </a>
        <a href="/painel" wire:navigate class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400">← Painel</a>
    </header>

    <main class="mx-auto max-w-5xl p-6">
        <h1 class="text-2xl font-extrabold tracking-tight">Webhooks</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Entregas assinadas (HMAC-SHA256) a cada folha fechada — com histórico e reenvio.</p>

        @if ($flash)
            <div class="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:bg-blue-500/10 dark:text-blue-300">{{ $flash }}</div>
        @endif

        {{-- Cadastro --}}
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-bold">Novo webhook</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="mb-1.5 block text-sm font-semibold">Nome</label>
                    <input wire:model="name" id="name" type="text" placeholder="ERP financeiro"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="url" class="mb-1.5 block text-sm font-semibold">URL de destino</label>
                    <input wire:model="url" id="url" type="url" placeholder="https://exemplo.com/webhooks/peopleflow"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="secret" class="mb-1.5 block text-sm font-semibold">Segredo (assina o corpo)</label>
                    <input wire:model="secret" id="secret" type="text"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 font-mono text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('secret') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-4">
                <button wire:click="cadastrar" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                    <span wire:loading.remove wire:target="cadastrar">Cadastrar webhook</span>
                    <span wire:loading wire:target="cadastrar">Cadastrando…</span>
                </button>
            </div>
        </section>

        {{-- Webhooks cadastrados --}}
        <h2 class="mt-8 text-lg font-bold">Endpoints</h2>
        <div class="mt-3 space-y-2">
            @forelse ($webhooks as $webhook)
                <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $webhook->name }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $webhook->config['url'] ?? '' }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span @class([
                            'rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' => $webhook->is_active,
                            'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! $webhook->is_active,
                        ])>{{ $webhook->is_active ? 'Ativo' : 'Inativo' }}</span>
                        <button wire:click="alternar('{{ $webhook->id }}')" class="text-sm font-semibold text-blue-600 hover:underline">{{ $webhook->is_active ? 'Desativar' : 'Ativar' }}</button>
                    </div>
                </div>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400 dark:border-slate-800">Nenhum webhook cadastrado.</p>
            @endforelse
        </div>

        {{-- Entregas --}}
        <h2 class="mt-8 text-lg font-bold">Entregas recentes</h2>
        <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Evento</th>
                        <th class="px-5 py-3 text-right font-semibold">Código</th>
                        <th class="px-5 py-3 text-right font-semibold">Tentativas</th>
                        <th class="px-5 py-3 font-semibold">Entregue</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $log->event }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                <span @class([
                                    'font-semibold',
                                    'text-emerald-600 dark:text-emerald-400' => $log->delivered_at !== null,
                                    'text-red-600 dark:text-red-400' => $log->delivered_at === null,
                                ])>{{ $log->response_code ?? '—' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $log->attempts }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $log->delivered_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                <button wire:click="reenviar('{{ $log->id }}')" class="font-semibold text-blue-600 hover:underline">Reenviar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-slate-400">Nenhuma entrega ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</div>
