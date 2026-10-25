@props(['target'])

@php
    $tasks = $target->tasks ?: collect();
    $hasTasks = $tasks->isNotEmpty();
    
    // Legacy subtasks calculation
    $subs = $target->subTasks ?: collect();
    $tSubsTotal = $subs->count();
    $tSubsDone = $subs->where('is_completed', true)->count();
    $tSubPct = $tSubsTotal > 0 ? round(($tSubsDone / $tSubsTotal) * 100) : 0;

    // Bulk Tasks calculation
    $tTasksTotal = $tasks->count();
    $tTasksDone = $tasks->where('is_completed', true)->count();
    $tTaskPct = $tTasksTotal > 0 ? round(($tTasksDone / $tTasksTotal) * 100) : 0;
    
    $totalSubtasksAcrossTasks = $tasks->sum(fn($t) => $t->subTasks ? $t->subTasks->count() : 0);
    $doneSubtasksAcrossTasks = $tasks->sum(fn($t) => $t->subTasks ? $t->subTasks->where('is_completed', true)->count() : 0);

    $classifyPeriod = function($title) {
        $t = strtolower($title);
        // 1. Afternoon / Siang (midday study, university, dzuhur, ashar, lunch)
        if (str_contains($t, 'siang') || str_contains($t, 'dzuhur') || str_contains($t, 'ashar') 
            || str_contains($t, 'kuliah') || str_contains($t, 'jeda kos') || str_contains($t, 'power nap')
            || preg_match('/\b(1[2-6])[:.][0-5][0-9]\b/u', $t)) {
            return 'afternoon';
        }

        // 2. Evening / Malam (evening creative work, dinner, leisure, bedtime)
        if (str_contains($t, 'sore') || str_contains($t, 'malam') || str_contains($t, 'maghrib') 
            || str_contains($t, 'isya') || str_contains($t, 'konten') || str_contains($t, 'gaming') 
            || str_contains($t, 'genshin') || str_contains($t, 'ea fc') || str_contains($t, 'dinner') 
            || str_contains($t, 'makan malam') || str_contains($t, 'journaling') || str_contains($t, 'tidur') 
            || str_contains($t, 'evaluasi') || str_contains($t, 'workout otot')
            || preg_match('/\b(1[7-9]|2[0-3]|0[0-3])[:.][0-5][0-9]\b/u', $t)) {
            return 'evening';
        }

        // 3. Default to Morning / Pagi
        return 'morning';
    };

    $parseTimeAndTitle = function($rawTitle) {
        $raw = trim($rawTitle);
        if (preg_match('/^\[([0-9]{1,2}[.:][0-9]{2}\s*[–\-\x{2013}\x{2014}]\s*[0-9]{1,2}[.:][0-9]{2})\]\s*(.*)$/u', $raw, $m)) {
            return ['time' => trim($m[1]), 'clean_title' => trim($m[2])];
        }
        if (preg_match('/^\[(Pagi|Siang|Sore|Malam)\]\s*(.*)$/ui', $raw, $m)) {
            return ['time' => trim($m[1]), 'clean_title' => trim($m[2])];
        }
        if (preg_match('/^([0-9]{1,2}[.:][0-9]{2}\s*[–\-\x{2013}\x{2014}]\s*[0-9]{1,2}[.:][0-9]{2})[:\s\-]+(.*)$/u', $raw, $m)) {
            return ['time' => trim($m[1]), 'clean_title' => trim($m[2])];
        }
        if (preg_match('/^([0-9]{1,2}[.:][0-9]{2})[:\s\-]+(.*)$/u', $raw, $m)) {
            return ['time' => trim($m[1]), 'clean_title' => trim($m[2])];
        }
        return ['time' => null, 'clean_title' => $raw];
    };

    $classifiedSubs = $subs->map(function($st) use ($classifyPeriod, $parseTimeAndTitle) {
        $parsed = $parseTimeAndTitle($st->title);
        $period = $classifyPeriod($st->title);
        return [
            'id' => $st->id,
            'model' => $st,
            'time' => $parsed['time'],
            'clean_title' => $parsed['clean_title'],
            'period' => $period,
            'is_completed' => (bool) $st->is_completed,
        ];
    });

    $morningSubs = $classifiedSubs->where('period', 'morning');
    $afternoonSubs = $classifiedSubs->where('period', 'afternoon');
    $eveningSubs = $classifiedSubs->where('period', 'evening');

    $hasSegments = $tSubsTotal >= 5 && ($morningSubs->count() > 0 && ($afternoonSubs->count() > 0 || $eveningSubs->count() > 0));
    $mDone = $morningSubs->where('is_completed', true)->count();
    $aDone = $afternoonSubs->where('is_completed', true)->count();
    $eDone = $eveningSubs->where('is_completed', true)->count();
