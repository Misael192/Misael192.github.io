<div class="min-h-screen">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <a href="/painel" wire:navigate class="flex items-center gap-2 font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-8 w-8"> PeopleFlow
        </a>
        <a href="/painel" wire:navigate class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400">← Painel</a>
    </header>

    <main class="mx-auto max-w-5xl p-6">
        <h1 class="text-2xl font-extrabold tracking-tight">Colaboradores</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Cadastro de pessoas e contrato vigente — alimenta a folha.</p>

        @if ($flash)
            <div class="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:bg-blue-500/10 dark:text-blue-300">{{ $flash }}</div>
        @endif

        {{-- Cadastro --------------------------------------------------------- --}}
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-bold">Novo colaborador</h2>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="fullName" class="mb-1.5 block text-sm font-semibold">Nome completo</label>
                    <input wire:model="fullName" id="fullName" type="text"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('fullName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="registrationNumber" class="mb-1.5 block text-sm font-semibold">Matrícula</label>
                    <input wire:model="registrationNumber" id="registrationNumber" type="text"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('registrationNumber') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="hiredAt" class="mb-1.5 block text-sm font-semibold">Admissão</label>
                    <input wire:model="hiredAt" id="hiredAt" type="date"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('hiredAt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="type" class="mb-1.5 block text-sm font-semibold">Vínculo</label>
                    <select wire:model="type" id="type"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                        <option value="clt">CLT</option>
                        <option value="pj">PJ</option>
                        <option value="estagio">Estágio</option>
                        <option value="temporario">Temporário</option>
                    </select>
                </div>
                <div>
                    <label for="salary" class="mb-1.5 block text-sm font-semibold">Salário (R$)</label>
                    <input wire:model="salary" id="salary" type="number" step="0.01" min="0"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('salary') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="weeklyHours" class="mb-1.5 block text-sm font-semibold">Jornada semanal (h)</label>
                    <input wire:model="weeklyHours" id="weeklyHours" type="number" min="1" max="60" placeholder="44"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900">
                    @error('weeklyHours') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-4">
                <button wire:click="cadastrar" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                    <span wire:loading.remove wire:target="cadastrar">Cadastrar colaborador</span>
                    <span wire:loading wire:target="cadastrar">Cadastrando…</span>
                </button>
            </div>
        </section>

        {{-- Lista ------------------------------------------------------------ --}}
        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Colaborador</th>
                        <th class="px-5 py-3 font-semibold">Matrícula</th>
                        <th class="px-5 py-3 text-right font-semibold">Salário</th>
                        <th class="px-5 py-3 font-semibold">Situação</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($employees as $employee)
                        @php $contract = $employee->contracts->first(); @endphp
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $employee->full_name }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $employee->registration_number }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                {{ $contract?->salary_cents ? 'R$ '.number_format($contract->salary_cents / 100, 2, ',', '.') : '—' }}
                            </td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' => $employee->status === 'active',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400' => $employee->status === 'admission',
                                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! in_array($employee->status, ['active', 'admission'], true),
                                ])>{{ $statusLabels[$employee->status] ?? $employee->status }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($employee->status === 'admission')
                                    <button wire:click="ativar('{{ $employee->id }}')" class="font-semibold text-blue-600 hover:underline">Ativar</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-slate-400">Nenhum colaborador cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</div>
