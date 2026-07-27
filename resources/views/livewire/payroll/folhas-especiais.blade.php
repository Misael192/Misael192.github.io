<div class="min-h-screen">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <a href="/painel" wire:navigate class="flex items-center gap-2 font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-8 w-8"> PeopleFlow
        </a>
        <a href="/folha" wire:navigate class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400">← Folha mensal</a>
    </header>

    <main class="mx-auto max-w-5xl p-6">
        <h1 class="text-2xl font-extrabold tracking-tight">Folhas especiais</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">13º salário, recibo de férias e rescisão.</p>

        @if ($flash)
            <div class="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:bg-blue-500/10 dark:text-blue-300">{{ $flash }}</div>
        @endif

        {{-- 13º salário --------------------------------------------------------- --}}
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-bold">13º salário</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A 1ª parcela cai em novembro; a 2ª (final) em dezembro, descontando o adiantamento.</p>

            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div>
                    <label for="decimoAno" class="mb-1.5 block text-sm font-semibold">Ano</label>
                    <input wire:model="decimoAno" id="decimoAno" type="number" min="2000" max="2100"
                           class="w-28 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('decimoAno') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="decimoParcela" class="mb-1.5 block text-sm font-semibold">Parcela</label>
                    <select wire:model="decimoParcela" id="decimoParcela"
                            class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                        <option value="1">1ª parcela (adiantamento)</option>
                        <option value="2">2ª parcela (final)</option>
                    </select>
                    @error('decimoParcela') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button wire:click="calcularDecimo" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                    <span wire:loading.remove wire:target="calcularDecimo">Calcular 13º</span>
                    <span wire:loading wire:target="calcularDecimo">Calculando…</span>
                </button>
            </div>
        </section>

        {{-- Recibo de férias ---------------------------------------------------- --}}
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-bold">Recibo de férias</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pedidos de férias aprovados prontos para gerar o recibo.</p>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                        <tr>
                            <th class="py-3 pr-5 font-semibold">Colaborador</th>
                            <th class="py-3 pr-5 font-semibold">Período</th>
                            <th class="py-3 pr-5 text-right font-semibold">Dias</th>
                            <th class="py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($vacations as $vacation)
                            <tr>
                                <td class="py-3 pr-5 font-medium">{{ $vacation->employee?->full_name }}</td>
                                <td class="py-3 pr-5 text-slate-500">
                                    {{ $vacation->start_date->format('d/m/Y') }} – {{ $vacation->end_date->format('d/m/Y') }}
                                </td>
                                <td class="py-3 pr-5 text-right tabular-nums">{{ $vacation->days }}</td>
                                <td class="py-3 text-right">
                                    @if ($comRecibo->has($vacation->id))
                                        <a href="/folha/holerite/{{ $comRecibo->get($vacation->id) }}" wire:navigate class="font-semibold text-blue-600 hover:underline">Ver recibo</a>
                                    @else
                                        <button wire:click="gerarRecibo('{{ $vacation->id }}')" class="font-semibold text-blue-600 hover:underline">
                                            <span wire:loading.remove wire:target="gerarRecibo('{{ $vacation->id }}')">Gerar recibo</span>
                                            <span wire:loading wire:target="gerarRecibo('{{ $vacation->id }}')">Gerando…</span>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-slate-400">Nenhum pedido de férias aprovado pendente.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Rescisão ------------------------------------------------------------ --}}
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-bold">Rescisão</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Simule as verbas rescisórias e depois efetive — gera o termo e desliga o colaborador.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="rescisaoEmployeeId" class="mb-1.5 block text-sm font-semibold">Colaborador</label>
                    <select wire:model="rescisaoEmployeeId" id="rescisaoEmployeeId"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                        <option value="">Selecione…</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                    @error('rescisaoEmployeeId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="rescisaoData" class="mb-1.5 block text-sm font-semibold">Data do desligamento</label>
                    <input wire:model="rescisaoData" id="rescisaoData" type="date"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('rescisaoData') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="rescisaoTipo" class="mb-1.5 block text-sm font-semibold">Modalidade</label>
                    <select wire:model="rescisaoTipo" id="rescisaoTipo"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                        <option value="sem_justa_causa">Sem justa causa</option>
                        <option value="pedido">Pedido de demissão</option>
                        <option value="acordo">Acordo (art. 484-A)</option>
                        <option value="justa_causa">Justa causa</option>
                    </select>
                </div>
                <div>
                    <label for="rescisaoAviso" class="mb-1.5 block text-sm font-semibold">Aviso prévio</label>
                    <select wire:model="rescisaoAviso" id="rescisaoAviso"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                        <option value="indenizado">Indenizado</option>
                        <option value="trabalhado">Trabalhado</option>
                        <option value="dispensado">Dispensado</option>
                    </select>
                </div>
                <div>
                    <label for="rescisaoSaldoFgts" class="mb-1.5 block text-sm font-semibold">Saldo do FGTS (R$)</label>
                    <input wire:model="rescisaoSaldoFgts" id="rescisaoSaldoFgts" type="number" step="0.01" min="0"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('rescisaoSaldoFgts') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="rescisaoDiasFerias" class="mb-1.5 block text-sm font-semibold">Dias de férias pendentes</label>
                    <input wire:model="rescisaoDiasFerias" id="rescisaoDiasFerias" type="number" min="0"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('rescisaoDiasFerias') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="rescisaoMotivo" class="mb-1.5 block text-sm font-semibold">Motivo (opcional)</label>
                    <input wire:model="rescisaoMotivo" id="rescisaoMotivo" type="text" maxlength="500"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <button wire:click="simular" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                    <span wire:loading.remove wire:target="simular">Simular verbas</span>
                    <span wire:loading wire:target="simular">Simulando…</span>
                </button>
                @if ($simulacao)
                    <button wire:click="efetivar" wire:confirm="Efetivar a rescisão? O colaborador será desligado."
                            class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-red-700">
                        <span wire:loading.remove wire:target="efetivar">Efetivar rescisão</span>
                        <span wire:loading wire:target="efetivar">Efetivando…</span>
                    </button>
                @endif
            </div>

            @if ($simulacao)
                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Verba</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($simulacao['items'] as $item)
                                <tr>
                                    <td class="px-4 py-2.5">{{ $item['description'] }}</td>
                                    <td @class([
                                        'px-4 py-2.5 text-right tabular-nums',
                                        'text-slate-500' => $item['type'] === 'deduction',
                                    ])>
                                        {{ $item['type'] === 'deduction' ? '−' : '' }}R$ {{ number_format($item['amount'] / 100, 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-slate-200 font-semibold dark:border-slate-800">
                            <tr>
                                <td class="px-4 py-2.5">Líquido</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">R$ {{ number_format($simulacao['net'] / 100, 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>
    </main>
</div>