@endphp

<div class="mt-2.5 p-3.5 bg-gray-50/80 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-700/60 space-y-2.5 text-left transition-all">

    @if($hasTasks)
        <!-- ========================================== -->
        <!-- UPGRADED HIERARCHICAL TASK LIST & SUBTASKS -->
        <!-- ========================================== -->
        <div class="flex items-center justify-between text-[11px] font-extrabold text-gray-500 dark:text-gray-400">
            <span class="inline-flex items-center gap-1.5">
                <x-icon.check-circle class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan inline shrink-0" />
                <span>{{ __('Task List Hari Ke-:day', ['day' => $target->day_number]) }}</span>
            </span>
            <div class="flex items-center gap-2">
                @if($totalSubtasksAcrossTasks > 0)
                    <span class="text-[10px] font-medium text-gray-400 dark:text-gray-500">
                        ({{ $doneSubtasksAcrossTasks }}/{{ $totalSubtasksAcrossTasks }} subtask)
                    </span>
                @endif
                <span class="text-atoma-blue dark:text-atoma-cyan font-mono font-black text-xs">
                    {{ $tTasksDone }}/{{ $tTasksTotal }} ({{ $tTaskPct }}%)
                </span>
            </div>
        </div>

        <!-- Micro-progress Bar -->
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
            <div class="bg-gradient-to-r from-atoma-blue via-atoma-cyan to-emerald-400 h-1.5 rounded-full transition-all duration-300" style="width: {{ $tTaskPct }}%"></div>
        </div>

        <!-- Task List Items -->
        <div class="space-y-2 pt-0.5">
            @foreach($tasks as $taskIndex => $task)
                @php
                    $taskSubs = $task->subTasks ?: collect();
                    $taskSubsTotal = $taskSubs->count();
                    $taskSubsDone = $taskSubs->where('is_completed', true)->count();
                @endphp
                <div class="rounded-xl border border-gray-200/80 dark:border-gray-700/70 bg-white/95 dark:bg-gray-800/90 p-2.5 transition-all shadow-2xs hover:border-atoma-blue/40"
                     x-data="{ showSubs: true, addingSub: false, newSubTitle: '' }">
                    <!-- Task Header Row -->
                    <div class="flex items-start justify-between gap-2">
                        <label class="flex items-start gap-2.5 cursor-pointer select-none flex-1 min-w-0">
                            <input type="checkbox" 
                                   wire:click="toggleTargetTask({{ $target->id }}, {{ $task->id }})" 
                                   {{ $task->is_completed ? 'checked' : '' }} 
                                   class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-atoma-blue focus:ring-atoma-blue w-4 h-4 cursor-pointer shrink-0">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-xs sm:text-[13px] font-bold leading-snug break-words {{ $task->is_completed ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-800 dark:text-gray-100' }}">
                                        {{ $task->title }}
                                    </span>
                                    @if($taskSubsTotal > 0)
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold shrink-0 {{ $taskSubsDone === $taskSubsTotal ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300' : 'bg-atoma-ice text-atoma-blue dark:bg-atoma-blue/20 dark:text-atoma-cyan' }}">
                                            {{ $taskSubsDone }}/{{ $taskSubsTotal }} subtask
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </label>

                        <!-- Actions: Add Subtask & Toggle Accordion -->
                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button" 
                                    @click="addingSub = !addingSub" 
                                    class="px-1.5 py-0.5 rounded-lg text-gray-400 hover:text-atoma-blue dark:hover:text-atoma-cyan hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-[10px] font-bold inline-flex items-center gap-0.5 cursor-pointer" 
                                    title="{{ __('Tambah subtask ke tugas ini') }}">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>+ Sub</span>
                            </button>
                            @if($taskSubsTotal > 0)
                                <button type="button" 
                                        @click="showSubs = !showSubs" 
                                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-transform duration-200 cursor-pointer"
                                        :class="showSubs ? 'rotate-180' : ''"
                                        title="{{ __('Buka/tutup subtask') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Inline Input to Add Subtask to this Task -->
                    <div x-show="addingSub" x-cloak class="mt-2 pt-2 border-t border-gray-150 dark:border-gray-700/60 flex items-center gap-1.5">
                        <input type="text" 
                               x-model="newSubTitle" 
                               @keydown.enter.prevent="if (newSubTitle.trim()) { $wire.quickAddSubTaskToTask({{ $target->id }}, {{ $task->id }}, newSubTitle); newSubTitle = ''; addingSub = false; }" 
                               placeholder="{{ __('+ Nama subtask...') }}" 
                               class="w-full text-xs px-2.5 py-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-atoma-blue">
                        <button type="button" 
                                @click="if (newSubTitle.trim()) { $wire.quickAddSubTaskToTask({{ $target->id }}, {{ $task->id }}, newSubTitle); newSubTitle = ''; addingSub = false; }" 
                                class="px-2 py-1 bg-atoma-blue text-white rounded-lg text-xs font-bold shrink-0 hover:bg-[#3455dc] cursor-pointer">
                            {{ __('Tambah') }}
                        </button>
                        <button type="button" @click="addingSub = false" class="text-xs text-gray-400 hover:text-gray-600 px-1 cursor-pointer">✕</button>
                    </div>

                    <!-- Nested Subtasks List -->
                    @if($taskSubsTotal > 0)
                        <div x-show="showSubs" class="mt-2 pl-3 ml-2 border-l-2 border-atoma-blue/30 dark:border-atoma-blue/40 space-y-1.5 transition-all">
                            @foreach($taskSubs as $st)
                                <label class="flex items-start gap-2 cursor-pointer select-none text-xs group hover:bg-gray-50/60 dark:hover:bg-gray-750/50 p-1 rounded-md transition-colors">
                                    <input type="checkbox" 
                                           wire:click="toggleTargetSubTask({{ $target->id }}, {{ $st->id }})" 
                                           {{ $st->is_completed ? 'checked' : '' }} 
                                           class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-atoma-blue focus:ring-atoma-blue w-3.5 h-3.5 cursor-pointer shrink-0">
                                    <span class="break-words font-medium leading-relaxed {{ $st->is_completed ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-700 dark:text-gray-300' }}">
                                        {{ $st->title }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Quick Add Task to Target Row -->
        <div class="pt-1.5 flex items-center gap-1.5" x-data="{ newTaskTitle: '' }">
            <input type="text" 
                   x-model="newTaskTitle" 
                   @keydown.enter.prevent="if (newTaskTitle.trim()) { $wire.quickAddTaskToTarget({{ $target->id }}, newTaskTitle); newTaskTitle = ''; }" 
                   placeholder="{{ __('+ Tambah tugas baru ke Hari :day...', ['day' => $target->day_number]) }}" 
                   class="w-full text-xs px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-atoma-blue transition-all">
            <button type="button" 
                    @click="if (newTaskTitle.trim()) { $wire.quickAddTaskToTarget({{ $target->id }}, newTaskTitle); newTaskTitle = ''; }" 
                    class="px-3 py-1.5 bg-atoma-blue text-white rounded-xl text-xs font-bold shrink-0 hover:bg-[#3455dc] cursor-pointer shadow-xs active:scale-95 transition-all inline-flex items-center gap-1" 
                    title="{{ __('Tambah Tugas Baru') }}">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>{{ __('Tugas') }}</span>
            </button>
        </div>

    @else
        <!-- ========================================== -->
        <!-- LEGACY FLAT SUBTASKS CHECKLIST FALLBACK     -->
        <!-- ========================================== -->
        <div x-data="{
            activePeriod: 'all',
            expanded: false,
            matchesPeriod(period) {
                return this.activePeriod === 'all' || this.activePeriod === period;
            }
        }">
            <!-- Progress Header -->
            <div class="flex items-center justify-between text-[11px] font-extrabold text-gray-500 dark:text-gray-400">
                <span class="inline-flex items-center gap-1.5">
                    <x-icon.check-circle class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan inline shrink-0" />
                    <span>{{ __('Checklist Subtask:') }}</span>
                </span>
                @if($tSubsTotal > 0)
                    <span class="text-atoma-blue dark:text-atoma-cyan font-mono font-black text-xs">{{ $tSubsDone }}/{{ $tSubsTotal }} ({{ $tSubPct }}%)</span>
                @endif
            </div>

            @if($tSubsTotal > 0)
                <!-- Micro-progress Bar -->
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-gradient-to-r from-atoma-blue via-atoma-cyan to-emerald-400 h-1.5 rounded-full transition-all duration-300" style="width: {{ $tSubPct }}%"></div>
                </div>

                <!-- Smart Time-Block Filter Pills (Shown if 5 or more complex tasks) -->
                @if($hasSegments)
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pt-0.5 pb-1">
                        <button type="button" 
                                @click="activePeriod = 'all'" 
                                :class="activePeriod === 'all' ? 'bg-atoma-blue text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-750'" 
                                class="px-2.5 py-1 rounded-xl text-[10px] sm:text-[11px] font-bold transition-all cursor-pointer shrink-0">
                            {{ __('Semua') }} ({{ $tSubsTotal }})
                        </button>
                        @if($morningSubs->count() > 0)
                            <button type="button" 
                                    @click="activePeriod = 'morning'" 
                                    :class="activePeriod === 'morning' ? 'bg-amber-500 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-amber-50 dark:hover:bg-gray-750'" 
                                    class="px-2.5 py-1 rounded-xl text-[10px] sm:text-[11px] font-bold transition-all cursor-pointer shrink-0 inline-flex items-center gap-1">
                                <span>🌅 {{ __('Pagi') }}</span>
                                <span class="font-mono text-[10px] opacity-90">({{ $mDone }}/{{ $morningSubs->count() }})</span>
                                @if($mDone === $morningSubs->count())
                                    <span class="text-xs font-black">✓</span>
                                @endif
                            </button>
                        @endif
                        @if($afternoonSubs->count() > 0)
                            <button type="button" 
                                    @click="activePeriod = 'afternoon'" 
                                    :class="activePeriod === 'afternoon' ? 'bg-blue-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-blue-50 dark:hover:bg-gray-750'" 
                                    class="px-2.5 py-1 rounded-xl text-[10px] sm:text-[11px] font-bold transition-all cursor-pointer shrink-0 inline-flex items-center gap-1">
                                <span>☀️ {{ __('Siang') }}</span>
                                <span class="font-mono text-[10px] opacity-90">({{ $aDone }}/{{ $afternoonSubs->count() }})</span>
                                @if($aDone === $afternoonSubs->count())
                                    <span class="text-xs font-black">✓</span>
                                @endif
                            </button>
                        @endif
                        @if($eveningSubs->count() > 0)
                            <button type="button" 
                                    @click="activePeriod = 'evening'" 
                                    :class="activePeriod === 'evening' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-indigo-50 dark:hover:bg-gray-750'" 
                                    class="px-2.5 py-1 rounded-xl text-[10px] sm:text-[11px] font-bold transition-all cursor-pointer shrink-0 inline-flex items-center gap-1">
                                <span>🌆 {{ __('Malam') }}</span>
                                <span class="font-mono text-[10px] opacity-90">({{ $eDone }}/{{ $eveningSubs->count() }})</span>
                                @if($eDone === $eveningSubs->count())
                                    <span class="text-xs font-black">✓</span>
                                @endif
                            </button>
                        @endif
                    </div>
                @endif

                <!-- Subtasks Checklist List -->
                <div class="space-y-1 pt-0.5">
                    @foreach($classifiedSubs as $index => $item)
                        @php
                            $isMorning = $item['period'] === 'morning';
                            $isAfternoon = $item['period'] === 'afternoon';
                            $timeBadgeClass = $isMorning 
                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-200/50 dark:border-amber-800/40'
                                : ($isAfternoon 
                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/70 dark:text-blue-300 border border-blue-200/50 dark:border-blue-800/40'
                                    : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/70 dark:text-indigo-300 border border-indigo-200/50 dark:border-indigo-800/40');
                        @endphp
                        <div x-show="matchesPeriod('{{ $item['period'] }}') && (expanded || activePeriod !== 'all' || {{ $index }} < 4)"
                             class="transition-all">
                            <label class="flex items-start gap-2.5 p-1.5 rounded-xl hover:bg-white dark:hover:bg-gray-800/70 transition-colors cursor-pointer select-none text-xs group">
                                <input type="checkbox" 
                                       wire:click="toggleTargetSubTask({{ $target->id }}, {{ $item['id'] }})" 
                                       {{ $item['is_completed'] ? 'checked' : '' }} 
                                       class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-atoma-blue focus:ring-atoma-blue w-3.5 h-3.5 cursor-pointer shrink-0">
                                
                                <div class="flex-1 flex flex-wrap items-center gap-1.5 min-w-0">
                                    @if(!empty($item['time']))
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold shrink-0 {{ $timeBadgeClass }}">
                                            {{ $item['time'] }}
                                        </span>
                                    @endif
                                    <span class="break-words font-medium leading-relaxed {{ $item['is_completed'] ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-800 dark:text-gray-200' }}">
                                        {{ $item['clean_title'] }}
                                    </span>
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>

                <!-- Progressive Disclosure Accordion (Toggle to show full list when viewing 'Semua') -->
                @if($tSubsTotal > 4)
                    <div x-show="activePeriod === 'all'" class="pt-1">
                        <button type="button" 
                                @click="expanded = !expanded" 
                                class="w-full py-1.5 px-3 rounded-xl bg-gray-150/70 hover:bg-gray-200 dark:bg-gray-800/80 dark:hover:bg-gray-750 text-gray-600 dark:text-gray-300 text-[11px] font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer">
                            <span x-text="expanded ? '{{ __('Sembunyikan tugas tambahan') }}' : '{{ __('+ Tampilkan :count tugas lainnya (Total :total)', ['count' => $tSubsTotal - 4, 'total' => $tSubsTotal]) }}'"></span>
                            <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    </div>
                @endif
            @endif

            <!-- Quick Add Subtask Input -->
            <div class="pt-1 flex items-center gap-1.5" x-data="{ newSub: '' }">
                <input type="text" 
                       x-model="newSub" 
                       @keydown.enter.prevent="if (newSub.trim()) { $wire.quickAddSubTask({{ $target->id }}, newSub); newSub = ''; }" 
                       placeholder="{{ __('+ Tambah subtask...') }}" 
                       class="w-full text-xs px-2.5 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-atoma-blue transition-all">
                <button type="button" 
                        @click="if (newSub.trim()) { $wire.quickAddSubTask({{ $target->id }}, newSub); newSub = ''; }" 
                        class="px-2.5 py-1.5 bg-atoma-blue text-white rounded-xl text-xs font-bold shrink-0 hover:bg-[#3455dc] cursor-pointer shadow-xs active:scale-95 transition-all" 
                        title="{{ __('Tambah Subtask') }}">+</button>
            </div>
        </div>
    @endif

</div>
