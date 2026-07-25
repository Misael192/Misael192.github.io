<div class="min-h-screen">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <a href="/painel" wire:navigate class="flex items-center gap-2 font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-8 w-8"> PeopleFlow
        </a>
        <a href="/painel" wire:navigate class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400">← Painel</a>
    </header>

    <main class="mx-auto max-w-5xl p-6">
        <h1 class="text-2xl font-extrabold tracking-tight">Folha de pagamento</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Fechamento mensal da competência.</p>

        <div class="mt-6 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div>
                <label for="competency" class="mb-1.5 block text-sm font-semibold">Competência</label>
                <input wire:model="competency" id="competency" type="month"
                       class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                @error('competency') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <button wire:click="calcular" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                <span wire:loading.remove wire:target="calcular">Calcular folha</span>
                <span wire:loading wire:target="calcular">Calculando…</span>
            </button>

            @if ($period && $period->status === \App\Models\PayrollPeriod::STATUS_CALCULATED)
                <button wire:click="fechar" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Fechar competência</button>
            @elseif ($period && $period->status === \App\Models\PayrollPeriod::STATUS_CLOSED)
                <button wire:click="reabrir" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Reabrir</button>
            @endif

            @if ($period)
                <span @class([
                    'ml-auto rounded-full px-3 py-1 text-xs font-semibold',
                    'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400' => $period->status === 'calculated',
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' => $period->status === 'closed',
                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $period->status === 'open',
                ])>
                    {{ ['open' => 'Aberta', 'calculated' => 'Calculada', 'closed' => 'Fechada'][$period->status] ?? $period->status }}
                </span>
            @endif
        </div>

        @if ($flash)
            <div class="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:bg-blue-500/10 dark:text-blue-300">{{ $flash }}</div>
        @endif

        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Colaborador</th>
                        <th class="px-5 py-3 text-right font-semibold">Bruto</th>
                        <th class="px-5 py-3 text-right font-semibold">Descontos</th>
                        <th class="px-5 py-3 text-right font-semibold">Líquido</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($payrolls as $payroll)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $payroll->employee?->full_name }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">R$ {{ number_format($payroll->gross_cents / 100, 2, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-500">R$ {{ number_format($payroll->deductions_cents / 100, 2, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-semibold tabular-nums">R$ {{ number_format($payroll->net_cents / 100, 2, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="/folha/holerite/{{ $payroll->id }}" wire:navigate class="font-semibold text-blue-600 hover:underline">Holerite</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-slate-400">Nenhuma folha calculada nesta competência.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</div>
