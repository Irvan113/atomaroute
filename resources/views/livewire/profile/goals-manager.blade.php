<?php

use Livewire\Volt\Component;
use App\Models\Goal;

new class extends Component {
    public $goals = [];
    public $showCreateModal = false;
    public $editingGoalId = null;
    public $managingGoalTasksId = null;
    public $taskSearch = '';

    // Form fields
    public $title = '';
    public $description = '';
    public $target_value = 4;
    public $current_value = 0;
    public $unit = 'x/minggu';
    public $is_public = true;

    public function mount()
    {
        $this->loadGoals();
    }

    public function loadGoals()
    {
        $this->goals = auth()->user()->goals()->with(['tasks' => function($q) {
            $q->latest();
        }])->orderBy('created_at', 'asc')->get();
    }

    public function openManageTasksModal($goalId)
    {
        $this->managingGoalTasksId = $goalId;
        $this->taskSearch = '';
    }

    public function closeManageTasksModal()
    {
        $this->managingGoalTasksId = null;
        $this->taskSearch = '';
    }

    public function toggleTaskLinkToGoal($taskId)
    {
        if (!$this->managingGoalTasksId) return;

        $task = auth()->user()->tasks()->findOrFail($taskId);
        if ($task->goal_id == $this->managingGoalTasksId) {
            $task->goal_id = null;
            $task->save();
            $this->dispatch('toast', message: "Tugas '{$task->title}' dilepas dari goal.", type: 'info');
        } else {
            $task->goal_id = $this->managingGoalTasksId;
            $task->save();
            $this->dispatch('toast', message: "Tugas '{$task->title}' berhasil dihubungkan ke goal!", type: 'success');
        }
        $this->loadGoals();
    }

    public function unlinkTaskFromGoal($goalId, $taskId)
    {
        $task = auth()->user()->tasks()->where('goal_id', $goalId)->findOrFail($taskId);
        $task->goal_id = null;
        $task->save();
        $this->dispatch('toast', message: "Tugas '{$task->title}' dilepas dari goal.", type: 'info');
        $this->loadGoals();
    }

    public function syncGoalProgressWithTasks($goalId)
    {
        $goal = auth()->user()->goals()->with('tasks')->findOrFail($goalId);
        $completedCount = $goal->tasks()->where('is_completed', true)->count();
        $goal->current_value = $completedCount;
        $goal->status = ($goal->current_value >= $goal->target_value) ? 'completed' : 'active';
        $goal->save();
        $this->dispatch('toast', message: "⚡ Progres goal disinkronkan: {$completedCount}/{$goal->target_value} {$goal->unit} ({$goal->progress_percentage}%)", type: 'success');
        $this->loadGoals();
    }

    public function getUserTasksProperty()
    {
        $query = auth()->user()->tasks()->latest();
        if (!empty($this->taskSearch)) {
            $s = trim($this->taskSearch);
            $query->where(function($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }
        return $query->take(40)->get();
    }

    public function openCreateModal()
    {
        if (auth()->user()->goals()->count() >= 3) {
            $this->dispatch('toast', message: 'Maksimal hanya 3 Goals yang dapat dibuat!', type: 'warning');
            return;
        }

        $this->reset(['editingGoalId', 'title', 'description', 'current_value']);
        $this->target_value = 4;
        $this->unit = 'x/minggu';
        $this->is_public = true;
        $this->showCreateModal = true;
    }

    public function openEditModal($goalId)
    {
        $goal = auth()->user()->goals()->findOrFail($goalId);
        $this->editingGoalId = $goal->id;
        $this->title = $goal->title;
        $this->description = $goal->description ?? '';
        $this->target_value = $goal->target_value;
        $this->current_value = $goal->current_value;
        $this->unit = $goal->unit ?? 'target';
        $this->is_public = (bool) $goal->is_public;
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->reset(['editingGoalId', 'title', 'description', 'target_value', 'current_value', 'unit', 'is_public']);
    }

    public function saveGoal()
    {
        $this->validate([
            'title' => 'required|string|min:3|max:100',
            'target_value' => 'required|integer|min:1|max:10000',
            'current_value' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:30',
        ]);

        if (!$this->editingGoalId && auth()->user()->goals()->count() >= 3) {
            $this->addError('goalLimit', 'Maksimal hanya 3 Goals yang dapat dibuat!');
            $this->dispatch('toast', message: 'Maksimal hanya 3 Goals yang dapat dibuat!', type: 'warning');
            return;
        }

        $status = ($this->current_value >= $this->target_value) ? 'completed' : 'active';

        if ($this->editingGoalId) {
            $goal = auth()->user()->goals()->findOrFail($this->editingGoalId);
            $goal->update([
                'title' => $this->title,
                'description' => $this->description ?: null,
                'target_value' => (int) $this->target_value,
                'current_value' => (int) ($this->current_value ?? 0),
                'unit' => $this->unit ?: 'target',
                'is_public' => (bool) $this->is_public,
                'status' => $status,
            ]);
            $this->dispatch('toast', message: 'Goal berhasil diperbarui!', type: 'success');
        } else {
            auth()->user()->goals()->create([
                'title' => $this->title,
                'description' => $this->description ?: null,
                'target_value' => (int) $this->target_value,
                'current_value' => (int) ($this->current_value ?? 0),
                'unit' => $this->unit ?: 'target',
                'is_public' => (bool) $this->is_public,
                'status' => $status,
            ]);
            $this->dispatch('toast', message: 'Goal baru berhasil ditambahkan!', type: 'success');
        }

        $this->closeCreateModal();
        $this->loadGoals();
    }

    public function incrementProgress($goalId)
    {
        $goal = auth()->user()->goals()->findOrFail($goalId);
        $goal->increment('current_value');
        if ($goal->current_value >= $goal->target_value) {
            $goal->update(['status' => 'completed']);
            $this->dispatch('toast', message: "🔥 Goal '{$goal->title}' selesai 100%! Selamat!", type: 'success');
        } else {
            $this->dispatch('toast', message: "Progres bertambah: {$goal->current_value}/{$goal->target_value} {$goal->unit}", type: 'info');
        }
        $this->loadGoals();
    }

    public function decrementProgress($goalId)
    {
        $goal = auth()->user()->goals()->findOrFail($goalId);
        if ($goal->current_value > 0) {
            $goal->decrement('current_value');
            if ($goal->status === 'completed' && $goal->current_value < $goal->target_value) {
                $goal->update(['status' => 'active']);
            }
        }
        $this->loadGoals();
    }

    public function toggleVisibility($goalId)
    {
        $goal = auth()->user()->goals()->findOrFail($goalId);
        $goal->is_public = !$goal->is_public;
        $goal->save();
        $msg = $goal->is_public ? 'Goal sekarang tampil di profil publik.' : 'Goal disembunyikan dari profil publik.';
        $this->dispatch('toast', message: $msg, type: 'info');
        $this->loadGoals();
    }

    public function deleteGoal($goalId)
    {
        $goal = auth()->user()->goals()->findOrFail($goalId);
        $goal->delete();
        $this->dispatch('toast', message: 'Goal berhasil dihapus.', type: 'info');
        $this->loadGoals();
    }

    public function setPreset($title, $target, $unit)
    {
        $this->title = $title;
        $this->target_value = $target;
        $this->unit = $unit;
    }
}; ?>

<div class="space-y-6">
    <!-- Header Card -->
    <div class="p-5 sm:p-6 bg-white dark:bg-gray-800 rounded-3xl border border-gray-200/80 dark:border-gray-700 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-lg shadow-orange-500/25 shrink-0">
                <!-- Flame SVG Icon -->
                <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12.0002 2.5C12.0002 2.5 13.5002 6.5 15.5002 8.5C17.5002 10.5 19.5002 12.5 19.5002 15.5C19.5002 19.5 16.0002 22 12.0002 22C8.00024 22 4.50024 19.5 4.50024 15.5C4.50024 11.5 8.00024 7.5 12.0002 2.5Z" fill="url(#headerFlameGrad)" />
                    <path d="M12.0002 11C12.0002 11 13.0002 13 14.5002 14.5C15.5002 15.5 16.0002 16.5 16.0002 17.5C16.0002 19.5 14.0002 21 12.0002 21C10.0002 21 8.00024 19.5 8.00024 17.5C8.00024 15.5 10.0002 13.5 12.0002 11Z" fill="#FFFBEB" />
                    <defs>
                        <linearGradient id="headerFlameGrad" x1="12" y1="2.5" x2="12" y2="22" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#F59E0B" />
                            <stop offset="0.5" stop-color="#EA580C" />
                            <stop offset="1" stop-color="#DC2626" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg sm:text-xl font-black text-gray-900 dark:text-white tracking-tight">
                        {{ __('Target & Goals') }}
                    </h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider {{ count($goals) >= 3 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' }}">
                        {{ count($goals) }} / 3 {{ __('Aktif') }}
                    </span>
                </div>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ __('Tetapkan maksimal 3 target utama hidupmu. Terhubung otomatis dengan checklist tugas dan dilengkapi logo api menyala.') }}
                </p>
            </div>
        </div>

        <div class="shrink-0">
            @if(count($goals) < 3)
                <button wire:click="openCreateModal" 
                        class="w-full sm:w-auto px-4 py-2.5 rounded-2xl bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-bold text-xs sm:text-sm shadow-md shadow-orange-500/25 transition-all flex items-center justify-center gap-2 cursor-pointer active:scale-95">
                    <span>+</span>
                    <span>{{ __('Tambah Goal Baru') }}</span>
                </button>
            @else
                <button disabled 
                        class="w-full sm:w-auto px-4 py-2.5 rounded-2xl bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 font-bold text-xs sm:text-sm cursor-not-allowed flex items-center justify-center gap-1.5"
                        title="{{ __('Slot penuh! Selesaikan atau hapus goal yang ada untuk menambah baru.') }}">
                    <span>🔒</span>
                    <span>{{ __('Batas Maksimal 3 Goal') }}</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Goals List -->
    @if(count($goals) === 0)
        <!-- Empty State with Presets -->
        <div class="p-8 sm:p-12 text-center bg-white dark:bg-gray-800 rounded-3xl border border-dashed border-gray-300 dark:border-gray-700">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-orange-50 dark:bg-orange-950/40 text-orange-500 flex items-center justify-center border border-orange-200 dark:border-orange-900/50">
                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12.0002 2.5C12.0002 2.5 13.5002 6.5 15.5002 8.5C17.5002 10.5 19.5002 12.5 19.5002 15.5C19.5002 19.5 16.0002 22 12.0002 22C8.00024 22 4.50024 19.5 4.50024 15.5C4.50024 11.5 8.00024 7.5 12.0002 2.5Z" fill="#F59E0B" />
                </svg>
            </div>
            <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white mb-1">
                {{ __('Belum Ada Goal yang Dibuat') }}
            </h3>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">
                {{ __('Pilih inspirasi target cepat di bawah ini atau buat tujuan spesifik impianmu!') }}
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-xl mx-auto mb-6">
                <button wire:click="setPreset('Konsisten lari 4x / minggu', 4, 'x/minggu'); openCreateModal();" 
                        class="p-3 rounded-2xl bg-orange-50/70 hover:bg-orange-100/80 dark:bg-gray-700/50 dark:hover:bg-gray-700 border border-orange-200/80 dark:border-gray-600 text-left transition-all cursor-pointer group">
                    <div class="text-base mb-1">🏃‍♂️</div>
                    <div class="text-xs font-black text-gray-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400">Konsisten lari 4x / minggu</div>
                    <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Target: 4 x/minggu</div>
                </button>

                <button wire:click="setPreset('Belajar fokus setiap hari', 7, 'hari/minggu'); openCreateModal();" 
                        class="p-3 rounded-2xl bg-indigo-50/70 hover:bg-indigo-100/80 dark:bg-gray-700/50 dark:hover:bg-gray-700 border border-indigo-200/80 dark:border-gray-600 text-left transition-all cursor-pointer group">
                    <div class="text-base mb-1">📚</div>
                    <div class="text-xs font-black text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Belajar setiap hari</div>
                    <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Target: 7 hari/minggu</div>
                </button>

                <button wire:click="setPreset('Dapat nilai A di matkul ini', 100, '%'); openCreateModal();" 
                        class="p-3 rounded-2xl bg-emerald-50/70 hover:bg-emerald-100/80 dark:bg-gray-700/50 dark:hover:bg-gray-700 border border-emerald-200/80 dark:border-gray-600 text-left transition-all cursor-pointer group">
                    <div class="text-base mb-1">🎯</div>
                    <div class="text-xs font-black text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400">Dapat nilai A di matkul</div>
                    <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Target: 100% tuntas</div>
                </button>
            </div>

            <button wire:click="openCreateModal" class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-bold text-orange-600 dark:text-orange-400 hover:underline cursor-pointer">
                <span>+ {{ __('Tulis Goal Kustom Sendiri') }}</span> &rarr;
            </button>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4">
            @foreach($goals as $index => $goal)
                @php
                    $pct = $goal->progress_percentage;
                    $isDone = $goal->status === 'completed' || $pct >= 100;
                    $linkedTasksCount = $goal->tasks()->count();
                @endphp
                <div wire:key="goal-card-{{ $goal->id }}" 
                     class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 border {{ $isDone ? 'border-emerald-300 dark:border-emerald-700/60 shadow-md bg-emerald-50/10' : 'border-gray-200/80 dark:border-gray-700 shadow-sm' }} transition-all relative overflow-hidden">
                    
                    <!-- Background Glow -->
                    <div class="absolute top-0 right-0 w-40 h-40 {{ $isDone ? 'bg-emerald-500/10' : 'bg-orange-500/10' }} rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none"></div>

                    <!-- Top Row: Flame Icon, Title & Actions -->
                    <div class="flex items-start justify-between gap-3 relative z-10">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <!-- Custom Animated Flame SVG -->
                            <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 {{ $isDone ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600' : 'bg-orange-100 dark:bg-orange-950/60 text-orange-600 shadow-sm shadow-orange-500/20' }}">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12.0002 2.5C12.0002 2.5 13.5002 6.5 15.5002 8.5C17.5002 10.5 19.5002 12.5 19.5002 15.5C19.5002 19.5 16.0002 22 12.0002 22C8.00024 22 4.50024 19.5 4.50024 15.5C4.50024 11.5 8.00024 7.5 12.0002 2.5Z" fill="{{ $isDone ? '#10B981' : '#F97316' }}" />
                                    <path d="M12.0002 11C12.0002 11 13.0002 13 14.5002 14.5C15.5002 15.5 16.0002 16.5 16.0002 17.5C16.0002 19.5 14.0002 21 12.0002 21C10.0002 21 8.00024 19.5 8.00024 17.5C8.00024 15.5 10.0002 13.5 12.0002 11Z" fill="{{ $isDone ? '#ECFDF5' : '#FEF3C7' }}" />
                                </svg>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[10px] font-mono font-black uppercase px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                        Goal #{{ $index + 1 }}
                                    </span>
                                    @if($isDone)
                                        <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
                                            ✓ {{ __('Tercapai') }}
                                        </span>
                                    @endif
                                    <!-- Public / Private Status Badge -->
                                    <button wire:click="toggleVisibility({{ $goal->id }})" 
                                            class="text-[10px] font-bold px-2 py-0.5 rounded-md border transition-colors cursor-pointer {{ $goal->is_public ? 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-gray-600' }}"
                                            title="{{ $goal->is_public ? __('Klik untuk sembunyikan dari profil publik') : __('Klik untuk tampilkan di profil publik') }}">
                                        {{ $goal->is_public ? '🌐 ' . __('Publik (Bisa dishare)') : '🔒 ' . __('Pribadi (Disembunyikan)') }}
                                    </button>
                                </div>

                                <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white mt-1 tracking-tight truncate">
                                    {{ $goal->title }}
                                </h3>

                                @if($goal->description)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">
                                        {{ $goal->description }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <!-- Action Icons -->
                        <div class="flex items-center gap-1 shrink-0">
                            <button wire:click="openEditModal({{ $goal->id }})" 
                                    class="p-2 rounded-xl text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer" 
                                    title="{{ __('Edit Goal') }}">
                                <x-icon.pencil class="w-4 h-4" />
                            </button>
                            <button wire:click="deleteGoal({{ $goal->id }})" 
                                    wire:confirm="Yakin ingin menghapus goal ini?" 
                                    class="p-2 rounded-xl text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition-colors cursor-pointer" 
                                    title="{{ __('Hapus Goal') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Progress Bar & Stepper -->
                    <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/60 relative z-10">
                        <div class="flex items-center justify-between text-xs sm:text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-base font-black {{ $isDone ? 'text-emerald-600 dark:text-emerald-400' : 'text-orange-600 dark:text-orange-400' }}">
                                    {{ $goal->current_value }} / {{ $goal->target_value }}
                                </span>
                                <span class="text-xs text-gray-400 font-normal">{{ $goal->unit }}</span>
                            </div>

                            <div class="flex items-center gap-3">
                                @if($linkedTasksCount > 0)
                                    <span class="text-[11px] text-gray-400 font-medium">
                                        ⚡ {{ $linkedTasksCount }} {{ __('tugas terhubung') }}
                                    </span>
                                @endif
                                <span class="font-mono font-black {{ $isDone ? 'text-emerald-600 dark:text-emerald-400' : 'text-orange-600 dark:text-orange-400' }}">
                                    {{ $pct }}%
                                </span>
                            </div>
                        </div>

                        <!-- Modern Gradient Progress Bar -->
                        <div class="w-full bg-gray-100 dark:bg-gray-700/70 rounded-full h-3 overflow-hidden shadow-inner p-0.5">
                            <div class="h-full rounded-full transition-all duration-700 ease-out {{ $isDone ? 'bg-gradient-to-r from-emerald-500 to-teal-400 shadow-sm shadow-emerald-500/50' : 'bg-gradient-to-r from-amber-500 via-orange-500 to-red-500 shadow-sm shadow-orange-500/40' }}"
                                 style="width: {{ $pct }}%"></div>
                        </div>

                        <!-- Manual Quick Adjust Controls -->
                        <div class="flex items-center justify-between mt-3 text-xs text-gray-400">
                            <span class="text-[11px]">
                                {{ __('Tugas harian berlogo api otomatis menambah progres') }}
                            </span>
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="decrementProgress({{ $goal->id }})" 
                                        class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 flex items-center justify-center font-black transition-colors cursor-pointer select-none active:scale-95" 
                                        title="{{ __('Kurangi Progres') }}">-</button>
                                <button type="button" wire:click="incrementProgress({{ $goal->id }})" 
                                        class="w-7 h-7 rounded-lg bg-orange-100 hover:bg-orange-200 dark:bg-orange-950/60 dark:hover:bg-orange-900/60 text-orange-700 dark:text-orange-300 flex items-center justify-center font-black transition-colors cursor-pointer select-none active:scale-95" 
                                        title="{{ __('Tambah Progres') }}">+</button>
                            </div>
                        </div>

                        <!-- Dynamic Linked Tasks Section -->
                        <div class="mt-3.5 pt-3 border-t border-gray-100 dark:border-gray-700/50">
                            <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-black text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
                                        <span>🎯</span>
                                        <span>{{ __('Tugas Terhubung') }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold {{ $linkedTasksCount > 0 ? 'bg-orange-100 dark:bg-orange-950/60 text-orange-700 dark:text-orange-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500' }}">
                                            {{ $linkedTasksCount }}
                                        </span>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($linkedTasksCount > 0)
                                        <button type="button" wire:click="syncGoalProgressWithTasks({{ $goal->id }})"
                                                class="text-[11px] font-bold text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 flex items-center gap-1 px-2 py-1 rounded-lg bg-orange-50 dark:bg-orange-950/40 hover:bg-orange-100 dark:hover:bg-orange-900/40 transition-colors cursor-pointer"
                                                title="{{ __('Sinkronkan nilai progres goal dengan jumlah tugas selesai') }}">
                                            <span>🔄</span>
                                            <span>{{ __('Sinkronkan') }}</span>
                                        </button>
                                    @endif
                                    <button type="button" wire:click="openManageTasksModal({{ $goal->id }})"
                                            class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 transition-colors cursor-pointer">
                                        <span>🔗</span>
                                        <span>{{ __('Kelola Tugas') }}</span>
                                    </button>
                                </div>
                            </div>

                            @if($goal->tasks->isNotEmpty())
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    @foreach($goal->tasks->take(4) as $t)
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-[11px] font-medium border {{ $t->is_completed ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300' : 'bg-gray-50 dark:bg-gray-700/60 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300' }}">
                                            <span>{{ $t->is_completed ? '✓' : '○' }}</span>
                                            <span class="truncate max-w-[130px] sm:max-w-[160px]">{{ $t->title }}</span>
                                            <button type="button" wire:click="unlinkTaskFromGoal({{ $goal->id }}, {{ $t->id }})" class="text-gray-400 hover:text-red-500 transition-colors cursor-pointer ml-0.5 text-[10px]" title="Lepas tugas">✕</button>
                                        </div>
                                    @endforeach
                                    @if($goal->tasks->count() > 4)
                                        <button type="button" wire:click="openManageTasksModal({{ $goal->id }})" class="text-[10px] font-bold text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 cursor-pointer">
                                            +{{ $goal->tasks->count() - 4 }} {{ __('lainnya') }}
                                        </button>
                                    @endif
                                </div>
                            @else
                                <div class="text-[11px] text-gray-400 dark:text-gray-500 italic flex items-center justify-between">
                                    <span>{{ __('Belum ada tugas yang dihubungkan ke goal ini.') }}</span>
                                    <button type="button" wire:click="openManageTasksModal({{ $goal->id }})" class="font-bold text-orange-600 dark:text-orange-400 hover:underline cursor-pointer not-italic">
                                        + {{ __('Hubungkan Sekarang') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Create / Edit Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="closeCreateModal"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden border border-gray-100 dark:border-gray-800 relative z-10">
                    <form wire:submit="saveGoal" class="p-6 sm:p-8">
                        <div class="flex justify-between items-center mb-6">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-orange-500 text-white flex items-center justify-center">
                                    🔥
                                </div>
                                <h3 class="text-xl font-black text-gray-900 dark:text-white">
                                    {{ $editingGoalId ? __('Edit Goal') : __('Target / Goal Baru') }}
                                </h3>
                            </div>
                            <button type="button" wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-full p-2 transition-colors cursor-pointer">
                                <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                                    {{ __('Judul Goal / Target') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text" wire:model="title" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-orange-500 text-sm" placeholder="e.g. Konsisten lari 4x / minggu">
                                @error('title') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                                        {{ __('Target Angka') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" wire:model="target_value" min="1" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-orange-500 text-sm" placeholder="4">
                                    @error('target_value') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                                        {{ __('Satuan / Unit') }}
                                    </label>
                                    <input type="text" wire:model="unit" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-orange-500 text-sm" placeholder="x/minggu, hari, %">
                                    @error('unit') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                                    {{ __('Progres Saat Ini') }}
                                </label>
                                <input type="number" wire:model="current_value" min="0" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-orange-500 text-sm" placeholder="0">
                                @error('current_value') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                                    {{ __('Catatan / Deskripsi (Opsional)') }}
                                </label>
                                <textarea wire:model="description" rows="2" class="block w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-orange-500 text-xs" placeholder="Detail atau motivasi di balik target ini..."></textarea>
                            </div>

                            <!-- Public Sharing Switch -->
                            <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200/80 dark:border-gray-700 flex items-center justify-between">
                                <div>
                                    <div class="text-xs font-black text-gray-900 dark:text-white flex items-center gap-1.5">
                                        <span>🌐</span>
                                        <span>{{ __('Tampilkan di Profil Publik') }}</span>
                                    </div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        {{ __('Pengunjung link profilmu dapat melihat target dan progres ini.') }}
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="is_public" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500"></div>
                                </label>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-2.5">
                            <button type="button" wire:click="closeCreateModal" class="px-5 py-2.5 rounded-xl text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 font-bold text-xs transition-colors cursor-pointer">
                                {{ __('Batal') }}
                            </button>
                            <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-bold text-xs shadow-md shadow-orange-500/25 transition-all cursor-pointer">
                                {{ $editingGoalId ? __('Simpan Perubahan') : __('Buat Goal') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Manage Linked Tasks Modal -->
    @if($managingGoalTasksId)
        @php
            $activeGoal = auth()->user()->goals()->find($managingGoalTasksId);
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="closeManageTasksModal"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100 dark:border-gray-800 relative z-10 flex flex-col max-h-[85vh]">
                    <!-- Header -->
                    <div class="p-5 sm:p-6 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-orange-100 dark:bg-orange-950/60 text-orange-600 flex items-center justify-center text-lg">
                                🔗
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white">
                                    {{ __('Hubungkan Tugas ke Goal') }}
                                </h3>
                                <p class="text-xs text-orange-600 dark:text-orange-400 font-bold truncate max-w-[280px]">
                                    {{ $activeGoal ? $activeGoal->title : 'Goal' }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeManageTasksModal" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-full p-2 transition-colors cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <!-- Search & Info Bar -->
                    <div class="p-4 bg-gray-50/70 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800 space-y-2.5">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </span>
                            <input type="text" wire:model.live.debounce.250ms="taskSearch" 
                                   placeholder="Cari nama tugas..." 
                                   class="w-full pl-9 pr-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-xs sm:text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                            <span>💡</span>
                            <span>{{ __('Centang tugas untuk menghubungkan ke goal ini. Menyelesaikan tugas akan otomatis menambah progres!') }}</span>
                        </div>
                    </div>

                    <!-- Tasks Checklist Body -->
                    <div class="p-4 overflow-y-auto space-y-2 flex-1 divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->userTasks as $task)
                            @php
                                $isLinkedToThisGoal = ($task->goal_id == $managingGoalTasksId);
                                $isLinkedToOtherGoal = ($task->goal_id && $task->goal_id != $managingGoalTasksId);
                            @endphp
                            <div wire:key="manage-task-item-{{ $task->id }}" 
                                 class="pt-2 first:pt-0 flex items-center justify-between gap-3 p-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800/60 transition-colors">
                                <label class="flex items-start gap-3 flex-1 cursor-pointer min-w-0">
                                    <input type="checkbox" 
                                           wire:click="toggleTaskLinkToGoal({{ $task->id }})" 
                                           {{ $isLinkedToThisGoal ? 'checked' : '' }}
                                           class="mt-0.5 rounded text-orange-600 focus:ring-orange-500 h-4 w-4 border-gray-300 dark:border-gray-600 dark:bg-gray-800 cursor-pointer">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs sm:text-sm font-bold {{ $task->is_completed ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-white' }} truncate">
                                                {{ $task->title }}
                                            </span>
                                            @if($task->is_completed)
                                                <span class="text-[10px] font-black uppercase px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                    ✓ {{ __('Selesai') }}
                                                </span>
                                            @endif
                                            @if($isLinkedToOtherGoal && $task->goal)
                                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200/60">
                                                    {{ __('Di goal:') }} {{ \Illuminate\Support\Str::limit($task->goal->title, 15) }}
                                                </span>
                                            @endif
                                        </div>
                                        @if($task->category || $task->deadline)
                                            <div class="text-[10px] text-gray-400 mt-0.5 flex items-center gap-2">
                                                @if($task->category)
                                                    <span>🏷️ {{ $task->category }}</span>
                                                @endif
                                                @if($task->deadline)
                                                    <span>📅 {{ \Carbon\Carbon::parse($task->deadline)->format('d M') }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </label>
                            </div>
                        @empty
                            <div class="py-8 text-center text-xs text-gray-400">
                                {{ __('Tidak ada tugas yang ditemukan.') }}
                            </div>
                        @endforelse
                    </div>

                    <!-- Footer Actions -->
                    <div class="p-4 sm:p-5 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/50 flex items-center justify-between gap-3">
                        @if($activeGoal)
                            <button type="button" wire:click="syncGoalProgressWithTasks({{ $activeGoal->id }})" 
                                    class="px-4 py-2 rounded-xl bg-orange-50 hover:bg-orange-100 dark:bg-orange-950/60 dark:hover:bg-orange-900/60 text-orange-700 dark:text-orange-300 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                                <span>🔄</span>
                                <span>{{ __('Sinkronkan Progres') }}</span>
                            </button>
                        @endif
                        <button type="button" wire:click="closeManageTasksModal" 
                                class="px-5 py-2 rounded-xl bg-gray-900 hover:bg-gray-800 text-white dark:bg-white dark:hover:bg-gray-100 dark:text-gray-900 font-bold text-xs transition-all cursor-pointer ml-auto">
                            {{ __('Selesai') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
