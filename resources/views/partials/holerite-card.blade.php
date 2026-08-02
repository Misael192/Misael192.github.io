{{-- Corpo do holerite (itens, totais, encargos). Compartilhado entre a folha
     de DP e o portal do colaborador — recebe $payroll. --}}
<div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-xl font-extrabold tracking-tight">{{ $payroll->employee?->full_name }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Competência {{ $payroll->period?->competency }} · {{ $payroll->kind }}
            </p>
        </div>
        <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Holerite</span>
    </div>

    <table class="mt-6 w-full text-sm">
        <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
            <tr>
                <th class="py-2 font-semibold">Descrição</th>
                <th class="py-2 text-right font-semibold">Ref.</th>
                <th class="py-2 text-right font-semibold">Valor</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($payroll->items as $item)
                <tr>
                    <td class="py-2">{{ $item->description }}</td>
                    <td class="py-2 text-right tabular-nums text-slate-500">{{ $item->reference !== null ? rtrim(rtrim(number_format($item->reference, 2, ',', '.'), '0'), ',') : '—' }}</td>
                    <td @class([
                        'py-2 text-right tabular-nums',
                        'text-emerald-600 dark:text-emerald-400' => $item->type === 'earning',
                        'text-red-600 dark:text-red-400' => $item->type === 'deduction',
                    ])>
                        {{ $item->type === 'deduction' ? '−' : '' }}R$ {{ number_format($item->amount_cents / 100, 2, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4 grid grid-cols-3 gap-3 border-t border-slate-200 pt-4 text-sm dark:border-slate-800">
        <div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Bruto</p>
            <p class="mt-1 font-semibold tabular-nums">R$ {{ number_format($payroll->gross_cents / 100, 2, ',', '.') }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Descontos</p>
            <p class="mt-1 font-semibold tabular-nums">R$ {{ number_format($payroll->deductions_cents / 100, 2, ',', '.') }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Líquido</p>
            <p class="mt-1 text-lg font-extrabold tabular-nums">R$ {{ number_format($payroll->net_cents / 100, 2, ',', '.') }}</p>
        </div>
    </div>

    @if ($payroll->charges->isNotEmpty())
        <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Encargos (não saem do líquido)</p>
            @foreach ($payroll->charges as $charge)
                <div class="mt-2 flex justify-between text-sm">
                    <span class="text-slate-600 dark:text-slate-300">{{ strtoupper($charge->type) }} ({{ rtrim(rtrim(number_format($charge->rate, 2, ',', '.'), '0'), ',') }}%)</span>
                    <span class="tabular-nums">R$ {{ number_format($charge->amount_cents / 100, 2, ',', '.') }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
