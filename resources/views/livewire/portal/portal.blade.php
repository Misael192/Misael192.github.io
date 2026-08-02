<div class="min-h-screen">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <span class="flex items-center gap-2 font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-8 w-8"> PeopleFlow
        </span>
        <div class="flex items-center gap-4 text-sm">
            <span class="text-slate-500 dark:text-slate-400">{{ $employee->full_name }}</span>
            <button wire:click="logout" class="rounded-lg border border-slate-200 px-3 py-1.5 font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Sair</button>
        </div>
    </header>

    <main class="mx-auto max-w-4xl p-6">
        <h1 class="text-2xl font-extrabold tracking-tight">Meu portal</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Seus holerites, ponto e férias.</p>

        @if ($flash)
            <div class="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:bg-blue-500/10 dark:text-blue-300">{{ $flash }}</div>
        @endif

        {{-- Ponto + Férias --}}
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold">Ponto</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Registre entrada e saída.</p>
                <button wire:click="baterPonto" class="mt-4 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                    <span wire:loading.remove wire:target="baterPonto">Bater ponto</span>
                    <span wire:loading wire:target="baterPonto">Registrando…</span>
                </button>
                <div class="mt-4 flex flex-wrap gap-2">
                    @forelse ($todayPunches as $punch)
                        <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            {{ $punch->type === 'clock_in' ? '↓' : '↑' }} {{ $punch->recorded_at->format('H:i') }}
                        </span>
                    @empty
                        <span class="text-xs text-slate-400">Sem batidas hoje.</span>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold">Solicitar férias</h2>
                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <div>
                        <label for="startDate" class="mb-1.5 block text-sm font-semibold">Início</label>
                        <input wire:model="startDate" id="startDate" type="date"
                               class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                        @error('startDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="days" class="mb-1.5 block text-sm font-semibold">Dias</label>
                        <input wire:model="days" id="days" type="number" min="1" max="30"
                               class="w-24 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                        @error('days') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button wire:click="solicitarFerias" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Solicitar</button>
                </div>
                <div class="mt-4 space-y-2">
                    @foreach ($vacations as $vacation)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-600 dark:text-slate-300">{{ $vacation->start_date->format('d/m/Y') }} · {{ $vacation->days }}d</span>
                            <span @class([
                                'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400' => $vacation->status === 'requested',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' => $vacation->status === 'approved',
                                'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => $vacation->status === 'rejected',
                            ])>{{ $vacationLabels[$vacation->status] ?? $vacation->status }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Holerites próprios --}}
        <h2 class="mt-8 text-lg font-bold">Meus holerites</h2>
        <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Competência</th>
                        <th class="px-5 py-3 font-semibold">Tipo</th>
                        <th class="px-5 py-3 text-right font-semibold">Líquido</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($payrolls as $payroll)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $payroll->period?->competency }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $payroll->kind }}</td>
                            <td class="px-5 py-3 text-right font-semibold tabular-nums">R$ {{ number_format($payroll->net_cents / 100, 2, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="/portal/holerite/{{ $payroll->id }}" wire:navigate class="font-semibold text-blue-600 hover:underline">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-slate-400">Nenhum holerite disponível ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</div>
