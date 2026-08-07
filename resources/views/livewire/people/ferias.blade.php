<div class="min-h-screen">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <a href="/painel" wire:navigate class="flex items-center gap-2 font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-8 w-8"> PeopleFlow
        </a>
        <a href="/painel" wire:navigate class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400">← Painel</a>
    </header>

    <main class="mx-auto max-w-5xl p-6">
        <h1 class="text-2xl font-extrabold tracking-tight">Férias</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Solicitação e aprovação — o pedido aprovado vira recibo nas folhas especiais.</p>

        @if ($flash)
            <div class="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:bg-blue-500/10 dark:text-blue-300">{{ $flash }}</div>
        @endif

        {{-- Solicitação ------------------------------------------------------ --}}
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-bold">Nova solicitação</h2>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="employeeId" class="mb-1.5 block text-sm font-semibold">Colaborador</label>
                    <select wire:model="employeeId" id="employeeId"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                        <option value="">Selecione…</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                    @error('employeeId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="startDate" class="mb-1.5 block text-sm font-semibold">Início</label>
                    <input wire:model="startDate" id="startDate" type="date"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('startDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="days" class="mb-1.5 block text-sm font-semibold">Dias de gozo</label>
                        <input wire:model="days" id="days" type="number" min="1" max="30"
                               class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                        @error('days') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="sellDays" class="mb-1.5 block text-sm font-semibold">Abono (dias)</label>
                        <input wire:model="sellDays" id="sellDays" type="number" min="0" max="10"
                               class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                        @error('sellDays') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button wire:click="solicitar" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                    <span wire:loading.remove wire:target="solicitar">Solicitar férias</span>
                    <span wire:loading wire:target="solicitar">Registrando…</span>
                </button>
            </div>
        </section>

        {{-- Lista ------------------------------------------------------------ --}}
        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Colaborador</th>
                        <th class="px-5 py-3 font-semibold">Período</th>
                        <th class="px-5 py-3 text-right font-semibold">Dias</th>
                        <th class="px-5 py-3 text-right font-semibold">Abono</th>
                        <th class="px-5 py-3 font-semibold">Situação</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($vacations as $vacation)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $vacation->employee?->full_name }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $vacation->start_date->format('d/m/Y') }} – {{ $vacation->end_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $vacation->days }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $vacation->sell_days }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400' => $vacation->status === 'requested',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' => $vacation->status === 'approved',
                                    'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => $vacation->status === 'rejected',
                                ])>{{ $statusLabels[$vacation->status] ?? $vacation->status }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($vacation->status === 'requested')
                                    <div class="flex justify-end gap-3">
                                        <button wire:click="aprovar('{{ $vacation->id }}')" class="font-semibold text-emerald-600 hover:underline">Aprovar</button>
                                        <button wire:click="recusar('{{ $vacation->id }}')" class="font-semibold text-slate-500 hover:underline">Recusar</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-slate-400">Nenhuma solicitação de férias.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</div>
