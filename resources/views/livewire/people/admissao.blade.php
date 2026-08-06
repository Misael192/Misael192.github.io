<div class="min-h-screen">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <a href="/painel" wire:navigate class="flex items-center gap-2 font-bold">
            <img src="{{ asset('assets/img/favicon.svg') }}" alt="" class="h-8 w-8"> PeopleFlow
        </a>
        <a href="/colaboradores" wire:navigate class="text-sm font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400">← Colaboradores</a>
    </header>

    <main class="mx-auto max-w-3xl p-6">
        <h1 class="text-2xl font-extrabold tracking-tight">Admissão digital</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Conclua o checklist de cada colaborador — ao marcar tudo, ele é ativado automaticamente.</p>

        @if ($flash)
            <div class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $flash }}</div>
        @endif

        <div class="mt-6 space-y-4">
            @forelse ($employees as $employee)
                @php
                    $tasks = $employee->admissionTasks;
                    $done = $tasks->where('is_done', true)->count();
                    $total = $tasks->count();
                @endphp
                <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold">{{ $employee->full_name }}</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Matrícula {{ $employee->registration_number }}</p>
                        </div>
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">{{ $done }}/{{ $total }}</span>
                    </div>

                    <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full bg-emerald-500 transition-all" style="width: {{ $total > 0 ? round($done / $total * 100) : 0 }}%"></div>
                    </div>

                    <ul class="mt-4 space-y-1.5">
                        @foreach ($tasks as $task)
                            <li>
                                <button wire:click="alternarItem('{{ $task->id }}')" class="flex w-full items-center gap-3 rounded-lg px-2 py-1.5 text-left text-sm transition-colors hover:bg-slate-50 dark:hover:bg-slate-800">
                                    <span @class([
                                        'flex h-5 w-5 flex-none items-center justify-center rounded-md border text-xs',
                                        'border-emerald-500 bg-emerald-500 text-white' => $task->is_done,
                                        'border-slate-300 dark:border-slate-600' => ! $task->is_done,
                                    ])>
                                        @if ($task->is_done) ✓ @endif
                                    </span>
                                    <span @class(['line-through text-slate-400' => $task->is_done])>{{ $task->label }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-400 dark:border-slate-800">
                    Nenhum colaborador em admissão. Cadastre um em <a href="/colaboradores" wire:navigate class="font-semibold text-blue-600 hover:underline">Colaboradores</a> com a situação "Em admissão".
                </p>
            @endforelse
        </div>
    </main>
</div>
