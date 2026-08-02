<div class="min-h-screen">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <a href="/painel" wire:navigate class="flex items-center gap-2 font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-8 w-8"> PeopleFlow
        </a>
        <a href="/painel" wire:navigate class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400">← Painel</a>
    </header>

    <main class="mx-auto max-w-5xl p-6">
        <h1 class="text-2xl font-extrabold tracking-tight">Ponto &amp; banco de horas</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Crédito (hora-extra) alimenta a folha do mês; débito compensa o saldo.</p>

        @if ($flash)
            <div class="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:bg-blue-500/10 dark:text-blue-300">{{ $flash }}</div>
        @endif

        {{-- Lançamento ------------------------------------------------------- --}}
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-bold">Novo lançamento</h2>

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
                    <label for="referenceDate" class="mb-1.5 block text-sm font-semibold">Data de referência</label>
                    <input wire:model="referenceDate" id="referenceDate" type="date"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('referenceDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="hours" class="mb-1.5 block text-sm font-semibold">Horas</label>
                        <input wire:model="hours" id="hours" type="number" step="0.25" min="0.25"
                               class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                        @error('hours') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="kind" class="mb-1.5 block text-sm font-semibold">Tipo</label>
                        <select wire:model="kind" id="kind"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                            <option value="credito">Crédito (hora-extra)</option>
                            <option value="debito">Débito (compensação)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button wire:click="lancar" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                    <span wire:loading.remove wire:target="lancar">Registrar lançamento</span>
                    <span wire:loading wire:target="lancar">Registrando…</span>
                </button>
            </div>
        </section>

        {{-- Saldos ----------------------------------------------------------- --}}
        <h2 class="mt-8 text-lg font-bold">Saldo por colaborador</h2>
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($employees as $employee)
                @php $minutes = (int) ($balances[$employee->id] ?? 0); @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <p class="truncate text-sm font-medium">{{ $employee->full_name }}</p>
                    <p @class([
                        'mt-1 text-2xl font-extrabold tabular-nums',
                        'text-emerald-600 dark:text-emerald-400' => $minutes > 0,
                        'text-red-600 dark:text-red-400' => $minutes < 0,
                    ])>
                        {{ $minutes >= 0 ? '+' : '−' }}{{ number_format(abs($minutes) / 60, 1, ',', '.') }} h
                    </p>
                </div>
            @endforeach
        </div>

        {{-- Lançamentos recentes -------------------------------------------- --}}
        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Data</th>
                        <th class="px-5 py-3 font-semibold">Colaborador</th>
                        <th class="px-5 py-3 font-semibold">Motivo</th>
                        <th class="px-5 py-3 text-right font-semibold">Horas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="px-5 py-3 text-slate-500">{{ $entry->reference_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 font-medium">{{ $employeeNames[$entry->employee_id] ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ ['overtime' => 'Hora-extra', 'compensation' => 'Compensação', 'adjustment' => 'Ajuste'][$entry->reason] ?? $entry->reason }}</td>
                            <td @class([
                                'px-5 py-3 text-right tabular-nums font-semibold',
                                'text-emerald-600 dark:text-emerald-400' => $entry->minutes > 0,
                                'text-red-600 dark:text-red-400' => $entry->minutes < 0,
                            ])>
                                {{ $entry->minutes >= 0 ? '+' : '−' }}{{ number_format(abs($entry->minutes) / 60, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-slate-400">Nenhum lançamento no banco de horas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</div>
