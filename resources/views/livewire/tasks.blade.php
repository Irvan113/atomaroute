<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Models\Task;
use App\Models\SharedTaskInvitation;
use App\Models\TaskTemplate;
use App\Models\TemplateShare;
use App\Models\WorkoutSession;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use App\Models\Goal;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public $tasks = [];
    #[Session]
    public $mode = 'sistematis'; // sistematis or zen
    
    public $selectedDate;
    public $weekDates = [];
    public $chartMode = 'weekly'; // 'weekly' or 'monthly'

    // Search, Filter, Sort & View Layout
    public $search = '';
    public $filterPriority = '';
    public $filterCategory = '';
    public $filterStatus = 'all';
    public $filterTimeBlock = 'all'; // 'all', 'morning', 'afternoon', 'evening'
    public $sortBy = 'default';
    #[Session]
    public $viewLayout = 'timeline'; // 'timeline' or 'card'

    // Quick Add & Voice Command
    public $quickAddInput = '';

    // H+1 Midnight Settlement
    public $showSettlementModal = false;
    public $settlementData = [];

    // Ascension Celebration (Prestige Tier Up)
    public $showAscensionModal = false;
    public $ascensionData = [];

    // Co-op Shared Tasks
    public $showCoopModal = false;
    public $coopTaskId = null;
    public $selectedFriendId = null;

    // Overdue Triage & Reason Modal
    public $showOverdueReasonModal = false;
    public $selectedOverdueTaskId = null;
    public $selectedOverdueTaskTitle = '';
    public $chosenOverdueReason = 'Kelelahan / Burnout';
    public $customOverdueReasonNote = '';

    // Email Magic
    public $showEmailMagicModal = false;
    public $emailMagicInput = '';
    public $emailMagicTasks = [];
    public $isProcessingEmailMagic = false;
    public $emailMagicSuccessMessage = '';

    public function setChartMode($mode)
    {
        $this->chartMode = $mode;
    }

    #[Computed]
    public function taskDistribution()
    {
        $user = auth()->user();
        if ($this->chartMode === 'weekly') {
            $startDate = Carbon::parse($this->selectedDate)->startOfWeek();
            $endDate = Carbon::parse($this->selectedDate)->endOfWeek();
        } else {
            $startDate = Carbon::parse($this->selectedDate)->startOfMonth();
            $endDate = Carbon::parse($this->selectedDate)->endOfMonth();
        }

        $tasks = $user->tasks()
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy(fn($t) => $t->date ? $t->date->toDateString() : '');

        $distribution = [];
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateStr = $current->toDateString();
            $count = isset($tasks[$dateStr]) ? $tasks[$dateStr]->count() : 0;
            $completedCount = isset($tasks[$dateStr]) ? $tasks[$dateStr]->where('is_completed', true)->count() : 0;
            
            $distribution[] = [
                'date' => $dateStr,
                'day_number' => $current->day,
                'day_name' => $current->format('D'),
                'count' => $count,
                'completed_count' => $completedCount,
            ];
            $current->addDay();
        }

        return $distribution;
    }

    #[Computed]
    public function myTemplates()
    {
        return auth()->user()->taskTemplates()->latest()->get();
    }

    #[Computed]
    public function communityTemplates()
    {
        $query = TaskTemplate::publicCommunity()->with('user');

        if ($this->templateCategoryFilter && $this->templateCategoryFilter !== 'all') {
            $query->where('category', $this->templateCategoryFilter);
        }

        if ($this->templateSearchQuery) {
            $q = $this->templateSearchQuery;
            $query->where(function($b) use ($q) {
                $b->where('title', 'like', "%{$q}%")
                  ->orWhere('description', 'like', "%{$q}%");
            });
        }

        return $query->orderBy('clones_count', 'desc')->latest()->take(30)->get();
    }

    #[Computed]
    public function receivedSharedTemplates()
    {
        return auth()->user()->receivedTemplateShares()->with(['template', 'sender'])->where('status', 'pending')->latest()->get();
    }

    #[Computed]
    public function userFriends()
    {
        return auth()->user()->friends()->get();
    }

    #[Computed]
    public function userSkills()
    {
        return auth()->user()->skills()->orderBy('created_at', 'desc')->get();
    }

    #[Computed]
    public function goals()
    {
        return auth()->user()->goals()->where('status', 'active')->get();
    }
    
    // Form fields
    public $title = '';
    public $description = '';
    public $priority = 'normal';
    public $category = '';
    public $skill_id = null;
    public $goal_id = null;
    public $start_time = '';
    public $deadline = '';
    public $estimated_minutes = 30;
    public $is_recurring = false;
    public $recurrence_pattern = 'daily';
    public $recurrence_days = [];
    public $subTasksList = []; // Used for both create and edit
    public $showAddTaskModal = false;
    #[Session]
    public $task_mode = 'zen';
    public $editingTaskId = null;

    // Gamification state
    public $showLevelUp = false;
    public $newLevel = 1;

    // Task to Journal
    public $showJournalModal = false;
    public $journalTaskId = null;
    public $journalContent = '';
    public $journalMood = 'happy';

    // Task to Finance (Kategori Finance)
    public $showFinancePromptModal = false;
    public $financeTaskId = null;
    public $financeTaskTitle = '';
    public $financeTaskAmount = '';
    public $financeTaskType = 'expense';
    public $financeTaskCategory = 'Tagihan';
    public $financeTaskDescription = '';
    public $financeTaskDate = '';

    // AI Task Templates, Decomposer, Community & Sharing
    public $showAiTemplateModal = false;
    public $aiDecomposePrompt = '';
    public $isGeneratingAiTask = false;
    public $templateActiveTab = 'presets'; // 'presets', 'ai_decomposer', 'global_community', 'my_templates'
    public $templateCategoryFilter = 'all'; // 'all', 'Work', 'Study', 'Health', 'Finance', 'Personal'
    public $templateSearchQuery = '';

    // Interactive Preview State
    public $showTemplatePreview = false;
    public $previewTaskData = [
        'id' => null,
        'title' => '',
        'description' => '',
        'category' => 'Work',
        'priority' => 'normal',
        'estimated_minutes' => 45,
        'mode' => 'sistematis',
        'subtasks' => [],
        'date' => '',
    ];
    public $newSubtaskInput = '';

    // Share with Friend State
    public $showShareModal = false;
    public $selectedTemplateToShareId = null;
    public $selectedTemplateToShareTitle = '';
    public $shareFriendId = null;
    public $shareFriendNote = '';

    // Workout Tracker State (Fitur Khusus Olahraga)
    public $showWorkoutModal = false;
    public $activeWorkoutTaskId = null;
    public $activeWorkoutTaskTitle = '';
    public $activeWorkoutTitle = 'Sesi Latihan';
    public $activeWorkoutNotes = '';
    public $activeWorkoutDurationSeconds = 0;
    public $workoutExercises = [];
    public $showAddExerciseModal = false;
    public $newExerciseName = '';
    public $newExerciseMuscle = 'Dada';
    public $workoutSummaryData = null;
    public $showWorkoutSummaryModal = false;
    public $workoutType = 'gym'; // 'gym', 'calisthenics', 'cardio', 'home', 'custom'
    public $workoutSplit = 'Push Day';
    public $customWorkoutExerciseInput = '';
    public $swapTargetExerciseIndex = null;

    public function mount()
    {
        $this->selectedDate = Carbon::today()->toDateString();

        $this->generateWeekDates();
        $this->loadTasks();

        // Process H+1 Midnight Settlement
        $settlement = auth()->user()->processDailySettlement();
        if (isset($settlement['settled']) && $settlement['settled']) {
            $this->settlementData = $settlement;
            $this->showSettlementModal = true;
        }
    }

    #[On('pomodoro-completed')]
    public function handlePomodoroCompleted($taskId = null, $minutes = 25)
    {
        if ($taskId) {
            $task = auth()->user()->tasks()->find($taskId);
            if ($task) {
                $task->increment('actual_minutes', $minutes);
                $task->increment('pomodoro_sessions');
                $this->loadTasks();
                $this->dispatch('toast', message: 'Pomodoro dicatat untuk task: ' . $task->title, type: 'success');
            }
        }
    }
    
    public function generateWeekDates()
    {
        $selected = Carbon::parse($this->selectedDate);
        $this->weekDates = [];
        for ($i = -3; $i <= 3; $i++) {
            $this->weekDates[] = $selected->copy()->addDays($i)->toDateString();
        }
    }
    
    public function selectDate($date)
    {
        $this->selectedDate = $date;
        $this->generateWeekDates();
        $this->loadTasks();
    }

    public function previousDay()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->toDateString();
        $this->generateWeekDates();
        $this->loadTasks();
    }

    public function nextDay()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->toDateString();
        $this->generateWeekDates();
        $this->loadTasks();
    }

    public function loadTasks()
    {
        $query = auth()->user()->tasks()
            ->with(['subTasks', 'sharedWithUser', 'skill', 'goal'])
            ->whereDate('date', $this->selectedDate)
            ->where('mode', $this->mode);

        if (!empty($this->search)) {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%")
                  ->orWhere('category', 'like', "%{$term}%")
                  ->orWhereHas('subTasks', function ($sq) use ($term) {
                      $sq->where('title', 'like', "%{$term}%");
                  });
            });
        }

        if (!empty($this->filterPriority)) {
            $query->where('priority', $this->filterPriority);
        }

        if (!empty($this->filterCategory)) {
            $query->where('category', $this->filterCategory);
        }

        if ($this->filterStatus === 'pending') {
            $query->where('is_completed', false);
        } elseif ($this->filterStatus === 'completed') {
            $query->where('is_completed', true);
        }

        switch ($this->sortBy) {
            case 'time_asc':
                $query->orderByRaw('CASE WHEN start_time IS NOT NULL AND start_time != "" THEN 0 WHEN deadline IS NOT NULL AND deadline != "" THEN 1 ELSE 2 END ASC')
                      ->orderByRaw('COALESCE(NULLIF(start_time, ""), deadline) ASC')
                      ->orderBy('is_completed');
                break;
            case 'time_desc':
                $query->orderByRaw('CASE WHEN start_time IS NOT NULL AND start_time != "" THEN 0 WHEN deadline IS NOT NULL AND deadline != "" THEN 1 ELSE 2 END ASC')
                      ->orderByRaw('COALESCE(NULLIF(start_time, ""), deadline) DESC')
                      ->orderBy('is_completed');
                break;
            case 'priority_desc':
                $query->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END ASC")
                      ->orderBy('is_completed')
                      ->orderBy('created_at', 'desc');
                break;
            case 'priority_asc':
                $query->orderByRaw("CASE priority WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END ASC")
                      ->orderBy('is_completed')
                      ->orderBy('created_at', 'desc');
                break;
            case 'category_asc':
                $query->orderByRaw('category IS NULL, category ASC')
                      ->orderBy('is_completed');
                break;
            case 'xp_desc':
                $query->withCount('subTasks')
                      ->orderByDesc('sub_tasks_count')
                      ->orderBy('is_completed');
                break;
            case 'title_asc':
                $query->orderBy('title', 'asc');
                break;
            default:
                $query->orderBy('is_completed')
                      ->orderByRaw('CASE WHEN start_time IS NOT NULL AND start_time != "" THEN 0 WHEN deadline IS NOT NULL AND deadline != "" THEN 1 ELSE 2 END ASC')
                      ->orderByRaw('COALESCE(NULLIF(start_time, ""), deadline) ASC')
                      ->orderBy('created_at', 'desc');
                break;
        }

        $rawTasks = $query->get();

        if ($this->filterTimeBlock !== 'all') {
            $this->tasks = $rawTasks->filter(function ($t) {
                return $this->getTaskTimeBlock($t) === $this->filterTimeBlock;
            })->values();
        } else {
            $this->tasks = $rawTasks;
        }
    }

    public function updatedSearch()
    {
        $this->loadTasks();
    }

    public function updatedFilterPriority()
    {
        $this->loadTasks();
    }

    public function updatedFilterCategory()
    {
        $this->loadTasks();
    }

    public function updatedFilterStatus()
    {
        $this->loadTasks();
    }

    public function updatedFilterTimeBlock()
    {
        $this->loadTasks();
    }

    public function updatedSortBy()
    {
        $this->loadTasks();
    }

    public function resetFilters()
    {
        $this->search = '';
        $this->filterPriority = '';
        $this->filterCategory = '';
        $this->filterStatus = 'all';
        $this->filterTimeBlock = 'all';
        $this->sortBy = 'default';
        $this->loadTasks();
    }

    #[Computed]
    public function dailyTimeBlockStats()
    {
        $dayTasks = auth()->user()->tasks()
            ->whereDate('date', $this->selectedDate)
            ->where('mode', $this->mode)
            ->get();

        $stats = [
            'total' => $dayTasks->count(),
            'morning' => ['total' => 0, 'done' => 0],
            'afternoon' => ['total' => 0, 'done' => 0],
            'evening' => ['total' => 0, 'done' => 0],
        ];

        foreach ($dayTasks as $t) {
            $block = $this->getTaskTimeBlock($t);
            if (isset($stats[$block])) {
                $stats[$block]['total']++;
                if ($t->is_completed) {
                    $stats[$block]['done']++;
                }
            }
        }

        return $stats;
    }

    public function getTaskTimeBlock($task): string
    {
        // 1. Check start_time or deadline
        $timeStr = $task->start_time ?: $task->deadline;
        if ($timeStr) {
            $hour = (int) substr($timeStr, 0, 2);
            if ($hour >= 3 && $hour < 12) {
                return 'morning';
            } elseif ($hour >= 12 && $hour < 17) {
                return 'afternoon';
            } else {
                return 'evening';
            }
        }

        // 2. Check title for time format [04.00 – 04.30] or 04.00
        $title = $task->title ?? '';
        if (preg_match('/(?:\[|\b)([0-9]{1,2})[.:][0-9]{2}/u', $title, $m)) {
            $hour = (int) $m[1];
            if ($hour >= 3 && $hour < 12) {
                return 'morning';
            } elseif ($hour >= 12 && $hour < 17) {
                return 'afternoon';
            } else {
                return 'evening';
            }
        }

        // 3. Heuristic keywords
        $lower = strtolower($title);
        if (str_contains($lower, 'siang') || str_contains($lower, 'dzuhur') || str_contains($lower, 'ashar') 
            || str_contains($lower, 'kuliah') || str_contains($lower, 'jeda kos') || str_contains($lower, 'power nap') || str_contains($lower, 'lunch')) {
            return 'afternoon';
        }

        if (str_contains($lower, 'sore') || str_contains($lower, 'malam') || str_contains($lower, 'maghrib') 
            || str_contains($lower, 'isya') || str_contains($lower, 'konten') || str_contains($lower, 'gaming') 
            || str_contains($lower, 'genshin') || str_contains($lower, 'ea fc') || str_contains($lower, 'dinner') 
            || str_contains($lower, 'makan malam') || str_contains($lower, 'journaling') || str_contains($lower, 'tidur') 
            || str_contains($lower, 'wind-down') || str_contains($lower, 'workout otot')) {
            return 'evening';
        }

        return 'morning';
    }

    public function setViewLayout($layout)
    {
        $this->viewLayout = $layout;
    }

    #[Computed]
    public function availableCategories()
    {
        return auth()->user()->tasks()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();
    }

    #[Computed]
    public function overdueTasks()
    {
        $today = Carbon::today()->toDateString();
        return auth()->user()->tasks()
            ->with(['subTasks', 'goal'])
            ->whereNotNull('date')
            ->where('is_overdue_dismissed', false)
            ->where(function ($query) use ($today) {
                $query->where(function ($q) use ($today) {
                    $q->where('is_completed', false)
                      ->whereDate('date', '<', $today);
                })->orWhere(function ($q) use ($today) {
                    $q->where('is_completed', true)
                      ->where('is_completed_overdue', true)
                      ->whereDate('updated_at', $today);
                });
            })
            ->orderBy('is_completed', 'asc')
            ->orderBy('date', 'desc')
            ->get();
    }

    public function dismissOverdueTask($taskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $task->update([
            'is_overdue_dismissed' => true,
        ]);
        $this->loadTasks();
        $this->dispatch('toast', message: 'Tugas diabaikan dari notifikasi hari ini. Tugas tetap tersimpan di tanggal aslinya.', type: 'info');
    }

    public function dismissAllOverdue()
    {
        $today = Carbon::today()->toDateString();
        $count = auth()->user()->tasks()
            ->where('is_completed', false)
            ->whereNotNull('date')
            ->where('is_overdue_dismissed', false)
            ->whereDate('date', '<', $today)
            ->update(['is_overdue_dismissed' => true]);

        $this->loadTasks();
        $this->dispatch('toast', message: "✨ {$count} notifikasi tugas tertunda diabaikan. Tugas tetap tersimpan di tanggal aslinya.", type: 'info');
    }

    public function openOverdueReasonModal($taskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $this->selectedOverdueTaskId = $task->id;
        $this->selectedOverdueTaskTitle = $task->title;
        $this->chosenOverdueReason = $task->skip_reason ?: 'Kelelahan / Burnout';
        $this->customOverdueReasonNote = $task->skip_reason_note ?: '';
        $this->showOverdueReasonModal = true;
    }

    public function closeOverdueReasonModal()
    {
        $this->showOverdueReasonModal = false;
        $this->selectedOverdueTaskId = null;
        $this->selectedOverdueTaskTitle = '';
        $this->chosenOverdueReason = 'Kelelahan / Burnout';
        $this->customOverdueReasonNote = '';
    }

    public function saveOverdueReason()
    {
        if (!$this->selectedOverdueTaskId) return;

        $task = auth()->user()->tasks()->findOrFail($this->selectedOverdueTaskId);
        $reason = $this->chosenOverdueReason ?: 'Lainnya';
        $task->update([
            'skip_reason' => $reason,
            'skip_reason_note' => $this->customOverdueReasonNote,
        ]);

        $this->closeOverdueReasonModal();
        $this->loadTasks();
        $this->dispatch('toast', message: "Alasan '{$reason}' berhasil dicatat untuk evaluasi Insights!", type: 'success');
    }

    public function rescheduleOverdueTask($taskId)
    {
        return $this->dismissOverdueTask($taskId);
    }

    public function rescheduleAllOverdue()
    {
        return $this->dismissAllOverdue();
    }

    public function moveOverdueToBacklog($taskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $task->update([
            'date' => null,
            'start_time' => null,
            'deadline' => null,
        ]);
        $this->loadTasks();
        $this->dispatch('toast', message: '📦 Tugas dipindahkan ke Backlog (tanpa tanggal).', type: 'info');
    }

    #[Computed]
    public function dueFlashcards()
    {
        if (!Carbon::parse($this->selectedDate)->isToday()) {
            return collect();
        }

        return \App\Models\Flashcard::where('user_id', auth()->id())
            ->dueForReview()
            ->orderBy('next_review_at', 'asc')
            ->get();
    }

    #[Computed]
    public function dueFlashcardsCount()
    {
        return $this->dueFlashcards->count();
    }

    #[Computed]
    public function dueFlashcardDecks()
    {
        return $this->dueFlashcards->groupBy('deck')->map->count();
    }

    public function recordQuickFlashcardReview($cardId, $rating)
    {
        $card = \App\Models\Flashcard::where('user_id', auth()->id())->find($cardId);
        if ($card) {
            $card->recordReview($rating);

            // Award Flashcard XP (respecting daily cap)
            $awardedXp = \App\Services\GamificationService::awardFlashcardXp(auth()->user(), 1);

            $this->dispatch('toast', message: "🧠 Review SRS dicatat! (+{$awardedXp} XP)", type: 'success');
            $this->dispatch('play-sound', preset: 'pop');
        }
    }
    
    public function toggleMode()
    {
        $this->mode = $this->mode === 'zen' ? 'sistematis' : 'zen';
        $this->loadTasks();
    }

    public function switchTaskMode($taskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $newMode = $task->mode === 'zen' ? 'sistematis' : 'zen';
        $task->update(['mode' => $newMode]);
        $this->loadTasks();
        $this->dispatch('toast', message: 'Task dipindah ke mode ' . ($newMode === 'zen' ? '🧘 Zen' : '🎯 Systematic'), type: 'success');
    }

    public function openEmailMagicModal()
    {
        $this->showEmailMagicModal = true;
        $this->emailMagicInput = '';
        $this->emailMagicTasks = [];
        $this->emailMagicSuccessMessage = '';
    }

    public function closeEmailMagicModal()
    {
        $this->showEmailMagicModal = false;
        $this->emailMagicInput = '';
        $this->emailMagicTasks = [];
        $this->isProcessingEmailMagic = false;
    }

    public function processEmailMagic()
    {
        $input = trim($this->emailMagicInput);
        if (empty($input)) {
            $this->dispatch('toast', message: 'Silakan tempel isi email terlebih dahulu.', type: 'error');
            return;
        }

        $this->isProcessingEmailMagic = true;
        try {
            $tasks = \App\Services\EmailMagicService::parseEmailToTasks(
                $input,
                auth()->id(),
                $this->selectedDate
            );

            if (empty($tasks)) {
                $this->dispatch('toast', message: 'Tidak ada to-do terdeteksi dari email ini.', type: 'info');
            } else {
                $this->emailMagicTasks = $tasks;
                $this->dispatch('toast', message: 'Berhasil mendeteksi ' . count($tasks) . ' to-do dari email!', type: 'success');
                $this->dispatch('play-sound', preset: 'pop');
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Gagal memproses email: ' . $e->getMessage(), type: 'error');
        } finally {
            $this->isProcessingEmailMagic = false;
        }
    }

    public function removeEmailMagicTask($index)
    {
        if (isset($this->emailMagicTasks[$index])) {
            unset($this->emailMagicTasks[$index]);
            $this->emailMagicTasks = array_values($this->emailMagicTasks);
        }
    }

    public function confirmInsertEmailTasks()
    {
        if (empty($this->emailMagicTasks)) {
            $this->dispatch('toast', message: 'Tidak ada to-do untuk ditambahkan.', type: 'error');
            return;
        }

        $count = \App\Services\EmailMagicService::insertTasks(
            $this->emailMagicTasks,
            auth()->id(),
            $this->selectedDate
        );

        $this->loadTasks();
        $this->closeEmailMagicModal();
        $this->dispatch('toast', message: "✨ {$count} to-do dari Email Magic berhasil ditambahkan!", type: 'success');
        $this->dispatch('play-sound', preset: 'level_up');
    }

    #[On('open-add-task-modal')]
    public function openAddTaskModal()
    {
        $this->reset(['title', 'description', 'priority', 'category', 'start_time', 'deadline', 'estimated_minutes', 'is_recurring', 'recurrence_pattern', 'recurrence_days', 'subTasksList', 'editingTaskId']);
        $this->task_mode = $this->mode;
        $this->showAddTaskModal = true;
    }

    public function parseAndSaveQuickAdd()
    {
        $raw = trim($this->quickAddInput);
        if (empty($raw)) return;

        $text = $raw;
        $date = $this->selectedDate;
        $priority = 'normal';
        $category = null;
        $startTime = null;
        $deadline = null;
        $estimatedMinutes = 30;

        // ========================================
        // HELPER: Fuzzy match — tolerate typos
        // ========================================
        $fuzzyMatch = function(string $word, array $targets, int $maxDistance = 2): ?string {
            $word = strtolower(trim($word));
            if (strlen($word) < 3) return null; // too short to fuzzy match reliably
            foreach ($targets as $target) {
                if ($word === $target) return $target;
                if (levenshtein($word, $target) <= $maxDistance) return $target;
            }
            return null;
        };

        // ========================================
        // STEP 1: Extract TIME — "jam 11 malam", "jam 3:30 sore", "jam 8 pagi", "10.00", "pukul 14"
        // ========================================
        $timeOfDayKeywords = [
            'pagi' => [6, 11],      // 06:00 - 11:59
            'siang' => [12, 14],    // 12:00 - 14:59
            'sore' => [15, 17],     // 15:00 - 17:59
            'malam' => [18, 23],    // 18:00 - 23:59
            'subuh' => [3, 5],      // 03:00 - 05:59
            'dini hari' => [0, 2],  // 00:00 - 02:59
        ];

        // Pattern: "jam/pukul HH(:MM) (pagi/siang/sore/malam)"
        $timePattern = '/(?:jam|pukul|pk|at)\s*(\d{1,2})(?:[:\.](\d{2}))?\s*(pagi|siang|sore|malam|subuh)?/iu';
        if (preg_match($timePattern, $text, $tm)) {
            $hour = (int)$tm[1];
            $min = isset($tm[2]) && $tm[2] !== '' ? (int)$tm[2] : 0;
            $period = isset($tm[3]) ? strtolower($tm[3]) : null;

            // Smart AM/PM inference
            if ($period) {
                if ($period === 'malam' && $hour < 12 && $hour !== 12) {
                    $hour += 12; // "jam 8 malam" → 20:00
                    if ($hour >= 24) $hour -= 24;
                } elseif ($period === 'sore' && $hour < 12) {
                    $hour += 12; // "jam 3 sore" → 15:00
                } elseif ($period === 'siang' && $hour < 12 && $hour !== 12) {
                    $hour += 12; // "jam 1 siang" → 13:00
                } elseif ($period === 'pagi' && $hour == 12) {
                    $hour = 0; // "jam 12 pagi" → 00:00
                }
                // "subuh" / "pagi" with hour <= 12 stays as-is
            } else {
                // No period specified — heuristic: if hour <= 6, likely PM context unless explicitly 0-padded
                // Default: keep as-is (assume 24h or reasonable context)
            }

            $hour = max(0, min(23, $hour));
            $min = max(0, min(59, $min));
            $startTime = sprintf('%02d:%02d', $hour, $min);
            $text = preg_replace($timePattern, '', $text);
        }

        // Fallback: bare time pattern "10:00", "14.30", "10:30" without jam/pukul
        if (!$startTime && preg_match('/\b(\d{1,2})[:\.](\d{2})\b/', $text, $bareTm)) {
            $hour = (int)$bareTm[1];
            $min = (int)$bareTm[2];
            if ($hour >= 0 && $hour <= 23 && $min >= 0 && $min <= 59) {
                $startTime = sprintf('%02d:%02d', $hour, $min);
                $text = str_replace($bareTm[0], '', $text);
            }
        }

        // Standalone time-of-day without "jam" — "pagi ini", "nanti sore", "malam ini"
        if (!$startTime) {
            foreach (['subuh' => '04:30', 'pagi' => '08:00', 'siang' => '12:00', 'sore' => '16:00', 'malam' => '20:00'] as $tod => $defaultTime) {
                if (preg_match('/\b' . $tod . '(\s+ini)?\b/iu', $text)) {
                    $startTime = $defaultTime;
                    $text = preg_replace('/\b' . $tod . '(\s+ini)?\b/iu', '', $text);
                    break;
                }
            }
        }

        // ========================================
        // STEP 2: Extract DATE — with typo tolerance
        // ========================================
        $dateKeywords = [
            // Exact & fuzzy targets → handler
            'hari ini' => fn() => Carbon::today(),
            'today' => fn() => Carbon::today(),
            'besok' => fn() => Carbon::tomorrow(),
            'tomorrow' => fn() => Carbon::tomorrow(),
            'lusa' => fn() => Carbon::today()->addDays(2),
            'minggu depan' => fn() => Carbon::today()->addWeek(),
            'next week' => fn() => Carbon::today()->addWeek(),
        ];

        $lower = strtolower($text);

        // Multi-word date phrases first
        foreach ($dateKeywords as $keyword => $handler) {
            if (str_contains(strtolower($keyword), ' ')) {
                // Multi-word: check direct contains
                if (str_contains($lower, $keyword)) {
                    $date = $handler()->toDateString();
                    $text = preg_replace('/' . preg_quote($keyword, '/') . '/iu', '', $text);
                    break;
                }
            }
        }

        // Single-word date keywords with fuzzy matching
        if ($date === $this->selectedDate) {
            $words = preg_split('/\s+/', $text);
            $singleDateTargets = ['besok', 'tomorrow', 'lusa', 'today'];
            $typoVariants = [
                'besok' => ['bsok', 'besokk', 'bsk', 'besook', 'beso', 'besk', 'bsk', 'besik'],
                'lusa' => ['lsa', 'lusaa', 'lussa', 'lusa'],
                'tomorrow' => ['tommorow', 'tomorow', 'tmrw', 'tmrow'],
            ];

            foreach ($words as $wi => $word) {
                $cleanWord = strtolower(preg_replace('/[^a-z]/i', '', $word));
                if (strlen($cleanWord) < 2) continue;

                // Check exact typo variants first
                foreach ($typoVariants as $canonical => $variants) {
                    if (in_array($cleanWord, $variants) || $cleanWord === $canonical) {
                        $date = $dateKeywords[$canonical]()->toDateString();
                        $words[$wi] = '';
                        $text = implode(' ', array_filter($words));
                        break 2;
                    }
                }

                // Then fuzzy levenshtein
                $matched = $fuzzyMatch($cleanWord, $singleDateTargets, 2);
                if ($matched && isset($dateKeywords[$matched])) {
                    $date = $dateKeywords[$matched]()->toDateString();
                    $words[$wi] = '';
                    $text = implode(' ', array_filter($words));
                    break;
                }
            }
        }

        // Indonesian day names — "senin", "selasa", etc. → next occurrence
        if ($date === $this->selectedDate) {
            $dayMap = [
                'senin' => Carbon::MONDAY, 'monday' => Carbon::MONDAY,
                'selasa' => Carbon::TUESDAY, 'tuesday' => Carbon::TUESDAY,
                'rabu' => Carbon::WEDNESDAY, 'wednesday' => Carbon::WEDNESDAY,
                'kamis' => Carbon::THURSDAY, 'thursday' => Carbon::THURSDAY,
                'jumat' => Carbon::FRIDAY, 'friday' => Carbon::FRIDAY,
                'sabtu' => Carbon::SATURDAY, 'saturday' => Carbon::SATURDAY,
                'minggu' => Carbon::SUNDAY, 'sunday' => Carbon::SUNDAY,
            ];
            $dayNames = array_keys($dayMap);

            $words = preg_split('/\s+/', $text);
            foreach ($words as $wi => $word) {
                $cleanWord = strtolower(preg_replace('/[^a-z]/i', '', $word));
                $matched = $fuzzyMatch($cleanWord, $dayNames, 2);
                if ($matched && isset($dayMap[$matched])) {
                    $targetDay = $dayMap[$matched];
                    $nextDate = Carbon::today()->next($targetDay);
                    // If the day is today, use today
                    if (Carbon::today()->dayOfWeekIso === $targetDay || Carbon::today()->dayOfWeek === $targetDay) {
                        $nextDate = Carbon::today();
                    }
                    $date = $nextDate->toDateString();
                    $words[$wi] = '';
                    // Also remove "hari" before it if present
                    if ($wi > 0 && strtolower($words[$wi - 1]) === 'hari') {
                        $words[$wi - 1] = '';
                    }
                    $text = implode(' ', array_filter($words));
                    break;
                }
            }
        }

        // ========================================
        // STEP 3: Extract PRIORITY — with typo tolerance & natural keywords
        // ========================================
        // Explicit markers: !high, !low, !tinggi, !rendah
        if (preg_match('/!(high|tinggi|penting|urgent)/iu', $text)) {
            $priority = 'high';
            $text = preg_replace('/!(high|tinggi|penting|urgent)/iu', '', $text);
        } elseif (preg_match('/!(low|rendah|santai|biasa)/iu', $text)) {
            $priority = 'low';
            $text = preg_replace('/!(low|rendah|santai|biasa)/iu', '', $text);
        }

        // Natural priority words without ! prefix
        if ($priority === 'normal') {
            $highPriorityWords = ['penting', 'urgent', 'darurat', 'mendesak', 'segera', 'important', 'critical'];
            $lowPriorityWords = ['santai', 'kalem', 'nanti aja', 'ntar', 'low', 'optional'];

            $words = preg_split('/\s+/', strtolower($text));
            foreach ($words as $word) {
                $cleanWord = preg_replace('/[^a-z]/i', '', $word);
                $matchHigh = $fuzzyMatch($cleanWord, $highPriorityWords, 2);
                if ($matchHigh) {
                    $priority = 'high';
                    $text = preg_replace('/\b' . preg_quote($word, '/') . '\b/iu', '', $text);
                    break;
                }
                $matchLow = $fuzzyMatch($cleanWord, $lowPriorityWords, 2);
                if ($matchLow) {
                    $priority = 'low';
                    $text = preg_replace('/\b' . preg_quote($word, '/') . '\b/iu', '', $text);
                    break;
                }
            }
        }

        // ========================================
        // STEP 4: Extract CATEGORY — #hashtag or natural keywords
        // ========================================
        if (preg_match('/#([\w]+)/u', $text, $catMatch)) {
            $category = ucfirst(strtolower($catMatch[1]));
            $text = str_replace($catMatch[0], '', $text);
        }

        // Natural category detection from common keywords
        if (!$category) {
            $categoryMap = [
                'Work' => ['kerja', 'kerjaan', 'kantor', 'office', 'work', 'meeting', 'rapat'],
                'Study' => ['belajar', 'kuliah', 'tugas', 'study', 'ujian', 'homework', 'pr'],
                'Personal' => ['pribadi', 'personal', 'diri'],
                'Health' => ['olahraga', 'gym', 'lari', 'workout', 'exercise', 'jogging', 'sehat', 'diet'],
                'Shopping' => ['belanja', 'beli', 'shopping', 'groceries'],
                'Finance' => ['bayar', 'transfer', 'tagihan', 'bill', 'payment', 'cicilan'],
                'Social' => ['teman', 'hangout', 'nongkrong', 'ketemuan', 'gathering'],
                'Errands' => ['jemput', 'antar', 'ambil', 'kirim'],
            ];

            $lowerText = strtolower($text);
            foreach ($categoryMap as $cat => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($lowerText, $kw)) {
                        $category = $cat;
                        break 2;
                    }
                }
            }
        }

        if (!$category) $category = 'General';

        // ========================================
        // STEP 5: Extract DURATION — "30 menit", "1 jam", "2 jam"
        // ========================================
        if (preg_match('/(\d+)\s*(menit|mnt|min|minutes?)/iu', $text, $durMatch)) {
            $estimatedMinutes = max(5, min(480, (int)$durMatch[1]));
            $text = str_replace($durMatch[0], '', $text);
        } elseif (preg_match('/(\d+(?:\.\d+)?)\s*(jam|hour|hr|hours?)/iu', $text, $durMatch)) {
            $estimatedMinutes = max(5, min(480, (int)(floatval($durMatch[1]) * 60)));
            $text = str_replace($durMatch[0], '', $text);
        }

        // ========================================
        // STEP 6: Extract DEADLINE — "deadline jam 5 sore", "sebelum jam 10"
        // ========================================
        $deadlinePattern = '/(?:deadline|sebelum|before|sampai|hingga|max)\s*(?:jam|pukul|pk)?\s*(\d{1,2})(?:[:\.](\d{2}))?\s*(pagi|siang|sore|malam)?/iu';
        if (preg_match($deadlinePattern, $text, $dlMatch)) {
            $dlHour = (int)$dlMatch[1];
            $dlMin = isset($dlMatch[2]) && $dlMatch[2] !== '' ? (int)$dlMatch[2] : 0;
            $dlPeriod = isset($dlMatch[3]) ? strtolower($dlMatch[3]) : null;

            if ($dlPeriod === 'malam' && $dlHour < 12) $dlHour += 12;
            elseif ($dlPeriod === 'sore' && $dlHour < 12) $dlHour += 12;
            elseif ($dlPeriod === 'siang' && $dlHour < 12 && $dlHour !== 12) $dlHour += 12;

            $dlHour = max(0, min(23, $dlHour));
            $dlMin = max(0, min(59, $dlMin));
            $deadline = sprintf('%02d:%02d', $dlHour, $dlMin);
            $text = preg_replace($deadlinePattern, '', $text);
        }

        // ========================================
        // STEP 7: Final cleanup — remove noise words, extra spaces
        // ========================================
        $noiseWords = ['/\bdi\b/i', '/\bke\b/i', '/\buntuk\b/i', '/\byang\b/i', '/\bdan\b/i', '/\bini\b/i', '/\bnanti\b/i'];
        // Only remove noise words if they are at start/end to avoid stripping meaning
        $cleanTitle = trim(preg_replace('/\s+/', ' ', $text));
        $cleanTitle = preg_replace('/^(di|ke|untuk|yang|dan|nanti)\s+/i', '', $cleanTitle);
        $cleanTitle = preg_replace('/\s+(di|ke|untuk|yang|dan)$/i', '', $cleanTitle);
        $cleanTitle = trim($cleanTitle);

        if (empty($cleanTitle)) $cleanTitle = 'Task Baru';

        // Content Quality Filter
        $quality = \App\Services\ContentQualityService::validateText($cleanTitle, 1, 3);
        if (!$quality['valid']) {
            $this->dispatch('toast', message: $quality['message'], type: 'error');
            return;
        }

        // Build descriptive toast message
        $parsedInfo = [];
        if ($date !== $this->selectedDate) {
            $parsedInfo[] = '📅 ' . Carbon::parse($date)->translatedFormat('l, d M');
        }
        if ($startTime) $parsedInfo[] = '⏰ ' . $startTime;
        if ($deadline) $parsedInfo[] = '🏁 Deadline ' . $deadline;
        if ($priority !== 'normal') $parsedInfo[] = ($priority === 'high' ? '🔴' : '🟢') . ' ' . ucfirst($priority);
        if ($category !== 'General') $parsedInfo[] = '🏷️ ' . $category;

        auth()->user()->tasks()->create([
            'title' => $cleanTitle,
            'date' => $date,
            'priority' => $priority,
            'category' => $category,
            'start_time' => $startTime,
            'deadline' => $deadline,
            'estimated_minutes' => $estimatedMinutes,
            'mode' => $this->mode,
        ]);

        $this->quickAddInput = '';
        $this->loadTasks();

        $toastMsg = '✅ Task "' . $cleanTitle . '" berhasil ditambahkan!';
        if (!empty($parsedInfo)) {
            $toastMsg .= ' → ' . implode(' • ', $parsedInfo);
        }
        $this->dispatch('toast', message: $toastMsg, type: 'success');
    }

    public function openCoopModal($taskId)
    {
        $this->coopTaskId = $taskId;
        $this->selectedFriendId = null;
        $this->showCoopModal = true;
    }

    public function sendCoopInvitation()
    {
        if (!$this->selectedFriendId || !$this->coopTaskId) return;

        $task = auth()->user()->tasks()->findOrFail($this->coopTaskId);
        SharedTaskInvitation::create([
            'task_id' => $task->id,
            'inviter_id' => auth()->id(),
            'invitee_id' => $this->selectedFriendId,
            'status' => 'pending',
        ]);

        $this->showCoopModal = false;
        $this->dispatch('toast', message: 'Undangan Co-op Task berhasil dikirim!', type: 'success');
    }

    public function acceptCoopInvitation($invitationId)
    {
        $invitation = SharedTaskInvitation::where('invitee_id', auth()->id())->findOrFail($invitationId);
        $invitation->status = 'accepted';
        $invitation->save();

        $invitation->task->update(['shared_with_user_id' => auth()->id()]);
        
        // Grant +25 Pending XP to invitee for H+1 Settlement
        $user = auth()->user();
        $user->addPendingXp(25, 'coop', 'Menerima Undangan Co-op Task', null, $invitation->id);
        $user->save();

        $this->loadTasks();
        $this->dispatch('toast', message: 'Undangan Co-op Task diterima! +25 Pending XP (Settlement 00:00)', type: 'success');
    }

    public function declineCoopInvitation($invitationId)
    {
        $invitation = SharedTaskInvitation::where('invitee_id', auth()->id())->findOrFail($invitationId);
        $invitation->status = 'declined';
        $invitation->save();
        $this->dispatch('toast', message: 'Undangan Co-op Task ditolak.', type: 'nudge');
    }

    public function openJournalModal($taskId)
    {
        $this->journalTaskId = $taskId;
        $this->journalContent = '';
        $this->journalMood = 'happy';
        $this->showJournalModal = true;
    }

    public function saveTaskJournal()
    {
        $quality = \App\Services\ContentQualityService::validateText($this->journalContent, 3, 10);
        if (!$quality['valid']) {
            $this->dispatch('toast', message: $quality['message'], type: 'error');
            return;
        }

        $task = auth()->user()->tasks()->findOrFail($this->journalTaskId);
        
        if (!$task->is_completed) {
            $this->toggleComplete($task->id);
        }
        
        auth()->user()->journals()->create([
            'title' => 'Reflection: ' . $task->title,
            'content' => $this->journalContent,
            'mood' => $this->journalMood,
            'tags' => ['task_reflection', $task->category ?? 'general'],
        ]);

        $this->showJournalModal = false;
        $this->dispatch('toast', message: 'Task diselesaikan & Refleksi Jurnal tersimpan!', type: 'success');
        $this->loadTasks();
    }

    public function editTask($taskId)
    {
        $task = auth()->user()->tasks()->with('subTasks')->find($taskId);
        if (!$task) {
            $this->dispatch('toast', message: 'Tugas tidak ditemukan.', type: 'error');
            return;
        }

        if ($task->isOverdue()) {
            $this->dispatch('toast', message: 'Tugas yang telah lewat deadline terkunci dan tidak dapat diedit jamnya.', type: 'warning');
            return;
        }

        $this->editingTaskId = $task->id;
        $this->title = $task->title;
        $this->description = $task->description ?? '';
        $this->priority = $task->priority ?? 'normal';
        $this->category = $task->category ?? '';
        $this->estimated_minutes = $task->estimated_minutes ?? 30;
        
        $this->start_time = $task->start_time ? Carbon::parse($task->start_time)->format('H:i') : '';
        $this->deadline = $task->deadline ? Carbon::parse($task->deadline)->format('H:i') : '';
        
        $this->task_mode = $task->mode ?? 'zen';
        $this->skill_id = $task->skill_id;
        $this->goal_id = $task->goal_id;
        $this->is_recurring = (bool)$task->is_recurring;
        $this->recurrence_pattern = $task->recurrence_pattern ?? 'daily';
        $this->recurrence_days = $task->recurrence_days ?? [];
        
        $this->subTasksList = $task->subTasks->map(function($st) {
            return ['id' => $st->id, 'title' => $st->title];
        })->toArray();
        
        $this->showAddTaskModal = true;
    }

    public function addSubTaskInput()
    {
        $this->subTasksList[] = ['id' => null, 'title' => ''];
    }

    public function removeSubTaskInput($index)
    {
        unset($this->subTasksList[$index]);
        $this->subTasksList = array_values($this->subTasksList);
    }

    public function saveTask()
    {
        try {
            $quality = \App\Services\ContentQualityService::validateText($this->title, 1, 3);
            if (!$quality['valid']) {
                $this->dispatch('toast', message: $quality['message'], type: 'error');
                return;
            }

            $taskData = [
                'title' => $this->title,
                'description' => !empty($this->description) ? $this->description : null,
                'priority' => !empty($this->priority) ? $this->priority : 'normal',
                'category' => !empty($this->category) ? $this->category : null,
                'skill_id' => !empty($this->skill_id) ? $this->skill_id : null,
                'goal_id' => !empty($this->goal_id) ? $this->goal_id : null,
                'start_time' => !empty($this->start_time) ? $this->start_time : null,
                'deadline' => !empty($this->deadline) ? $this->deadline : null,
                'estimated_minutes' => $this->estimated_minutes ? (int)$this->estimated_minutes : 30,
                'mode' => $this->task_mode ?? 'zen',
                'is_recurring' => (bool)$this->is_recurring,
                'recurrence_pattern' => $this->is_recurring ? $this->recurrence_pattern : null,
                'recurrence_days' => ($this->is_recurring && $this->recurrence_pattern === 'custom') ? $this->recurrence_days : null,
            ];

            if ($this->editingTaskId) {
                $task = auth()->user()->tasks()->findOrFail($this->editingTaskId);
                $task->update($taskData);
            } else {
                $taskData['date'] = Carbon::parse($this->selectedDate);
                $task = auth()->user()->tasks()->create($taskData);
            }

            if (is_array($this->subTasksList)) {
                $existingIds = collect($this->subTasksList)->pluck('id')->filter()->toArray();
                $deletedSubTasks = $task->subTasks()->whereNotIn('id', $existingIds)->get();
                foreach ($deletedSubTasks as $dst) {
                    \App\Services\GamificationService::revertSubTaskXp(auth()->user(), $dst);
                    $dst->delete();
                }
                
                foreach ($this->subTasksList as $st) {
                    if (trim($st['title']) !== '') {
                        if (isset($st['id']) && $st['id']) {
                            $task->subTasks()->where('id', $st['id'])->update(['title' => $st['title']]);
                        } else {
                            $task->subTasks()->create(['title' => $st['title']]);
                        }
                    }
                }
            }

            $this->reset(['title', 'description', 'priority', 'category', 'skill_id', 'goal_id', 'start_time', 'deadline', 'estimated_minutes', 'is_recurring', 'recurrence_pattern', 'recurrence_days', 'subTasksList', 'showAddTaskModal', 'editingTaskId', 'workoutType', 'workoutSplit', 'customWorkoutExerciseInput']);
            $this->loadTasks();
            $this->dispatch('toast', message: 'Tugas berhasil disimpan!', type: 'success');

            // Native & Local notification reminder scheduling
            if (!empty($task->deadline) && !empty($task->date)) {
                try {
                    $taskDateStr = \Carbon\Carbon::parse($task->date)->format('Y-m-d');
                    $deadlineDateTime = \Carbon\Carbon::parse("{$taskDateStr} {$task->deadline}");
                    if ($deadlineDateTime->isFuture()) {
                        $this->dispatch('schedule-reminder', [
                            'id' => (int)$task->id,
                            'title' => '⚠️ Pengingat Deadline: ' . $task->title,
                            'body' => 'Deadline tugas ini adalah pukul ' . $task->deadline . '. Selesaikan sekarang!',
                            'at' => $deadlineDateTime->toIso8601String(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    // ignore scheduling date parsing errors
                }
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('toast', message: $e->validator->errors()->first(), type: 'nudge');
        } catch (\Exception $e) {
            \Log::error('Task Save Error: ' . $e->getMessage());
            $this->dispatch('toast', message: 'Error: ' . $e->getMessage(), type: 'nudge');
        }
    }

    public function toggleComplete($taskId)
    {
        $task = auth()->user()->tasks()->with('subTasks')->findOrFail($taskId);
        
        if (!$task->is_completed) {
            // Intercept workout tasks: User must complete via workout popup modal
            if ($this->isWorkoutTask($task)) {
                $this->openWorkoutModal($task->id);
                return;
            }

            $isOverdue = $task->isOverdue();
            $task->is_completed_overdue = $isOverdue;

            foreach ($task->subTasks as $st) {
                if (!$st->is_completed) {
                    $st->update(['is_completed' => true]);
                    if (!$isOverdue) {
                        \App\Services\GamificationService::awardSubTaskXp(auth()->user(), $st);
                    }
                }
            }
        } else {
            $task->is_completed_overdue = false;
            $task->xp_settled_at = null;
            foreach ($task->subTasks as $st) {
                if ($st->is_completed) {
                    \App\Services\GamificationService::revertSubTaskXp(auth()->user(), $st);
                    $st->update(['is_completed' => false, 'earned_xp' => 0, 'xp_settled_at' => null]);
                }
            }
        }
        
        $task->is_completed = !$task->is_completed;
        $task->save();

        if ($task->goal_id && $goal = $task->goal) {
            if ($task->is_completed) {
                $goal->increment('current_value');
                if ($goal->current_value >= $goal->target_value) {
                    $goal->update(['status' => 'completed']);
                    $this->dispatch('toast', message: "🔥 Goal '{$goal->title}' tercapai 100%! Selamat!", type: 'success');
                } else {
                    $this->dispatch('toast', message: "🔥 Progres Goal '{$goal->title}': {$goal->current_value}/{$goal->target_value} {$goal->unit}", type: 'info');
                }
            } else {
                if ($goal->current_value > 0) {
                    $goal->decrement('current_value');
                }
                if ($goal->status === 'completed' && $goal->current_value < $goal->target_value) {
                    $goal->update(['status' => 'active']);
                }
            }
        }

        $this->syncSkillTargetProgress($task);

        if ($task->is_completed) {
            if (!$task->is_completed_overdue) {
                $this->updateStreak(true, $task);
            }
            $evaluation = \App\Services\TaskXpService::evaluateTaskCompletion($task, auth()->user());
            $this->generateNextRecurrence($task);
            $this->dispatch('play-sound', type: $task->is_completed_overdue ? 'warning' : 'success');
            $this->dispatch('cancel-reminder', id: (int)$task->id);
            $this->dispatch('task-toggled', id: (int)$task->id, completed: true);
            $this->dispatch('native-notify', [
                'title' => '🎉 Tugas Selesai!',
                'body' => $task->title . ' telah diselesaikan.',
                'sound' => $task->is_completed_overdue ? 'chime' : 'success',
            ]);
            $this->dispatch('toast', message: $evaluation['message'], type: $evaluation['type']);
            $this->dispatch('profile-updated');

            // Check Hidden Mission (Overachiever: 250 Task XP + 60 mins Pomodoro in 1 day)
            if (!$task->is_completed_overdue) {
                $hiddenReward = \App\Services\GamificationService::checkOverachieverHiddenMission(auth()->user());
                if ($hiddenReward) {
                    $this->dispatch('toast', message: $hiddenReward['message'], type: 'success');
                    $this->dispatch('play-sound', preset: 'fanfare');
                    $this->dispatch('trigger-confetti');
                }
            }

            // Trigger Ascension Celebration Modal if tier ascended!
            if (!empty($evaluation['prestige']['ascended'])) {
                $this->ascensionData = [
                    'new_tier' => $evaluation['prestige']['new_tier'],
                    'old_tier' => $evaluation['prestige']['old_tier'],
                    'details' => \App\Services\PrestigeService::getTierDetails($evaluation['prestige']['new_tier']),
                ];
                $this->showAscensionModal = true;
                $this->dispatch('play-sound', type: 'level_up');
            }

            // Senggol Bacok: Check active duels & recent nudges
            $userId = auth()->id();
            $recentNudge = \App\Models\FriendNudge::where('receiver_id', $userId)
                ->where('created_at', '>=', now()->subHours(2))
                ->first();
            if ($recentNudge) {
                (new \App\Services\AchievementService())->unlock(auth()->user(), 'kebal_bacok');
            }

            $activeDuels = \App\Models\FriendNudge::where('status', 'accepted')
                ->where(function($q) use ($userId) {
                    $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
                })->get();

            foreach ($activeDuels as $duel) {
                if ($duel->sender_id === $userId) {
                    $duel->increment('sender_task_count');
                    if ($duel->sender_task_count >= $duel->target_task_count && !$duel->winner_id) {
                        $duel->update(['status' => 'completed', 'winner_id' => $userId]);
                        auth()->user()->addPendingXp($duel->xp_reward, 'duel', 'Kemenangan Duel Senggol Bacok', null, $duel->id);
                        $this->dispatch('toast', message: "🏆 Kamu memenangkan Duel Senggol Bacok! (+{$duel->xp_reward} XP)", type: 'success');
                        $this->dispatch('play-sound', preset: 'fanfare');
                    }
                } elseif ($duel->receiver_id === $userId) {
                    $duel->increment('receiver_task_count');
                    if ($duel->receiver_task_count >= $duel->target_task_count && !$duel->winner_id) {
                        $duel->update(['status' => 'completed', 'winner_id' => $userId]);
                        auth()->user()->addPendingXp($duel->xp_reward, 'duel', 'Kemenangan Duel Senggol Bacok', null, $duel->id);
                        $this->dispatch('toast', message: "🏆 Kamu memenangkan Duel Senggol Bacok! (+{$duel->xp_reward} XP)", type: 'success');
                        $this->dispatch('play-sound', preset: 'fanfare');
                    }
                }
            }

            // Check & Unlock Achievements
            $unlockedAchievements = (new \App\Services\AchievementService())->checkAndUnlock(auth()->user());
            foreach ($unlockedAchievements as $unlockedBadge) {
                $this->dispatch('achievement-unlocked', badge: $unlockedBadge);
            }

            // Check if task is Finance Category -> Trigger Finance recording popup
            if ($task->category) {
                $catLower = strtolower($task->category);
                if (str_contains($catLower, 'finan') || str_contains($catLower, 'keuangan') || str_contains($catLower, 'uang') || str_contains($catLower, 'duit') || str_contains($catLower, 'tagihan') || str_contains($catLower, 'bayar') || str_contains($catLower, 'gaji')) {
                    $this->financeTaskId = $task->id;
                    $this->financeTaskTitle = $task->title;
                    $this->financeTaskAmount = '';
                    $this->financeTaskType = (str_contains($catLower, 'gaji') || str_contains($catLower, 'income') || str_contains($catLower, 'pemasukan')) ? 'income' : 'expense';
                    $this->financeTaskCategory = 'Tagihan';
                    $this->financeTaskDescription = $task->title;
                    $this->financeTaskDate = $task->date ? $task->date->format('Y-m-d') : Carbon::today()->format('Y-m-d');
                    $this->showFinancePromptModal = true;
                }
            }
        } else {
            $this->updateStreak(false, $task);
            \App\Services\TaskXpService::revertTaskCompletion($task, auth()->user());
            $this->dispatch('task-toggled', id: (int)$task->id, completed: false);
            $this->dispatch('profile-updated');
            $this->dispatch('toast', message: 'Status tugas dibatalkan.', type: 'info');
        }

        $this->loadTasks();
    }

    public function saveFinanceFromTask()
    {
        $this->validate([
            'financeTaskAmount' => 'required|numeric|min:1',
            'financeTaskType' => 'required|in:income,expense',
            'financeTaskCategory' => 'required|string',
        ]);

        auth()->user()->financeTransactions()->create([
            'type' => $this->financeTaskType,
            'amount' => $this->financeTaskAmount,
            'category' => $this->financeTaskCategory,
            'description' => empty($this->financeTaskDescription) ? $this->financeTaskTitle : $this->financeTaskDescription,
            'date' => Carbon::parse($this->financeTaskDate ?: now()),
        ]);

        $this->reset(['showFinancePromptModal', 'financeTaskId', 'financeTaskAmount', 'financeTaskDescription']);
        $this->dispatch('toast', message: '💰 Transaksi keuangan berhasil dicatat & masuk ke Tab Finance!', type: 'success');
        $this->dispatch('trigger-confetti');
    }

    public function skipFinanceFromTask()
    {
        $this->reset(['showFinancePromptModal', 'financeTaskId', 'financeTaskAmount', 'financeTaskDescription']);
    }

    public function setTemplateTab($tab)
    {
        $this->templateActiveTab = $tab;
        $this->showTemplatePreview = false;
    }

    public function setTemplateCategoryFilter($cat)
    {
        $this->templateCategoryFilter = $cat;
    }

    public function getCuratedPresets(): array
    {
        return [
            'deep_work' => [
                'id' => 'deep_work',
                'title' => 'Deep Work: Sprint Pengembangan Fitur',
                'description' => 'Fokus mendalam 90 menit tanpa distraksi untuk menyelesaikan modul atau logika kode inti.',
                'category' => 'Work',
                'priority' => 'high',
                'estimated_minutes' => 90,
                'mode' => 'sistematis',
                'icon' => 'code',
                'badge' => 'Coding & Tech',
                'subtasks' => [
                    'Tinjau requirement & desain arsitektur data',
                    'Implementasi logika inti & struktur database',
                    'Tulis unit test & verifikasi fungsionalitas',
                    'Refactoring & commit code dengan pesan rapi',
                ],
            ],
            'bug_hunt' => [
                'id' => 'bug_hunt',
                'title' => 'Bug Investigation & Hotfix Sprint',
                'description' => 'Proses terstruktur untuk mereproduksi bug, mencari akar masalah, dan merilis patch.',
                'category' => 'Work',
                'priority' => 'high',
                'estimated_minutes' => 60,
                'mode' => 'sistematis',
                'icon' => 'bug',
                'badge' => 'Debugging',
                'subtasks' => [
                    'Reproduksi bug pada lingkungan lokal / staging',
                    'Periksa error logs & lacak stack trace',
                    'Tulis regression test untuk menangkap bug',
                    'Deploy hotfix & verifikasi di production',
                ],
            ],
            'study_session' => [
                'id' => 'study_session',
                'title' => 'Sesi Belajar & Penguasaan Konsep',
                'description' => 'Metode pembelajaran aktif (active recall) dengan ekstraksi ringkasan dan kartu SRS.',
                'category' => 'Study',
                'priority' => 'high',
                'estimated_minutes' => 60,
                'mode' => 'sistematis',
                'icon' => 'book-open',
                'badge' => 'Learning',
                'subtasks' => [
                    'Baca materi & buat catatan ringkas (Feynman technique)',
                    'Identifikasi poin kunci & rumus penting',
                    'Ekstrak menjadi Flashcard Spaced Repetition di ATOMA',
                    'Latihan 3 soal / studi kasus nyata',
                ],
            ],
            'exam_cramming' => [
                'id' => 'exam_cramming',
                'title' => 'Simulasi Ujian & Drill Soal Intensif',
                'description' => 'Latihan terfokus dengan batas waktu untuk mempersiapkan ujian atau tes sertifikasi.',
                'category' => 'Study',
                'priority' => 'high',
                'estimated_minutes' => 75,
                'mode' => 'sistematis',
                'icon' => 'file-text',
                'badge' => 'Ujian & Tes',
                'subtasks' => [
                    'Kerjakan 1 set simulasi soal dengan batas waktu',
                    'Koreksi jawaban & beri tanda soal yang salah',
                    'Ulas konsep di balik kesalahan jawaban',
                    'Buat rangkuman cheatsheet 1 halaman',
                ],
            ],
            'finance_audit' => [
                'id' => 'finance_audit',
                'title' => 'Audit Keuangan & Budgeting Bulanan',
                'description' => 'Evaluasi kesehatan kas bulanan, alokasi tabungan, dan rekonsiliasi pengeluaran.',
                'category' => 'Finance',
                'priority' => 'high',
                'estimated_minutes' => 45,
                'mode' => 'sistematis',
                'icon' => 'wallet',
                'badge' => 'Keuangan',
                'subtasks' => [
                    'Rekap seluruh bukti transaksi & mutasi rekening',
                    'Cek saldo rekening, e-wallet, dan pos investasi',
                    'Alokasi 20% tabungan & dana darurat',
                    'Evaluasi pos pengeluaran yang melebihi batas anggaran',
                ],
            ],
            'expense_cleanup' => [
                'id' => 'expense_cleanup',
                'title' => 'Bersih-Bersih Tagihan & Langganan Rutin',
                'description' => 'Identifikasi langganan yang tidak terpakai dan optimasi tagihan rutin bulanan.',
                'category' => 'Finance',
                'priority' => 'normal',
                'estimated_minutes' => 30,
                'mode' => 'sistematis',
                'icon' => 'scissors',
                'badge' => 'Hemat & Optimasi',
                'subtasks' => [
                    'Daftar semua langganan aktif (Netflix, SaaS, gym, dll)',
                    'Batalkan atau pause langganan yang jarang dipakai',
                    'Bayar tagihan wajib sebelum jatuh tempo',
                    'Catat penghematan bulanan di Jurnal Finance',
                ],
            ],
            'workout_routine' => [
                'id' => 'workout_routine',
                'title' => 'Latihan Fisik & Kebugaran Harian',
                'description' => 'Sesi olahraga seimbang untuk memelihara stamina, kesehatan jantung, dan postur.',
                'category' => 'Health',
                'priority' => 'normal',
                'estimated_minutes' => 45,
                'mode' => 'zen',
                'icon' => 'activity',
                'badge' => 'Olahraga & Kebugaran',
                'subtasks' => [
                    'Pemanasan dinamis & mobilitas sendi 7 menit',
                    'Latihan inti (beban / kardio / calisthenics) 30 menit',
                    'Pendinginan statis & stretching punggung 8 menit',
                    'Catat log progres latihan & minum 500ml air putih',
                ],
            ],
            'posture_stretching' => [
                'id' => 'posture_stretching',
                'title' => 'Desk Ergonomics & Posture Reset',
                'description' => 'Peregangan cepat di sela-sela kerja untuk meredakan pegal leher, bahu, dan mata lelah.',
                'category' => 'Health',
                'priority' => 'low',
                'estimated_minutes' => 15,
                'mode' => 'zen',
                'icon' => 'sparkles',
                'badge' => 'Ergonomic Reset',
                'subtasks' => [
                    'Peregangan leher & rotasi bahu 3 menit',
                    'Hamstring & hip flexor stretch dari kursi 5 menit',
                    'Aturan 20-20-20 untuk relaksasi mata',
                    'Ambil air minum & jalan ringan 50 langkah',
                ],
            ],
            'weekly_reset' => [
                'id' => 'weekly_reset',
                'title' => 'Weekly Reset & Perencanaan Minggu Depan',
                'description' => 'Menyegarkan kembali ruang kerja, mengosongkan inbox to-do, dan menyusun target mingguan.',
                'category' => 'Personal',
                'priority' => 'normal',
                'estimated_minutes' => 35,
                'mode' => 'sistematis',
                'icon' => 'sprout',
                'badge' => 'Weekly Reset',
                'subtasks' => [
                    'Bersihkan & rapikan meja kerja fisik',
                    'Inbox zero email & to-do list tertunda',
                    'Tinjau pencapaian & evaluasi produktivitas minggu ini',
                    'Tentukan 3 target prioritas utama untuk minggu depan',
                ],
            ],
            'morning_kickstart' => [
                'id' => 'morning_kickstart',
                'title' => 'Morning Kickstart: Rutinitas Bangun Pagi',
                'description' => 'Ritual pagi berenergi tinggi untuk mengawali hari dengan fokus dan kejernihan.',
                'category' => 'Personal',
                'priority' => 'normal',
                'estimated_minutes' => 25,
                'mode' => 'zen',
                'icon' => 'sun',
                'badge' => 'Rutinitas Pagi',
                'subtasks' => [
                    'Minum 1 gelas air putih & rapikan tempat tidur',
                    'Peregangan ringan & hirup udara segar 5 menit',
                    'Tulis 1 catatan rasa syukur di Jurnal ATOMA',
                    'Pilih 1 tugas paling penting hari ini (Eat That Frog)',
                ],
            ],
            'content_creation' => [
                'id' => 'content_creation',
                'title' => 'Produksi Konten & Publikasi Media',
                'description' => 'Alur terstruktur dari riset ide, penulisan naskah, hingga publikasi terjadwal.',
                'category' => 'Work',
                'priority' => 'normal',
                'estimated_minutes' => 75,
                'mode' => 'sistematis',
                'icon' => 'file-text',
                'badge' => 'Konten & Kreator',
                'subtasks' => [
                    'Riset tren & tentukan hook / judul menarik',
                    'Susun kerangka outline & poin-poin utama',
                    'Drafting naskah lengkap atau aset visual',
                    'Final review, editing, & jadwalkan waktu tayang',
                ],
            ],
            'client_presentation' => [
                'id' => 'client_presentation',
                'title' => 'Persiapan Deck Presentasi & Pitching',
                'description' => 'Langkah persiapan materi slide presentasi dan latihan berbicara yang meyakinkan.',
                'category' => 'Work',
                'priority' => 'high',
                'estimated_minutes' => 60,
                'mode' => 'sistematis',
                'icon' => 'target',
                'badge' => 'Presentasi & Pitch',
                'subtasks' => [
                    'Tentukan 3 pesan utama yang ingin diingat audiens',
                    'Susun alur slide (Problem, Solution, Action Plan)',
                    'Desain visual & masukkan data pendukung yang kredibel',
                    'Simulasi latihan berbicara 2 kali dengan stopwatch',
                ],
            ],
        ];
    }

    public function applyTaskTemplate($key)
    {
        $presets = $this->getCuratedPresets();
        
        if (isset($presets[$key])) {
            $t = $presets[$key];
            $createdTask = auth()->user()->tasks()->create([
                'title' => $t['title'],
                'date' => $this->selectedDate ? Carbon::parse($this->selectedDate)->toDateString() : Carbon::today()->toDateString(),
                'category' => $t['category'],
                'priority' => $t['priority'],
                'estimated_minutes' => $t['estimated_minutes'],
                'mode' => $t['mode'],
                'is_completed' => false,
            ]);

            foreach ($t['subtasks'] as $stTitle) {
                $createdTask->subTasks()->create([
                    'title' => $stTitle,
                    'is_completed' => false,
                ]);
            }

            $this->showAiTemplateModal = false;
            $this->showTemplatePreview = false;
            $this->loadTasks();
            $this->dispatch('toast', message: "✨ Template '{$t['title']}' berhasil dibuat!", type: 'success');
            $this->dispatch('trigger-confetti');
            return;
        }

        // Check if $key is database template ID
        if (is_numeric($key)) {
            $template = TaskTemplate::find($key);
            if ($template) {
                $createdTask = auth()->user()->tasks()->create([
                    'title' => $template->title,
                    'description' => $template->description,
                    'date' => $this->selectedDate ? Carbon::parse($this->selectedDate)->toDateString() : Carbon::today()->toDateString(),
                    'category' => $template->category,
                    'priority' => $template->priority,
                    'estimated_minutes' => $template->estimated_minutes,
                    'mode' => $template->mode,
                    'is_completed' => false,
                ]);

                if (is_array($template->subtasks)) {
                    foreach ($template->subtasks as $stTitle) {
                        $createdTask->subTasks()->create([
                            'title' => $stTitle,
                            'is_completed' => false,
                        ]);
                    }
                }

                $template->increment('clones_count');
                $this->showAiTemplateModal = false;
                $this->showTemplatePreview = false;
                $this->loadTasks();
                $this->dispatch('toast', message: "✨ Template '{$template->title}' berhasil diterapkan!", type: 'success');
                $this->dispatch('trigger-confetti');
            }
        }
    }

    public function previewPreset($key)
    {
        $presets = $this->getCuratedPresets();
        if (!isset($presets[$key])) return;

        $t = $presets[$key];
        $this->previewTaskData = [
            'id' => null,
            'title' => $t['title'],
            'description' => $t['description'],
            'category' => $t['category'],
            'priority' => $t['priority'],
            'estimated_minutes' => $t['estimated_minutes'],
            'mode' => $t['mode'],
            'subtasks' => $t['subtasks'],
            'date' => $this->selectedDate ? Carbon::parse($this->selectedDate)->toDateString() : Carbon::today()->toDateString(),
        ];
        $this->showTemplatePreview = true;
    }

    public function previewDatabaseTemplate($templateId)
    {
        $t = TaskTemplate::findOrFail($templateId);
        $this->previewTaskData = [
            'id' => $t->id,
            'title' => $t->title,
            'description' => $t->description ?: '',
            'category' => $t->category,
            'priority' => $t->priority,
            'estimated_minutes' => $t->estimated_minutes,
            'mode' => $t->mode,
            'subtasks' => is_array($t->subtasks) ? $t->subtasks : [],
            'date' => $this->selectedDate ? Carbon::parse($this->selectedDate)->toDateString() : Carbon::today()->toDateString(),
        ];
        $this->showTemplatePreview = true;
    }

    public function decomposeAiTask()
    {
        $prompt = trim($this->aiDecomposePrompt);
        if (!$prompt) {
            $this->dispatch('toast', message: 'Tuliskan topik atau tujuan tugas terlebih dahulu!', type: 'nudge');
            return;
        }

        $userId = auth()->id() ?? 'guest';
        $lock = \Illuminate\Support\Facades\Cache::lock("ai_lock_user_{$userId}", 15);
        if (!$lock->get()) {
            $this->dispatch('toast', message: 'Permintaan AI sedang berjalan. Mohon tunggu sebentar...', type: 'warning');
            return;
        }

        // Rate limiter (max 5 AI requests per minute per user)
        $rateKey = "ai_rate_limit_user_{$userId}";
        $requests = (int) \Illuminate\Support\Facades\Cache::get($rateKey, 0);
        if ($requests >= 5) {
            $lock->release();
            $this->dispatch('toast', message: 'Batas permintaan AI tercapai (maksimal 5x per menit). Silakan coba lagi nanti.', type: 'warning');
            return;
        }
        \Illuminate\Support\Facades\Cache::put($rateKey, $requests + 1, 60);

        try {
            $this->isGeneratingAiTask = true;
            $decomposed = \App\Services\AiTaskDecomposerService::decompose($prompt, $this->selectedDate);
        } finally {
            $this->isGeneratingAiTask = false;
            $lock->release();
        }

        $this->previewTaskData = [
            'id' => null,
            'title' => $decomposed['title'],
            'description' => $decomposed['description'] ?? '',
            'category' => $decomposed['category'],
            'priority' => $decomposed['priority'],
            'estimated_minutes' => $decomposed['estimated_minutes'],
            'mode' => $decomposed['mode'] ?? 'sistematis',
            'subtasks' => $decomposed['subtasks'] ?? [],
            'date' => $this->selectedDate ? Carbon::parse($this->selectedDate)->toDateString() : Carbon::today()->toDateString(),
        ];

        $this->showTemplatePreview = true;
    }

    public function applyPreviewedTask()
    {
        $title = trim($this->previewTaskData['title'] ?? '');
        if (!$title) {
            $this->dispatch('toast', message: 'Judul tugas tidak boleh kosong!', type: 'warning');
            return;
        }

        $createdTask = auth()->user()->tasks()->create([
            'title' => $title,
            'description' => $this->previewTaskData['description'] ?? null,
            'date' => !empty($this->previewTaskData['date']) ? $this->previewTaskData['date'] : Carbon::today()->toDateString(),
            'category' => $this->previewTaskData['category'] ?? 'Work',
            'priority' => $this->previewTaskData['priority'] ?? 'normal',
            'estimated_minutes' => (int) ($this->previewTaskData['estimated_minutes'] ?? 45),
            'mode' => $this->previewTaskData['mode'] ?? 'sistematis',
            'is_completed' => false,
        ]);

        if (!empty($this->previewTaskData['subtasks']) && is_array($this->previewTaskData['subtasks'])) {
            foreach ($this->previewTaskData['subtasks'] as $stTitle) {
                if (trim($stTitle)) {
                    $createdTask->subTasks()->create([
                        'title' => trim($stTitle),
                        'is_completed' => false,
                    ]);
                }
            }
        }

        // If it was previewed from a community template, increment clone count
        if (!empty($this->previewTaskData['id'])) {
            $sourceTpl = TaskTemplate::find($this->previewTaskData['id']);
            if ($sourceTpl) {
                $sourceTpl->increment('clones_count');
            }
        }

        $this->showAiTemplateModal = false;
        $this->showTemplatePreview = false;
        $this->loadTasks();
        $this->dispatch('toast', message: "✨ Tugas '{$createdTask->title}' berhasil ditambahkan ke jadwal!", type: 'success');
        $this->dispatch('trigger-confetti');
    }

    public function savePreviewAsTemplate($makePublic = false)
    {
        $title = trim($this->previewTaskData['title'] ?? '');
        if (!$title) {
            $this->dispatch('toast', message: 'Judul template tidak boleh kosong!', type: 'warning');
            return;
        }

        TaskTemplate::create([
            'user_id' => auth()->id(),
            'title' => $title,
            'description' => $this->previewTaskData['description'] ?? null,
            'category' => $this->previewTaskData['category'] ?? 'Work',
            'priority' => $this->previewTaskData['priority'] ?? 'normal',
            'estimated_minutes' => (int) ($this->previewTaskData['estimated_minutes'] ?? 45),
            'mode' => $this->previewTaskData['mode'] ?? 'sistematis',
            'subtasks' => $this->previewTaskData['subtasks'] ?? [],
            'is_public' => $makePublic,
        ]);

        $this->dispatch('toast', message: $makePublic ? "✨ Template berhasil disimpan & dipublikasikan ke Global Komunitas!" : "✨ Template berhasil disimpan ke Template Saya!", type: 'success');
    }

    public function saveTaskAsTemplate($taskId, $makePublic = false)
    {
        $task = auth()->user()->tasks()->with('subTasks')->findOrFail($taskId);

        $subtasksList = $task->subTasks->pluck('title')->toArray();

        TaskTemplate::create([
            'user_id' => auth()->id(),
            'title' => $task->title,
            'description' => $task->description,
            'category' => $task->category ?: 'Work',
            'priority' => $task->priority ?: 'normal',
            'estimated_minutes' => (int) ($task->estimated_minutes ?: 45),
            'mode' => $task->mode ?: 'sistematis',
            'subtasks' => $subtasksList,
            'is_public' => $makePublic,
        ]);

        $this->dispatch('toast', message: "✨ Tugas '{$task->title}' berhasil dijadikan Template!", type: 'success');
    }

    public function togglePublicTemplate($templateId)
    {
        $template = auth()->user()->taskTemplates()->findOrFail($templateId);
        $template->is_public = !$template->is_public;
        $template->save();

        $msg = $template->is_public
            ? "Template kini dibagikan ke Global Komunitas! 🌐"
            : "Template kini bersifat Pribadi 🔒";
        $this->dispatch('toast', message: $msg, type: 'success');
    }

    public function deleteCustomTemplate($templateId)
    {
        $template = auth()->user()->taskTemplates()->findOrFail($templateId);
        $template->delete();
        $this->dispatch('toast', message: "Template berhasil dihapus.", type: 'nudge');
    }

    public function openShareWithFriendModal($templateId)
    {
        $template = TaskTemplate::findOrFail($templateId);
        $this->selectedTemplateToShareId = $template->id;
        $this->selectedTemplateToShareTitle = $template->title;
        $this->shareFriendId = null;
        $this->shareFriendNote = '';
        $this->showShareModal = true;
    }

    public function closeShareWithFriendModal()
    {
        $this->showShareModal = false;
        $this->selectedTemplateToShareId = null;
        $this->selectedTemplateToShareTitle = '';
        $this->shareFriendId = null;
        $this->shareFriendNote = '';
    }

    public function sendTemplateToFriend()
    {
        if (!$this->selectedTemplateToShareId || !$this->shareFriendId) {
            $this->dispatch('toast', message: 'Pilih teman terlebih dahulu!', type: 'warning');
            return;
        }

        TemplateShare::create([
            'task_template_id' => $this->selectedTemplateToShareId,
            'sender_id' => auth()->id(),
            'receiver_id' => $this->shareFriendId,
            'note' => $this->shareFriendNote,
            'status' => 'pending',
        ]);

        $this->dispatch('toast', message: '🎁 Template berhasil dikirimkan ke teman!', type: 'success');
        $this->closeShareWithFriendModal();
    }

    public function acceptTemplateShare($shareId)
    {
        $share = auth()->user()->receivedTemplateShares()->findOrFail($shareId);
        $share->update(['status' => 'accepted']);

        $template = $share->template;
        if ($template) {
            $createdTask = auth()->user()->tasks()->create([
                'title' => $template->title,
                'description' => $template->description,
                'date' => $this->selectedDate ? Carbon::parse($this->selectedDate)->toDateString() : Carbon::today()->toDateString(),
                'category' => $template->category,
                'priority' => $template->priority,
                'estimated_minutes' => $template->estimated_minutes,
                'mode' => $template->mode,
                'is_completed' => false,
            ]);

            if (is_array($template->subtasks)) {
                foreach ($template->subtasks as $stTitle) {
                    $createdTask->subTasks()->create([
                        'title' => $stTitle,
                        'is_completed' => false,
                    ]);
                }
            }

            $this->loadTasks();
            $this->dispatch('toast', message: "🎁 Template dari teman berhasil diterapkan ke tugasmu!", type: 'success');
            $this->dispatch('trigger-confetti');
        }
    }

    public function removePreviewSubtask($index)
    {
        if (isset($this->previewTaskData['subtasks'][$index])) {
            array_splice($this->previewTaskData['subtasks'], $index, 1);
        }
    }

    public function addPreviewSubtask()
    {
        $input = trim($this->newSubtaskInput);
        if ($input) {
            $this->previewTaskData['subtasks'][] = $input;
            $this->newSubtaskInput = '';
        }
    }

    public function openAiTemplateModal()
    {
        $this->showAiTemplateModal = true;
        $this->showTemplatePreview = false;
    }

    public function closeAiTemplateModal()
    {
        $this->showAiTemplateModal = false;
        $this->showTemplatePreview = false;
    }

    
    public function toggleSubTask($taskId, $subTaskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $subTask = $task->subTasks()->findOrFail($subTaskId);
        $subTask->is_completed = !$subTask->is_completed;
        $subTask->save();

        if ($subTask->is_completed) {
            $awarded = \App\Services\GamificationService::awardSubTaskXp(auth()->user(), $subTask);
            if ($awarded > 0) {
                $this->dispatch('profile-updated');
            }

            // Check if completing subtask triggers today's dynamic hidden mission
            $hiddenReward = \App\Services\GamificationService::checkDailyHiddenMission(auth()->user());
            if ($hiddenReward) {
                $this->dispatch('toast', message: $hiddenReward['message'], type: 'success');
                $this->dispatch('play-sound', preset: 'fanfare');
                $this->dispatch('trigger-confetti');
            }
        } else {
            // Anti-exploit: revert awarded subtask XP upon unchecking
            \App\Services\GamificationService::revertSubTaskXp(auth()->user(), $subTask);
            $this->dispatch('profile-updated');
        }

        // Auto sync parent task completion state if all subtasks are completed or any is unchecked
        $totalSubTasks = $task->subTasks()->count();
        $completedSubTasks = $task->subTasks()->where('is_completed', true)->count();
        if ($totalSubTasks > 0) {
            if ($completedSubTasks === $totalSubTasks && !$task->is_completed) {
                $isOverdue = $task->isOverdue();
                $task->is_completed_overdue = $isOverdue;
                $task->is_completed = true;
                $task->save();

                if (!$task->is_completed_overdue) {
                    $this->updateStreak(true, $task);
                }

                $evaluation = \App\Services\TaskXpService::evaluateTaskCompletion($task, auth()->user());
                $this->generateNextRecurrence($task);
                $this->dispatch('play-sound', type: $task->is_completed_overdue ? 'warning' : 'success');
                $this->dispatch('cancel-reminder', id: (int)$task->id);
                $this->dispatch('task-toggled', id: (int)$task->id, completed: true);
                $this->dispatch('native-notify', [
                    'title' => '🎉 Tugas Selesai!',
                    'body' => $task->title . ' telah diselesaikan.',
                    'sound' => $task->is_completed_overdue ? 'chime' : 'success',
                ]);
                $this->dispatch('toast', message: $evaluation['message'], type: $evaluation['type']);
                $this->dispatch('profile-updated');

                $this->syncSkillTargetProgress($task);
            } elseif ($completedSubTasks < $totalSubTasks && $task->is_completed) {
                $task->is_completed = false;
                $task->is_completed_overdue = false;
                $task->xp_settled_at = null;
                $task->save();

                $this->updateStreak(false, $task);
                \App\Services\TaskXpService::revertTaskCompletion($task, auth()->user());
                $this->dispatch('task-toggled', id: (int)$task->id, completed: false);
                $this->dispatch('profile-updated');
                $this->dispatch('toast', message: 'Status tugas dibatalkan.', type: 'info');

                $this->syncSkillTargetProgress($task);
            }
        }

        $this->loadTasks();
    }

    public function addInlineSubTask($taskId, $title)
    {
        $title = trim($title);
        if (!$title) return;
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $task->subTasks()->create([
            'skill_target_id' => $task->skill_target_id,
            'title' => $title,
            'is_completed' => false,
        ]);
        $this->loadTasks();
    }

    protected function syncSkillTargetProgress(\App\Models\Task $task)
    {
        if (!$task->skill_target_id || !($target = $task->skillTarget)) {
            return;
        }

        $totalTargetTasks = \App\Models\Task::where('skill_target_id', $target->id)->count();
        $completedTargetTasks = \App\Models\Task::where('skill_target_id', $target->id)->where('is_completed', true)->count();

        // Also sync corresponding SubTask if it exists on target
        $matchingSub = $target->subTasks()->where('title', $task->title)->first();
        if ($matchingSub) {
            $matchingSub->update(['is_completed' => $task->is_completed]);
        }

        if ($totalTargetTasks > 0) {
            if ($completedTargetTasks === $totalTargetTasks) {
                $target->update([
                    'status' => 'completed',
                    'evaluation_feedback' => "Seluruh {$totalTargetTasks} tugas harian selesai tepat waktu melalui Task Board.",
                    'pending_evaluation_data' => [
                        'user_feedback' => 'Seluruh tugas diselesaikan.',
                        'date' => now()->toDateTimeString()
                    ]
                ]);

                $target->subTasks()->update(['is_completed' => true]);

                $this->dispatch('toast', message: "🎉 Luar biasa! Seluruh {$totalTargetTasks} tugas Hari {$target->day_number} selesai!", type: 'success');
                $this->dispatch('play-sound', preset: 'fanfare');
                $this->dispatch('trigger-confetti');

                if ($target->skill && $target->day_number === $target->skill->current_day && $target->skill->current_day < $target->skill->target_days) {
                    $target->skill->increment('current_day');
                    $nextDayNum = $target->skill->current_day;
                    $existingNext = $target->skill->targets()->where('day_number', $nextDayNum)->first();

                    if (!$existingNext) {
                        $existingNext = $target->skill->targets()->create([
                            'day_number' => $nextDayNum,
                            'title' => "Hari {$nextDayNum}: Pendalaman " . $target->skill->title,
                            'description' => "• Pelajari modul materi " . $target->skill->title . ".\n• Selesaikan 1 sesi fokus Pomodoro 25 menit.",
                            'is_ai_generated' => false,
                            'status' => 'pending'
                        ]);
                    }

                    if ($target->skill->mode === 'systematic' || $target->skill->mode === 'sistematis') {
                        \App\Services\AiSkillPlannerService::createBulkTasksForTarget($target->skill, $existingNext, auth()->user());
                    }
                } elseif ($target->skill && $target->skill->targets()->where('status', '!=', 'completed')->count() === 0) {
                    $target->skill->update(['status' => 'completed']);
                    auth()->user()->addPendingXp(100, 'skill', "Penguasaan Milestone Penuh: {$target->skill->title}", "Menuntaskan seluruh target milestone", $target->skill->id);
                    $this->dispatch('toast', message: '🏆 SELAMAT! Seluruh tantangan milestone berhasil diselesaikan! +100 Pending XP', type: 'success');
                }
            } else {
                $target->update(['status' => 'pending']);
                $pct = round(($completedTargetTasks / $totalTargetTasks) * 100);
                $this->dispatch('toast', message: "🎯 Milestone {$target->skill->title} (Hari {$target->day_number}): {$completedTargetTasks}/{$totalTargetTasks} selesai ({$pct}%)", type: 'nudge');
            }
        }
    }

    public function deleteSubTask($taskId, $subTaskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $subTask = $task->subTasks()->find($subTaskId);
        if ($subTask) {
            // Anti-exploit: revert awarded subtask XP if deleted before settlement
            \App\Services\GamificationService::revertSubTaskXp(auth()->user(), $subTask);
            $subTask->delete();
            $this->dispatch('profile-updated');
        }
        $this->loadTasks();
    }
    
    public function skipTask($taskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $this->generateNextRecurrence($task);
        $this->loadTasks();
    }
    
    private function generateNextRecurrence($task)
    {
        if (!$task->is_recurring) return;
        
        $userTz = auth()->user()->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::today($userTz);
        $nextDate = Carbon::parse($task->date);

        if ($task->recurrence_pattern === 'daily') {
            $nextDate->addDay();
            if ($nextDate->lt($today)) {
                $nextDate = $today->copy();
            }
        } elseif ($task->recurrence_pattern === 'weekly') {
            $nextDate->addWeek();
            if ($nextDate->lt($today)) {
                $nextDate = $today->copy()->addWeek();
            }
        } elseif ($task->recurrence_pattern === 'custom' && !empty($task->recurrence_days)) {
            $daysMap = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];
            $allowedDays = array_map(function($day) use ($daysMap) { return $daysMap[$day]; }, $task->recurrence_days);
            
            $attempts = 0;
            do {
                $nextDate->addDay();
                $attempts++;
                if ($attempts > 7) break;
            } while (!in_array($nextDate->dayOfWeek, $allowedDays));

            if ($nextDate->lt($today)) {
                $nextDate = $today->copy();
                $attempts = 0;
                while (!in_array($nextDate->dayOfWeek, $allowedDays) && $attempts <= 7) {
                    $nextDate->addDay();
                    $attempts++;
                }
            }
        }
        
        $exists = auth()->user()->tasks()
            ->whereDate('date', $nextDate)
            ->where('title', $task->title)
            ->exists();
            
        if (!$exists) {
            $newTask = $task->replicate();
            $newTask->date = $nextDate;
            $newTask->is_completed = false;
            $newTask->is_completed_overdue = false;
            $newTask->is_deleted_overdue = false;
            $newTask->is_overdue_dismissed = false;
            $newTask->skip_reason = null;
            $newTask->skip_reason_note = null;
            $newTask->earned_xp = 0;
            $newTask->xp_settled_at = null;
            $newTask->actual_minutes = 0;
            $newTask->pomodoro_sessions = 0;
            $newTask->save();
            
            foreach ($task->subTasks as $st) {
                $newTask->subTasks()->create([
                    'skill_target_id' => $st->skill_target_id,
                    'title' => $st->title,
                    'is_completed' => false,
                    'earned_xp' => 0,
                    'xp_settled_at' => null,
                ]);
            }
        }
    }

    public function deleteTask($taskId)
    {
        try {
            $task = auth()->user()->tasks()->find($taskId);
            if ($task) {
                $wasCompleted = $task->is_completed;
                $wasOverdue = $task->isOverdue();
                
                // Anti-exploit: revert any subtask XP that was earned before deleting task
                foreach ($task->subTasks as $st) {
                    \App\Services\GamificationService::revertSubTaskXp(auth()->user(), $st);
                    $st->delete();
                }

                if ($wasCompleted) {
                    \App\Services\TaskXpService::revertTaskCompletion($task, auth()->user());
                    $this->updateStreak(false, $task);
                }

                if ($wasOverdue) {
                    $task->is_deleted_overdue = true;
                    $task->save();
                }

                $task->delete();
                $this->dispatch('cancel-reminder', id: (int)$taskId);
                $this->loadTasks();
                $this->dispatch('toast', message: 'Tugas berhasil dihapus!', type: 'success');
            }
        } catch (\Exception $e) {
            \Log::error('Delete Task Error: ' . $e->getMessage());
            $this->dispatch('toast', message: 'Gagal menghapus tugas: ' . $e->getMessage(), type: 'error');
        }
    }

    private function updateStreak($isCompleted, $task = null)
    {
        // Anti-manja: Overdue tasks CANNOT increment or revive streak!
        if ($task && ($task->is_completed_overdue || $task->isOverdue())) {
            return;
        }

        $user = auth()->user();
        $user = \App\Services\StreakEvaluatorService::evaluate($user);

        // Anti-manja: If completing an overdue task from a past day,
        // NEVER record or revive streak history on that past day!
        if ($task && $task->date) {
            $parsedDate = Carbon::parse($task->date)->startOfDay();
            if ($parsedDate->isBefore(Carbon::today())) {
                return;
            }
        }
        
        $targetDate = Carbon::today()->toDateString();
        if ($task && $task->date) {
            $parsedDate = Carbon::parse($task->date)->startOfDay();
            if ($parsedDate->isToday()) {
                $targetDate = $parsedDate->toDateString();
            }
        }
        
        if ($isCompleted) {
            $history = \App\Models\StreakHistory::where('user_id', $user->id)
                ->whereDate('date', $targetDate)
                ->first();
                
            if (!$history) {
                \App\Models\StreakHistory::create([
                    'user_id' => $user->id,
                    'date' => $targetDate,
                    'status' => 'completed'
                ]);
            } elseif ($history->status === 'frozen') {
                $history->status = 'completed';
                $history->save();
            }
        } else {
            $query = $user->tasks()
                ->where('is_completed', true)
                ->whereDate('date', $targetDate);
                
            if ($task) {
                $query->where('id', '!=', $task->id);
            }
            
            $otherCompletedTasks = $query->count();
                
            if ($otherCompletedTasks === 0) {
                \App\Models\StreakHistory::where('user_id', $user->id)
                    ->whereDate('date', $targetDate)
                    ->where('status', 'completed')
                    ->delete();
            }
        }

        // Deterministic streak recalculation from history (anti-drift and anti double-decrement)
        $user = \App\Services\StreakEvaluatorService::recalculateStreak($user);

        $startMonth = Carbon::now()->startOfMonth();
        $endMonth = Carbon::now()->endOfMonth();
        $streakRecords = \App\Models\StreakHistory::where('user_id', $user->id)
            ->whereBetween('date', [$startMonth->copy()->startOfDay()->toDateTimeString(), $endMonth->copy()->endOfDay()->toDateTimeString()])
            ->get();
            
        $streakDays = [];
        foreach ($streakRecords as $record) {
            $day = Carbon::parse($record->date)->day;
            $streakDays[$day] = $record->status;
        }

        $completedTasks = $user->tasks()
            ->where('is_completed', true)
            ->whereBetween('date', [$startMonth->copy()->startOfDay()->toDateTimeString(), $endMonth->copy()->endOfDay()->toDateTimeString()])
            ->get();
        foreach ($completedTasks as $t) {
            $day = Carbon::parse($t->date)->day;
            $streakDays[$day] = 'completed';
        }
        
        $this->dispatch('streak-updated', 
            streakDays: $streakDays, 
            currentStreak: $user->current_streak, 
            longestStreak: $user->longest_streak
        );
    }
    
    public function closeLevelUp()
    {
        $this->showLevelUp = false;
    }

    public function closeSettlementModal()
    {
        $this->showSettlementModal = false;
    }

    public function closeAscensionModal()
    {
        $this->showAscensionModal = false;
        $this->ascensionData = [];
    }

    // Workout Splits & Preset Library
    public static function getWorkoutSplits(): array
    {
        return [
            'gym' => [
                'Push Day' => ['Bench Press (Barbell)', 'Incline Dumbbell Press', 'Overhead Shoulder Press', 'Dips (Dada/Trisep)', 'Tricep Rope Pushdown', 'Dumbbell Lateral Raise'],
                'Pull Day' => ['Pull Up', 'Lat Pulldown', 'Barbell Row', 'Seated Cable Row', 'Bicep Barbell Curl', 'Face Pull'],
                'Leg Day' => ['Barbell Squat', 'Leg Press', 'Romanian Deadlift', 'Bulgarian Split Squat', 'Calf Raise'],
                'Chest Day' => ['Bench Press (Barbell)', 'Incline Dumbbell Press', 'Cable Chest Fly', 'Dips (Dada)', 'Push Up'],
                'Back Day' => ['Pull Up', 'Deadlift', 'Lat Pulldown', 'Barbell Row', 'Seated Cable Row'],
                'Shoulder & Arms' => ['Overhead Shoulder Press', 'Dumbbell Lateral Raise', 'Bicep Barbell Curl', 'Tricep Rope Pushdown', 'Hammer Curl'],
                'Full Body' => ['Barbell Squat', 'Bench Press (Barbell)', 'Barbell Row', 'Overhead Shoulder Press', 'Romanian Deadlift'],
            ],
            'calisthenics' => [
                'Push (Dada/Trisep/Bahu)' => ['Push Up', 'Parallel Bar Dips', 'Diamond Push Up', 'Pike Push Up', 'Decline Push Up'],
                'Pull (Punggung/Bisep)' => ['Pull Up', 'Chin Up', 'Australian Inverted Row', 'Commando Pull Up'],
                'Legs & Core' => ['Pistol Squat', 'Bulgarian Split Squat', 'Hanging Leg Raise', 'L-Sit', 'Calf Raise'],
                'Skills & Strength' => ['Muscle Up', 'Handstand Push Up', 'Dragon Flag', 'Front Lever Progression'],
            ],
            'cardio' => [
                'Lari & Jogging' => ['Lari Outdoor 5K', 'Interval Sprint', 'Treadmill Running', 'Lari Santai 30 Menit'],
                'Sepeda & Cycling' => ['Sepeda Statis 30 Menit', 'Gowes Outdoor', 'Interval Cycling'],
                'HIIT & Circuit' => ['Jump Rope (Lompat Tali)', 'Burpees', 'Mountain Climbers', 'Box Jump'],
                'Renang' => ['Renang Gaya Bebas', 'Renang Gaya Dada'],
            ],
            'home' => [
                'Bodyweight Full Body' => ['Push Up', 'Bodyweight Squat', 'Lunges', 'Plank', 'Glute Bridge'],
                'Dumbbell Only' => ['Dumbbell Floor Press', 'Goblet Squat', 'Dumbbell Bent-over Row', 'Dumbbell Bicep Curl'],
                'Abs & Core' => ['Plank', 'Russian Twist', 'Bicycle Crunch', 'Hollow Body Hold'],
            ],
        ];
    }

    public function detectMuscleGroup($name): string
    {
        $n = strtolower($name);
        if (str_contains($n, 'squat') || str_contains($n, 'leg') || str_contains($n, 'kaki') || str_contains($n, 'calf') || str_contains($n, 'lunge') || str_contains($n, 'hamstring')) return 'Kaki';
        if (str_contains($n, 'bench') || str_contains($n, 'chest') || str_contains($n, 'dada') || str_contains($n, 'fly') || str_contains($n, 'push up') || str_contains($n, 'dip')) return 'Dada';
        if (str_contains($n, 'pull up') || str_contains($n, 'chin up') || str_contains($n, 'row') || str_contains($n, 'lat') || str_contains($n, 'deadlift') || str_contains($n, 'punggung') || str_contains($n, 'back')) return 'Punggung';
        if (str_contains($n, 'shoulder') || str_contains($n, 'overhead') || str_contains($n, 'bahu') || str_contains($n, 'lateral') || str_contains($n, 'pike') || str_contains($n, 'press')) return 'Bahu';
        if (str_contains($n, 'curl') || str_contains($n, 'bicep') || str_contains($n, 'tricep') || str_contains($n, 'lengan') || str_contains($n, 'arm') || str_contains($n, 'skull')) return 'Lengan';
        if (str_contains($n, 'plank') || str_contains($n, 'abs') || str_contains($n, 'core') || str_contains($n, 'perut') || str_contains($n, 'l-sit') || str_contains($n, 'crunch') || str_contains($n, 'hanging')) return 'Perut';
        if (str_contains($n, 'lari') || str_contains($n, 'run') || str_contains($n, 'jog') || str_contains($n, 'sepeda') || str_contains($n, 'cycle') || str_contains($n, 'cardio') || str_contains($n, 'kardio') || str_contains($n, 'treadmill') || str_contains($n, 'rope') || str_contains($n, 'hiit') || str_contains($n, 'burpee')) return 'Kardio';
        return 'Full Body';
    }

    public function inferExercisesFromTitle($title): array
    {
        $t = strtolower($title);
        if (str_contains($t, 'leg') || str_contains($t, 'kaki') || str_contains($t, 'lower')) {
            return [
                ['name' => 'Barbell Squat', 'muscle' => 'Kaki'],
                ['name' => 'Leg Press', 'muscle' => 'Kaki'],
                ['name' => 'Romanian Deadlift', 'muscle' => 'Kaki'],
            ];
        }
        if (str_contains($t, 'pull') || str_contains($t, 'back') || str_contains($t, 'punggung')) {
            return [
                ['name' => 'Pull Up', 'muscle' => 'Punggung'],
                ['name' => 'Barbell Row', 'muscle' => 'Punggung'],
                ['name' => 'Lat Pulldown', 'muscle' => 'Punggung'],
            ];
        }
        if (str_contains($t, 'bahu') || str_contains($t, 'shoulder') || str_contains($t, 'arm') || str_contains($t, 'lengan')) {
            return [
                ['name' => 'Overhead Shoulder Press', 'muscle' => 'Bahu'],
                ['name' => 'Dumbbell Lateral Raise', 'muscle' => 'Bahu'],
                ['name' => 'Bicep Barbell Curl', 'muscle' => 'Lengan'],
            ];
        }
        if (str_contains($t, 'calisthenic')) {
            return [
                ['name' => 'Push Up', 'muscle' => 'Dada'],
                ['name' => 'Parallel Bar Dips', 'muscle' => 'Dada'],
                ['name' => 'Pull Up', 'muscle' => 'Punggung'],
            ];
        }
        if (str_contains($t, 'lari') || str_contains($t, 'cardio') || str_contains($t, 'kardio') || str_contains($t, 'run')) {
            return [
                ['name' => 'Lari Outdoor 5K', 'muscle' => 'Kardio'],
                ['name' => 'Jump Rope (Lompat Tali)', 'muscle' => 'Kardio'],
            ];
        }
        return [
            ['name' => 'Bench Press (Barbell)', 'muscle' => 'Dada'],
            ['name' => 'Incline Dumbbell Press', 'muscle' => 'Dada'],
        ];
    }

    public function setWorkoutType($type)
    {
        $this->workoutType = $type;
        $splits = self::getWorkoutSplits()[$type] ?? [];
        $firstSplit = array_key_first($splits);
        if ($firstSplit) {
            $this->selectWorkoutSplit($type, $firstSplit);
        }
    }

    public function selectWorkoutSplit($type, $split)
    {
        $this->workoutType = $type;
        $this->workoutSplit = $split;
        $exercises = self::getWorkoutSplits()[$type][$split] ?? [];

        if (empty($this->title) || str_starts_with($this->title, 'Latihan:')) {
            $this->title = 'Latihan: ' . $split;
        }

        $this->subTasksList = [];
        foreach ($exercises as $ex) {
            $this->subTasksList[] = ['id' => null, 'title' => $ex];
        }
    }

    public function toggleWorkoutExerciseInSubtasks($name)
    {
        $existingIndex = null;
        foreach ($this->subTasksList as $idx => $st) {
            if (strcasecmp(trim($st['title']), trim($name)) === 0) {
                $existingIndex = $idx;
                break;
            }
        }

        if ($existingIndex !== null) {
            unset($this->subTasksList[$existingIndex]);
            $this->subTasksList = array_values($this->subTasksList);
        } else {
            $this->subTasksList[] = ['id' => null, 'title' => $name];
        }
    }

    public function addCustomExerciseToTask()
    {
        $name = trim($this->customWorkoutExerciseInput);
        if (!empty($name)) {
            $this->subTasksList[] = ['id' => null, 'title' => $name];
            $this->customWorkoutExerciseInput = '';
        }
    }

    public function isWorkoutTask($task): bool
    {
        if (!$task) return false;
        $cat = strtolower(trim($task->category ?? ''));
        $workoutCats = ['olahraga', 'workout', 'fitness', 'gym', 'health', 'kesehatan', 'kardio', 'cardio', 'calisthenics', 'lari', 'running'];
        if (in_array($cat, $workoutCats)) return true;
        
        $title = strtolower($task->title ?? '');
        if (str_contains($title, 'sprint review') || str_contains($title, 'sprint planning') || str_contains($title, 'sprint retro') || str_contains($title, 'meeting') || str_contains($title, 'standup')) {
            return false;
        }

        $keywords = ['olahraga', 'workout', 'fitness', 'gym', 'lari', 'jogging', 'push up', 'pull up', 'treadmill', 'angkat beban', 'renang', 'sepeda', 'leg day', 'chest day', 'pull day', 'push day', 'calisthenics', 'kalistenik', 'kardio', 'cardio', 'sprint lari', 'hiit sprint', 'interval sprint', 'lari sprint'];
        foreach ($keywords as $kw) {
            if (str_contains($title, $kw)) return true;
        }
        return false;
    }

    public function isFinanceTask($task): bool
    {
        if (!$task) return false;
        $cat = strtolower(trim($task->category ?? ''));
        $financeCats = ['finance', 'keuangan', 'finansial', 'uang', 'money', 'investasi'];
        if (in_array($cat, $financeCats)) return true;
        
        $title = strtolower($task->title ?? '');
        $keywords = ['keuangan', 'finance', 'bayar', 'tagihan', 'invoice', 'belanja', 'transfer', 'gaji', 'catat keuangan', 'catat pengeluaran', 'catat pemasukan', 'investasi', 'budget'];
        foreach ($keywords as $kw) {
            if (str_contains($title, $kw)) return true;
        }
        return false;
    }

    public function getTaskCardColorClasses($task, $isCompletedOverdue = false, $isOverdue = false): array
    {
        $isCompleted = (bool)($task->is_completed ?? false);
        $isHigh = ($task->priority === 'high');
        $isWorkout = $this->isWorkoutTask($task);
        $isFinance = $this->isFinanceTask($task);

        if ($isCompleted) {
            return [
                'border' => $isCompletedOverdue ? 'border-l-4 border-l-amber-500 bg-amber-50/20 dark:bg-amber-950/20' : 'border-l-4 border-l-emerald-500',
                'bg' => 'bg-white dark:bg-gray-800',
                'type' => 'completed',
            ];
        }

        if ($isOverdue) {
            return [
                'border' => 'border-l-4 border-l-red-500 bg-red-50/20 dark:bg-red-950/20',
                'bg' => 'bg-white dark:bg-gray-800',
                'type' => 'overdue',
            ];
        }

        // 1. Gabungan Workout + High Priority (Merah Orange)
        if ($isWorkout && $isHigh) {
            return [
                'border' => 'border-l-4 border-l-red-500 border-y border-r border-orange-200 dark:border-orange-800/60',
                'bg' => 'bg-gradient-to-r from-red-50/70 via-orange-50/40 to-white dark:from-red-950/30 dark:via-orange-950/20 dark:to-gray-800',
                'type' => 'workout_high',
            ];
        }

        // 2. Gabungan Finance + High Priority (Hijau Merah)
        if ($isFinance && $isHigh) {
            return [
                'border' => 'border-l-4 border-l-red-500 border-y border-r border-emerald-200 dark:border-emerald-800/60',
                'bg' => 'bg-gradient-to-r from-red-50/70 via-emerald-50/40 to-white dark:from-red-950/30 dark:via-emerald-950/20 dark:to-gray-800',
                'type' => 'finance_high',
            ];
        }

        // 3. High Priority Non-Workout / Non-Finance (Merah)
        if ($isHigh) {
            return [
                'border' => 'border-l-4 border-l-red-500 border-y border-r border-red-200/80 dark:border-red-900/50',
                'bg' => 'bg-red-50/40 dark:bg-red-950/20',
                'type' => 'high',
            ];
        }

        // 4. Workout / Fitness Normal Priority (Orange)
        if ($isWorkout) {
            return [
                'border' => 'border-l-4 border-l-orange-500 border-y border-r border-orange-200/80 dark:border-orange-900/50',
                'bg' => 'bg-orange-50/40 dark:bg-orange-950/20',
                'type' => 'workout',
            ];
        }

        // 5. Finance Normal Priority (Hijau)
        if ($isFinance) {
            return [
                'border' => 'border-l-4 border-l-emerald-500 border-y border-r border-emerald-200/80 dark:border-emerald-900/50',
                'bg' => 'bg-emerald-50/40 dark:bg-emerald-950/20',
                'type' => 'finance',
            ];
        }

        // 6. Lain dari itu (Putih Polos / Neutral)
        return [
            'border' => 'border-l-4 border-l-transparent border-y border-r border-gray-100 dark:border-gray-700/80',
            'bg' => 'bg-white dark:bg-gray-800',
            'type' => 'plain',
        ];
    }

    public function isCardioExercise($name, $muscle = null): bool
    {
        if ($muscle === 'Kardio' || strtolower($muscle ?? '') === 'kardio') return true;
        $lower = strtolower($name ?? '');
        return (str_contains($lower, 'lari') || 
                str_contains($lower, 'running') || 
                str_contains($lower, 'jogging') || 
                str_contains($lower, 'run') || 
                str_contains($lower, 'sepeda') || 
                str_contains($lower, 'cycling') || 
                str_contains($lower, 'treadmill') || 
                str_contains($lower, 'kardio') || 
                str_contains($lower, 'cardio') ||
                str_contains($lower, 'tempo') ||
                str_contains($lower, 'interval') ||
                str_contains($lower, 'sprint') ||
                str_contains($lower, 'jalan cepat') ||
                str_contains($lower, 'brisk walk'));
    }

    public function calculateLiveVolume(): float
    {
        $vol = 0;
        foreach ($this->workoutExercises as $ex) {
            $isCardio = (($ex['exercise_type'] ?? '') === 'cardio' || ($ex['unit'] ?? '') === 'km' || $this->isCardioExercise($ex['name'] ?? '', $ex['muscle'] ?? null));
            if ($isCardio) continue;

            $unit = $ex['unit'] ?? 'kg';
            foreach ($ex['sets'] as $s) {
                if (!empty($s['is_completed'])) {
                    $w = (float)($s['weight_kg'] ?? 0);
                    $wKg = ($unit === 'lbs') ? ($w * 0.453592) : $w;
                    $vol += ($wKg * (int)($s['reps'] ?? 0));
                }
            }
        }
        return round($vol, 1);
    }

    public function calculateLiveDistance(): float
    {
        $km = 0;
        foreach ($this->workoutExercises as $ex) {
            $isCardio = (($ex['exercise_type'] ?? '') === 'cardio' || ($ex['unit'] ?? '') === 'km' || $this->isCardioExercise($ex['name'] ?? '', $ex['muscle'] ?? null));
            if (!$isCardio) continue;

            foreach ($ex['sets'] as $s) {
                if (!empty($s['is_completed'])) {
                    $km += (float)($s['distance_km'] ?? $s['weight_kg'] ?? 0);
                }
            }
        }
        return round($km, 2);
    }

    public function getLiveWorkoutSummaryMetrics(): array
    {
        $hasCardio = false;
        $hasStrength = false;
        $totalDist = 0.0;
        $completedStrengthSets = 0;
        $totalStrengthSets = 0;
        $completedCardioSets = 0;
        $totalCardioSets = 0;

        foreach ($this->workoutExercises as $ex) {
            $isCardio = (($ex['exercise_type'] ?? '') === 'cardio' || 
                         ($ex['unit'] ?? '') === 'km' || 
                         $this->isCardioExercise($ex['name'] ?? '', $ex['muscle'] ?? null));
            
            if ($isCardio) {
                $hasCardio = true;
                foreach ($ex['sets'] as $s) {
                    $totalCardioSets++;
                    if (!empty($s['is_completed'])) {
                        $completedCardioSets++;
                        $totalDist += (float)($s['distance_km'] ?? $s['weight_kg'] ?? 0);
                    }
                }
            } else {
                $hasStrength = true;
                foreach ($ex['sets'] as $s) {
                    $totalStrengthSets++;
                    if (!empty($s['is_completed'])) {
                        $completedStrengthSets++;
                    }
                }
            }
        }

        return [
            'has_cardio' => $hasCardio,
            'has_strength' => $hasStrength,
            'distance_km' => round($totalDist, 2),
            'completed_strength_sets' => $completedStrengthSets,
            'total_strength_sets' => $totalStrengthSets,
            'completed_cardio_sets' => $completedCardioSets,
            'total_cardio_sets' => $totalCardioSets,
            'total_completed_sets' => $completedCardioSets + $completedStrengthSets,
        ];
    }

    public function detectExerciseUnit($name): string
    {
        $lower = strtolower($name);
        if ($this->isCardioExercise($name)) {
            return 'km';
        }
        if (str_contains($lower, 'pull up') || 
            str_contains($lower, 'chin up') || 
            str_contains($lower, 'push up') || 
            str_contains($lower, 'dips') || 
            str_contains($lower, 'plank') || 
            str_contains($lower, 'l-sit') || 
            str_contains($lower, 'leg raise') ||
            str_contains($lower, 'muscle up') ||
            str_contains($lower, 'crunch') ||
            str_contains($lower, 'lompat tali') ||
            str_contains($lower, 'jump rope')) {
            return 'bw';
        }
        return 'kg';
    }

    public function setExerciseUnit($exerciseIndex, $unit)
    {
        if (!isset($this->workoutExercises[$exerciseIndex])) return;
        $valid = ['kg', 'lbs', 'bw', 'km'];
        if (!in_array($unit, $valid)) return;

        $oldUnit = $this->workoutExercises[$exerciseIndex]['unit'] ?? 'kg';
        $this->workoutExercises[$exerciseIndex]['unit'] = $unit;

        if ($unit === 'km') {
            $this->workoutExercises[$exerciseIndex]['exercise_type'] = 'cardio';
            $this->workoutExercises[$exerciseIndex]['cardio_mode'] = $this->workoutExercises[$exerciseIndex]['cardio_mode'] ?? 'standard';
        } else {
            $this->workoutExercises[$exerciseIndex]['exercise_type'] = 'strength';
            if ($unit === 'bw' && $oldUnit !== 'bw') {
                foreach ($this->workoutExercises[$exerciseIndex]['sets'] as &$set) {
                    if ($set['weight_kg'] >= 20) {
                        $set['weight_kg'] = 0;
                    }
                }
            } elseif ($oldUnit === 'bw' && $unit !== 'bw') {
                foreach ($this->workoutExercises[$exerciseIndex]['sets'] as &$set) {
                    if ($set['weight_kg'] == 0) {
                        $set['weight_kg'] = 20;
                    }
                }
            }
        }
    }

    public function setCardioMode($exerciseIndex, $mode)
    {
        if (!isset($this->workoutExercises[$exerciseIndex])) return;
        $valid = ['standard', 'interval', 'tempo'];
        if (!in_array($mode, $valid)) return;

        $this->workoutExercises[$exerciseIndex]['cardio_mode'] = $mode;

        foreach ($this->workoutExercises[$exerciseIndex]['sets'] as $idx => &$set) {
            if ($mode === 'interval') {
                $set['set_type'] = $idx === 0 ? 'warmup' : 'interval';
                if (($set['distance_km'] ?? 0) == 0 || $set['distance_km'] >= 5) {
                    $set['distance_km'] = 0.4;
                    $set['duration_formatted'] = '01:30';
                    $set['duration_seconds'] = 90;
                    $set['pace'] = '03:45';
                }
            } elseif ($mode === 'tempo') {
                $set['set_type'] = 'tempo';
                if (($set['distance_km'] ?? 0) == 0) {
                    $set['distance_km'] = 5.0;
                    $set['duration_formatted'] = '27:30';
                    $set['duration_seconds'] = 1650;
                    $set['pace'] = '05:30';
                }
            } else {
                $set['set_type'] = 'normal';
            }
        }
    }

    public function recalculateCardioPace($exerciseIndex, $setIndex)
    {
        if (!isset($this->workoutExercises[$exerciseIndex]['sets'][$setIndex])) return;
        $set = &$this->workoutExercises[$exerciseIndex]['sets'][$setIndex];
        
        $dist = (float)($set['distance_km'] ?? 0);
        $durRaw = trim((string)($set['duration_formatted'] ?? ''));
        
        $seconds = $this->parseDurationStringToSeconds($durRaw);
        $set['duration_seconds'] = $seconds;
        $set['reps'] = (int) round($seconds / 60);
        $set['weight_kg'] = $dist;
        
        if ($dist > 0 && $seconds > 0) {
            $set['pace'] = $this->calculatePaceString($dist, $seconds);
        } else {
            $set['pace'] = '-';
        }
    }

    public function parseDurationStringToSeconds($str): int
    {
        if (empty($str)) return 0;
        $str = trim((string)$str);
        $parts = explode(':', $str);
        if (count($parts) === 3) {
            return ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (int)$parts[2];
        }
        if (count($parts) === 2) {
            return ((int)$parts[0] * 60) + (int)$parts[1];
        }
        if (str_ends_with($str, 's')) {
            return (int)$str;
        }
        return (int)((float)$str * 60);
    }

    public function calculatePaceString($distanceKm, $seconds): string
    {
        if ($distanceKm <= 0 || $seconds <= 0) return '-';
        $secPerKm = $seconds / $distanceKm;
        $m = floor($secPerKm / 60);
        $s = round($secPerKm % 60);
        return sprintf('%02d:%02d', $m, $s);
    }

    public function getExercisePreviousBestText($name, $isCardio = null): string
    {
        if ($isCardio === null) {
            $isCardio = $this->isCardioExercise($name);
        }

        $prevBest = WorkoutSet::whereHas('exercise', function($q) use ($name) {
            $q->where('exercise_name', $name)
              ->whereHas('session', function($sq) {
                  $sq->where('user_id', auth()->id())->whereNotNull('completed_at');
              });
        })->where('is_completed', true)->latest()->first();

        if (!$prevBest) return 'Sesi perdana';

        if ($isCardio) {
            $d = (float)($prevBest->distance_km ?: ($prevBest->weight_kg > 0 && $prevBest->weight_kg <= 50 && $prevBest->weight_kg != 20 ? $prevBest->weight_kg : 5.0));
            $dur = (int)($prevBest->duration_seconds ?? ($prevBest->reps > 0 && $prevBest->reps <= 180 && $prevBest->reps != 10 ? ($prevBest->reps * 60) : 1800));
            $durStr = $dur > 0 ? sprintf('%02d:%02d', floor($dur / 60), $dur % 60) : '30:00';
            $paceStr = $prevBest->pace ?: ($d > 0 && $dur > 0 ? $this->calculatePaceString($d, $dur) : '06:00');
            return "{$d} km" . ($durStr ? " • {$durStr}" : "") . ($paceStr ? " (Pace {$paceStr}/km)" : "");
        }

        return "{$prevBest->weight_kg} kg × {$prevBest->reps} reps";
    }

    public function cycleSetType($exerciseIndex, $setIndex)
    {
        if (!isset($this->workoutExercises[$exerciseIndex]['sets'][$setIndex])) return;
        $isCardio = (($this->workoutExercises[$exerciseIndex]['exercise_type'] ?? '') === 'cardio' || 
                     ($this->workoutExercises[$exerciseIndex]['unit'] ?? '') === 'km' || 
                     $this->isCardioExercise($this->workoutExercises[$exerciseIndex]['name'] ?? '', $this->workoutExercises[$exerciseIndex]['muscle'] ?? null));
        
        if ($isCardio) {
            $types = ['normal', 'warmup', 'interval', 'tempo'];
        } else {
            $types = ['normal', 'warmup', 'drop', 'failure'];
        }
        $current = $this->workoutExercises[$exerciseIndex]['sets'][$setIndex]['set_type'] ?? 'normal';
        $nextIdx = (array_search($current, $types) + 1) % count($types);
        $this->workoutExercises[$exerciseIndex]['sets'][$setIndex]['set_type'] = $types[$nextIdx];
    }

    public function prepareSwapExercise($exerciseIndex)
    {
        $this->swapTargetExerciseIndex = $exerciseIndex;
        $this->showAddExerciseModal = true;
    }

    public function swapExercise($exerciseIndex, $newName, $newMuscle = null)
    {
        if (!isset($this->workoutExercises[$exerciseIndex])) return;
        $newName = trim($newName);
        if (empty($newName)) return;

        $muscle = $newMuscle ?: $this->detectMuscleGroup($newName);
        $isCardio = $this->isCardioExercise($newName, $muscle);
        $unit = $isCardio ? 'km' : $this->detectExerciseUnit($newName);

        $prevBestText = $this->getExercisePreviousBestText($newName, $isCardio);

        $this->workoutExercises[$exerciseIndex]['name'] = $newName;
        $this->workoutExercises[$exerciseIndex]['muscle'] = $muscle;
        $this->workoutExercises[$exerciseIndex]['unit'] = $unit;
        $this->workoutExercises[$exerciseIndex]['exercise_type'] = $isCardio ? 'cardio' : 'strength';
        $this->workoutExercises[$exerciseIndex]['cardio_mode'] = 'standard';
        $this->workoutExercises[$exerciseIndex]['previous_best'] = $prevBestText;
        $this->workoutExercises[$exerciseIndex]['previous_best_short'] = $isCardio ? '-' : '20 × 10';

        if ($isCardio) {
            foreach ($this->workoutExercises[$exerciseIndex]['sets'] as $k => $s) {
                if (!$s['is_completed']) {
                    $this->workoutExercises[$exerciseIndex]['sets'][$k]['distance_km'] = 5.0;
                    $this->workoutExercises[$exerciseIndex]['sets'][$k]['weight_kg'] = 5.0;
                    $this->workoutExercises[$exerciseIndex]['sets'][$k]['duration_seconds'] = 1800;
                    $this->workoutExercises[$exerciseIndex]['sets'][$k]['duration_formatted'] = '30:00';
                    $this->workoutExercises[$exerciseIndex]['sets'][$k]['pace'] = '06:00';
                    $this->workoutExercises[$exerciseIndex]['sets'][$k]['reps'] = 30;
                }
            }
        } else {
            $defaultWeight = $unit === 'bw' ? 0.0 : 20.0;
            $defaultReps = $unit === 'bw' ? 12 : 10;
            foreach ($this->workoutExercises[$exerciseIndex]['sets'] as $k => $s) {
                if (!$s['is_completed']) {
                    $this->workoutExercises[$exerciseIndex]['sets'][$k]['weight_kg'] = $defaultWeight;
                    $this->workoutExercises[$exerciseIndex]['sets'][$k]['reps'] = $defaultReps;
                }
            }
        }

        $this->swapTargetExerciseIndex = null;
        $this->showAddExerciseModal = false;
        $this->dispatch('toast', message: "Gerakan diganti menjadi: {$newName}", type: 'success');
    }

    // Workout Tracker Methods
    public function openWorkoutModal($taskId = null)
    {
        $this->activeWorkoutTaskId = $taskId;
        $this->activeWorkoutDurationSeconds = 0;
        $this->activeWorkoutNotes = '';
        $this->workoutExercises = [];

        if ($taskId) {
            $task = auth()->user()->tasks()->with('subTasks')->find($taskId);
            $this->activeWorkoutTaskTitle = $task ? $task->title : '';
            $this->activeWorkoutTitle = $task ? 'Latihan: ' . $task->title : 'Sesi Latihan';

            // Memory: Check if a saved/previous WorkoutSession exists for this task
            $savedSession = WorkoutSession::where('user_id', auth()->id())
                ->where('task_id', $taskId)
                ->with(['exercises.sets'])
                ->latest()
                ->first();

            if ($savedSession && $savedSession->exercises->count() > 0) {
                $this->activeWorkoutTitle = $savedSession->title ?: $this->activeWorkoutTitle;
                $this->activeWorkoutNotes = $savedSession->notes ?? '';
                $this->activeWorkoutDurationSeconds = (int)$savedSession->duration_seconds;

                foreach ($savedSession->exercises as $ex) {
                    $isCardio = ($ex->weight_unit === 'km' || 
                                 strtolower($ex->muscle_group ?? '') === 'kardio' || 
                                 $this->isCardioExercise($ex->exercise_name, $ex->muscle_group));
                    $sets = [];
                    foreach ($ex->sets as $sIndex => $s) {
                        $dist = (float)($s->distance_km ?? 0);
                        if ($isCardio && $dist <= 0) {
                            $dist = (float)($s->weight_kg > 0 && $s->weight_kg <= 50 && $s->weight_kg != 20 ? $s->weight_kg : 5.0);
                        }
                        $dur = (int)($s->duration_seconds ?? 0);
                        if ($isCardio && $dur <= 0) {
                            $dur = (int)($s->reps > 0 && $s->reps <= 180 && $s->reps != 10 ? ($s->reps * 60) : 1800);
                        }
                        $durFormatted = $dur > 0 ? sprintf('%02d:%02d', floor($dur / 60), $dur % 60) : '30:00';
                        $pace = $s->pace ?? ($dist > 0 && $dur > 0 ? $this->calculatePaceString($dist, $dur) : '06:00');

                        $sets[] = [
                            'set_number' => $s->set_number ?? ($sIndex + 1),
                            'weight_kg' => (float)($s->weight_kg ?? 0),
                            'reps' => (int)($s->reps ?? 10),
                            'distance_km' => $isCardio ? $dist : null,
                            'duration_seconds' => $isCardio ? $dur : null,
                            'duration_formatted' => $isCardio ? $durFormatted : '',
                            'pace' => $isCardio ? $pace : null,
                            'set_type' => $s->set_type ?? 'normal',
                            'is_completed' => !empty($s->is_completed),
                            'is_pr' => !empty($s->is_pr),
                        ];
                    }
                    $this->workoutExercises[] = [
                        'id' => uniqid('ex_'),
                        'name' => $ex->exercise_name,
                        'muscle' => $isCardio ? 'Kardio' : ($ex->muscle_group ?? 'Full Body'),
                        'unit' => $isCardio ? 'km' : ($ex->weight_unit ?? 'kg'),
                        'exercise_type' => $isCardio ? 'cardio' : 'strength',
                        'cardio_mode' => $ex->cardio_mode ?? ($isCardio ? 'standard' : null),
                        'previous_best' => $this->getExercisePreviousBestText($ex->exercise_name, $isCardio),
                        'previous_best_short' => '-',
                        'previous_max_weight' => 0,
                        'previous_max_reps' => 0,
                        'notes' => '',
                        'sets' => $sets,
                    ];
                }
            } else {
                // Regular fresh initialization
                if ($task && $task->subTasks && $task->subTasks->count() > 0) {
                    foreach ($task->subTasks as $st) {
                        if (trim($st->title)) {
                            $this->addExerciseToSession($st->title, $this->detectMuscleGroup($st->title));
                        }
                    }
                } else {
                    $exercises = $this->inferExercisesFromTitle($task ? $task->title : '');
                    foreach ($exercises as $ex) {
                        $this->addExerciseToSession($ex['name'], $ex['muscle']);
                    }
                }
            }
        } else {
            $this->activeWorkoutTaskTitle = '';
            $this->activeWorkoutTitle = 'Sesi Latihan Mandiri';
            $this->addExerciseToSession('Bench Press (Barbell)', 'Dada');
        }

        $this->showWorkoutModal = true;
    }

    public function closeWorkoutModal()
    {
        $this->showWorkoutModal = false;
        $this->workoutExercises = [];
        $this->activeWorkoutTaskId = null;
        $this->swapTargetExerciseIndex = null;
        $this->dispatch('toast', message: 'Sesi latihan ditutup. Data sesi tersimpan.', type: 'info');
    }

    public function addExerciseToSession($name, $muscle = null)
    {
        $name = trim($name);
        if (empty($name)) return;

        $muscle = $muscle ?: $this->detectMuscleGroup($name);
        $isCardio = $this->isCardioExercise($name, $muscle);
        $unit = $isCardio ? 'km' : $this->detectExerciseUnit($name);

        $prevBestText = $this->getExercisePreviousBestText($name, $isCardio);

        if ($isCardio) {
            $lower = strtolower($name);
            $isIntervalNamed = str_contains($lower, 'interval') || str_contains($lower, 'sprint') || str_contains($lower, 'hiit');

            if ($isIntervalNamed) {
                $sets = [
                    [
                        'set_number' => 1,
                        'weight_kg' => 0.4,
                        'reps' => 2,
                        'distance_km' => 0.4,
                        'duration_seconds' => 90,
                        'duration_formatted' => '01:30',
                        'pace' => '03:45',
                        'set_type' => 'interval',
                        'is_completed' => false,
                        'is_pr' => false,
                    ],
                ];
            } else {
                $sets = [
                    [
                        'set_number' => 1,
                        'weight_kg' => 5.0,
                        'reps' => 30,
                        'distance_km' => 5.0,
                        'duration_seconds' => 1800,
                        'duration_formatted' => '30:00',
                        'pace' => '06:00',
                        'set_type' => 'normal',
                        'is_completed' => false,
                        'is_pr' => false,
                    ],
                ];
            }

            $this->workoutExercises[] = [
                'id' => uniqid('ex_'),
                'name' => $name,
                'muscle' => $muscle ?: 'Kardio',
                'unit' => 'km',
                'exercise_type' => 'cardio',
                'cardio_mode' => 'standard',
                'previous_best' => $prevBestText,
                'previous_best_short' => '-',
                'previous_max_weight' => 0,
                'previous_max_reps' => 0,
                'notes' => '',
                'sets' => $sets,
            ];
        } else {
            $prevBest = WorkoutSet::whereHas('exercise', function($q) use ($name) {
                $q->where('exercise_name', $name)
                  ->whereHas('session', function($sq) {
                      $sq->where('user_id', auth()->id())->whereNotNull('completed_at');
                  });
            })->where('is_completed', true)->orderByDesc('weight_kg')->orderByDesc('reps')->first();

            $prevBestShort = $prevBest ? "{$prevBest->weight_kg} × {$prevBest->reps}" : "-";
            $defaultWeight = $prevBest ? (float)$prevBest->weight_kg : ($unit === 'bw' ? 0.0 : 20.0);
            $defaultReps = $prevBest ? (int)$prevBest->reps : ($unit === 'bw' ? 12 : 10);

            $this->workoutExercises[] = [
                'id' => uniqid('ex_'),
                'name' => $name,
                'muscle' => $muscle,
                'unit' => $unit,
                'exercise_type' => 'strength',
                'cardio_mode' => 'standard',
                'previous_best' => $prevBestText,
                'previous_best_short' => $prevBestShort,
                'previous_max_weight' => $prevBest ? (float)$prevBest->weight_kg : 0,
                'previous_max_reps' => $prevBest ? (int)$prevBest->reps : 0,
                'notes' => '',
                'sets' => [
                    [
                        'set_number' => 1,
                        'weight_kg' => ($unit === 'bw' ? 0 : ($defaultWeight > 20 ? max(20, $defaultWeight - 10) : $defaultWeight)),
                        'reps' => $defaultReps,
                        'set_type' => 'warmup',
                        'is_completed' => false,
                        'is_pr' => false,
                    ],
                    [
                        'set_number' => 2,
                        'weight_kg' => $defaultWeight,
                        'reps' => $defaultReps,
                        'set_type' => 'normal',
                        'is_completed' => false,
                        'is_pr' => false,
                    ],
                    [
                        'set_number' => 3,
                        'weight_kg' => $defaultWeight,
                        'reps' => $defaultReps,
                        'set_type' => 'normal',
                        'is_completed' => false,
                        'is_pr' => false,
                    ],
                ]
            ];
        }

        $this->newExerciseName = '';
        $this->showAddExerciseModal = false;
    }

    public function removeExerciseFromSession($index)
    {
        if (isset($this->workoutExercises[$index])) {
            unset($this->workoutExercises[$index]);
            $this->workoutExercises = array_values($this->workoutExercises);
        }
    }

    public function addSetToExercise($exerciseIndex)
    {
        if (!isset($this->workoutExercises[$exerciseIndex])) return;
        $sets = $this->workoutExercises[$exerciseIndex]['sets'];
        $lastSet = end($sets);
        $nextNum = count($sets) + 1;
        $isCardio = (($this->workoutExercises[$exerciseIndex]['exercise_type'] ?? '') === 'cardio' || 
                     ($this->workoutExercises[$exerciseIndex]['unit'] ?? '') === 'km' || 
                     $this->isCardioExercise($this->workoutExercises[$exerciseIndex]['name'] ?? '', $this->workoutExercises[$exerciseIndex]['muscle'] ?? null));

        if ($isCardio) {
            $defaultDist = $lastSet ? (float)($lastSet['distance_km'] ?? 5.0) : 5.0;
            $defaultDurSec = $lastSet ? (int)($lastSet['duration_seconds'] ?? 1800) : 1800;
            $defaultDurFmt = $lastSet ? ($lastSet['duration_formatted'] ?? '30:00') : '30:00';
            $defaultPace = $lastSet ? ($lastSet['pace'] ?? '06:00') : $this->calculatePaceString($defaultDist, $defaultDurSec);

            $this->workoutExercises[$exerciseIndex]['sets'][] = [
                'set_number' => $nextNum,
                'weight_kg' => (float)$defaultDist,
                'reps' => (int)round($defaultDurSec / 60),
                'distance_km' => (float)$defaultDist,
                'duration_seconds' => (int)$defaultDurSec,
                'duration_formatted' => $defaultDurFmt,
                'pace' => $defaultPace,
                'set_type' => 'normal',
                'is_completed' => false,
                'is_pr' => false,
            ];
            return;
        }

        $unit = $this->workoutExercises[$exerciseIndex]['unit'] ?? 'kg';
        $defaultWeight = $unit === 'bw' ? 0.0 : 20.0;

        $this->workoutExercises[$exerciseIndex]['sets'][] = [
            'set_number' => $nextNum,
            'weight_kg' => $lastSet ? $lastSet['weight_kg'] : $defaultWeight,
            'reps' => $lastSet ? $lastSet['reps'] : 10,
            'set_type' => 'normal',
            'is_completed' => false,
            'is_pr' => false,
        ];
    }

    public function addIntervalSetToExercise($exerciseIndex)
    {
        if (!isset($this->workoutExercises[$exerciseIndex])) return;
        $sets = $this->workoutExercises[$exerciseIndex]['sets'];
        $nextNum = count($sets) + 1;

        $this->workoutExercises[$exerciseIndex]['sets'][] = [
            'set_number' => $nextNum,
            'weight_kg' => 0.4,
            'reps' => 2,
            'distance_km' => 0.4,
            'duration_seconds' => 90,
            'duration_formatted' => '01:30',
            'pace' => '03:45',
            'set_type' => 'interval',
            'is_completed' => false,
            'is_pr' => false,
        ];
    }

    public function removeSetFromExercise($exerciseIndex, $setIndex)
    {
        if (!isset($this->workoutExercises[$exerciseIndex]['sets'][$setIndex])) return;
        unset($this->workoutExercises[$exerciseIndex]['sets'][$setIndex]);
        $this->workoutExercises[$exerciseIndex]['sets'] = array_values($this->workoutExercises[$exerciseIndex]['sets']);
        foreach ($this->workoutExercises[$exerciseIndex]['sets'] as $k => $s) {
            $this->workoutExercises[$exerciseIndex]['sets'][$k]['set_number'] = $k + 1;
        }
    }

    public function toggleSetCompleted($exerciseIndex, $setIndex)
    {
        if (!isset($this->workoutExercises[$exerciseIndex]['sets'][$setIndex])) return;
        
        $current = !empty($this->workoutExercises[$exerciseIndex]['sets'][$setIndex]['is_completed']);
        $newVal = !$current;
        $this->workoutExercises[$exerciseIndex]['sets'][$setIndex]['is_completed'] = $newVal;

        if ($newVal) {
            $isCardio = (($this->workoutExercises[$exerciseIndex]['exercise_type'] ?? '') === 'cardio' || 
                         ($this->workoutExercises[$exerciseIndex]['unit'] ?? '') === 'km' || 
                         $this->isCardioExercise($this->workoutExercises[$exerciseIndex]['name'] ?? '', $this->workoutExercises[$exerciseIndex]['muscle'] ?? null));
            
            if ($isCardio) {
                $this->recalculateCardioPace($exerciseIndex, $setIndex);
                $dist = (float)($this->workoutExercises[$exerciseIndex]['sets'][$setIndex]['distance_km'] ?? 0);
                $pace = $this->workoutExercises[$exerciseIndex]['sets'][$setIndex]['pace'] ?? '';
                $setNum = $this->workoutExercises[$exerciseIndex]['sets'][$setIndex]['set_number'] ?? ($setIndex + 1);
                $this->dispatch('toast', message: "Set {$setNum} selesai: {$dist} km (Pace {$pace}/km)", type: 'success');
                $this->dispatch('start-rest-timer', seconds: 60);
                return;
            }

            $weight = (float)($this->workoutExercises[$exerciseIndex]['sets'][$setIndex]['weight_kg'] ?? 0);
            $reps = (int)($this->workoutExercises[$exerciseIndex]['sets'][$setIndex]['reps'] ?? 0);
            $prevWeight = (float)($this->workoutExercises[$exerciseIndex]['previous_max_weight'] ?? 0);
            $prevReps = (int)($this->workoutExercises[$exerciseIndex]['previous_max_reps'] ?? 0);
            $unit = $this->workoutExercises[$exerciseIndex]['unit'] ?? 'kg';

            if ($prevWeight > 0 && ($weight > $prevWeight || ($weight == $prevWeight && $reps > $prevReps))) {
                $this->workoutExercises[$exerciseIndex]['sets'][$setIndex]['is_pr'] = true;
                $unitLabel = $unit === 'bw' ? ($weight > 0 ? "BW + {$weight} kg" : "BW") : "{$weight} {$unit}";
                $this->dispatch('toast', message: "Rekor Baru (PR)! {$unitLabel} × {$reps} reps!", type: 'success');
                $this->dispatch('play-sound', type: 'level_up');
            }

            $this->dispatch('start-rest-timer', seconds: 90);
        }
    }

    public function finishWorkout($elapsedSeconds = 0)
    {
        $totalVolume = 0;
        $totalDistance = 0;
        $totalCompletedSets = 0;
        $totalPRs = 0;
        $hasCardio = false;
        $hasStrength = false;

        foreach ($this->workoutExercises as $ex) {
            $isCardio = (($ex['exercise_type'] ?? '') === 'cardio' || 
                         ($ex['unit'] ?? '') === 'km' || 
                         $this->isCardioExercise($ex['name'] ?? '', $ex['muscle'] ?? null));
            $unit = $ex['unit'] ?? ($isCardio ? 'km' : 'kg');
            if ($isCardio) {
                $hasCardio = true;
            } else {
                $hasStrength = true;
            }
            foreach ($ex['sets'] as $s) {
                if (!empty($s['is_completed'])) {
                    $totalCompletedSets++;
                    if ($isCardio) {
                        $totalDistance += (float)($s['distance_km'] ?? $s['weight_kg'] ?? 0);
                    } else {
                        $w = (float)($s['weight_kg'] ?? 0);
                        $wKg = ($unit === 'lbs') ? ($w * 0.453592) : $w;
                        $totalVolume += ($wKg * (int)($s['reps'] ?? 0));
                    }
                    if (!empty($s['is_pr'])) $totalPRs++;
                }
            }
        }

        if ($totalCompletedSets === 0) {
            $this->dispatch('toast', message: 'Selesaikan minimal 1 set latihan sebelum mengakhiri sesi!', type: 'warning');
            return;
        }

        $totalVolume = round($totalVolume, 1);
        $totalDistance = round($totalDistance, 2);
        $earnedXp = (int) min(300, max(50, 50 + ($totalVolume / 40) + ($totalDistance * 15) + ($elapsedSeconds / 60)));

        $session = null;
        if ($this->activeWorkoutTaskId) {
            $session = WorkoutSession::where('user_id', auth()->id())
                ->where('task_id', $this->activeWorkoutTaskId)
                ->first();
        }

        if (!$session) {
            $session = new WorkoutSession();
            $session->user_id = auth()->id();
            $session->task_id = $this->activeWorkoutTaskId;
        }

        $session->title = $this->activeWorkoutTitle ?: 'Sesi Latihan';
        $session->notes = $this->activeWorkoutNotes;
        $session->duration_seconds = (int)$elapsedSeconds;
        $session->total_volume_kg = $totalVolume;
        $session->total_distance_km = $totalDistance;
        $session->xp_earned = $earnedXp;
        $session->completed_at = now();
        $session->save();

        $session->exercises()->delete();

        foreach ($this->workoutExercises as $order => $ex) {
            $isCardio = (($ex['exercise_type'] ?? '') === 'cardio' || 
                         ($ex['unit'] ?? '') === 'km' || 
                         $this->isCardioExercise($ex['name'] ?? '', $ex['muscle'] ?? null));
            $createdEx = WorkoutExercise::create([
                'workout_session_id' => $session->id,
                'exercise_name' => $ex['name'],
                'muscle_group' => $ex['muscle'] ?? ($isCardio ? 'Kardio' : 'Full Body'),
                'weight_unit' => $isCardio ? 'km' : ($ex['unit'] ?? 'kg'),
                'cardio_mode' => $ex['cardio_mode'] ?? 'standard',
                'order' => $order + 1,
            ]);

            foreach ($ex['sets'] as $sIndex => $s) {
                $dist = (float)($s['distance_km'] ?? ($isCardio ? ($s['weight_kg'] ?? 0) : 0));
                $dur = (int)($s['duration_seconds'] ?? 0);
                WorkoutSet::create([
                    'workout_exercise_id' => $createdEx->id,
                    'set_number' => $s['set_number'] ?? ($sIndex + 1),
                    'weight_kg' => $isCardio ? $dist : (float)($s['weight_kg'] ?? 0),
                    'reps' => $isCardio ? ((int)round($dur / 60)) : (int)($s['reps'] ?? 0),
                    'distance_km' => $isCardio ? $dist : null,
                    'duration_seconds' => $isCardio ? $dur : null,
                    'pace' => $s['pace'] ?? null,
                    'set_type' => $s['set_type'] ?? 'normal',
                    'is_completed' => !empty($s['is_completed']),
                    'is_pr' => !empty($s['is_pr']),
                ]);
            }
        }

        $user = auth()->user();
        if ($this->activeWorkoutTaskId) {
            $task = $user->tasks()->find($this->activeWorkoutTaskId);
            if ($task && !$task->is_completed) {
                $task->is_completed_overdue = $task->isOverdue();
                $task->is_completed = true;
                $task->save();
                if ($task->goal_id && $goal = $task->goal) {
                    $goal->increment('current_value');
                    if ($goal->current_value >= $goal->target_value) {
                        $goal->update(['status' => 'completed']);
                    }
                }
                if (!$task->is_completed_overdue) {
                    $this->updateStreak(true, $task);
                }
                \App\Services\TaskXpService::evaluateTaskCompletion($task, $user);
                $this->generateNextRecurrence($task);
            }
        } else {
            $awarded = \App\Services\GamificationService::awardWorkoutXp($user, $earnedXp);
            $user->daily_xp_earned += $awarded;
            $user->save();
        }

        \App\Services\RpgStatsService::calculateAllStats($user);

        $durMinutes = (int) floor($elapsedSeconds / 60);
        $durSecs = $elapsedSeconds % 60;

        $this->workoutSummaryData = [
            'title' => $session->title,
            'duration_formatted' => sprintf('%02d:%02d', $durMinutes, $durSecs),
            'total_volume_kg' => number_format($totalVolume, 0, ',', '.'),
            'total_volume_raw' => $totalVolume,
            'total_distance_km' => number_format($totalDistance, 2, ',', '.'),
            'total_distance_raw' => $totalDistance,
            'total_sets' => $totalCompletedSets,
            'pr_count' => $totalPRs,
            'xp_earned' => $earnedXp,
            'has_cardio' => $hasCardio,
            'has_strength' => $hasStrength,
            'task_title' => $this->activeWorkoutTaskTitle,
        ];

        $this->showWorkoutModal = false;
        $this->showWorkoutSummaryModal = true;
        $this->loadTasks();
        $this->dispatch('play-sound', type: 'task_complete');
        $this->dispatch('toast', message: "Sesi latihan '{$session->title}' berhasil diselesaikan! (+{$earnedXp} EXP)", type: 'success');
    }

    public function closeWorkoutSummaryModal()
    {
        $this->showWorkoutSummaryModal = false;
        $this->workoutSummaryData = null;
    }
}; ?>

<div class="relative pb-24 [contain:layout]"
     x-data="{}"
     x-init="
        try {
            const params = new URLSearchParams(window.location.search);
            if (params.get('action') === 'new' || params.get('new') === '1') {
                $wire.openAddTaskModal();
                setTimeout(() => { const el = document.querySelector('[x-ref=titleInput]'); if (el) el.focus(); }, 150);
            }
        } catch(e) {}
     "
     @open-new-task-modal.window="$wire.openAddTaskModal(); setTimeout(() => { const el = document.querySelector('[x-ref=titleInput]'); if (el) el.focus(); }, 120)"
     @focus-quick-search.window="const el = document.querySelector('[x-ref=quickAddInput]'); if (el) { el.focus(); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }">
    <style>
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>

    <!-- Header Controls (Mobile First Optimized with Next/Prev Stepper) -->
    <div class="flex items-center justify-between gap-3 mb-4">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <h2 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-gray-100">
                    {{ __('Tasks') }}
                </h2>
                <!-- Prev / Next Day Stepper -->
                <div class="flex items-center bg-gray-100 dark:bg-gray-800 rounded-xl p-0.5 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <button wire:click="previousDay" class="p-1 rounded-lg text-gray-500 hover:text-atoma-blue dark:text-gray-400 dark:hover:text-atoma-cyan hover:bg-white dark:hover:bg-gray-700 transition-colors cursor-pointer" title="{{ __('Hari Sebelumnya') }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                    </button>
                    <button wire:click="selectDate('{{ \Carbon\Carbon::today()->toDateString() }}')" class="px-2 py-0.5 text-[11px] font-bold text-gray-700 dark:text-gray-200 hover:text-atoma-blue dark:hover:text-atoma-cyan transition-colors cursor-pointer" title="{{ __('Kembali ke Hari Ini') }}">
                        {{ \Carbon\Carbon::parse($selectedDate)->isToday() ? __('Hari Ini') : \Carbon\Carbon::parse($selectedDate)->format('d M') }}
                    </button>
                    <button wire:click="nextDay" class="p-1 rounded-lg text-gray-500 hover:text-atoma-blue dark:text-gray-400 dark:hover:text-atoma-cyan hover:bg-white dark:hover:bg-gray-700 transition-colors cursor-pointer" title="{{ __('Hari Berikutnya') }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($selectedDate)->format('l, d F Y') }}</p>
        </div>
        
        <!-- Actions Group -->
        <div class="flex items-center gap-2 shrink-0">
            <!-- AI Template Button -->
            <button type="button" 
                    wire:click="openAiTemplateModal" 
                    class="px-3 py-2 bg-atoma-ice hover:bg-atoma-aqua/40 dark:bg-atoma-blue/15 dark:hover:bg-atoma-blue/25 text-atoma-blue dark:text-atoma-cyan text-xs sm:text-sm font-bold rounded-xl border border-atoma-aqua/60 dark:border-atoma-cyan/30 shadow-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                <x-icon.sparkles class="w-4 h-4 text-atoma-blue dark:text-atoma-cyan" />
                <span class="hidden sm:inline">{{ __('Template AI') }}</span>
            </button>

            <!-- Manual Add Task Button (Hidden on mobile, mobile uses bottom center navbar +) -->
            <button x-show="!$store.taskConfig || $store.taskConfig.inputMode !== 'nlp'" 
                    @click="$wire.openAddTaskModal(); setTimeout(() => $refs.titleInput.focus(), 50)" 
                    class="hidden sm:inline-flex px-3 sm:px-4 py-2 bg-atoma-blue hover:bg-[#3455dc] text-white text-xs sm:text-sm font-bold rounded-xl shadow-sm shadow-atoma-blue/30 transition-all items-center cursor-pointer shrink-0 active:scale-95">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>{{ __('Task Baru') }}</span>
            </button>
        </div>
    </div>

    <!-- FITUR 5: Quick Add Bar & Voice Command 🎙️ -->
    <div x-data="{ 
        listening: false,
        recogInstance: null,
        init() {
            this.listening = false;
            window.addEventListener('livewire:navigating', () => {
                this.stopVoice();
            });
        },
        stopVoice() {
            this.listening = false;
            if (this.recogInstance) {
                try { this.recogInstance.abort(); } catch(e){}
                this.recogInstance = null;
            }
            if (window.__atomaSpeechRecognition) {
                try { window.__atomaSpeechRecognition.abort(); } catch(e){}
                window.__atomaSpeechRecognition = null;
            }
        },
        async requestMicPermission() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                return true;
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                stream.getTracks().forEach(track => track.stop());
                return true;
            } catch (err) {
                console.error('Microphone permission error:', err);
                return false;
            }
        },
        async startVoice() {
            if (this.listening) {
                this.stopVoice();
                return;
            }
            this.stopVoice();

            const Speech = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!Speech) {
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { title: 'Browser ini belum mendukung Voice Input. Coba gunakan Google Chrome atau Microsoft Edge.', icon: 'error' } }));
                return;
            }

            const hasPermission = await this.requestMicPermission();
            if (!hasPermission) {
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { title: '⚠️ Izin mikrofon ditolak! Klik ikon gembok 🔒 di address bar browser untuk mengizinkan.', icon: 'warning' } }));
                return;
            }

            try {
                const recog = new Speech();
                recog.lang = 'id-ID';
                recog.continuous = true;
                recog.interimResults = true;

                this.recogInstance = recog;
                window.__atomaSpeechRecognition = recog;

                let hasResult = false;
                this.listening = true;
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { title: '🎙️ Mendengarkan... Katakan tugasmu sekarang!', icon: 'info' } }));

                const timeoutTimer = setTimeout(() => {
                    if (this.listening && !hasResult) {
                        this.stopVoice();
                    }
                }, 8000);

                recog.onresult = (e) => {
                    let transcript = '';
                    for (let i = e.resultIndex; i < e.results.length; i++) {
                        transcript += e.results[i][0].transcript;
                    }
                    if (transcript.trim()) {
                        hasResult = true;
                        if (this.$refs.quickAddInput) {
                            this.$refs.quickAddInput.value = transcript;
                            this.$refs.quickAddInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        $wire.set('quickAddInput', transcript);

                        if (e.results[e.results.length - 1].isFinal) {
                            clearTimeout(timeoutTimer);
                            this.stopVoice();
                            window.dispatchEvent(new CustomEvent('show-toast', { detail: { title: '✅ Terdeteksi: ' + transcript, icon: 'success' } }));
                        }
                    }
                };

                recog.onerror = (err) => {
                    clearTimeout(timeoutTimer);
                    this.stopVoice();
                    console.error('Speech error details:', err.error, err);
                    
                    let errorMsg = '⚠️ Error Suara (' + err.error + '): ';
                    if (err.error === 'network') {
                        errorMsg = '💡 Tip: Pada domain .test, gunakan Dikte Mac (Tekan tombol Fn 2x saat mengetik)!';
                    } else if (err.error === 'not-allowed') {
                        errorMsg += 'Akses mikrofon ditolak oleh browser/OS.';
                    } else if (err.error === 'no-speech') {
                        errorMsg += 'Tidak ada suara yang terdeteksi. Periksa mic di System Settings Mac.';
                    } else if (err.error === 'audio-capture') {
                        errorMsg += 'Mikrofon sedang dipakai aplikasi lain (Zoom/Meet/Discord).';
                    } else {
                        errorMsg += 'Gagal memproses suara. Silakan coba lagi.';
                    }
                    
                    window.dispatchEvent(new CustomEvent('show-toast', { detail: { title: errorMsg, icon: 'warning' } }));
                };

                recog.onend = () => {
                    clearTimeout(timeoutTimer);
                    this.stopVoice();
                };

                recog.start();
            } catch (err) {
                this.stopVoice();
                console.error(err);
            }
        }
    }" x-show="!$store.taskConfig || $store.taskConfig.inputMode !== 'manual'" class="mb-6">
        <div class="flex items-center gap-2">
            <!-- Input Field -->
            <div class="relative flex-1 min-w-0">
                <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" 
                       x-ref="quickAddInput" 
                       wire:model="quickAddInput" 
                       wire:keydown.enter="parseAndSaveQuickAdd" 
                       placeholder="{{ __('Rapat bsok jam 11 malam #Kerja penting') }}" 
                       class="w-full bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 focus:border-atoma-blue dark:focus:border-atoma-blue rounded-xl py-3 pl-10 pr-4 text-sm text-gray-900 dark:text-white shadow-sm font-medium placeholder-gray-400 dark:placeholder-gray-500 transition-colors focus:ring-2 focus:ring-atoma-blue/20 outline-none">
            </div>

            <!-- Mic Button -->
            <button type="button" 
                    @click="startVoice()" 
                    :class="listening 
                        ? 'bg-red-500 text-white shadow-lg shadow-red-500/30 scale-110' 
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-atoma-ice dark:hover:bg-atoma-blue/20 hover:text-atoma-blue dark:hover:text-atoma-cyan'" 
                    class="shrink-0 w-11 h-11 flex items-center justify-center rounded-xl transition-all duration-200 cursor-pointer" 
                    :title="listening ? '{{ __('Mendengarkan...') }}' : '{{ __('Input Suara (Voice Command)') }}'">
                <template x-if="!listening">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
                </template>
                <template x-if="listening">
                    <span class="flex items-center justify-center">
                        <span class="animate-ping absolute inline-flex h-5 w-5 rounded-full bg-red-400 opacity-60"></span>
                        <svg class="w-5 h-5 relative" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
                    </span>
                </template>
            </button>

            <!-- Tambah Button -->
            <button wire:click="parseAndSaveQuickAdd" class="shrink-0 h-11 px-4 bg-atoma-blue hover:bg-[#3455dc] active:scale-95 text-white font-bold text-sm rounded-xl shadow-md shadow-atoma-blue/25 transition-all cursor-pointer flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span class="hidden sm:inline">{{ __('Tambah') }}</span>
                <span class="sm:hidden">⚡</span>
            </button>
        </div>

        <!-- Listening indicator banner -->
        <div x-show="listening" x-cloak x-transition.opacity class="mt-2 flex items-center gap-2 px-4 py-2.5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/40 rounded-xl text-xs font-bold text-red-600 dark:text-red-400">
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
            </span>
            {{ __('Mendengarkan suara kamu... Bicara sekarang dalam Bahasa Indonesia') }} 🎙️
        </div>

        <!-- Smart parsing hint -->
        <p class="mt-1.5 text-[10px] text-gray-400 dark:text-gray-500 leading-relaxed pl-1">
            💡 <strong>{{ __('Tips:') }}</strong> {{ __('Ketik natural —') }} <em>"{{ __('Rapat bsok jam 3 sore penting') }}"</em> • {{ __('Typo? No problem!') }} • {!! __('Gunakan :tag untuk kategori', ['tag' => '<code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">#Kerja</code>']) !!}
        </p>
    </div>

    <!-- FITUR 6: Pending Co-op Shared Task Invitations Banner -->
    @php
        $pendingInvs = auth()->user()->receivedSharedTaskInvitations()->with('inviter', 'task')->get();
    @endphp
    @if($pendingInvs->count() > 0)
        <div class="mb-6 bg-gradient-to-r from-atoma-blue/30 to-atoma-cyan/20 border border-atoma-blue/40 rounded-2xl p-4 shadow-lg text-white">
            <h4 class="font-bold text-sm text-atoma-ice flex items-center mb-2">
                👥 {{ __('Undangan Co-op Task Terbaru (:count)', ['count' => $pendingInvs->count()]) }}
            </h4>
            <div class="space-y-2">
                @foreach($pendingInvs as $inv)
                    <div class="flex items-center justify-between bg-black/30 rounded-xl p-3 text-xs border border-white/10">
                        <div>
                            {!! __(':name mengundangmu mengerjakan Co-op Task: :title', ['name' => '<span class="font-bold text-atoma-aqua">' . e($inv->inviter->name) . '</span>', 'title' => '<strong class="text-white">"' . e($inv->task->title) . '"</strong>']) !!}
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="acceptCoopInvitation({{ $inv->id }})" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg cursor-pointer">
                                {{ __('Terima (+25 XP)') }}
                            </button>
                            <button wire:click="declineCoopInvitation({{ $inv->id }})" class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-gray-300 font-bold rounded-lg cursor-pointer">
                                {{ __('Tolak') }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Dashboard Cards Row & Modals (Compact 4-Column Grid) -->
    <div class="mb-5" x-data="{ showCalendarModal: false, showModeModal: false }">
        <div class="grid grid-cols-4 gap-1.5 sm:gap-3">
            <!-- Calendar Card -->
            <button @click="showCalendarModal = true" class="flex flex-col items-center justify-center bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 py-2 sm:py-2.5 px-1 hover:bg-atoma-ice/30 dark:hover:bg-gray-750 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 transition-all transform hover:scale-[1.02] cursor-pointer text-atoma-blue dark:text-atoma-cyan min-h-[56px] sm:min-h-[68px]">
                <span class="text-[8px] sm:text-[10px] font-bold text-gray-400 uppercase mb-0.5 truncate max-w-full">{{ \Carbon\Carbon::parse($selectedDate)->format('D') }}</span>
                <span class="text-sm sm:text-xl font-black leading-tight">{{ \Carbon\Carbon::parse($selectedDate)->format('d') }}</span>
            </button>
            
            <!-- Mode Card -->
            <button @click="showModeModal = true" class="flex flex-col items-center justify-center bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 py-2 sm:py-2.5 px-1 hover:bg-atoma-ice/30 dark:hover:bg-gray-750 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 transition-all transform hover:scale-[1.02] cursor-pointer text-atoma-blue dark:text-atoma-cyan min-h-[56px] sm:min-h-[68px]">
                <div class="h-5 w-5 sm:h-6 sm:w-6 mb-0.5 flex items-center justify-center">
                    <img x-show="$wire.mode === 'zen'" src="{{ asset('images/icons/feature_zen.svg') }}" alt="Zen" class="w-4.5 h-4.5 sm:w-6 sm:h-6 object-contain">
                    <img x-show="$wire.mode !== 'zen'" src="{{ asset('images/icons/feature_system.svg') }}" alt="System" class="w-4.5 h-4.5 sm:w-6 sm:h-6 object-contain">
                </div>
                <span class="text-[8px] sm:text-[10px] font-extrabold uppercase tracking-tight sm:tracking-wider text-gray-500 truncate max-w-full" x-text="$wire.mode === 'sistematis' ? 'SYSTEM' : 'ZEN'"></span>
            </button>

            <!-- Insights Card -->
            <a href="{{ route('insights') }}" wire:navigate class="flex flex-col items-center justify-center bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 py-2 sm:py-2.5 px-1 hover:bg-atoma-ice/30 dark:hover:bg-gray-750 hover:border-atoma-cyan/40 transition-all transform hover:scale-[1.02] cursor-pointer text-atoma-cyan min-h-[56px] sm:min-h-[68px]">
                <div class="h-5 w-5 sm:h-6 sm:w-6 mb-0.5 flex items-center justify-center">
                    <img src="{{ asset('images/icons/feature_insights.svg') }}" alt="Insights" class="w-4.5 h-4.5 sm:w-6 sm:h-6 object-contain">
                </div>
                <span class="text-[8px] sm:text-[10px] font-extrabold uppercase tracking-tight sm:tracking-wider text-gray-500 truncate max-w-full">INSIGHTS</span>
            </a>

            <!-- Milestone Card (Renamed from Skills) -->
            <a href="{{ route('skills.index') }}" wire:navigate class="flex flex-col items-center justify-center bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 py-2 sm:py-2.5 px-1 hover:bg-atoma-ice/30 dark:hover:bg-gray-750 hover:border-atoma-blue/40 transition-all transform hover:scale-[1.02] cursor-pointer text-atoma-blue dark:text-atoma-cyan min-h-[56px] sm:min-h-[68px]">
                <div class="h-5 w-5 sm:h-6 sm:w-6 mb-0.5 flex items-center justify-center">
                    <img src="{{ asset('images/icons/feature_milestone.svg') }}" alt="Milestone" class="w-full h-full object-contain">
                </div>
                <span class="text-[8px] sm:text-[10px] font-extrabold uppercase tracking-tight sm:tracking-wider text-gray-500 truncate max-w-full">MILESTONE</span>
            </a>
        </div>

        <!-- Full Calendar Modal -->
        <div x-show="showCalendarModal" x-cloak style="display: none;" class="relative z-50">
            <div x-show="showCalendarModal" x-cloak x-transition.opacity class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
            <div x-show="showCalendarModal" x-cloak x-transition class="fixed inset-0 flex items-center justify-center p-4">
                <div @click.away="showCalendarModal = false" class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-xl w-full max-w-md border border-gray-100 dark:border-gray-700">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ \Carbon\Carbon::parse($selectedDate)->format('F Y') }}
                        </h3>
                        <button @click="showCalendarModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-7 gap-1 text-center mb-2">
                        @foreach(['Su','Mo','Tu','We','Th','Fr','Sa'] as $dayName)
                            <div class="text-xs font-semibold text-gray-400 dark:text-gray-500">{{ $dayName }}</div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center">
                        @php
                            $startOfMonth = \Carbon\Carbon::parse($selectedDate)->startOfMonth();
                            $endOfMonth = \Carbon\Carbon::parse($selectedDate)->endOfMonth();
                            $startDayOfWeek = $startOfMonth->dayOfWeek;
                            $daysInMonth = $endOfMonth->daysInMonth;
                        @endphp
                        
                        @for($i = 0; $i < $startDayOfWeek; $i++)
                            <div></div>
                        @endfor
                        
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $loopDate = $startOfMonth->copy()->addDays($d - 1)->toDateString();
                                $isToday = $loopDate === \Carbon\Carbon::today()->toDateString();
                                $isSelected = $loopDate === $selectedDate;
                            @endphp
                            <button wire:click="selectDate('{{ $loopDate }}'); showCalendarModal = false" 
                                    class="h-8 w-8 rounded-full mx-auto flex items-center justify-center text-sm transition-colors 
                                    {{ $isSelected ? 'bg-indigo-600 text-white font-bold shadow-md' : ($isToday ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700') }}">
                                {{ $d }}
                            </button>
                        @endfor
                    </div>
                    
                    <div class="flex justify-between mt-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <button wire:click="selectDate('{{ \Carbon\Carbon::parse($selectedDate)->subMonth()->toDateString() }}')" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 cursor-pointer">
                            &larr; {{ __('Prev') }}
                        </button>
                        <button wire:click="selectDate('{{ \Carbon\Carbon::today()->toDateString() }}'); showCalendarModal = false" class="text-sm font-bold text-gray-700 dark:text-gray-300 hover:text-gray-900 cursor-pointer">
                            {{ __('Today') }}
                        </button>
                        <button wire:click="selectDate('{{ \Carbon\Carbon::parse($selectedDate)->addMonth()->toDateString() }}')" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 cursor-pointer">
                            {{ __('Next') }} &rarr;
                        </button>
                    </div>

                    <!-- Task Distribution Graphic Chart -->
                    <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                {{ __('Grafik Jumlah Task') }}
                            </h4>
                            
                            <div class="inline-flex p-0.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-[10px] font-bold">
                                <button type="button" wire:click="setChartMode('weekly')" class="px-2.5 py-1 rounded-md transition-all cursor-pointer {{ $chartMode === 'weekly' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ __('Mingguan') }}
                                </button>
                                <button type="button" wire:click="setChartMode('monthly')" class="px-2.5 py-1 rounded-md transition-all cursor-pointer {{ $chartMode === 'monthly' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ __('Bulanan') }}
                                </button>
                            </div>
                        </div>

                        @php
                            $dist = $this->taskDistribution;
                            $counts = array_column($dist, 'count');
                            $maxCount = count($counts) > 0 ? max(1, max($counts)) : 1;
                        @endphp

                        <div class="flex items-end space-x-1.5 h-28 pt-4 px-1 overflow-x-auto custom-scrollbar">
                            @foreach($dist as $item)
                                @php
                                    $heightPercent = $item['count'] > 0 ? max(18, round(($item['count'] / $maxCount) * 100)) : 8;
                                    $isSelected = $item['date'] === $selectedDate;
                                @endphp
                                <button type="button" 
                                    wire:click="selectDate('{{ $item['date'] }}'); showCalendarModal = false"
                                    title="{{ $item['date'] }}: {{ $item['count'] }} tasks"
                                    class="flex-1 flex flex-col items-center group relative min-w-[22px] focus:outline-none">
                                    
                                    <div class="text-[9px] font-black {{ $item['count'] > 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-300 dark:text-gray-600' }} mb-1 transition-opacity opacity-80 group-hover:opacity-100">
                                        {{ $item['count'] }}
                                    </div>

                                    <div class="w-full rounded-t-md transition-all duration-300 {{ $isSelected ? 'bg-indigo-600 shadow-md ring-2 ring-indigo-400' : ($item['count'] > 0 ? 'bg-indigo-400/80 dark:bg-indigo-500/80 group-hover:bg-indigo-500' : 'bg-gray-200 dark:bg-gray-700') }}" 
                                         style="height: {{ $heightPercent }}%;"></div>
                                    
                                    <div class="text-[9px] font-bold text-gray-500 dark:text-gray-400 mt-1 uppercase">
                                        {{ $item['day_number'] }}
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mode Selection Modal -->
        <div x-show="showModeModal" x-cloak style="display: none;" class="relative z-50">
            <div x-show="showModeModal" x-cloak x-transition.opacity class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
            <div x-show="showModeModal" x-cloak x-transition class="fixed inset-0 flex items-center justify-center p-4">
                <div @click.away="showModeModal = false" class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-xl w-full max-w-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex justify-between items-center mb-5">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ __('Pilih Mode Task') }}
                        </h3>
                        <button @click="showModeModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <div class="space-y-3">
                        <!-- Zen Mode Option -->
                        <button 
                            @click="$wire.set('mode', 'zen').then(() => $wire.loadTasks()); showModeModal = false"
                            class="w-full text-left p-4 rounded-xl border-2 transition-all cursor-pointer {{ $mode === 'zen' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 ring-1 ring-indigo-500/30' : 'border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600 hover:bg-gray-50 dark:hover:bg-gray-700/50' }}">
                            <div class="flex items-center mb-2">
                                <div class="w-8 h-8 mr-3 shrink-0 flex items-center justify-center p-1 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                                    <img src="{{ asset('images/icons/feature_zen.svg') }}" alt="Zen" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900 dark:text-white">Zen Mode</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Simpel & tanpa gangguan') }}</p>
                                </div>
                                @if($mode === 'zen')
                                    <span class="ml-auto">
                                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 ml-11">{{ __('Cukup tulis judul task dan selesai. Cocok untuk yang ingin fokus tanpa overthinking.') }}</p>
                        </button>

                        <!-- Sistematis Mode Option -->
                        <button 
                            @click="$wire.set('mode', 'sistematis').then(() => $wire.loadTasks()); showModeModal = false"
                            class="w-full text-left p-4 rounded-xl border-2 transition-all cursor-pointer {{ $mode === 'sistematis' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 ring-1 ring-indigo-500/30' : 'border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600 hover:bg-gray-50 dark:hover:bg-gray-700/50' }}">
                            <div class="flex items-center mb-2">
                                <div class="w-8 h-8 mr-3 shrink-0 flex items-center justify-center p-1 rounded-xl bg-blue-500/10 border border-blue-500/20">
                                    <img src="{{ asset('images/icons/feature_system.svg') }}" alt="System" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900 dark:text-white">Sistematis Mode</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Detail & terstruktur') }}</p>
                                </div>
                                @if($mode === 'sistematis')
                                    <span class="ml-auto">
                                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 ml-9">{{ __('Tambahkan deskripsi, prioritas, kategori, deadline, estimasi waktu, dan recurring. Cocok untuk planning detail.') }}</p>
                        </button>
                    </div>

                    <p class="mt-4 text-center text-[10px] text-gray-400 dark:text-gray-500">
                        {!! __('Mode aktif: :mode — Task yang ditampilkan berubah sesuai mode.', ['mode' => '<span class="font-bold text-indigo-600 dark:text-indigo-400 uppercase">' . e($mode) . '</span>']) !!}
                    </p>
                </div>
            </div>
        </div>
    </div>

        <!-- Advanced Search, Filter & Sorting Toolbar Area (Toggleable to keep UI clean & serene) -->
        <div x-data="{ showFilterBar: {{ ($search || $filterPriority || $filterCategory || $filterStatus !== 'all' || $filterTimeBlock !== 'all' || $sortBy !== 'default') ? 'true' : 'false' }} }" class="mb-4">
            
            <!-- Compact Toolbar Header with Toggle Button & Layout Switcher -->
            <div class="flex items-center justify-between gap-2 mb-2.5">
                <!-- Left: Title or Active Filter Indicator -->
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ __('Jadwal Hari Ini') }}
                    </span>
                    @if($search || $filterPriority || $filterCategory || $filterStatus !== 'all' || $filterTimeBlock !== 'all' || $sortBy !== 'default')
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-teal-50 dark:bg-teal-950/60 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-teal-500 animate-pulse"></span>
                            {{ __('Filter Aktif') }}
                        </span>
                    @endif
                </div>

                <!-- Right Controls: Toggle Filter & Layout Switcher -->
                <div class="flex items-center gap-2 shrink-0">
                    <!-- Toggle Button for Search & Sort -->
                    <button type="button" 
                            @click="showFilterBar = !showFilterBar" 
                            class="flex items-center gap-1.5 py-1.5 px-3 rounded-xl text-xs font-bold transition-all cursor-pointer select-none border shadow-xs"
                            :class="showFilterBar 
                                ? 'bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300 border-teal-300 dark:border-teal-700 shadow-xs' 
                                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:border-teal-400 dark:hover:border-teal-500 hover:bg-gray-50 dark:hover:bg-gray-750'">
                        <template x-if="showFilterBar">
                            <svg class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </template>
                        <template x-if="!showFilterBar">
                            <svg class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </template>
                        <span x-text="showFilterBar ? '{{ __('Tutup Filter') }}' : '{{ __('Cari & Urutkan') }}'"></span>
                        @if($search || $filterPriority || $filterCategory || $filterStatus !== 'all' || $filterTimeBlock !== 'all' || $sortBy !== 'default')
                            <span class="w-2 h-2 rounded-full bg-atoma-cyan animate-pulse"></span>
                        @endif
                    </button>

                    <!-- Layout Switcher (Timeline vs Card) -->
                    <div class="bg-gray-100 dark:bg-gray-900/80 p-0.5 rounded-xl flex items-center border border-gray-200 dark:border-gray-700 shrink-0">
                        <button type="button" 
                                wire:click="setViewLayout('timeline')" 
                                class="px-2 py-1 rounded-lg text-xs transition-all cursor-pointer flex items-center gap-1.5 {{ $viewLayout === 'timeline' ? 'bg-white dark:bg-gray-800 text-atoma-blue dark:text-atoma-cyan shadow-xs font-black' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white font-medium' }}"
                                title="Tampilan Agenda Timeline">
                            <svg class="w-3.5 h-3.5 {{ $viewLayout === 'timeline' ? 'text-atoma-blue dark:text-atoma-cyan' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="hidden sm:inline">{{ __('Timeline') }}</span>
                        </button>
                        <button type="button" 
                                wire:click="setViewLayout('card')" 
                                class="px-2 py-1 rounded-lg text-xs transition-all cursor-pointer flex items-center gap-1.5 {{ $viewLayout === 'card' ? 'bg-white dark:bg-gray-800 text-atoma-blue dark:text-atoma-cyan shadow-xs font-black' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white font-medium' }}"
                                title="Tampilan Kartu Standar">
                            <svg class="w-3.5 h-3.5 {{ $viewLayout === 'card' ? 'text-atoma-blue dark:text-atoma-cyan' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            <span class="hidden sm:inline">{{ __('Kartu') }}</span>
                    </div>
                </div>
            </div>

            <!-- Smart Time-Block Filter Bar (Semua, 🌅 Pagi, ☀️ Siang, 🌆 Malam) -->
            @php
                $tbStats = $this->dailyTimeBlockStats;
            @endphp
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pt-0.5 pb-2">
                <button type="button" 
                        wire:click="$set('filterTimeBlock', 'all')" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 inline-flex items-center gap-1.5 {{ $filterTimeBlock === 'all' ? 'bg-atoma-blue text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750' }}">
                    <span>{{ __('Semua Jadwal') }}</span>
                    <span class="font-mono text-[10px] {{ $filterTimeBlock === 'all' ? 'text-white/90' : 'text-gray-400 dark:text-gray-500' }}">({{ $tbStats['total'] }})</span>
                </button>
                <button type="button" 
                        wire:click="$set('filterTimeBlock', 'morning')" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 inline-flex items-center gap-1.5 {{ $filterTimeBlock === 'morning' ? 'bg-amber-500 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-amber-50/50 dark:hover:bg-gray-750' }}">
                    <span>🌅 {{ __('Pagi') }}</span>
                    <span class="font-mono text-[10px] {{ $filterTimeBlock === 'morning' ? 'text-white/90' : 'text-amber-600 dark:text-amber-400 font-bold' }}">({{ $tbStats['morning']['done'] }}/{{ $tbStats['morning']['total'] }})</span>
                    @if($tbStats['morning']['total'] > 0 && $tbStats['morning']['done'] === $tbStats['morning']['total'])
                        <span class="text-xs font-black {{ $filterTimeBlock === 'morning' ? 'text-white' : 'text-emerald-500' }}">✓</span>
                    @endif
                </button>
                <button type="button" 
                        wire:click="$set('filterTimeBlock', 'afternoon')" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 inline-flex items-center gap-1.5 {{ $filterTimeBlock === 'afternoon' ? 'bg-blue-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-blue-50/50 dark:hover:bg-gray-750' }}">
                    <span>☀️ {{ __('Siang') }}</span>
                    <span class="font-mono text-[10px] {{ $filterTimeBlock === 'afternoon' ? 'text-white/90' : 'text-blue-600 dark:text-blue-400 font-bold' }}">({{ $tbStats['afternoon']['done'] }}/{{ $tbStats['afternoon']['total'] }})</span>
                    @if($tbStats['afternoon']['total'] > 0 && $tbStats['afternoon']['done'] === $tbStats['afternoon']['total'])
                        <span class="text-xs font-black {{ $filterTimeBlock === 'afternoon' ? 'text-white' : 'text-emerald-500' }}">✓</span>
                    @endif
                </button>
                <button type="button" 
                        wire:click="$set('filterTimeBlock', 'evening')" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 inline-flex items-center gap-1.5 {{ $filterTimeBlock === 'evening' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-indigo-50/50 dark:hover:bg-gray-750' }}">
                    <span>🌆 {{ __('Malam') }}</span>
                    <span class="font-mono text-[10px] {{ $filterTimeBlock === 'evening' ? 'text-white/90' : 'text-indigo-600 dark:text-indigo-400 font-bold' }}">({{ $tbStats['evening']['done'] }}/{{ $tbStats['evening']['total'] }})</span>
                    @if($tbStats['evening']['total'] > 0 && $tbStats['evening']['done'] === $tbStats['evening']['total'])
                        <span class="text-xs font-black {{ $filterTimeBlock === 'evening' ? 'text-white' : 'text-emerald-500' }}">✓</span>
                    @endif
                </button>
            </div>

            <!-- Collapsible Search & Filter Toolbar Box -->
            <div x-show="showFilterBar" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="bg-white dark:bg-gray-800 p-3.5 sm:p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs space-y-3 mb-2"
                 style="display: none;">
                
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                    <!-- Search Input -->
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" 
                               wire:model.live.debounce.250ms="search" 
                               placeholder="{{ __('Cari tugas, subtugas, atau kata kunci...') }}" 
                               class="w-full pl-9 pr-8 py-2 text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                        @if($search)
                            <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer">
                                ✕
                            </button>
                        @endif
                    </div>

                    <!-- Sort Control -->
                    <div class="flex items-center gap-2">
                        <!-- Custom Sort Control (Mobile Bottom Sheet + Desktop Floating Menu) -->
                        <div class="relative shrink-0" x-data="{ openSort: false }" @click.outside="openSort = false">
                            @php
                                $sortLabels = [
                                    'default' => ['icon' => '⏳', 'label' => 'Urutan Default', 'desc' => 'Tugas belum selesai & terbaru'],
                                    'time_asc' => ['icon' => '🕒', 'label' => 'Waktu: Terdekat (Awal)', 'desc' => 'Deadline paling awal'],
                                    'time_desc' => ['icon' => '🕒', 'label' => 'Waktu: Terlama (Akhir)', 'desc' => 'Deadline paling akhir'],
                                    'priority_desc' => ['icon' => '⚡', 'label' => 'Prioritas: Tertinggi', 'desc' => 'Tinggi ➔ Sedang ➔ Normal'],
                                    'category_asc' => ['icon' => '🗂️', 'label' => 'Kategori: A-Z', 'desc' => 'Urut alfabet kategori'],
                                    'xp_desc' => ['icon' => '⭐', 'label' => 'XP Reward: Terbesar', 'desc' => 'Reward XP terbanyak'],
                                    'title_asc' => ['icon' => '🔤', 'label' => 'Judul Tugas: A-Z', 'desc' => 'Urut abjad judul tugas'],
                                ];
                                $currentSort = $sortLabels[$sortBy] ?? $sortLabels['default'];
                            @endphp

                            <!-- Trigger Button -->
                            <button type="button" 
                                    @click="openSort = !openSort" 
                                    class="flex items-center gap-2 py-2 px-3 text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-teal-400 dark:hover:border-teal-500 rounded-xl text-gray-800 dark:text-gray-200 font-bold shadow-xs hover:bg-white dark:hover:bg-gray-800 transition-all cursor-pointer select-none">
                                <span class="text-sm leading-none">{{ $currentSort['icon'] }}</span>
                                <span class="truncate max-w-[125px] sm:max-w-none">{{ $currentSort['label'] }}</span>
                                <svg class="w-3.5 h-3.5 text-gray-400 ml-0.5 transition-transform duration-200 shrink-0" :class="openSort ? 'rotate-180 text-teal-500' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </button>

                            <!-- Mobile Bottom Sheet (Sleek, thumb-friendly, anti-clipping) -->
                            <div x-show="openSort" x-cloak
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 @click="openSort = false"
                                 class="sm:hidden fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-xs"
                                 style="display: none;">
                                <div class="w-full bg-white dark:bg-gray-850 rounded-t-3xl p-5 shadow-2xl border-t border-gray-100 dark:border-gray-700 max-h-[85vh] overflow-y-auto"
                                     x-transition:enter="transition ease-out duration-250"
                                     x-transition:enter-start="translate-y-full"
                                     x-transition:enter-end="translate-y-0"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="translate-y-0"
                                     x-transition:leave-end="translate-y-full"
                                     @click.stop>
                                    <div class="w-12 h-1.5 bg-gray-300 dark:bg-gray-600 rounded-full mx-auto mb-4"></div>
                                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700/80 mb-3">
                                        <div class="flex items-center gap-2 font-black text-gray-900 dark:text-gray-100 text-sm">
                                            <span>⏳</span>
                                            <span>Urutkan Berdasarkan</span>
                                        </div>
                                        <button type="button" @click="openSort = false" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-bold">✕</button>
                                    </div>
                                    <div class="space-y-1.5 pb-6">
                                        @foreach($sortLabels as $key => $item)
                                            <button type="button" 
                                                    wire:click="$set('sortBy', '{{ $key }}')" 
                                                    @click="openSort = false"
                                                    class="w-full text-left p-3 rounded-2xl flex items-center justify-between gap-3 transition-colors cursor-pointer {{ $sortBy === $key ? 'bg-teal-50 dark:bg-teal-950/50 text-teal-700 dark:text-teal-300 font-bold border border-teal-200 dark:border-teal-800' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-750' }}">
                                                <div class="flex items-center gap-3 min-w-0">
                                                    <span class="w-9 h-9 rounded-xl flex items-center justify-center text-lg shrink-0 {{ $sortBy === $key ? 'bg-teal-100 dark:bg-teal-900/60' : 'bg-gray-100 dark:bg-gray-750' }}">
                                                        {{ $item['icon'] }}
                                                    </span>
                                                    <div class="truncate">
                                                        <div class="text-sm font-bold leading-tight">{{ $item['label'] }}</div>
                                                        <div class="text-[11px] text-gray-400 dark:text-gray-500 font-normal mt-0.5 leading-tight">{{ $item['desc'] }}</div>
                                                    </div>
                                                </div>
                                                @if($sortBy === $key)
                                                    <svg class="w-5 h-5 text-teal-600 dark:text-teal-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- Desktop Floating Dropdown Menu (Clean, aligned left-0) -->
                            <div x-show="openSort" x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                 class="hidden sm:block absolute left-0 mt-1.5 w-64 bg-white/95 dark:bg-gray-800/95 backdrop-blur-md rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700/80 py-1.5 z-40 text-xs font-semibold overflow-hidden" 
                                 style="display: none;">
                                
                                <div class="px-3.5 py-1.5 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700/50 mb-1">
                                    Urutkan Berdasarkan
                                </div>

                                @foreach($sortLabels as $key => $item)
                                    <button type="button" 
                                            wire:click="$set('sortBy', '{{ $key }}')" 
                                            @click="openSort = false"
                                            class="w-full text-left px-3 py-2 flex items-center justify-between gap-2 transition-colors cursor-pointer {{ $sortBy === $key ? 'bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300 font-bold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/60' }}">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="w-6 h-6 rounded-lg flex items-center justify-center text-sm shrink-0 {{ $sortBy === $key ? 'bg-teal-100 dark:bg-teal-900/60' : 'bg-gray-100 dark:bg-gray-750' }}">
                                                {{ $item['icon'] }}
                                            </span>
                                            <div class="truncate">
                                                <div class="leading-tight">{{ $item['label'] }}</div>
                                                <div class="text-[10px] text-gray-400 dark:text-gray-500 font-normal leading-tight mt-0.5">{{ $item['desc'] }}</div>
                                            </div>
                                        </div>
                                        @if($sortBy === $key)
                                            <svg class="w-4 h-4 text-teal-600 dark:text-teal-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Filters Row -->
            <div class="flex items-center gap-2 overflow-x-auto sm:overflow-visible pb-1 text-xs hide-scrollbar">
                <span class="text-[11px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 shrink-0">Filter:</span>
                
                <!-- Custom Priority Filter -->
                <div class="relative shrink-0" x-data="{ openPriority: false }" @click.outside="openPriority = false">
                    @php
                        $priorityMap = [
                            '' => ['icon' => '🔘', 'label' => 'Semua Prioritas', 'desc' => 'Tampilkan semua tingkat prioritas'],
                            'high' => ['icon' => '🔴', 'label' => 'Tinggi Saja', 'desc' => 'Tugas mendesak & prioritas utama'],
                            'medium' => ['icon' => '🟡', 'label' => 'Sedang Saja', 'desc' => 'Tugas penting dengan waktu cukup'],
                            'normal' => ['icon' => '🟢', 'label' => 'Normal Saja', 'desc' => 'Tugas rutin harian standar'],
                        ];
                        $curPrio = $priorityMap[$filterPriority] ?? $priorityMap[''];
                    @endphp

                    <button type="button" 
                            @click="openPriority = !openPriority" 
                            class="flex items-center gap-1.5 py-1.5 px-3 text-xs bg-gray-50 dark:bg-gray-900 border {{ $filterPriority ? 'border-teal-500 bg-teal-50/50 dark:bg-teal-950/30 text-teal-700 dark:text-teal-300' : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300' }} hover:border-teal-400 rounded-xl font-semibold shadow-xs transition-all cursor-pointer select-none">
                        <span>{{ $curPrio['icon'] }}</span>
                        <span>{{ $curPrio['label'] }}</span>
                        <svg class="w-3 h-3 text-gray-400 transition-transform duration-200" :class="openPriority ? 'rotate-180 text-teal-500' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <!-- Mobile Bottom Sheet for Priority -->
                    <div x-show="openPriority" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @click="openPriority = false"
                         class="sm:hidden fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-xs"
                         style="display: none;">
                        <div class="w-full bg-white dark:bg-gray-850 rounded-t-3xl p-5 shadow-2xl border-t border-gray-100 dark:border-gray-700 max-h-[85vh] overflow-y-auto"
                             x-transition:enter="transition ease-out duration-250"
                             x-transition:enter-start="translate-y-full"
                             x-transition:enter-end="translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="translate-y-0"
                             x-transition:leave-end="translate-y-full"
                             @click.stop>
                            <div class="w-12 h-1.5 bg-gray-300 dark:bg-gray-600 rounded-full mx-auto mb-4"></div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700/80 mb-3">
                                <div class="flex items-center gap-2 font-black text-gray-900 dark:text-gray-100 text-sm">
                                    <span>🎯</span>
                                    <span>Filter Prioritas</span>
                                </div>
                                <button type="button" @click="openPriority = false" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-bold">✕</button>
                            </div>
                            <div class="space-y-1.5 pb-6">
                                @foreach($priorityMap as $pval => $pitem)
                                    <button type="button" 
                                            wire:click="$set('filterPriority', '{{ $pval }}')" 
                                            @click="openPriority = false"
                                            class="w-full text-left p-3 rounded-2xl flex items-center justify-between gap-3 transition-colors cursor-pointer {{ $filterPriority === $pval ? 'bg-teal-50 dark:bg-teal-950/50 text-teal-700 dark:text-teal-300 font-bold border border-teal-200 dark:border-teal-800' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-750' }}">
                                        <div class="flex items-center gap-3">
                                            <span class="w-9 h-9 rounded-xl flex items-center justify-center text-lg shrink-0 {{ $filterPriority === $pval ? 'bg-teal-100 dark:bg-teal-900/60' : 'bg-gray-100 dark:bg-gray-750' }}">
                                                {{ $pitem['icon'] }}
                                            </span>
                                            <div>
                                                <div class="text-sm font-bold leading-tight">{{ $pitem['label'] }}</div>
                                                <div class="text-[11px] text-gray-400 dark:text-gray-500 font-normal mt-0.5 leading-tight">{{ $pitem['desc'] }}</div>
                                            </div>
                                        </div>
                                        @if($filterPriority === $pval)
                                            <svg class="w-5 h-5 text-teal-600 dark:text-teal-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Floating Menu for Priority -->
                    <div x-show="openPriority" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                         class="hidden sm:block absolute left-0 mt-1.5 w-48 bg-white/95 dark:bg-gray-800/95 backdrop-blur-md rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700/80 py-1.5 z-40 text-xs font-semibold overflow-hidden" 
                         style="display: none;">
                        
                        <div class="px-3 py-1 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700/50 mb-1">
                            Filter Prioritas
                        </div>

                        @foreach($priorityMap as $pval => $pitem)
                            <button type="button" 
                                    wire:click="$set('filterPriority', '{{ $pval }}')" 
                                    @click="openPriority = false"
                                    class="w-full text-left px-3 py-2 flex items-center justify-between gap-2 transition-colors cursor-pointer {{ $filterPriority === $pval ? 'bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300 font-bold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/60' }}">
                                <div class="flex items-center gap-2">
                                    <span>{{ $pitem['icon'] }}</span>
                                    <span>{{ $pitem['label'] }}</span>
                                </div>
                                @if($filterPriority === $pval)
                                    <svg class="w-4 h-4 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Custom Category Filter -->
                @if($this->availableCategories->count() > 0)
                    <div class="relative shrink-0" x-data="{ openCategory: false }" @click.outside="openCategory = false">
                        <button type="button" 
                                @click="openCategory = !openCategory" 
                                class="flex items-center gap-1.5 py-1.5 px-3 text-xs bg-gray-50 dark:bg-gray-900 border {{ $filterCategory ? 'border-teal-500 bg-teal-50/50 dark:bg-teal-950/30 text-teal-700 dark:text-teal-300' : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300' }} hover:border-teal-400 rounded-xl font-semibold shadow-xs transition-all cursor-pointer select-none">
                            <span>🏷️</span>
                            <span class="truncate max-w-[120px]">{{ $filterCategory ? $filterCategory : 'Semua Kategori' }}</span>
                            <svg class="w-3 h-3 text-gray-400 transition-transform duration-200" :class="openCategory ? 'rotate-180 text-teal-500' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <!-- Mobile Bottom Sheet for Category -->
                        <div x-show="openCategory" x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             @click="openCategory = false"
                             class="sm:hidden fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-xs"
                             style="display: none;">
                            <div class="w-full bg-white dark:bg-gray-850 rounded-t-3xl p-5 shadow-2xl border-t border-gray-100 dark:border-gray-700 max-h-[85vh] overflow-y-auto"
                                 x-transition:enter="transition ease-out duration-250"
                                 x-transition:enter-start="translate-y-full"
                                 x-transition:enter-end="translate-y-0"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="translate-y-0"
                                 x-transition:leave-end="translate-y-full"
                                 @click.stop>
                                <div class="w-12 h-1.5 bg-gray-300 dark:bg-gray-600 rounded-full mx-auto mb-4"></div>
                                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700/80 mb-3">
                                    <div class="flex items-center gap-2 font-black text-gray-900 dark:text-gray-100 text-sm">
                                        <span>🏷️</span>
                                        <span>Filter Kategori</span>
                                    </div>
                                    <button type="button" @click="openCategory = false" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-bold">✕</button>
                                </div>
                                <div class="space-y-1.5 pb-6">
                                    <button type="button" 
                                            wire:click="$set('filterCategory', '')" 
                                            @click="openCategory = false"
                                            class="w-full text-left p-3 rounded-2xl flex items-center justify-between gap-3 transition-colors cursor-pointer {{ empty($filterCategory) ? 'bg-teal-50 dark:bg-teal-950/50 text-teal-700 dark:text-teal-300 font-bold border border-teal-200 dark:border-teal-800' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-750' }}">
                                        <div class="flex items-center gap-3">
                                            <span class="w-9 h-9 rounded-xl flex items-center justify-center text-lg shrink-0 {{ empty($filterCategory) ? 'bg-teal-100 dark:bg-teal-900/60' : 'bg-gray-100 dark:bg-gray-750' }}">🏷️</span>
                                            <div>
                                                <div class="text-sm font-bold">Semua Kategori</div>
                                                <div class="text-[11px] text-gray-400 dark:text-gray-500 font-normal">Tampilkan semua tugas tanpa filter kategori</div>
                                            </div>
                                        </div>
                                        @if(empty($filterCategory))
                                            <svg class="w-5 h-5 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        @endif
                                    </button>

                                    @foreach($this->availableCategories as $cat)
                                        <button type="button" 
                                                wire:click="$set('filterCategory', '{{ addslashes($cat) }}')" 
                                                @click="openCategory = false"
                                                class="w-full text-left p-3 rounded-2xl flex items-center justify-between gap-3 transition-colors cursor-pointer {{ $filterCategory === $cat ? 'bg-teal-50 dark:bg-teal-950/50 text-teal-700 dark:text-teal-300 font-bold border border-teal-200 dark:border-teal-800' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-750' }}">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <span class="w-9 h-9 rounded-xl flex items-center justify-center text-lg shrink-0 {{ $filterCategory === $cat ? 'bg-teal-100 dark:bg-teal-900/60' : 'bg-gray-100 dark:bg-gray-750' }}">🏷️</span>
                                                <div class="truncate">
                                                    <div class="text-sm font-bold">{{ $cat }}</div>
                                                    <div class="text-[11px] text-gray-400 dark:text-gray-500 font-normal">Hanya tugas dengan kategori {{ $cat }}</div>
                                                </div>
                                            </div>
                                            @if($filterCategory === $cat)
                                                <svg class="w-5 h-5 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Desktop Floating Menu for Category -->
                        <div x-show="openCategory" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                             class="hidden sm:block absolute left-0 mt-1.5 w-56 max-h-64 overflow-y-auto bg-white/95 dark:bg-gray-800/95 backdrop-blur-md rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700/80 py-1.5 z-40 text-xs font-semibold" 
                             style="display: none;">
                            
                            <div class="px-3 py-1 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700/50 mb-1">
                                Filter Kategori
                            </div>

                            <button type="button" 
                                    wire:click="$set('filterCategory', '')" 
                                    @click="openCategory = false"
                                    class="w-full text-left px-3 py-2 flex items-center justify-between gap-2 transition-colors cursor-pointer {{ empty($filterCategory) ? 'bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300 font-bold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/60' }}">
                                <span>Semua Kategori</span>
                                @if(empty($filterCategory))
                                    <svg class="w-4 h-4 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                @endif
                            </button>

                            @foreach($this->availableCategories as $cat)
                                <button type="button" 
                                        wire:click="$set('filterCategory', '{{ addslashes($cat) }}')" 
                                        @click="openCategory = false"
                                        class="w-full text-left px-3 py-2 flex items-center justify-between gap-2 transition-colors cursor-pointer {{ $filterCategory === $cat ? 'bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300 font-bold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/60' }}">
                                    <span class="truncate">🏷️ {{ $cat }}</span>
                                    @if($filterCategory === $cat)
                                        <svg class="w-4 h-4 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Custom Status Filter -->
                <div class="relative shrink-0" x-data="{ openStatus: false }" @click.outside="openStatus = false">
                    @php
                        $statusMap = [
                            'all' => ['icon' => '📋', 'label' => 'Semua Status', 'desc' => 'Semua tugas baik selesai maupun belum'],
                            'pending' => ['icon' => '⏳', 'label' => 'Belum Selesai', 'desc' => 'Hanya tugas yang masih pending / aktif'],
                            'completed' => ['icon' => '✅', 'label' => 'Selesai', 'desc' => 'Hanya tugas yang sudah diselesaikan'],
                        ];
                        $curStat = $statusMap[$filterStatus] ?? $statusMap['all'];
                    @endphp

                    <button type="button" 
                            @click="openStatus = !openStatus" 
                            class="flex items-center gap-1.5 py-1.5 px-3 text-xs bg-gray-50 dark:bg-gray-900 border {{ $filterStatus !== 'all' ? 'border-teal-500 bg-teal-50/50 dark:bg-teal-950/30 text-teal-700 dark:text-teal-300' : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300' }} hover:border-teal-400 rounded-xl font-semibold shadow-xs transition-all cursor-pointer select-none">
                        <span>{{ $curStat['icon'] }}</span>
                        <span>{{ $curStat['label'] }}</span>
                        <svg class="w-3 h-3 text-gray-400 transition-transform duration-200" :class="openStatus ? 'rotate-180 text-teal-500' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <!-- Mobile Bottom Sheet for Status -->
                    <div x-show="openStatus" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @click="openStatus = false"
                         class="sm:hidden fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-xs"
                         style="display: none;">
                        <div class="w-full bg-white dark:bg-gray-850 rounded-t-3xl p-5 shadow-2xl border-t border-gray-100 dark:border-gray-700 max-h-[85vh] overflow-y-auto"
                             x-transition:enter="transition ease-out duration-250"
                             x-transition:enter-start="translate-y-full"
                             x-transition:enter-end="translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="translate-y-0"
                             x-transition:leave-end="translate-y-full"
                             @click.stop>
                            <div class="w-12 h-1.5 bg-gray-300 dark:bg-gray-600 rounded-full mx-auto mb-4"></div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700/80 mb-3">
                                <div class="flex items-center gap-2 font-black text-gray-900 dark:text-gray-100 text-sm">
                                    <span>📋</span>
                                    <span>Filter Status</span>
                                </div>
                                <button type="button" @click="openStatus = false" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-bold">✕</button>
                            </div>
                            <div class="space-y-1.5 pb-6">
                                @foreach($statusMap as $sval => $sitem)
                                    <button type="button" 
                                            wire:click="$set('filterStatus', '{{ $sval }}')" 
                                            @click="openStatus = false"
                                            class="w-full text-left p-3 rounded-2xl flex items-center justify-between gap-3 transition-colors cursor-pointer {{ $filterStatus === $sval ? 'bg-teal-50 dark:bg-teal-950/50 text-teal-700 dark:text-teal-300 font-bold border border-teal-200 dark:border-teal-800' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-750' }}">
                                        <div class="flex items-center gap-3">
                                            <span class="w-9 h-9 rounded-xl flex items-center justify-center text-lg shrink-0 {{ $filterStatus === $sval ? 'bg-teal-100 dark:bg-teal-900/60' : 'bg-gray-100 dark:bg-gray-750' }}">
                                                {{ $sitem['icon'] }}
                                            </span>
                                            <div>
                                                <div class="text-sm font-bold leading-tight">{{ $sitem['label'] }}</div>
                                                <div class="text-[11px] text-gray-400 dark:text-gray-500 font-normal mt-0.5 leading-tight">{{ $sitem['desc'] }}</div>
                                            </div>
                                        </div>
                                        @if($filterStatus === $sval)
                                            <svg class="w-5 h-5 text-teal-600 dark:text-teal-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Floating Menu for Status -->
                    <div x-show="openStatus" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                         class="hidden sm:block absolute left-0 mt-1.5 w-52 bg-white/95 dark:bg-gray-800/95 backdrop-blur-md rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700/80 py-1.5 z-40 text-xs font-semibold overflow-hidden" 
                         style="display: none;">
                        
                        <div class="px-3 py-1 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700/50 mb-1">
                            Filter Status
                        </div>

                        @foreach($statusMap as $sval => $sitem)
                            <button type="button" 
                                    wire:click="$set('filterStatus', '{{ $sval }}')" 
                                    @click="openStatus = false"
                                    class="w-full text-left px-3 py-2 flex items-center justify-between gap-2 transition-colors cursor-pointer {{ $filterStatus === $sval ? 'bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300 font-bold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/60' }}">
                                <div class="flex items-center gap-2">
                                    <span>{{ $sitem['icon'] }}</span>
                                    <span>{{ $sitem['label'] }}</span>
                                </div>
                                @if($filterStatus === $sval)
                                    <svg class="w-4 h-4 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Auto-hide button (Desktop only: hover does not exist on mobile touch devices) -->
                <button type="button" 
                        @click="$store.taskConfig ? $store.taskConfig.toggleAutoHide() : null" 
                        class="ml-auto hidden sm:inline-flex items-center gap-1.5 text-[11px] font-bold text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 px-2 py-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800/60 transition-colors cursor-pointer shrink-0"
                        title="Klik untuk menyalakan/mematikan tombol auto-hide saat hover">
                    <span x-text="$store.taskConfig && $store.taskConfig.autoHide ? '👁️' : '👁️‍🗨️'"></span>
                    <span x-text="$store.taskConfig && $store.taskConfig.autoHide ? '{{ __('Auto-Hide: Aktif') }}' : '{{ __('Auto-Hide: Nonaktif') }}'"></span>
                </button>

                <!-- Reset Filter Button if active -->
                @if($search || $filterPriority || $filterCategory || $filterStatus !== 'all' || $filterTimeBlock !== 'all' || $sortBy !== 'default')
                    <button type="button" 
                            wire:click="resetFilters" 
                            class="py-1 px-2.5 text-xs bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 font-bold rounded-lg transition-colors shrink-0 flex items-center gap-1 cursor-pointer">
                        <span>✕</span> {{ __('Reset') }}
                    </button>
                @endif
            </div>
        </div>
    </div>

        <!-- Tasks List Container -->
        @php
            $tasksForScheduler = $tasks->map(function($t) {
                return [
                    'id' => $t->id,
                    'title' => $t->title,
                    'date' => $t->date?->format('Y-m-d'),
                    'start_time' => $t->start_time,
                    'deadline' => $t->deadline,
                    'is_completed' => (bool) $t->is_completed,
                ];
            })->values()->toArray();
        @endphp
        <div x-data="taskNotificationScheduler(@js($tasksForScheduler))">
            
            <div class="w-full min-w-0">
                @include('livewire.partials.tasks-overdue-and-srs')
            
            @if($viewLayout === 'timeline')
                <!-- ⏱️ AGENDA TIMELINE LAYOUT (Natural Flex, Anti-Tabrakan Mobile) -->
                <div class="space-y-4 pt-1 pb-28 sm:pb-8">
                    @forelse($tasks as $task)
                        @php
                            $isNotToday = $task->date ? !\Carbon\Carbon::parse($task->date)->startOfDay()->isSameDay(\Carbon\Carbon::today()) : false;
                            $isOverdue = $task->isOverdue();
                            $isCompletedOverdue = (bool)$task->is_completed && (bool)$task->is_completed_overdue;
                            $minutesLeftToDeadline = null;
                            if (!$task->is_completed && !$isOverdue && $task->deadline) {
                                $today = \Carbon\Carbon::today();
                                $taskDate = $task->date ? \Carbon\Carbon::parse($task->date)->startOfDay() : $today;
                                if ($taskDate->isSameDay($today)) {
                                    $taskDateStr = $taskDate->format('Y-m-d');
                                    $deadlineTime = \Carbon\Carbon::parse($taskDateStr . ' ' . $task->deadline);
                                    $diff = (int) now()->diffInMinutes($deadlineTime, false);
                                    if ($diff > 0 && $diff <= 30) {
                                        $minutesLeftToDeadline = $diff;
                                    }
                                }
                            }
                            $isZen = $task->mode === 'zen';

                            // Time presentation on the left
                            $timeDisplay = 'Bebas';
                            $isCurrentHour = false;
                            if ($task->start_time) {
                                $timeDisplay = substr($task->start_time, 0, 5);
                            } elseif ($task->deadline) {
                                $timeDisplay = substr($task->deadline, 0, 5);
                            }

                            // Check if task matches current hour
                            if (!$isNotToday && ($task->start_time || $task->deadline)) {
                                $curH = now()->format('H');
                                $taskH = substr($task->start_time ?? $task->deadline, 0, 2);
                                if ($curH === $taskH) {
                                    $isCurrentHour = true;
                                }
                            }

                            // Priority / Category Card Accent Classes
                            $cardColors = $this->getTaskCardColorClasses($task, $isCompletedOverdue, $isOverdue);

                            // Category icon
                            $catIcon = '🏷️';
                            if ($task->category) {
                                $catLower = strtolower($task->category);
                                if (str_contains($catLower, 'code') || str_contains($catLower, 'dev') || str_contains($catLower, 'web') || str_contains($catLower, 'it') || str_contains($catLower, 'program')) $catIcon = '💻';
                                elseif (str_contains($catLower, 'belajar') || str_contains($catLower, 'study') || str_contains($catLower, 'kuliah') || str_contains($catLower, 'buku') || str_contains($catLower, 'kursus')) $catIcon = '📚';
                                elseif (str_contains($catLower, 'kerja') || str_contains($catLower, 'work') || str_contains($catLower, 'office') || str_contains($catLower, 'proyek') || str_contains($catLower, 'klien')) $catIcon = '💼';
                                elseif (str_contains($catLower, 'kesehatan') || str_contains($catLower, 'health') || str_contains($catLower, 'gym') || str_contains($catLower, 'olahraga') || str_contains($catLower, 'fit')) $catIcon = '💪';
                                elseif (str_contains($catLower, 'personal') || str_contains($catLower, 'pribadi') || str_contains($catLower, 'life') || str_contains($catLower, 'rutinitas')) $catIcon = '🎯';
                            }

                            $subtasks = $task->subTasks ?? collect();
                            $totalSub = $subtasks->count();
                            $completedSub = $subtasks->where('is_completed', true)->count();
                            $subProgress = $totalSub > 0 ? round(($completedSub / $totalSub) * 100) : 0;
                            $estXp = 20 + ($totalSub * 5);

                            $uncompletedSubtasks = $subtasks->where('is_completed', false)->values();
                            $currentSubTask = $uncompletedSubtasks->first();
                            $currentSubIndex = $currentSubTask ? ($subtasks->search(fn($s) => $s->id === $currentSubTask->id) + 1) : $totalSub;
                            $nextSubTask = $uncompletedSubtasks->count() > 1 ? $uncompletedSubtasks->get(1) : null;
                        @endphp

                        <div wire:key="timeline-task-{{ $task->id }}-{{ $task->is_completed ? 'completed' : 'pending' }}" 
                             x-data="{ completed: @js((bool)$task->is_completed), showMenu: false, showSubtasks: true, newSubtaskText: '', showAddSubtask: false, subtaskMode: ($store.taskConfig && $store.taskConfig.subtaskMode) ? $store.taskConfig.subtaskMode : 'list' }"
                             class="flex items-start gap-2 sm:gap-3.5 group">
                            
                            <!-- 1. Left Time Column (Terisolasi rapi, tidak bertumpuk) -->
                            <div class="w-11 sm:w-14 shrink-0 text-right pt-1 select-none">
                                <span class="text-[10px] sm:text-xs font-mono font-black tracking-tight {{ $isCurrentHour ? 'text-atoma-blue dark:text-atoma-cyan bg-atoma-ice dark:bg-atoma-blue/20 px-1 py-0.5 rounded ring-1 ring-atoma-cyan/40' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $timeDisplay }}
                                </span>
                                @if($task->start_time && $task->deadline)
                                    <div class="text-[8px] sm:text-[9px] text-gray-400 font-mono leading-none mt-1">s/d {{ substr($task->deadline, 0, 5) }}</div>
                                @endif
                            </div>

                            <!-- 2. Connector Spine Node Checkbox (Center Column) -->
                            <div class="relative flex flex-col items-center shrink-0 self-stretch pt-0.5" @click.stop>
                                <button type="button"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleComplete({{ $task->id }})"
                                        @if(!$isNotToday) 
                                            @if($this->isWorkoutTask($task) && !$task->is_completed)
                                                @click="$wire.openWorkoutModal({{ $task->id }})"
                                            @else
                                                @click="completed = !completed; if (navigator.vibrate) navigator.vibrate(20);"
                                                wire:click="toggleComplete({{ $task->id }})" 
                                            @endif
                                        @endif
                                        :class="completed ? '{{ $isCompletedOverdue ? 'bg-gradient-to-r from-amber-500 to-orange-500 border-amber-500' : 'bg-emerald-500 border-emerald-500' }} text-white shadow-xs' : '{{ $isCurrentHour ? 'bg-white dark:bg-gray-800 border-atoma-blue ring-4 ring-atoma-blue/20' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 hover:border-atoma-blue hover:scale-105' }}'"
                                        class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all cursor-pointer z-10 {{ $isNotToday ? 'cursor-not-allowed opacity-60' : '' }}"
                                        {{ $isNotToday ? 'disabled' : '' }}>
                                    <svg x-show="completed" x-cloak class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    @if($isCurrentHour)
                                        <span x-show="!completed" class="w-2 h-2 rounded-full bg-atoma-cyan animate-pulse"></span>
                                    @endif
                                </button>

                                @if(!$loop->last)
                                    <div class="w-0.5 flex-1 bg-gray-200 dark:bg-gray-700/80 my-1 pointer-events-none"></div>
                                @endif
                            </div>

                            <!-- 3. Task Card Content with Left Accent Stripe (Right Column) -->
                            <div @click="{{ ($isOverdue || $isCompletedOverdue) ? 'return' : '$wire.editTask(' . $task->id . ')' }}"
                                 :class="completed ? 'opacity-55' : ''"
                                 class="flex-1 min-w-0 {{ $cardColors['bg'] }} rounded-2xl shadow-xs hover:shadow-md transition-all p-3.5 sm:p-4 {{ ($isOverdue || $isCompletedOverdue) ? 'cursor-default' : 'cursor-pointer' }} {{ $cardColors['border'] }}">
                                
                                <!-- Header Row: Title & Action Buttons -->
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <h3 :class="completed ? 'line-through text-gray-400 dark:text-gray-500 font-normal' : '{{ $isOverdue ? 'text-red-600 dark:text-red-400' : '' }}'"
                                                class="text-sm sm:text-base font-bold tracking-tight text-gray-900 dark:text-gray-100">
                                                {{ $task->title }}
                                            </h3>
                                            @if($task->goal_id && $task->goal)
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-orange-500/10 dark:bg-orange-500/20 border border-orange-500/30 text-orange-600 dark:text-orange-400 text-xs font-semibold shadow-xs" title="Goal: {{ $task->goal->title }} ({{ $task->goal->current_value }}/{{ $task->goal->target_value }} {{ $task->goal->unit }})">
                                                    <x-icon.goal-flame class="w-3.5 h-3.5" />
                                                    <span class="hidden sm:inline max-w-[140px] truncate">{{ $task->goal->title }}</span>
                                                </span>
                                            @endif
                                            @if($isCompletedOverdue)
                                                <span class="inline-flex items-center gap-1 text-[10px] font-black text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-950/60 px-2 py-0.5 rounded-md border border-amber-500/20">
                                                    🟠 {{ __('Overdue Checklist (0 EXP)') }}
                                                </span>
                                            @elseif($isOverdue)
                                                <span class="inline-flex items-center gap-1 text-[10px] font-black text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-950/60 px-2 py-0.5 rounded-md">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-ping"></span>
                                                    ⚠️ {{ __('Gagal (Lewat Deadline)') }}
                                                </span>
                                            @elseif($minutesLeftToDeadline !== null)
                                                <span class="inline-flex items-center gap-1 text-[10px] font-black text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-950/60 px-2 py-0.5 rounded-md animate-pulse">
                                                    ⏳ Sisa {{ $minutesLeftToDeadline }}m
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Clean Metadata Line (TIDAK RAME: Pengganti 5 gelembung tebal!) -->
                                        @if(!$isZen)
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                                @if($task->skill)
                                                    <a href="{{ route('skills.show', $task->skill_id) }}" wire:navigate class="inline-flex items-center gap-1 font-bold text-atoma-blue dark:text-atoma-cyan hover:underline text-xs bg-atoma-ice dark:bg-atoma-blue/20 px-2 py-0.5 rounded-md border border-atoma-aqua/40">
                                                        <span>🎯</span>
                                                        <span>{{ $task->skill->title }}</span>
                                                    </a>
                                                @endif

                                                @if($task->category)
                                                    <span class="inline-flex items-center gap-1 font-medium">
                                                        <span>{{ $catIcon }}</span>
                                                        <span>{{ $task->category }}</span>
                                                    </span>
                                                @endif

                                                @if($task->start_time || $task->deadline || $task->estimated_time)
                                                    <span class="inline-flex items-center gap-1 font-mono">
                                                        <span>⏱️</span>
                                                        @if($task->start_time && $task->deadline)
                                                            <span>{{ substr($task->start_time, 0, 5) }} - {{ substr($task->deadline, 0, 5) }}</span>
                                                        @elseif($task->deadline)
                                                            <span>Deadline {{ substr($task->deadline, 0, 5) }}</span>
                                                        @elseif($task->start_time)
                                                            <span>Mulai {{ substr($task->start_time, 0, 5) }}</span>
                                                        @endif
                                                        @if($task->estimated_time)
                                                            <span class="text-gray-400">({{ $task->estimated_time }}m)</span>
                                                        @endif
                                                    </span>
                                                @endif

                                                <!-- Priority: Only shown when High or Medium (Normal is hidden for cleanliness!) -->
                                                @if($task->priority === 'high')
                                                    <span class="inline-flex items-center gap-1 text-red-600 dark:text-red-400 font-bold">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                                        Tinggi
                                                    </span>
                                                @elseif($task->priority === 'medium')
                                                    <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 font-bold">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                        Sedang
                                                    </span>
                                                @endif

                                                @if($task->sharedWithUser)
                                                    <span class="inline-flex items-center gap-1 text-purple-600 dark:text-purple-400 font-bold">
                                                        <svg class="w-3 h-3 text-purple-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                                        Co-op: {{ $task->sharedWithUser->name }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Quick Action Buttons -->
                                    <div class="flex items-center space-x-1 sm:space-x-1.5 shrink-0" @click.stop>
                                        @if(!$task->is_completed)
                                            <button type="button" 
                                                    @click.stop="$dispatch('start-pomodoro-task', { taskId: {{ $task->id }}, taskTitle: '{{ addslashes($task->title) }}' })" 
                                                    :class="($store.taskConfig && $store.taskConfig.autoHide) ? 'opacity-100 sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100 transition-opacity duration-200' : 'opacity-100 transition-opacity duration-200'"
                                                    class="inline-flex items-center gap-1 text-[10px] sm:text-xs font-bold text-red-600 dark:text-red-400 bg-red-50 hover:bg-red-500 hover:text-white dark:bg-red-950/40 dark:hover:bg-red-600 px-2 sm:px-2.5 py-1 rounded-xl border border-red-200 dark:border-red-800/60 transition-all shadow-xs cursor-pointer active:scale-95" 
                                                    title="Mulai Sesi Fokus Pomodoro dengan tugas ini">
                                                <span>🍅</span>
                                                <span class="hidden sm:inline">Fokus</span>
                                            </button>
                                        @endif

                                        @if(!$isNotToday && !$task->is_completed)
                                            <button type="button" 
                                                    wire:click="openJournalModal({{ $task->id }})" 
                                                    @click.stop
                                                    :class="($store.taskConfig && $store.taskConfig.autoHide) ? 'opacity-100 sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100 transition-opacity duration-200' : 'opacity-100 transition-opacity duration-200'"
                                                    class="inline-flex items-center gap-1 text-[10px] sm:text-xs font-bold text-purple-600 dark:text-purple-400 bg-purple-50 hover:bg-purple-600 hover:text-white dark:bg-purple-950/40 dark:hover:bg-purple-600 px-2 sm:px-2.5 py-1 rounded-xl border border-purple-200 dark:border-purple-800/60 transition-all shadow-xs cursor-pointer active:scale-95" 
                                                    title="Selesaikan tugas dengan Jurnal">
                                                <span>📝</span>
                                                <span class="hidden sm:inline">Journal</span>
                                            </button>
                                        @endif

                                        @if(!$isNotToday && !$task->is_completed && $this->isWorkoutTask($task))
                                            <button type="button" 
                                                    wire:click="openWorkoutModal({{ $task->id }})" 
                                                    @click.stop
                                                    :class="($store.taskConfig && $store.taskConfig.autoHide) ? 'opacity-100 sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100 transition-opacity duration-200' : 'opacity-100 transition-opacity duration-200'"
                                                    class="inline-flex items-center gap-1.5 text-[10px] sm:text-xs font-bold text-amber-600 dark:text-amber-400 bg-amber-50 hover:bg-amber-500 hover:text-white dark:bg-amber-950/40 dark:hover:bg-amber-600 px-2 sm:px-2.5 py-1 rounded-xl border border-amber-200 dark:border-amber-800/60 transition-all shadow-xs cursor-pointer active:scale-95" 
                                                    title="Mulai Sesi Latihan (Catat Latihan/Lari)">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 5v14M18 5v14M3 8v8M21 8v8M6 12h12"></path></svg>
                                                <span class="hidden sm:inline">Latihan</span>
                                            </button>
                                        @endif

                                        <!-- More Actions Menu (•••) -->
                                        <div class="relative" @click.outside="showMenu = false" @click.stop>
                                            <button type="button" 
                                                    @click.stop="showMenu = !showMenu" 
                                                    class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/60 rounded-xl transition-all cursor-pointer" 
                                                    title="Opsi Tugas">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                                            </button>

                                            <div x-show="showMenu" 
                                                 x-transition:enter="transition ease-out duration-150"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 class="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 py-1.5 z-30 text-xs font-semibold" 
                                                 style="display: none;">
                                                @if($isOverdue || $isCompletedOverdue)
                                                    <div class="w-full text-left px-3 py-2 text-gray-400 dark:text-gray-500 flex items-center gap-2 cursor-not-allowed opacity-60" title="{{ __('Tugas lewat deadline terkunci dan tidak dapat diedit jamnya') }}">
                                                        <x-icon.lock class="w-3.5 h-3.5" />
                                                        <span>{{ __('Edit Terkunci (Overdue)') }}</span>
                                                    </div>
                                                @else
                                                    <button type="button" 
                                                            wire:click="editTask({{ $task->id }})" 
                                                            @click.stop="showMenu = false"
                                                            class="w-full text-left px-3 py-2 text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center gap-2 transition-colors cursor-pointer">
                                                        <span>✏️</span> {{ __('Edit Tugas') }}
                                                    </button>
                                                @endif

                                                @if(!$task->is_completed)
                                                    <button type="button" 
                                                            wire:click="openCoopModal({{ $task->id }})" 
                                                            @click.stop="showMenu = false"
                                                            class="w-full text-left px-3 py-2 text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 flex items-center gap-2 transition-colors cursor-pointer">
                                                        <span>👥</span> {{ __('Co-op Teman') }}
                                                    </button>
                                                @endif

                                                @if(!$isNotToday && $task->is_recurring && !$task->is_completed)
                                                    <button type="button" 
                                                            wire:click="skipTask({{ $task->id }})" 
                                                            @click.stop="showMenu = false"
                                                            class="w-full text-left px-3 py-2 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/30 flex items-center gap-2 transition-colors cursor-pointer">
                                                        <span>⏭️</span> {{ __('Lewati Hari Ini') }}
                                                    </button>
                                                @endif

                                                <button type="button" 
                                                        wire:click="switchTaskMode({{ $task->id }})" 
                                                        @click.stop="showMenu = false"
                                                        class="w-full text-left px-3 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center gap-2 transition-colors cursor-pointer">
                                                    <span>{{ $task->mode === 'zen' ? '🎯' : '🧘' }}</span> {{ __('Ubah ke') }} {{ $task->mode === 'zen' ? __('Mode Sistematis') : __('Zen Mode') }}
                                                </button>

                                                <button type="button" 
                                                        wire:click="saveTaskAsTemplate({{ $task->id }})" 
                                                        @click.stop="showMenu = false"
                                                        class="w-full text-left px-3 py-2 text-atoma-blue dark:text-atoma-cyan hover:bg-atoma-ice/50 dark:hover:bg-atoma-blue/20 flex items-center gap-2 transition-colors cursor-pointer font-medium">
                                                    <x-icon.sparkles class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan shrink-0" />
                                                    <span>{{ __('Simpan Jadi Template') }}</span>
                                                </button>

                                                <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

                                                <button type="button" 
                                                        wire:click="deleteTask({{ $task->id }})" 
                                                        wire:confirm="{{ __('Yakin ingin menghapus tugas ini? Tindakan ini tidak dapat dibatalkan.') }}"
                                                        @click.stop="showMenu = false"
                                                        class="w-full text-left px-3 py-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 flex items-center gap-2 transition-colors cursor-pointer">
                                                    <span>🗑️</span> {{ __('Hapus Tugas') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Description Box (Clean Inner Card - Reference 2 style) -->
                                @if(!$isZen && $task->description)
                                    <div class="mt-2.5 p-3 rounded-xl bg-gray-50/80 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                                        {{ $task->description }}
                                    </div>
                                @endif

                                <!-- Subtasks Section -->
                                @if(!$isZen && ($totalSub > 0 || $mode === 'sistematis'))
                                    <div class="mt-3 border-t border-gray-100 dark:border-gray-700/80 pt-2.5 space-y-2" @click.stop>
                                        @if($totalSub > 0)
                                            <div class="flex items-center justify-between gap-2">
                                                <button type="button" 
                                                        @click.stop="showSubtasks = !showSubtasks" 
                                                        class="flex items-center gap-1.5 text-xs font-bold text-gray-600 dark:text-gray-300 hover:text-teal-600 dark:hover:text-teal-400 cursor-pointer">
                                                    <span x-text="showSubtasks ? '▼' : '▶'" class="text-[9px] text-gray-400"></span>
                                                    <span>{{ __('Subtugas') }} ({{ $completedSub }}/{{ $totalSub }})</span>
                                                    <span class="text-[10px] font-mono text-teal-600 dark:text-teal-400 font-extrabold">• {{ $subProgress }}%</span>
                                                </button>

                                                <div class="flex items-center gap-1.5">
                                                    <!-- Mode Switcher (List vs 1-per-1) -->
                                                    <div class="flex items-center bg-gray-100 dark:bg-gray-750 p-0.5 rounded-lg text-[10px] font-bold border border-gray-200/60 dark:border-gray-700/60">
                                                        <button type="button" 
                                                                @click.stop="subtaskMode = 'list'" 
                                                                :class="subtaskMode === 'list' ? 'bg-white dark:bg-gray-800 text-teal-600 dark:text-teal-400 shadow-xs' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'" 
                                                                class="px-1.5 py-0.5 rounded-md transition-all cursor-pointer flex items-center gap-0.5" 
                                                                title="{{ __('Tampilkan Semua Subtugas') }}">
                                                            <span>☰</span>
                                                            <span class="hidden sm:inline">List</span>
                                                        </button>
                                                        <button type="button" 
                                                                @click.stop="subtaskMode = 'step'" 
                                                                :class="subtaskMode === 'step' ? 'bg-white dark:bg-gray-800 text-teal-600 dark:text-teal-400 shadow-xs' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'" 
                                                                class="px-1.5 py-0.5 rounded-md transition-all cursor-pointer flex items-center gap-0.5" 
                                                                title="{{ __('Mode Fokus 1-per-1 (Beruntun)') }}">
                                                            <span>🎯</span>
                                                            <span class="hidden sm:inline">1-per-1</span>
                                                        </button>
                                                    </div>

                                                    <button type="button" 
                                                            @click.stop="showAddSubtask = !showAddSubtask" 
                                                            class="text-[11px] font-bold text-teal-600 dark:text-teal-400 hover:underline cursor-pointer flex items-center gap-0.5">
                                                        <span>+ {{ __('Tambah') }}</span>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Progress Bar -->
                                            <div class="w-full h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-teal-500 to-emerald-400 transition-all duration-300 rounded-full" 
                                                     style="width: {{ $subProgress }}%;"></div>
                                            </div>
                                        @endif

                                        <div x-show="showSubtasks" class="space-y-2">
                                            <!-- A. Full Checklist (subtaskMode === 'list') -->
                                            <div x-show="subtaskMode === 'list'" class="space-y-1.5">
                                                @foreach($subtasks as $subTask)
                                                    <div wire:key="timeline-subtask-{{ $subTask->id }}-{{ $subTask->is_completed ? 'completed' : 'pending' }}"
                                                         x-data="{ subCompleted: @js((bool)$subTask->is_completed) }"
                                                         class="flex items-start justify-between group/sub py-0.5 rounded-lg px-1 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors gap-2">
                                                        <div class="flex items-start gap-2 min-w-0 flex-1">
                                                            <button type="button"
                                                                wire:loading.attr="disabled"
                                                                wire:target="toggleSubTask({{ $task->id }}, {{ $subTask->id }})"
                                                                @if(!$isNotToday) 
                                                                    @click.stop="subCompleted = !subCompleted; if (navigator.vibrate) navigator.vibrate(15);"
                                                                    wire:click="toggleSubTask({{ $task->id }}, {{ $subTask->id }})" 
                                                                @endif 
                                                                :class="subCompleted ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-gray-300 dark:border-gray-600 hover:border-indigo-400'"
                                                                class="h-4 w-4 rounded-md border flex items-center justify-center transition-all cursor-pointer mt-0.5 shrink-0 {{ $isNotToday ? 'cursor-not-allowed opacity-60' : '' }}"
                                                                {{ $isNotToday ? 'disabled' : '' }}>
                                                                <svg x-show="subCompleted" x-cloak class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                            </button>
                                                            <span :class="subCompleted ? 'line-through text-gray-400 dark:text-gray-500' : ''"
                                                                  class="text-xs text-gray-700 dark:text-gray-300 leading-snug break-words">
                                                                {{ $subTask->title }}
                                                            </span>
                                                        </div>
                                                        
                                                        <button type="button" 
                                                                wire:click="deleteSubTask({{ $task->id }}, {{ $subTask->id }})" 
                                                                wire:confirm="{{ __('Hapus subtugas ini?') }}"
                                                                @click.stop
                                                                class="opacity-0 group-hover/sub:opacity-100 p-1 text-gray-400 hover:text-red-500 text-xs transition-opacity cursor-pointer shrink-0" 
                                                                title="Hapus subtugas">
                                                            ✕
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <!-- B. Sequential 1-per-1 Focus Mode (subtaskMode === 'step') -->
                                            <div x-show="subtaskMode === 'step'" class="space-y-2">
                                                @if($currentSubTask)
                                                    <div class="p-3 bg-gradient-to-r from-teal-50/70 to-emerald-50/70 dark:from-teal-950/30 dark:to-emerald-950/30 rounded-2xl border border-teal-200/80 dark:border-teal-800/60 shadow-xs space-y-2.5">
                                                        <!-- Step Status Bar -->
                                                        <div class="flex items-center justify-between text-[11px]">
                                                            <span class="font-black text-teal-700 dark:text-teal-300 flex items-center gap-1.5">
                                                                <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
                                                                Langkah {{ $currentSubIndex }} dari {{ $totalSub }}
                                                            </span>
                                                            <span class="text-[10px] font-mono font-bold text-gray-500 dark:text-gray-400">
                                                                {{ $completedSub }}/{{ $totalSub }} Selesai
                                                            </span>
                                                        </div>

                                                        <!-- Active Subtask Box with Action Button -->
                                                        <div class="flex items-center justify-between gap-3 bg-white dark:bg-gray-800 p-2.5 rounded-xl border border-teal-100 dark:border-teal-900/60 shadow-xs">
                                                            <div class="min-w-0 flex-1">
                                                                <div class="text-xs sm:text-sm font-bold text-gray-900 dark:text-gray-100 leading-snug">
                                                                    {{ $currentSubTask->title }}
                                                                </div>
                                                            </div>
                                                            <button type="button" 
                                                                    @if(!$isNotToday) wire:click="toggleSubTask({{ $task->id }}, {{ $currentSubTask->id }})" @endif 
                                                                    @click.stop
                                                                    class="px-3 py-1.5 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-500 hover:to-emerald-500 text-white text-xs font-black rounded-xl shadow-xs transition-all cursor-pointer active:scale-95 flex items-center gap-1.5 shrink-0 {{ $isNotToday ? 'cursor-not-allowed opacity-60' : '' }}"
                                                                    {{ $isNotToday ? 'disabled' : '' }}>
                                                                <span>✓</span>
                                                                <span>Selesai & Lanjut</span>
                                                            </button>
                                                        </div>

                                                        <!-- Next Up Preview -->
                                                        <div class="flex items-center justify-between text-[10px] text-gray-500 dark:text-gray-400 px-0.5">
                                                            <div class="truncate">
                                                                @if($nextSubTask)
                                                                    <span>Berikutnya: <strong class="text-gray-700 dark:text-gray-300 font-semibold">{{ $nextSubTask->title }}</strong></span>
                                                                @else
                                                                    <span class="text-emerald-600 dark:text-emerald-400 font-bold inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v18M5 4h14l-2 5 2 5H5"></path></svg> Ini adalah langkah terakhir!</span>
                                                                @endif
                                                            </div>
                                                            <button type="button" 
                                                                    @click.stop="subtaskMode = 'list'" 
                                                                    class="text-teal-600 dark:text-teal-400 hover:underline shrink-0 font-medium ml-2">
                                                                Lihat Semua ({{ $totalSub }})
                                                            </button>
                                                        </div>
                                                    </div>
                                                @else
                                                    <!-- All Subtasks Completed Celebration Card -->
                                                    <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 rounded-2xl border border-emerald-200 dark:border-emerald-800/60 text-center space-y-2">
                                                        <div class="text-xs font-black text-emerald-700 dark:text-emerald-300 flex items-center justify-center gap-1.5">
                                                            <span>🎉</span> Semua {{ $totalSub }} subtugas telah selesai!
                                                        </div>
                                                        @if(!$task->is_completed && !$isNotToday)
                                                            <button type="button" 
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="toggleComplete({{ $task->id }})"
                                                                    @if($this->isWorkoutTask($task) && !$task->is_completed)
                                                                        @click.stop="$wire.openWorkoutModal({{ $task->id }})"
                                                                    @else
                                                                        wire:click="toggleComplete({{ $task->id }})" 
                                                                        @click.stop="completed = true; if (navigator.vibrate) navigator.vibrate(20);"
                                                                    @endif
                                                                    class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs rounded-xl shadow-sm transition-all cursor-pointer inline-flex items-center gap-1.5">
                                                                <span>✓ Selesaikan Tugas Utama Ini</span>
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Quick Add Subtask Input -->
                                        <div x-show="showAddSubtask || {{ $totalSub === 0 ? 'true' : 'false' }}" 
                                             class="pt-1 {{ $totalSub === 0 ? 'border-none' : '' }}">
                                            <div class="flex items-center gap-1.5">
                                                <input type="text" 
                                                       x-model="newSubtaskText" 
                                                       @keydown.enter.prevent="if (newSubtaskText.trim()) { $wire.addInlineSubTask({{ $task->id }}, newSubtaskText); newSubtaskText = ''; }" 
                                                       @click.stop
                                                       placeholder="+ Tambah subtugas & tekan Enter..." 
                                                       class="flex-1 bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 rounded-xl px-2.5 py-1 text-xs text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30">
                                                <button type="button" 
                                                        @click.stop="if (newSubtaskText.trim()) { $wire.addInlineSubTask({{ $task->id }}, newSubtaskText); newSubtaskText = ''; }" 
                                                        class="bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs px-2.5 py-1 rounded-xl transition-all shrink-0 cursor-pointer shadow-xs">
                                                    Tambah
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Tidak ada tugas yang cocok</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Coba ubah kata kunci pencarian, filter, atau tambahkan tugas baru.</p>
                        </div>
                    @endforelse
                </div>

            @else
                <!-- 📋 CARD VIEW (Mode Kartu Standar dengan Clean Metadata) -->
                <div class="space-y-3 pb-28 sm:pb-8">
                    @forelse($tasks as $task)
                        @php
                            $isNotToday = $task->date ? !\Carbon\Carbon::parse($task->date)->startOfDay()->isSameDay(\Carbon\Carbon::today()) : false;
                            $isOverdue = $task->isOverdue();
                            $isCompletedOverdue = (bool)$task->is_completed && (bool)$task->is_completed_overdue;
                            $minutesLeftToDeadline = null;
                            if (!$task->is_completed && !$isOverdue && $task->deadline) {
                                $today = \Carbon\Carbon::today();
                                $taskDate = $task->date ? \Carbon\Carbon::parse($task->date)->startOfDay() : $today;
                                if ($taskDate->isSameDay($today)) {
                                    $taskDateStr = $taskDate->format('Y-m-d');
                                    $deadlineTime = \Carbon\Carbon::parse($taskDateStr . ' ' . $task->deadline);
                                    $diff = (int) now()->diffInMinutes($deadlineTime, false);
                                    if ($diff > 0 && $diff <= 30) {
                                        $minutesLeftToDeadline = $diff;
                                    }
                                }
                            }
                            $isZen = $task->mode === 'zen';

                            // Priority / Category Card Accent Classes
                            $cardColors = $this->getTaskCardColorClasses($task, $isCompletedOverdue, $isOverdue);

                            $catIcon = '🏷️';
                            if ($task->category) {
                                $catLower = strtolower($task->category);
                                if (str_contains($catLower, 'code') || str_contains($catLower, 'dev') || str_contains($catLower, 'web') || str_contains($catLower, 'it') || str_contains($catLower, 'program')) $catIcon = '💻';
                                elseif (str_contains($catLower, 'belajar') || str_contains($catLower, 'study') || str_contains($catLower, 'kuliah') || str_contains($catLower, 'buku') || str_contains($catLower, 'kursus')) $catIcon = '📚';
                                elseif (str_contains($catLower, 'kerja') || str_contains($catLower, 'work') || str_contains($catLower, 'office') || str_contains($catLower, 'proyek') || str_contains($catLower, 'klien')) $catIcon = '💼';
                                elseif (str_contains($catLower, 'kesehatan') || str_contains($catLower, 'health') || str_contains($catLower, 'gym') || str_contains($catLower, 'olahraga') || str_contains($catLower, 'fit')) $catIcon = '💪';
                                elseif (str_contains($catLower, 'personal') || str_contains($catLower, 'pribadi') || str_contains($catLower, 'life') || str_contains($catLower, 'rutinitas')) $catIcon = '🎯';
                            }

                            $subtasks = $task->subTasks ?? collect();
                            $totalSub = $subtasks->count();
                            $completedSub = $subtasks->where('is_completed', true)->count();
                            $subProgress = $totalSub > 0 ? round(($completedSub / $totalSub) * 100) : 0;
                            $estXp = 20 + ($totalSub * 5);

                            $uncompletedSubtasks = $subtasks->where('is_completed', false)->values();
                            $currentSubTask = $uncompletedSubtasks->first();
                            $currentSubIndex = $currentSubTask ? ($subtasks->search(fn($s) => $s->id === $currentSubTask->id) + 1) : $totalSub;
                            $nextSubTask = $uncompletedSubtasks->count() > 1 ? $uncompletedSubtasks->get(1) : null;
                        @endphp

                        <div wire:key="card-task-{{ $task->id }}-{{ $task->is_completed ? 'completed' : 'pending' }}" 
                             x-data="{ completed: @js((bool)$task->is_completed), showMenu: false, showSubtasks: true, newSubtaskText: '', showAddSubtask: false, subtaskMode: ($store.taskConfig && $store.taskConfig.subtaskMode) ? $store.taskConfig.subtaskMode : 'list' }"
                             @click="{{ ($isOverdue || $isCompletedOverdue) ? 'return' : '$wire.editTask(' . $task->id . ')' }}"
                             :class="completed ? 'opacity-55' : ''"
                             class="group relative flex flex-col p-4 {{ $cardColors['bg'] }} rounded-2xl shadow-xs {{ ($isOverdue || $isCompletedOverdue) ? 'cursor-default' : 'cursor-pointer' }} {{ $cardColors['border'] }} hover:shadow-md transition-all {{ $isNotToday ? 'opacity-80' : '' }}">
                            
                            <div class="flex items-start">
                                <div class="flex-shrink-0 pt-0.5" @click.stop>
                                    <button 
                                        wire:loading.attr="disabled"
                                        wire:target="toggleComplete({{ $task->id }})"
                                        @if(!$isNotToday) 
                                            @if($this->isWorkoutTask($task) && !$task->is_completed)
                                                @click="$wire.openWorkoutModal({{ $task->id }})"
                                            @else
                                                @click="completed = !completed; if (navigator.vibrate) navigator.vibrate(20);"
                                                wire:click="toggleComplete({{ $task->id }})" 
                                            @endif
                                        @endif
                                        :class="completed ? '{{ $isCompletedOverdue ? 'bg-gradient-to-r from-amber-500 to-orange-500 border-amber-500' : 'bg-emerald-500 border-emerald-500' }} text-white shadow-xs' : 'border-gray-300 dark:border-gray-600 hover:border-indigo-500 hover:scale-105'"
                                        class="h-6 w-6 rounded-full border-2 flex items-center justify-center transition-all cursor-pointer {{ $isNotToday ? 'cursor-not-allowed opacity-60' : '' }}"
                                        {{ $isNotToday ? 'disabled' : '' }}>
                                        <svg x-show="completed" x-cloak class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    </button>
                                </div>
                                
                                <div class="ml-3 sm:ml-3.5 flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <h3 :class="completed ? 'line-through text-gray-400 dark:text-gray-500 font-normal' : '{{ $isOverdue ? 'text-red-600 dark:text-red-400' : '' }}'"
                                            class="text-sm sm:text-base font-bold tracking-tight text-gray-900 dark:text-gray-100">
                                            {{ $task->title }}
                                        </h3>
                                        @if($task->goal_id && $task->goal)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-orange-500/10 dark:bg-orange-500/20 border border-orange-500/30 text-orange-600 dark:text-orange-400 text-xs font-semibold shadow-xs" title="Goal: {{ $task->goal->title }} ({{ $task->goal->current_value }}/{{ $task->goal->target_value }} {{ $task->goal->unit }})">
                                                <x-icon.goal-flame class="w-3.5 h-3.5" />
                                                <span class="hidden sm:inline max-w-[120px] truncate">{{ $task->goal->title }}</span>
                                            </span>
                                        @endif
                                        @if($isCompletedOverdue)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-black text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-950/60 px-2 py-0.5 rounded-md border border-amber-500/20">
                                                🟠 {{ __('Overdue Checklist (0 EXP)') }}
                                            </span>
                                        @elseif($isOverdue)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-black text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-950/60 px-2 py-0.5 rounded-md">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-ping"></span>
                                                ⚠️ {{ __('Gagal (Lewat Deadline)') }}
                                            </span>
                                        @elseif($minutesLeftToDeadline !== null)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-black text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-950/60 px-2 py-0.5 rounded-md animate-pulse">
                                                ⏳ Sisa {{ $minutesLeftToDeadline }}m
                                            </span>
                                        @endif
                                    </div>

                                    @if(!$isZen && $task->description)
                                        <div class="mt-2 p-2.5 rounded-xl bg-gray-50/80 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                                            {{ $task->description }}
                                        </div>
                                    @endif

                                    <!-- Clean Metadata Row (No rainbow badges) -->
                                    @if(!$isZen)
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            @if($task->skill)
                                                <a href="{{ route('skills.show', $task->skill_id) }}" wire:navigate class="inline-flex items-center gap-1 font-bold text-atoma-blue dark:text-atoma-cyan hover:underline text-xs bg-atoma-ice dark:bg-atoma-blue/20 px-2 py-0.5 rounded-md border border-atoma-aqua/40">
                                                    <span>🎯</span>
                                                    <span>{{ $task->skill->title }}</span>
                                                </a>
                                            @endif
                                            @if($task->category)
                                                <span class="inline-flex items-center gap-1 font-medium">
                                                    <span>{{ $catIcon }}</span>
                                                    <span>{{ $task->category }}</span>
                                                </span>
                                            @endif

                                            @if($task->start_time || $task->deadline || $task->estimated_time)
                                                <span class="inline-flex items-center gap-1 font-mono">
                                                    <span>⏱️</span>
                                                    @if($task->start_time && $task->deadline)
                                                        <span>{{ substr($task->start_time, 0, 5) }} - {{ substr($task->deadline, 0, 5) }}</span>
                                                    @elseif($task->deadline)
                                                        <span>Deadline {{ substr($task->deadline, 0, 5) }}</span>
                                                    @elseif($task->start_time)
                                                        <span>Mulai {{ substr($task->start_time, 0, 5) }}</span>
                                                    @endif
                                                    @if($task->estimated_time)
                                                        <span class="text-gray-400">({{ $task->estimated_time }}m)</span>
                                                    @endif
                                                </span>
                                            @endif

                                            @if($task->priority === 'high')
                                                <span class="inline-flex items-center gap-1 text-red-600 dark:text-red-400 font-bold">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                                    Tinggi
                                                </span>
                                            @elseif($task->priority === 'medium')
                                                <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 font-bold">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                    Sedang
                                                </span>
                                            @endif

                                            @if($task->sharedWithUser)
                                                <span class="inline-flex items-center gap-1 text-purple-600 dark:text-purple-400 font-bold">
                                                    <svg class="w-3 h-3 text-purple-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                                    Co-op: {{ $task->sharedWithUser->name }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <div class="ml-2 flex items-center space-x-1 shrink-0" @click.stop>
                                    @if(!$task->is_completed)
                                        <button type="button" 
                                                @click.stop="$dispatch('start-pomodoro-task', { taskId: {{ $task->id }}, taskTitle: '{{ addslashes($task->title) }}' })" 
                                                class="inline-flex items-center gap-1 text-[10px] sm:text-xs font-bold text-red-600 dark:text-red-400 bg-red-50 hover:bg-red-500 hover:text-white px-2 py-1 rounded-xl border border-red-200 dark:border-red-800/60 transition-all cursor-pointer">
                                            <span>🍅</span>
                                            <span class="hidden sm:inline">Fokus</span>
                                        </button>
                                    @endif

                                    @if(!$isNotToday && !$task->is_completed)
                                        <button type="button" 
                                                wire:click="openJournalModal({{ $task->id }})" 
                                                class="inline-flex items-center gap-1 text-[10px] sm:text-xs font-bold text-purple-600 dark:text-purple-400 bg-purple-50 hover:bg-purple-600 hover:text-white px-2 py-1 rounded-xl border border-purple-200 dark:border-purple-800/60 transition-all cursor-pointer">
                                            <span>📝</span>
                                            <span class="hidden sm:inline">Journal</span>
                                        </button>
                                    @endif

                                    @if(!$isNotToday && !$task->is_completed && $this->isWorkoutTask($task))
                                        <button type="button" 
                                                wire:click="openWorkoutModal({{ $task->id }})" 
                                                class="inline-flex items-center gap-1.5 text-[10px] sm:text-xs font-bold text-amber-600 dark:text-amber-400 bg-amber-50 hover:bg-amber-500 hover:text-white dark:bg-amber-950/40 dark:hover:bg-amber-600 px-2 py-1 rounded-xl border border-amber-200 dark:border-amber-800/60 transition-all cursor-pointer" 
                                                title="{{ __('Mulai Sesi Latihan (Catat Latihan/Lari)') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 5v14M18 5v14M3 8v8M21 8v8M6 12h12"></path></svg>
                                            <span class="hidden sm:inline">{{ __('Latihan') }}</span>
                                        </button>
                                    @endif

                                    <div class="relative" @click.outside="showMenu = false" @click.stop>
                                        <button type="button" 
                                                @click.stop="showMenu = !showMenu" 
                                                class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded-xl cursor-pointer">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                                        </button>

                                        <div x-show="showMenu" 
                                             class="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 py-1.5 z-30 text-xs font-semibold" 
                                             style="display: none;">
                                            @if($isOverdue || $isCompletedOverdue)
                                                <div class="w-full text-left px-3 py-2 text-gray-400 dark:text-gray-500 flex items-center gap-2 cursor-not-allowed opacity-60" title="{{ __('Tugas lewat deadline terkunci dan tidak dapat diedit jamnya') }}">
                                                    <x-icon.lock class="w-3.5 h-3.5" />
                                                    <span>{{ __('Edit Terkunci (Overdue)') }}</span>
                                                </div>
                                            @else
                                                <button type="button" 
                                                        wire:click="editTask({{ $task->id }})" 
                                                        @click.stop="showMenu = false"
                                                        class="w-full text-left px-3 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 flex items-center gap-2 cursor-pointer">
                                                    <span>✏️</span> {{ __('Edit Tugas') }}
                                                </button>
                                            @endif
                                            <button type="button" 
                                                    wire:click="saveTaskAsTemplate({{ $task->id }})" 
                                                    @click.stop="showMenu = false"
                                                    class="w-full text-left px-3 py-2 text-atoma-blue dark:text-atoma-cyan hover:bg-atoma-ice/50 dark:hover:bg-atoma-blue/20 flex items-center gap-2 transition-colors cursor-pointer font-medium">
                                                <x-icon.sparkles class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan shrink-0" />
                                                <span>{{ __('Simpan Jadi Template') }}</span>
                                            </button>
                                            <button type="button" 
                                                    wire:click="deleteTask({{ $task->id }})" 
                                                    wire:confirm="{{ __('Yakin ingin menghapus tugas ini? Tindakan ini tidak dapat dibatalkan.') }}"
                                                    @click.stop="showMenu = false"
                                                    class="w-full text-left px-3 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 flex items-center gap-2 cursor-pointer">
                                                <span>🗑️</span> {{ __('Hapus Tugas') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Subtasks Section -->
                            @if(!$isZen && ($totalSub > 0 || $mode === 'sistematis'))
                                <div class="mt-3.5 ml-0 sm:ml-9.5 border-t border-gray-100 dark:border-gray-700/80 pt-2.5 space-y-2" @click.stop>
                                    @if($totalSub > 0)
                                        <div class="flex items-center justify-between gap-2">
                                            <button type="button" 
                                                    @click.stop="showSubtasks = !showSubtasks" 
                                                    class="flex items-center gap-1.5 text-xs font-bold text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 cursor-pointer">
                                                <span x-text="showSubtasks ? '▼' : '▶'" class="text-[9px] text-gray-400"></span>
                                                <span>{{ __('Subtugas') }} ({{ $completedSub }}/{{ $totalSub }})</span>
                                                <span class="text-[10px] font-mono text-indigo-500 font-extrabold">• {{ $subProgress }}%</span>
                                            </button>

                                            <div class="flex items-center gap-1.5">
                                                <!-- Mode Switcher (List vs 1-per-1) -->
                                                <div class="flex items-center bg-gray-100 dark:bg-gray-750 p-0.5 rounded-lg text-[10px] font-bold border border-gray-200/60 dark:border-gray-700/60">
                                                    <button type="button" 
                                                            @click.stop="subtaskMode = 'list'" 
                                                            :class="subtaskMode === 'list' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'" 
                                                            class="px-1.5 py-0.5 rounded-md transition-all cursor-pointer flex items-center gap-0.5" 
                                                            title="Tampilkan Semua Subtugas">
                                                        <span>☰</span>
                                                        <span class="hidden sm:inline">List</span>
                                                    </button>
                                                    <button type="button" 
                                                            @click.stop="subtaskMode = 'step'" 
                                                            :class="subtaskMode === 'step' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'" 
                                                            class="px-1.5 py-0.5 rounded-md transition-all cursor-pointer flex items-center gap-0.5" 
                                                            title="Mode Fokus 1-per-1 (Beruntun)">
                                                        <span>🎯</span>
                                                        <span class="hidden sm:inline">1-per-1</span>
                                                    </button>
                                                </div>

                                                <button type="button" 
                                                        @click.stop="showAddSubtask = !showAddSubtask" 
                                                        class="text-[11px] font-bold text-indigo-600 hover:underline cursor-pointer flex items-center gap-0.5">
                                                    <span>+ Tambah</span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="w-full h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="h-full bg-gradient-to-r from-indigo-500 to-emerald-400 rounded-full" style="width: {{ $subProgress }}%;"></div>
                                        </div>
                                    @endif

                                    <div x-show="showSubtasks" class="space-y-2">
                                        <!-- A. Full Checklist (subtaskMode === 'list') -->
                                        <div x-show="subtaskMode === 'list'" class="space-y-1.5">
                                            @foreach($subtasks as $subTask)
                                                <div wire:key="card-subtask-{{ $subTask->id }}-{{ $subTask->is_completed ? 'completed' : 'pending' }}"
                                                     x-data="{ subCompleted: @js((bool)$subTask->is_completed) }"
                                                     class="flex items-start justify-between group/sub py-0.5 px-1 gap-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                                                    <div class="flex items-start gap-2 min-w-0 flex-1">
                                                        <button type="button"
                                                            wire:loading.attr="disabled"
                                                            wire:target="toggleSubTask({{ $task->id }}, {{ $subTask->id }})"
                                                            @if(!$isNotToday) 
                                                                @click.stop="subCompleted = !subCompleted; if (navigator.vibrate) navigator.vibrate(15);"
                                                                wire:click="toggleSubTask({{ $task->id }}, {{ $subTask->id }})" 
                                                            @endif 
                                                            :class="subCompleted ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-gray-300 dark:border-gray-600 hover:border-indigo-500'"
                                                            class="h-4 w-4 rounded-md border flex items-center justify-center cursor-pointer mt-0.5 shrink-0 {{ $isNotToday ? 'cursor-not-allowed opacity-60' : '' }}"
                                                            {{ $isNotToday ? 'disabled' : '' }}>
                                                            <svg x-show="subCompleted" x-cloak class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                        </button>
                                                        <span :class="subCompleted ? 'line-through text-gray-400 dark:text-gray-500' : ''"
                                                              class="text-xs text-gray-700 dark:text-gray-300 leading-snug break-words">
                                                            {{ $subTask->title }}
                                                        </span>
                                                    </div>
                                                    <button type="button" 
                                                            wire:click="deleteSubTask({{ $task->id }}, {{ $subTask->id }})" 
                                                            wire:confirm="{{ __('Hapus subtugas ini?') }}"
                                                            @click.stop
                                                            class="opacity-0 group-hover/sub:opacity-100 p-1 text-gray-400 hover:text-red-500 text-xs cursor-pointer shrink-0"
                                                            title="Hapus subtugas">
                                                        ✕
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>

                                        <!-- B. Sequential 1-per-1 Focus Mode (subtaskMode === 'step') -->
                                        <div x-show="subtaskMode === 'step'" class="space-y-2">
                                            @if($currentSubTask)
                                                <div class="p-3 bg-gradient-to-r from-indigo-50/70 to-purple-50/70 dark:from-indigo-950/30 dark:to-purple-950/30 rounded-2xl border border-indigo-200/80 dark:border-indigo-800/60 shadow-xs space-y-2.5">
                                                    <!-- Step Status Bar -->
                                                    <div class="flex items-center justify-between text-[11px]">
                                                        <span class="font-black text-indigo-700 dark:text-indigo-300 flex items-center gap-1.5">
                                                            <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                                                            Langkah {{ $currentSubIndex }} dari {{ $totalSub }}
                                                        </span>
                                                        <span class="text-[10px] font-mono font-bold text-gray-500 dark:text-gray-400">
                                                            {{ $completedSub }}/{{ $totalSub }} Selesai
                                                        </span>
                                                    </div>

                                                    <!-- Active Subtask Box with Action Button -->
                                                    <div class="flex items-center justify-between gap-3 bg-white dark:bg-gray-800 p-2.5 rounded-xl border border-indigo-100 dark:border-indigo-900/60 shadow-xs">
                                                        <div class="min-w-0 flex-1">
                                                            <div class="text-xs sm:text-sm font-bold text-gray-900 dark:text-gray-100 leading-snug">
                                                                {{ $currentSubTask->title }}
                                                            </div>
                                                        </div>
                                                        <button type="button" 
                                                                @if(!$isNotToday) wire:click="toggleSubTask({{ $task->id }}, {{ $currentSubTask->id }})" @endif 
                                                                @click.stop
                                                                class="px-3 py-1.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-black rounded-xl shadow-xs transition-all cursor-pointer active:scale-95 flex items-center gap-1.5 shrink-0 {{ $isNotToday ? 'cursor-not-allowed opacity-60' : '' }}"
                                                                {{ $isNotToday ? 'disabled' : '' }}>
                                                            <span>✓</span>
                                                            <span>Selesai & Lanjut</span>
                                                        </button>
                                                    </div>

                                                    <!-- Next Up Preview -->
                                                    <div class="flex items-center justify-between text-[10px] text-gray-500 dark:text-gray-400 px-0.5">
                                                        <div class="truncate">
                                                            @if($nextSubTask)
                                                                <span>Berikutnya: <strong class="text-gray-700 dark:text-gray-300 font-semibold">{{ $nextSubTask->title }}</strong></span>
                                                            @else
                                                                <span class="text-emerald-600 dark:text-emerald-400 font-bold inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v18M5 4h14l-2 5 2 5H5"></path></svg> Ini adalah langkah terakhir!</span>
                                                            @endif
                                                        </div>
                                                        <button type="button" 
                                                                @click.stop="subtaskMode = 'list'" 
                                                                class="text-indigo-600 dark:text-indigo-400 hover:underline shrink-0 font-medium ml-2">
                                                            Lihat Semua ({{ $totalSub }})
                                                        </button>
                                                    </div>
                                                </div>
                                            @else
                                                <!-- All Subtasks Completed Celebration Card -->
                                                <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 rounded-2xl border border-emerald-200 dark:border-emerald-800/60 text-center space-y-2">
                                                    <div class="text-xs font-black text-emerald-700 dark:text-emerald-300 flex items-center justify-center gap-1.5">
                                                        <span>🎉</span> Semua {{ $totalSub }} subtugas telah selesai!
                                                    </div>
                                                    @if(!$task->is_completed && !$isNotToday)
                                                        <button type="button" 
                                                                wire:loading.attr="disabled"
                                                                wire:target="toggleComplete({{ $task->id }})"
                                                                @if($this->isWorkoutTask($task) && !$task->is_completed)
                                                                    @click.stop="$wire.openWorkoutModal({{ $task->id }})"
                                                                @else
                                                                    wire:click="toggleComplete({{ $task->id }})" 
                                                                    @click.stop="completed = true; if (navigator.vibrate) navigator.vibrate(20);"
                                                                @endif
                                                                class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs rounded-xl shadow-sm transition-all cursor-pointer inline-flex items-center gap-1.5">
                                                            <span>✓ Selesaikan Tugas Utama Ini</span>
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Quick Add Subtask Input -->
                                    <div x-show="showAddSubtask || {{ $totalSub === 0 ? 'true' : 'false' }}" class="pt-1">
                                        <div class="flex items-center gap-1.5">
                                            <input type="text" 
                                                   x-model="newSubtaskText" 
                                                   @keydown.enter.prevent="if (newSubtaskText.trim()) { $wire.addInlineSubTask({{ $task->id }}, newSubtaskText); newSubtaskText = ''; }" 
                                                   @click.stop
                                                   placeholder="+ Tambah subtugas & tekan Enter..." 
                                                   class="flex-1 bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 rounded-xl px-2.5 py-1 text-xs">
                                            <button type="button" 
                                                    @click.stop="if (newSubtaskText.trim()) { $wire.addInlineSubTask({{ $task->id }}, newSubtaskText); newSubtaskText = ''; }" 
                                                    class="bg-indigo-600 text-white font-bold text-xs px-2.5 py-1 rounded-xl">
                                                Tambah
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Tidak ada tugas yang cocok</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Coba ubah kata kunci pencarian, filter, atau tambahkan tugas baru.</p>
                        </div>
                    @endforelse
                </div>
            @endif
            </div>
        </div>

    <!-- Overdue Reason Modal -->
    @if($showOverdueReasonModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm"
         x-data
         @keydown.escape.window="$wire.closeOverdueReasonModal()">
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl p-5 sm:p-6 max-w-md w-full border border-gray-200 dark:border-gray-700 relative overflow-hidden">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700/60 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/60 flex items-center justify-center text-base shrink-0">
                        💬
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-gray-900 dark:text-white">Alasan Keterlambatan</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Dicatat untuk analisis pola hambatan AI di Insights</p>
                    </div>
                </div>
                <button type="button" wire:click="closeOverdueReasonModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="mb-3 px-3 py-2 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-700/60">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Tugas Terlambat:</span>
                <p class="text-xs sm:text-sm font-bold text-gray-800 dark:text-gray-200 truncate mt-0.5">{{ $selectedOverdueTaskTitle }}</p>
            </div>

            <div class="space-y-3">
                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300">Pilih Faktor Penyebab Utama:</label>
                @php
                    $presetOverdueReasons = [
                        'Kelelahan / Burnout' => 'Kurang energi, butuh istirahat',
                        'Waktu Tidak Cukup' => 'Jadwal bentrok atau ada urusan lain',
                        'Terlalu Rumit / Bingung' => 'Belum tahu cara memulai langkah awal',
                        'Terdistraksi / Prokrastinasi' => 'Kehilangan fokus / menunda-nunda',
                        'Urusan Darurat' => 'Ada keadaan mendesak di luar kendali',
                        'Lainnya' => 'Alasan lainnya...',
                    ];
                @endphp
                <div class="space-y-1.5">
                    @foreach($presetOverdueReasons as $rKey => $rDesc)
                        <label class="flex items-center gap-2.5 p-2.5 rounded-xl border cursor-pointer transition-all {{ $chosenOverdueReason === $rKey ? 'bg-amber-50 dark:bg-amber-950/30 border-amber-500 text-amber-900 dark:text-amber-200 font-bold shadow-xs' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-700 dark:text-gray-300 text-xs' }}">
                            <input type="radio" wire:model.live="chosenOverdueReason" value="{{ $rKey }}" class="text-amber-500 focus:ring-amber-500">
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold">{{ $rKey }}</div>
                                <div class="text-[10px] text-gray-400 font-normal">{{ $rDesc }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Catatan Tambahan (Opsional):</label>
                    <textarea wire:model="customOverdueReasonNote" placeholder="Tuliskan kendala spesifik jika ada..." rows="2" class="w-full text-xs bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 rounded-xl p-2.5 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-amber-500"></textarea>
                </div>

                <div class="p-2.5 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900/40 text-[11px] text-blue-700 dark:text-blue-300 flex items-center gap-2">
                    <span>💡</span>
                    <span>Tugas tetap tersimpan di tanggal aslinya tanpa dimanipulasi jam/jadwalnya.</span>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <button type="button" wire:click="closeOverdueReasonModal" class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-bold text-xs rounded-xl cursor-pointer">
                        Batal
                    </button>
                    <button type="button" wire:click="saveOverdueReason" class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white font-bold text-xs rounded-xl shadow-md shadow-amber-500/25 cursor-pointer">
                        Simpan Alasan
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- EMAIL MAGIC MODAL (Disabled per request) -->
    @if(false && $showEmailMagicModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-gray-900/80 backdrop-blur-sm"
         x-data
         @keydown.escape.window="$wire.closeEmailMagicModal()">
        <div class="bg-white dark:bg-gray-850 rounded-3xl shadow-2xl p-5 sm:p-6 max-w-2xl w-full border border-gray-200 dark:border-gray-700 relative overflow-hidden flex flex-col max-h-[92vh]">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700/60 mb-4 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white flex items-center justify-center text-lg shadow-md shadow-purple-500/30 shrink-0">
                        ✨
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white">{{ __('Email Magic AI') }}</h3>
                            <span class="text-[10px] bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 font-extrabold px-2 py-0.5 rounded-full uppercase tracking-wider">{{ __('Trello & Forwarding') }}</span>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ __('Ubah email masuk & forward jadi to-do terstruktur lengkap dengan tautan penting') }}</p>
                    </div>
                </div>
                <button type="button" wire:click="closeEmailMagicModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700/60 transition-colors cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Scrollable Content -->
            <div class="overflow-y-auto space-y-4 pr-1">
                <!-- Forwarding Info Box -->
                <div class="p-3.5 rounded-2xl bg-gradient-to-r from-purple-500/10 via-indigo-500/10 to-transparent border border-purple-200/80 dark:border-purple-800/50 text-xs">
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                        <span class="font-extrabold text-purple-800 dark:text-purple-300 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            {{ __('Alamat Forwarding Trello / Atoma Inbox:') }}
                        </span>
                        <span class="text-[10px] text-gray-400">{{ __('Simulasi / Direct Forward') }}</span>
                    </div>
                    <div class="flex items-center gap-2 bg-white dark:bg-gray-900/80 px-3 py-2 rounded-xl border border-purple-200 dark:border-purple-800/80 font-mono text-[11px] text-purple-700 dark:text-purple-300">
                        <span class="truncate flex-1 select-all" id="userForwardEmailAddress">inbox-user-{{ auth()->id() }}@mail.atoma.app</span>
                        <button type="button" 
                                @click="navigator.clipboard.writeText('inbox-user-{{ auth()->id() }}@mail.atoma.app'); $dispatch('toast', { message: 'Alamat inbox email berhasil disalin!', type: 'success' })" 
                                class="shrink-0 text-[10px] bg-purple-100 hover:bg-purple-200 dark:bg-purple-900/60 dark:hover:bg-purple-900 text-purple-700 dark:text-purple-300 px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer">
                            {{ __('Salin') }}
                        </button>
                    </div>
                    <p class="mt-1.5 text-[10px] text-gray-500 dark:text-gray-400 leading-relaxed">
                        💡 <em>{{ __('Forward email dari atasan/klien, atau langsung paste teks email di bawah ini untuk diekstrak menjadi to-do harian beserta link Meet/Docs/Lampiran.') }}</em>
                    </p>
                </div>

                <!-- Paste Email Input -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center justify-between">
                        <span>{{ __('Tempel (Paste) Isi Email:') }}</span>
                        <span class="text-[10px] text-gray-400 font-normal">{{ __('Mendukung format forward Gmail, Outlook, Trello') }}</span>
                    </label>
                    <textarea 
                        wire:model="emailMagicInput" 
                        rows="4" 
                        placeholder="{{ __('Contoh:&#10;Halo tim, mohon review proposal sprint minggu ini sebelum jam 3 sore. Link dokumen ada di https://docs.google.com/document/d/... dan tautan Zoom rapat: https://zoom.us/j/123456789. Harap update sheet keuangan https://docs.google.com/spreadsheets/d/... jika sudah selesai.') }}"
                        class="w-full bg-gray-50 dark:bg-gray-900/80 border border-gray-200 dark:border-gray-700 rounded-2xl p-3 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none leading-relaxed transition-all">
                    </textarea>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <p class="text-[10px] text-gray-400 flex items-center gap-1">
                        <span>⚡</span> {{ __('AI akan mengekstrak to-do, prioritas, estimasi menit, dan link otomatis.') }}
                    </p>
                    <button type="button" 
                            wire:click="processEmailMagic" 
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-purple-500/25 transition-all cursor-pointer flex items-center gap-1.5 shrink-0 disabled:opacity-50">
                        <span wire:loading.remove wire:target="processEmailMagic">✨ {{ __('Ekstrak dengan AI') }}</span>
                        <span wire:loading wire:target="processEmailMagic" class="flex items-center gap-1">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            {{ __('Menganalisis...') }}
                        </span>
                    </button>
                </div>

                <!-- Extracted Tasks Result Section -->
                @if(!empty($emailMagicTasks))
                    <div class="pt-3 border-t border-gray-100 dark:border-gray-700/60 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-black uppercase tracking-wider text-purple-700 dark:text-purple-300 flex items-center gap-1.5">
                                <span>📋</span>
                                <span>{{ __('Daftar To-Do Ditemukan') }} ({{ count($emailMagicTasks) }}):</span>
                            </h4>
                            <span class="text-[10px] text-gray-400">{{ __('Periksa atau hapus to-do yang tidak diinginkan') }}</span>
                        </div>

                        <div class="space-y-2.5">
                            @foreach($emailMagicTasks as $idx => $tItem)
                                <div class="p-3 bg-gray-50 dark:bg-gray-900/60 border border-gray-200/90 dark:border-gray-700/80 rounded-2xl flex items-start justify-between gap-3 transition-all hover:border-purple-300 dark:hover:border-purple-600">
                                    <div class="space-y-1.5 flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $tItem['title'] }}</span>
                                            <span class="text-[10px] px-2 py-0.5 rounded-md font-bold {{ ($tItem['priority'] ?? 'normal') === 'high' ? 'bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400' : (($tItem['priority'] ?? 'normal') === 'low' ? 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400' : 'bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400') }}">
                                                {{ ucfirst($tItem['priority'] ?? 'normal') }}
                                            </span>
                                            <span class="text-[10px] px-2 py-0.5 rounded-md font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                                                🏷️ {{ $tItem['category'] ?? 'Work' }}
                                            </span>
                                            <span class="text-[10px] text-gray-400 font-medium">
                                                ⏱️ {{ $tItem['estimated_minutes'] ?? 30 }}m
                                            </span>
                                        </div>

                                        @if(!empty($tItem['description']))
                                            <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line line-clamp-3">
                                                {{ $tItem['description'] }}
                                            </p>
                                        @endif

                                        @if(!empty($tItem['extracted_links']))
                                            <div class="flex items-center gap-1.5 flex-wrap pt-1">
                                                <span class="text-[10px] text-purple-600 dark:text-purple-400 font-bold">🔗 {{ __('Tautan:') }}</span>
                                                @foreach($tItem['extracted_links'] as $link)
                                                    <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="text-[10px] bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-800 hover:underline px-2 py-0.5 rounded truncate max-w-xs block">
                                                        {{ $link }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <button type="button" 
                                            wire:click="removeEmailMagicTask({{ $idx }})" 
                                            class="text-gray-400 hover:text-red-500 p-1 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer shrink-0" 
                                            title="{{ __('Hapus item ini') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Modal Footer -->
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-end gap-2.5 shrink-0 mt-3">
                <button type="button" wire:click="closeEmailMagicModal" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-bold text-xs rounded-xl cursor-pointer">
                    {{ __('Tutup') }}
                </button>
                @if(!empty($emailMagicTasks))
                    <button type="button" wire:click="confirmInsertEmailTasks" class="px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 active:scale-95 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-500/25 transition-all cursor-pointer flex items-center gap-1.5">
                        <span>✨</span>
                        <span>{{ __('Tambahkan ke To-Do Hari Ini') }} ({{ count($emailMagicTasks) }})</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- H+1 Midnight Settlement Modal -->
    @if($showSettlementModal)
    <div x-data="{ showModal: true }" 
         x-show="showModal"
         x-init="if (typeof confetti !== 'undefined') { confetti({ particleCount: 180, spread: 80, origin: { y: 0.5 } }); }" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-md">
        
        <div class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-3xl shadow-2xl p-6 sm:p-8 max-w-md w-full border border-gray-200 dark:border-gray-700 text-center relative overflow-hidden">
            
            <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-indigo-200 dark:border-indigo-700/50 text-3xl shadow-sm">
                🌙
            </div>
            
            <h3 class="text-xl font-black text-gray-900 dark:text-white tracking-tight mb-1">SETTLEMENT HARI INI (00:00)</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-6 font-medium">Hasil kinerja dan pencapaian kamu hari kemarin telah dihitung secara otomatis!</p>
            
            <div class="bg-gray-50 dark:bg-gray-800/80 rounded-2xl p-4 border border-gray-200 dark:border-gray-700 mb-6 space-y-3 shadow-inner text-left">
                <div class="flex justify-between items-center text-sm border-b border-gray-200 dark:border-gray-700/80 pb-2.5">
                    <span class="text-gray-600 dark:text-gray-300 font-bold">Pending XP Terkumpul:</span>
                    <span class="font-black text-indigo-600 dark:text-indigo-400 text-base">+{{ number_format($settlementData['xp'] ?? 0) }} XP</span>
                </div>
                <div class="flex justify-between items-center text-sm border-b border-gray-200 dark:border-gray-700/80 pb-2.5">
                    <span class="text-gray-600 dark:text-gray-300 font-bold">Koin Diterima (Rasio 10:1 Ceil):</span>
                    <span class="font-black text-amber-500 dark:text-amber-400 text-base">+{{ number_format($settlementData['coins'] ?? 0) }} 🪙</span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-600 dark:text-gray-300 font-bold">Rank & Prestige:</span>
                    <span class="font-black text-atoma-blue text-sm">{{ $settlementData['prestige_title'] ?? auth()->user()->prestige_title }}</span>
                </div>
                @if(isset($settlementData['leveled_up']) && $settlementData['leveled_up'])
                    <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700/50 rounded-xl p-2.5 text-xs text-emerald-700 dark:text-emerald-400 font-extrabold flex items-center justify-center gap-1.5 mt-2">
                        🎉 NAIK LEVEL: Level {{ $settlementData['new_level'] ?? 1 }}!
                    </div>
                @endif
            </div>

            <button @click="showModal = false" wire:click="closeSettlementModal" type="button" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-3.5 px-4 rounded-xl shadow-lg shadow-indigo-600/30 transition-all active:scale-95 cursor-pointer">
                Klaim & Lanjutkan! 🚀
            </button>
        </div>
    </div>
    @endif

    <!-- ASCENSION CELEBRATION MODAL (Prestige Tier Up) -->
    @if($showAscensionModal)
    @php
        $tierDetails = $ascensionData['details'] ?? \App\Services\PrestigeService::getTierDetails($ascensionData['new_tier'] ?? 1);
    @endphp
    <div x-data="{ showModal: true }" 
         x-show="showModal"
         x-init="if (typeof confetti !== 'undefined') { 
             confetti({ particleCount: 250, spread: 100, origin: { y: 0.4 } });
             setTimeout(() => { confetti({ particleCount: 150, angle: 60, spread: 80, origin: { x: 0 } }); }, 400);
             setTimeout(() => { confetti({ particleCount: 150, angle: 120, spread: 80, origin: { x: 1 } }); }, 800);
         }"
         class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-950/90 backdrop-blur-xl">
        
        <!-- Glow Backdrop Effect -->
        <div class="absolute inset-0 bg-radial from-amber-500/20 via-indigo-600/10 to-transparent pointer-events-none"></div>

        <div class="bg-gray-900 text-white rounded-3xl shadow-2xl p-6 sm:p-10 max-w-lg w-full border-2 border-amber-400/60 text-center relative overflow-hidden ring-4 ring-amber-400/20">
            
            <!-- Animated Aura Glow behind crest -->
            <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-72 h-72 bg-gradient-to-b from-amber-400/30 to-purple-600/20 rounded-full blur-3xl pointer-events-none animate-pulse"></div>

            <!-- Tier Crest / Badge -->
            <div class="relative z-10 mb-6">
                <div class="w-24 h-24 sm:w-28 sm:h-28 mx-auto rounded-3xl bg-gradient-to-br {{ $tierDetails['gradient'] }} p-1 shadow-2xl shadow-amber-500/40 animate-bounce">
                    <div class="w-full h-full bg-gray-900/90 rounded-[22px] flex items-center justify-center text-5xl sm:text-6xl border border-white/20">
                        {{ $tierDetails['icon'] }}
                    </div>
                </div>
                <!-- Sparkle decorations -->
                <div class="absolute top-0 right-1/4 text-2xl animate-ping opacity-75">✨</div>
                <div class="absolute bottom-2 left-1/4 text-xl animate-pulse">⭐</div>
            </div>

            <div class="relative z-10">
                <div class="inline-block px-4 py-1 rounded-full text-xs font-black uppercase tracking-widest bg-amber-400/20 text-amber-300 border border-amber-400/40 mb-2">
                    ⚡ PROMOSI TIER TERCAPAI ⚡
                </div>
                
                <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-amber-200 via-amber-400 to-yellow-200 mb-2">
                    ASCENSION!
                </h2>

                <h3 class="text-xl sm:text-2xl font-black text-white mb-3">
                    {{ $tierDetails['icon'] }} {{ $tierDetails['name'] }}
                </h3>
                
                <p class="text-xs sm:text-sm text-gray-300 leading-relaxed mb-6 font-medium px-4">
                    Selamat! Kamu telah menembus batas level dan berhasil melakukan <span class="text-amber-300 font-bold">Ascension</span> ke Tier {{ $ascensionData['new_tier'] ?? 1 }}. Levelmu direset ke Level 1 dengan gelar dan reputasi legendaris baru!
                </p>

                <!-- Tier Benefit / Perk Box -->
                <div class="bg-gray-800/80 rounded-2xl p-4 border border-amber-400/30 mb-6 text-left space-y-2">
                    <div class="flex items-center gap-2.5 text-xs text-amber-300 font-bold">
                        <span class="text-base">🛡️</span> Border Profil Eksklusif Tier {{ $tierDetails['name'] }}
                    </div>
                    <div class="flex items-center gap-2.5 text-xs text-gray-300 font-medium">
                        <span class="text-base">🚀</span> Progressive Quality Bonus terbuka
                    </div>
                    <div class="flex items-center gap-2.5 text-xs text-gray-300 font-medium">
                        <span class="text-base">💎</span> Status terdaftar di Legacy Hall of Fame
                    </div>
                </div>

                <button @click="showModal = false" wire:click="closeAscensionModal" type="button" 
                        class="w-full bg-gradient-to-r from-amber-500 via-yellow-500 to-amber-600 hover:from-amber-600 hover:to-yellow-600 text-gray-950 font-black py-4 px-6 rounded-2xl shadow-xl shadow-amber-500/30 transition-all transform active:scale-95 cursor-pointer text-base uppercase tracking-wider">
                    Lanjutkan Perjalanan ⚔️
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- FITUR 6: Co-op Invite Modal -->
    @if($showCoopModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-2xl w-full max-w-md border border-gray-100 dark:border-gray-700">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                    👥 Bagikan Task Co-op ke Teman
                </h3>
                <button wire:click="$set('showCoopModal', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                Pilih teman dari daftar /social milikmu untuk mengerjakan task ini bersama-sama. Saat keduanya selesai, kalian berdua mendapatkan bonus **+25 Shared XP**!
            </p>

            @php
                $friends = auth()->user()->friends;
            @endphp

            @if($friends->count() > 0)
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Pilih Teman Co-op</label>
                    <select wire:model="selectedFriendId" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 p-3 text-sm focus:ring-purple-500">
                        <option value="">-- Pilih Teman --</option>
                        @foreach($friends as $fr)
                            <option value="{{ $fr->id }}">{{ $fr->name }} (LVL {{ $fr->level }})</option>
                        @endforeach
                    </select>
                </div>

                <button wire:click="sendCoopInvitation" class="w-full bg-purple-600 hover:bg-purple-500 text-white font-bold py-3 px-4 rounded-xl shadow-lg transition-all cursor-pointer">
                    Kirim Undangan Co-op 🚀
                </button>
            @else
                <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-xl text-center text-xs text-gray-500 dark:text-gray-400">
                    Kamu belum memiliki teman di <strong>/social</strong>. Tambahkan teman dulu dari halaman Social!
                </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Level Up Modal Notification -->
    @if($showLevelUp)
    <div x-data x-init="if (typeof confetti !== 'undefined') { confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } }); $dispatch('play-sound', { type: 'levelup' }); }" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center border-2 border-indigo-500 animate-[bounce_1s_ease-in-out_infinite]">
            <div class="w-20 h-20 bg-indigo-100 dark:bg-indigo-900/50 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="text-4xl">🎉</span>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Level Up!</h2>
            <p class="text-gray-600 dark:text-gray-300 mb-6">Congratulations! You've reached <strong class="text-indigo-600 dark:text-indigo-400 text-lg">Level {{ $newLevel }}</strong>.</p>
            <button wire:click="closeLevelUp" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-xl transition-colors">
                Awesome!
            </button>
        </div>
    </div>
    @endif

    <!-- Task to Journal Modal -->
    <div x-show="$wire.showJournalModal" class="relative z-50" style="display: none;">
        <div x-show="$wire.showJournalModal" x-transition.opacity class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div @click.away="$wire.showJournalModal = false" x-show="$wire.showJournalModal" x-transition class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-gray-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-gray-100 dark:border-gray-700">
                    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-white flex items-center">
                                <span class="text-2xl mr-2">✍️</span> Task Reflection
                            </h3>
                            <button @click="$wire.showJournalModal = false" class="text-white/70 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <p class="text-purple-100 text-sm mt-1">Complete this task and save it to your journal.</p>
                    </div>
                    
                    <form wire:submit.prevent="saveTaskJournal">
                        <div class="px-6 py-5">
                            <div class="mb-4">
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">How did it go?</label>
                                <textarea wire:model="journalContent" rows="4" placeholder="Tuliskan refleksi singkat atau catatan setelah menyelesaikan tugas ini..." class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-purple-500 resize-none shadow-inner p-3" required></textarea>
                                @error('journalContent') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/50 px-6 py-4 flex flex-col rounded-b-3xl">
                            <button type="submit" class="w-full inline-flex justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-lg hover:bg-indigo-700 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                {{ __('Selesaikan Tugas & Simpan Jurnal') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add / Edit Task Modal -->
    @if($showAddTaskModal)
    <div class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop Overlay -->
        <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" 
             wire:click="$set('showAddTaskModal', false)"></div>

        <div class="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4 text-center">
            <div class="relative transform overflow-hidden rounded-t-3xl sm:rounded-2xl bg-white dark:bg-gray-800 text-left shadow-2xl transition-all w-full sm:max-w-lg border-t sm:border border-gray-100 dark:border-gray-700 z-10 max-h-[92dvh] sm:max-h-[88vh] flex flex-col">
                    <form wire:submit="saveTask" class="flex flex-col max-h-full">
                        <div class="bg-white dark:bg-gray-800 p-5 sm:p-6 max-h-[85dvh] sm:max-h-[80vh] overflow-y-auto hide-scrollbar overscroll-contain">
                            <!-- Title Header with Dynamic Mode -->
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                                    <span class="text-indigo-600 dark:text-indigo-400">{{ $editingTaskId ? '✏️' : '➕' }}</span>
                                    <span>{{ $editingTaskId ? __('Edit Tugas') : __('Tambah Tugas Baru') }}</span>
                                </h3>
                                <button type="button" wire:click="$set('showAddTaskModal', false)" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>

                            <!-- Segmented Mode Switcher -->
                            <div class="bg-gray-100 dark:bg-gray-700/60 p-1 rounded-2xl flex items-center mb-3">
                                <button type="button" wire:click="$set('task_mode', 'sistematis')" 
                                        class="flex-1 py-2 px-3 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer {{ $task_mode === 'sistematis' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                                    <span>🎯</span> {{ __('Sistematis') }}
                                </button>
                                <button type="button" wire:click="$set('task_mode', 'zen')" 
                                        class="flex-1 py-2 px-3 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer {{ $task_mode === 'zen' ? 'bg-white dark:bg-gray-800 text-purple-600 dark:text-purple-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                                    <span>🧘</span> {{ __('Zen Mode') }}
                                </button>
                            </div>

                            <!-- Helpful Mode Explanation Pill -->
                            <div class="mb-4 text-xs rounded-xl px-3 py-2 flex items-start gap-2 {{ $task_mode === 'zen' ? 'bg-purple-50 dark:bg-purple-950/30 text-purple-700 dark:text-purple-300 border border-purple-100 dark:border-purple-900/50' : 'bg-indigo-50 dark:bg-indigo-950/30 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-900/50' }}">
                                <span class="text-sm shrink-0">{{ $task_mode === 'zen' ? '💡' : '📌' }}</span>
                                <span class="leading-relaxed">
                                    @if($task_mode === 'zen')
                                        <strong>Zen Mode:</strong> {{ __('Fokus pada tugas sederhana tanpa jam mulai dan deadline yang kaku.') }}
                                    @else
                                        <strong>{{ __('Mode Sistematis') }}:</strong> {{ __('Atur tugas terstruktur dengan jam mulai, deadline, estimasi menit, dan sub-task.') }}
                                    @endif
                                </span>
                            </div>

                            <!-- Main Task Title Input with Voice Support -->
                            <div class="mb-4" x-data="{
                                listeningModal: false,
                                modalRecogInstance: null,
                                init() {
                                    this.listeningModal = false;
                                    window.addEventListener('livewire:navigating', () => {
                                        this.stopVoiceModal();
                                    });
                                },
                                stopVoiceModal() {
                                    this.listeningModal = false;
                                    if (this.modalRecogInstance) {
                                        try { this.modalRecogInstance.abort(); } catch(e){}
                                        this.modalRecogInstance = null;
                                    }
                                    if (window.__atomaSpeechRecognition) {
                                        try { window.__atomaSpeechRecognition.abort(); } catch(e){}
                                        window.__atomaSpeechRecognition = null;
                                    }
                                },
                                async requestMicPermission() {
                                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                                        return true;
                                    }
                                    try {
                                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                                        stream.getTracks().forEach(track => track.stop());
                                        return true;
                                    } catch (err) {
                                        console.error('Microphone permission error:', err);
                                        return false;
                                    }
                                },
                                async startVoiceModal() {
                                    if (this.listeningModal) {
                                        this.stopVoiceModal();
                                        return;
                                    }
                                    this.stopVoiceModal();

                                    const Speech = window.SpeechRecognition || window.webkitSpeechRecognition;
                                    if (!Speech) {
                                        window.dispatchEvent(new CustomEvent('show-toast', { detail: { title: 'Browser ini belum mendukung Voice Input. Coba gunakan Google Chrome atau Microsoft Edge.', icon: 'error' } }));
                                        return;
                                    }

                                    const hasPermission = await this.requestMicPermission();
                                    if (!hasPermission) {
                                        window.dispatchEvent(new CustomEvent('show-toast', { detail: { title: '⚠️ Izin mikrofon ditolak! Klik ikon gembok 🔒 di address bar browser untuk mengizinkan.', icon: 'warning' } }));
                                        return;
                                    }

                                    try {
                                        const recog = new Speech();
                                        recog.lang = 'id-ID';
                                        recog.continuous = false;
                                        recog.interimResults = true;

                                        this.modalRecogInstance = recog;
                                        window.__atomaSpeechRecognition = recog;

                                        this.listeningModal = true;
                                        window.dispatchEvent(new CustomEvent('show-toast', { detail: { title: '🎙️ Mendengarkan judul tugas...', icon: 'info' } }));

                                        const timeoutTimer = setTimeout(() => {
                                            if (this.listeningModal) {
                                                this.stopVoiceModal();
                                            }
                                        }, 6000);

                                        recog.onresult = (e) => {
                                            let transcript = '';
                                            for (let i = e.resultIndex; i < e.results.length; i++) {
                                                transcript += e.results[i][0].transcript;
                                            }
                                            if (transcript.trim()) {
                                                if (this.$refs.titleInput) {
                                                    this.$refs.titleInput.value = transcript;
                                                    this.$refs.titleInput.dispatchEvent(new Event('input', { bubbles: true }));
                                                }
                                                $wire.set('title', transcript);

                                                if (e.results[e.results.length - 1].isFinal) {
                                                    clearTimeout(timeoutTimer);
                                                    this.stopVoiceModal();
                                                }
                                            }
                                        };

                                        recog.onerror = (err) => {
                                            clearTimeout(timeoutTimer);
                                            this.stopVoiceModal();
                                        };

                                        recog.onend = () => {
                                            clearTimeout(timeoutTimer);
                                            this.stopVoiceModal();
                                        };

                                        recog.start();
                                    } catch (err) {
                                        this.stopVoiceModal();
                                    }
                                }
                            }">
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                                    {{ __('Judul Tugas') }} <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative flex items-center">
                                    <input type="text" wire:model="title" x-ref="titleInput" placeholder="{{ __('Apa yang ingin kamu selesaikan?') }}" class="w-full px-3.5 py-2.5 bg-gray-50 dark:bg-gray-900/70 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm font-medium pr-10 shadow-sm" required>
                                    
                                    <button type="button" 
                                            @click="startVoiceModal()"
                                            :class="listeningModal ? 'bg-red-500 text-white animate-pulse' : 'text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400'"
                                            class="absolute right-1.5 p-1.5 rounded-lg transition-colors cursor-pointer"
                                            :title="listeningModal ? '{{ __('Mendengarkan...') }}' : '🎙️ {{ __('Bicara untuk Judul Tugas') }}'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Description (Optional) -->
                            <div class="mb-4">
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">{{ __('Deskripsi / Catatan (Opsional)') }}</label>
                                <textarea wire:model="description" rows="2" placeholder="{{ __('Tuliskan catatan tambahan atau detail tugas...') }}" class="w-full px-3.5 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/70 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm resize-none"></textarea>
                            </div>

                            <!-- Priority and Category Grid -->
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">{{ __('Prioritas') }}</label>
                                    <select wire:model="priority" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/70 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm font-medium">
                                        <option value="low">🟢 {{ __('Rendah') }}</option>
                                        <option value="normal">🔵 {{ __('Normal') }}</option>
                                        <option value="high">🔴 {{ __('Tinggi') }} 🔥</option>
                                    </select>
                                </div>
                                <div x-data="{
                                    isCustom: false,
                                    selectedOption: @entangle('category').live,
                                    init() {
                                        let standard = ['Olahraga', 'Kerja', 'Belajar', 'Kesehatan', 'Finansial', 'Pribadi'];
                                        let available = @js($this->availableCategories);
                                        let allKnown = [...new Set([...standard, ...available])];
                                        if (this.selectedOption && !allKnown.includes(this.selectedOption)) {
                                            this.isCustom = true;
                                        }
                                        this.$watch('selectedOption', val => {
                                            if (val && !allKnown.includes(val)) {
                                                this.isCustom = true;
                                            }
                                        });
                                    }
                                }">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300">{{ __('Kategori / Tag') }}</label>
                                        <button type="button" 
                                                @click="isCustom = !isCustom; if (isCustom) { $nextTick(() => $refs.customCatInput && $refs.customCatInput.focus()) }" 
                                                class="text-[11px] text-indigo-600 dark:text-indigo-400 font-bold hover:underline cursor-pointer">
                                            <span x-text="isCustom ? '📋 {{ __('Pilih List') }}' : '{{ __('+ Baru') }}'"></span>
                                        </button>
                                    </div>
                                    <div x-show="!isCustom">
                                        <select wire:model.live="category" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/70 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm font-medium">
                                            <option value="">{{ __('-- Tanpa Kategori --') }}</option>
                                            <option value="Olahraga">🏋️ {{ __('Olahraga') }} (Fitness / Workout)</option>
                                            <option value="Kerja">💼 {{ __('Kerja') }} (Work)</option>
                                            <option value="Belajar">📚 {{ __('Belajar') }} (Study)</option>
                                            <option value="Kesehatan">❤️ {{ __('Kesehatan') }} (Health)</option>
                                            <option value="Finansial">💰 {{ __('Finansial') }} (Finance)</option>
                                            <option value="Pribadi">🧘 {{ __('Pribadi') }} (Personal)</option>
                                            @foreach($this->availableCategories as $cat)
                                                @if(!in_array($cat, ['Olahraga', 'Kerja', 'Belajar', 'Kesehatan', 'Finansial', 'Pribadi']))
                                                    <option value="{{ $cat }}">🏷️ {{ $cat }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div x-show="isCustom" style="display: none;">
                                        <input x-ref="customCatInput" type="text" wire:model.live="category" placeholder="{{ __('Tulis kategori baru...') }}" class="w-full px-3 py-2 rounded-xl border border-indigo-300 dark:border-indigo-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm font-medium">
                                    </div>
                                </div>
                            </div>

                            <!-- Milestone / Target Hubungan (Opsional) -->
                            <div class="mb-4">
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <span>🎯</span>
                                        <span>{{ __('Hubungkan ke Milestone (Opsional)') }}</span>
                                    </span>
                                    @if($skill_id)
                                        <span class="text-[10px] text-atoma-blue dark:text-atoma-cyan font-bold">{{ __('Terhubung ke Milestone') }}</span>
                                    @endif
                                </label>
                                <select wire:model="skill_id" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/70 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm font-medium">
                                    <option value="">-- {{ __('Tanpa Milestone (Tugas Bebas)') }} --</option>
                                    @foreach($this->userSkills as $uSkill)
                                        <option value="{{ $uSkill->id }}">🎯 {{ $uSkill->title }} ({{ $uSkill->calculated_progress }}% selesai)</option>
                                    @endforeach
                                </select>
                            </div>


                            <!-- Mode Spesialis Olahraga & Split Rutinitas -->
                            @if(in_array(strtolower($category ?? ''), ['olahraga', 'workout', 'fitness', 'gym', 'calisthenics', 'senam']))
                            <div class="p-4 bg-gradient-to-br from-amber-500/10 via-orange-500/5 to-transparent rounded-2xl border border-amber-300/50 dark:border-amber-700/50 space-y-3.5 mb-4 shadow-xs">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-base">🏋️</span>
                                        <span class="text-xs font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">
                                            {{ __('Mode Spesialis Olahraga & Split Rutinitas') }}
                                        </span>
                                    </div>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300">
                                        {{ __('Dinamis') }}
                                    </span>
                                </div>

                                <!-- Tipe Olahraga Selector -->
                                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 hide-scrollbar">
                                    @foreach([
                                        'gym' => ['label' => 'Gym Split', 'icon' => '🏋️‍♂️'],
                                        'calisthenics' => ['label' => 'Calisthenics', 'icon' => '🤸'],
                                        'cardio' => ['label' => 'Kardio & Lari', 'icon' => '🏃'],
                                        'home' => ['label' => 'Home Workout', 'icon' => '🏠'],
                                        'custom' => ['label' => 'Custom Bebas', 'icon' => '✏️']
                                    ] as $tKey => $tInfo)
                                        <button type="button" 
                                                wire:click="setWorkoutType('{{ $tKey }}')" 
                                                class="px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer flex items-center gap-1.5 {{ $workoutType === $tKey ? 'bg-amber-500 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-amber-50 dark:hover:bg-gray-700' }}">
                                            <span>{{ $tInfo['icon'] }}</span>
                                            <span>{{ __($tInfo['label']) }}</span>
                                        </button>
                                    @endforeach
                                </div>

                                <!-- Split Selector -->
                                @if($workoutType !== 'custom')
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1.5">{{ __('Pilih Split Latihan:') }}</label>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach(array_keys($this->getWorkoutSplits()[$workoutType] ?? []) as $sp)
                                                <button type="button" 
                                                        wire:click="selectWorkoutSplit('{{ $workoutType }}', '{{ $sp }}')" 
                                                        class="px-3 py-1.5 rounded-xl text-xs font-extrabold transition-all cursor-pointer {{ $workoutSplit === $sp ? 'bg-amber-600 text-white shadow-xs ring-2 ring-amber-400/40' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-amber-400' }}">
                                                    {{ $sp }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Gerakan Rekomendasi & Builder -->
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400">
                                            {{ __('Rekomendasi Gerakan (Klik untuk tambah / hapus):') }}
                                        </label>
                                    </div>

                                    @if($workoutType !== 'custom' && isset($this->getWorkoutSplits()[$workoutType][$workoutSplit]))
                                        <div class="flex flex-wrap gap-1.5 mb-2.5">
                                            @foreach($this->getWorkoutSplits()[$workoutType][$workoutSplit] as $recEx)
                                                @php
                                                    $isAlreadySelected = collect($subTasksList)->pluck('title')->contains($recEx);
                                                @endphp
                                                <button type="button" 
                                                        wire:click="toggleWorkoutExerciseInSubtasks('{{ $recEx }}')" 
                                                        class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer flex items-center gap-1 {{ $isAlreadySelected ? 'bg-emerald-500 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-emerald-50 hover:text-emerald-600 border border-dashed border-gray-300 dark:border-gray-600' }}">
                                                    <span>{{ $isAlreadySelected ? '✓' : '+' }}</span>
                                                    <span>{{ $recEx }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif

                                    <!-- Quick Add Custom Exercise -->
                                    <div class="flex items-center gap-2">
                                        <input type="text" 
                                               wire:model="customWorkoutExerciseInput" 
                                               wire:keydown.enter.prevent="addCustomExerciseToTask" 
                                               placeholder="{{ __('+ Tulis gerakan lain sesuka Anda...') }}" 
                                               class="flex-1 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs font-medium text-gray-900 dark:text-white focus:ring-2 focus:ring-amber-500">
                                        <button type="button" 
                                                wire:click="addCustomExerciseToTask" 
                                                class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs transition-all cursor-pointer">
                                            {{ __('Tambah') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <!-- Sistematis Extra Fields (Schedule & Duration) -->
                            @if($task_mode === 'sistematis')
                            <div class="p-3.5 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-200/70 dark:border-gray-700/70 space-y-3 mb-4">
                                <div class="text-[11px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                    <span>⏰</span> {{ __('Jadwal & Durasi Waktu') }}
                                </div>
                                
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Jam Mulai') }}</label>
                                        <input type="time" wire:model="start_time" class="w-full px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Deadline / Selesai') }}</label>
                                        <input type="time" wire:model="deadline" class="w-full px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Estimasi Pengerjaan (Menit)') }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="number" wire:model="estimated_minutes" min="5" step="5" class="w-24 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm font-bold">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('menit (rekomendasi: 25 - 60 mnt)') }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Recurring Checkbox (Sistematis Only) -->
                            <div class="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-200/70 dark:border-gray-700/70 mb-4">
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox" id="is_recurring" wire:model.live="is_recurring" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600 dark:border-gray-600 dark:bg-gray-900">
                                    <span class="text-xs sm:text-sm font-bold text-gray-900 dark:text-gray-200">
                                        🔄 {{ __('Ulangi Tugas Ini (Recurring)') }}
                                    </span>
                                </label>
                                
                                @if($is_recurring)
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 space-y-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Pola Pengulangan') }}</label>
                                        <select wire:model.live="recurrence_pattern" class="w-full px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-xs sm:text-sm">
                                            <option value="daily">{{ __('Setiap Hari (Daily)') }}</option>
                                            <option value="weekly">{{ __('Setiap Minggu (Weekly)') }}</option>
                                            <option value="custom">{{ __('Hari Tertentu (Custom)') }}</option>
                                        </select>
                                    </div>
                                    
                                    @if($recurrence_pattern === 'custom')
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Pilih Hari:') }}</label>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach(['Monday' => 'Sen', 'Tuesday' => 'Sel', 'Wednesday' => 'Rab', 'Thursday' => 'Kam', 'Friday' => 'Jum', 'Saturday' => 'Sab', 'Sunday' => 'Min'] as $day => $dayLabel)
                                                <label class="inline-flex items-center bg-white dark:bg-gray-800 px-2.5 py-1 rounded-lg text-xs font-bold border border-gray-200 dark:border-gray-700 cursor-pointer">
                                                    <input type="checkbox" value="{{ $day }}" wire:model="recurrence_days" class="rounded border-gray-300 text-indigo-600 mr-1.5">
                                                    <span>{{ __($dayLabel) }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif
                                </div>
                                @endif
                            </div>
                            @endif

                            <!-- Sub-Tasks Options -->
                            <div class="pt-3 border-t border-gray-100 dark:border-gray-700">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300">
                                        ☑️ {{ __('Sub-tasks / Daftar Checklist') }}
                                    </label>
                                    <button type="button" wire:click="addSubTaskInput" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 flex items-center font-bold cursor-pointer">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                        {{ __('Tambah Sub-task') }}
                                    </button>
                                </div>

                                @if(empty($subTasksList))
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 italic mb-2">{{ __('Belum ada sub-task. Tambahkan langkah-langkah kecil untuk mempermudah pengerjaan.') }}</p>
                                @endif

                                <div class="space-y-2 max-h-40 overflow-y-auto hide-scrollbar">
                                    @foreach($subTasksList as $index => $st)
                                        <div class="flex items-center gap-2" wire:key="subtask-input-{{ $index }}">
                                            <div class="w-2 h-2 rounded-full bg-indigo-400 shrink-0"></div>
                                            <input type="text" wire:model="subTasksList.{{ $index }}.title" placeholder="{{ __('Tulis langkah sub-task...') }}" class="flex-1 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/70 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base sm:text-sm">
                                            <button type="button" wire:click="removeSubTaskInput({{ $index }})" class="p-1.5 text-gray-400 hover:text-red-500 transition-colors cursor-pointer" title="{{ __('Hapus Sub-task') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-900/80 px-5 py-3.5 flex items-center justify-end gap-2.5 border-t border-gray-100 dark:border-gray-700 rounded-b-2xl">
                            <button type="button" wire:click="$set('showAddTaskModal', false)" class="px-4 py-2 text-xs sm:text-sm font-bold text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                                {{ __('Batal') }}
                            </button>
                            <button type="submit" class="px-5 py-2 text-xs sm:text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-500 shadow-md shadow-indigo-200 dark:shadow-none rounded-xl transition-all cursor-pointer" wire:loading.attr="disabled">
                                {{ $editingTaskId ? __('Simpan Perubahan') : __('Buat Tugas') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Mobile Floating Action Button (FAB) -->
    <div x-show="$store.taskConfig && $store.taskConfig.mobileFab === 'floating'" class="md:hidden fixed bottom-20 right-5 z-40">
        <button @click="$wire.openAddTaskModal(); setTimeout(() => $refs.titleInput ? $refs.titleInput.focus() : null, 50)" 
                class="w-14 h-14 rounded-full bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white shadow-xl shadow-indigo-600/40 flex items-center justify-center text-2xl font-bold cursor-pointer transition-transform">
            <span>+</span>
        </button>
    </div>

    <!-- Task Completion -> Finance Prompt Modal -->
    @if($showFinancePromptModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 w-full max-w-md rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <form wire:submit="saveFinanceFromTask">
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">💰</span>
                                <div>
                                    <h3 class="text-base font-black text-gray-900 dark:text-white">Catat Transaksi Keuangan</h3>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">Tugas selesai: "{{ $financeTaskTitle }}"</p>
                                </div>
                            </div>
                            <button type="button" wire:click="skipFinanceFromTask" class="text-gray-400 hover:text-gray-600 cursor-pointer">✕</button>
                        </div>

                        <!-- Type Selector -->
                        <div class="flex bg-gray-100 dark:bg-gray-900 rounded-2xl p-1">
                            <button type="button" wire:click="$set('financeTaskType', 'expense')" class="flex-1 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $financeTaskType === 'expense' ? 'bg-red-500 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">
                                🔴 Pengeluaran
                            </button>
                            <button type="button" wire:click="$set('financeTaskType', 'income')" class="flex-1 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $financeTaskType === 'income' ? 'bg-emerald-500 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">
                                🟢 Pemasukan
                            </button>
                        </div>

                        <!-- Amount Input -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                Jumlah Nominal (Rp) *
                            </label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-2.5 text-xs font-bold text-gray-400">Rp</span>
                                <input type="number" 
                                       wire:model="financeTaskAmount" 
                                       placeholder="misal: 50000" 
                                       class="w-full pl-10 pr-4 py-2 text-sm font-black rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-indigo-500 text-gray-900 dark:text-white" required autofocus>
                            </div>
                            @error('financeTaskAmount') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <!-- Category Selection -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                Kategori Transaksi
                            </label>
                            <select wire:model="financeTaskCategory" class="w-full px-3.5 py-2 text-xs rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white">
                                <option value="Tagihan">Tagihan & Cicilan</option>
                                <option value="Makanan">Makanan & Minuman</option>
                                <option value="Belanja">Belanja / Kebutuhan</option>
                                <option value="Transportasi">Transportasi / Bensin</option>
                                <option value="Gaji">Gaji & Upah</option>
                                <option value="Freelance">Proyek / Freelance</option>
                                <option value="Investasi">Investasi / Tabungan</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                Keterangan Transaksi
                            </label>
                            <input type="text" wire:model="financeTaskDescription" placeholder="Catatan transaksi..." class="w-full px-3.5 py-2 text-xs rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white">
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-900 px-6 py-3.5 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <button type="button" wire:click="skipFinanceFromTask" class="text-xs font-bold text-gray-500 hover:text-gray-700 dark:text-gray-400 hover:underline cursor-pointer">
                            Lewati (Selesaikan Saja)
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-black text-white bg-emerald-600 hover:bg-emerald-500 shadow-md shadow-emerald-200 dark:shadow-none cursor-pointer">
                            💰 Simpan & Hubungkan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ATOMA AI Task Templates, Decomposer & Community Sharing Modal -->
    @if($showAiTemplateModal)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-gray-950/75 backdrop-blur-sm animate-in fade-in duration-150" aria-modal="true" role="dialog">
            <div class="bg-white dark:bg-gray-800 w-full sm:max-w-4xl rounded-t-3xl sm:rounded-3xl shadow-2xl border-t sm:border border-gray-100 dark:border-gray-700 overflow-hidden flex flex-col max-h-[90dvh] sm:max-h-[88vh] overscroll-contain">
                
                <!-- Modal Header: Signature ATOMA Blue Gradient -->
                <div class="px-5 sm:px-7 py-4 sm:py-5 border-b border-white/10 flex items-center justify-between bg-gradient-to-r from-[#1b3cb8] via-atoma-blue to-[#284fd6] text-white shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-white/15 backdrop-blur-md flex items-center justify-center text-white shadow-xs shrink-0">
                            <x-icon.sparkles class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-black tracking-tight text-white flex items-center gap-2">
                                <span>ATOMA Task Templates & AI Decomposer</span>
                            </h3>
                            <p class="text-xs text-white/80 font-medium hidden sm:block">Alur kerja terstruktur, AI generator cerdas, & template komunitas</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeAiTemplateModal" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-sm font-bold transition-colors cursor-pointer">
                        ✕
                    </button>
                </div>

                @if($showTemplatePreview)
                    <!-- ============================================== -->
                    <!-- SUB-VIEW: INTERACTIVE TASK PREVIEW & CUSTOMIZE -->
                    <!-- ============================================== -->
                    <div class="p-5 sm:p-7 space-y-5 overflow-y-auto hide-scrollbar flex-1 bg-gray-50/50 dark:bg-gray-900/30">
                        <div class="flex items-center justify-between border-b border-gray-200/80 dark:border-gray-700/80 pb-3">
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="$set('showTemplatePreview', false)" class="text-xs font-bold text-atoma-blue hover:underline flex items-center gap-1 cursor-pointer">
                                    &larr; Kembali ke Daftar
                                </button>
                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                <span class="text-xs font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">Pratinjau & Kustomisasi Task</span>
                            </div>
                            <span class="text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan border border-atoma-aqua/40">
                                {{ count($previewTaskData['subtasks'] ?? []) }} Langkah
                            </span>
                        </div>

                        <!-- Editable Fields -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-3">
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Judul Tugas:</label>
                                <input type="text" wire:model="previewTaskData.title" class="w-full text-xs sm:text-sm font-bold rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-atoma-blue">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Kategori:</label>
                                <select wire:model="previewTaskData.category" class="w-full text-xs font-bold rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-atoma-blue">
                                    <option value="Work">Work & Coding</option>
                                    <option value="Study">Study & Skill</option>
                                    <option value="Health">Health & Fitness</option>
                                    <option value="Finance">Finance</option>
                                    <option value="Personal">Personal & Reset</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Prioritas:</label>
                                <select wire:model="previewTaskData.priority" class="w-full text-xs font-bold rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-atoma-blue">
                                    <option value="low">Rendah (Low)</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">Tinggi (Urgent)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Estimasi Waktu (Menit):</label>
                                <input type="number" wire:model="previewTaskData.estimated_minutes" min="5" step="5" class="w-full text-xs font-bold rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-atoma-blue">
                            </div>
                        </div>

                        <!-- Editable Subtasks List -->
                        <div class="space-y-2 bg-white dark:bg-gray-900 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80">
                            <label class="flex items-center gap-1.5 text-xs font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                <x-icon.check-circle class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan" />
                                <span>Langkah Rinci / Subtasks:</span>
                            </label>
                            
                            <div class="space-y-2">
                                @forelse($previewTaskData['subtasks'] ?? [] as $idx => $st)
                                    <div class="flex items-center gap-2 p-2.5 rounded-xl bg-gray-50 dark:bg-gray-800/70 border border-gray-200/60 dark:border-gray-700/60 text-xs">
                                        <span class="w-5 h-5 rounded-lg bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue font-bold flex items-center justify-center text-[10px] shrink-0">
                                            {{ $idx + 1 }}
                                        </span>
                                        <input type="text" wire:model="previewTaskData.subtasks.{{ $idx }}" class="flex-1 bg-transparent border-none p-0 text-xs text-gray-900 dark:text-white focus:ring-0">
                                        <button type="button" wire:click="removePreviewSubtask({{ $idx }})" class="text-rose-500 hover:text-rose-700 p-1 cursor-pointer">
                                            ✕
                                        </button>
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400 italic">Belum ada langkah subtask.</p>
                                @endforelse
                            </div>

                            <!-- Add Subtask Inline -->
                            <div class="flex items-center gap-2 pt-2">
                                <input type="text" wire:model="newSubtaskInput" wire:keydown.enter="addPreviewSubtask" placeholder="Tambah langkah baru..." class="flex-1 text-xs rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-atoma-blue">
                                <button type="button" wire:click="addPreviewSubtask" class="px-3.5 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white text-xs font-bold rounded-xl cursor-pointer">
                                    + Tambah
                                </button>
                            </div>
                        </div>

                        <!-- Target Date Option -->
                        <div class="p-3.5 rounded-2xl bg-atoma-ice/60 dark:bg-atoma-blue/10 border border-atoma-aqua/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300 font-medium">
                                <x-icon.clock class="w-3.5 h-3.5 text-gray-400" />
                                <span>Tanggal Pelaksanaan:</span>
                                <input type="date" wire:model="previewTaskData.date" class="bg-white dark:bg-gray-900 text-xs font-bold rounded-xl border border-gray-300 dark:border-gray-700 px-2.5 py-1.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-atoma-blue">
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="savePreviewAsTemplate(false)" class="inline-flex items-center gap-1.5 text-xs font-bold text-atoma-blue hover:underline cursor-pointer">
                                    <x-icon.folder-plus class="w-3.5 h-3.5" />
                                    <span>Simpan Sebagai Template Saya</span>
                                </button>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex flex-col sm:flex-row items-center justify-end gap-2 pt-2">
                            <button type="button" wire:click="$set('showTemplatePreview', false)" class="w-full sm:w-auto px-4 py-2.5 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                                Batal
                            </button>
                            <button type="button" wire:click="applyPreviewedTask" class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-atoma-blue to-[#284fd6] hover:bg-[#3455dc] text-white font-black text-xs rounded-xl shadow-md shadow-atoma-blue/25 transition-all active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                                <x-icon.rocket class="w-4 h-4 text-white" />
                                <span>Terapkan ke Daftar Task</span>
                            </button>
                        </div>
                    </div>
                @else
                    <!-- ============================================== -->
                    <!-- MAIN TABBED VIEW -->
                    <!-- ============================================== -->

                    <!-- Navigation Tabs Bar -->
                    <div class="px-5 sm:px-7 pt-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200/80 dark:border-gray-700/80 flex items-center gap-2 overflow-x-auto hide-scrollbar shrink-0">
                        <button type="button" 
                                wire:click="setTemplateTab('presets')" 
                                class="px-4 py-2.5 text-xs font-black rounded-t-2xl border-b-2 transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $templateActiveTab === 'presets' ? 'text-atoma-blue border-atoma-blue bg-white dark:bg-gray-800 shadow-xs' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 border-transparent' }}">
                            <x-icon.layers class="w-3.5 h-3.5 {{ $templateActiveTab === 'presets' ? 'text-atoma-blue' : 'text-gray-400' }}" />
                            <span>Presets ATOMA</span>
                        </button>
                        <button type="button" 
                                wire:click="setTemplateTab('ai_decomposer')" 
                                class="px-4 py-2.5 text-xs font-black rounded-t-2xl border-b-2 transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $templateActiveTab === 'ai_decomposer' ? 'text-atoma-blue border-atoma-blue bg-white dark:bg-gray-800 shadow-xs' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 border-transparent' }}">
                            <x-icon.sparkles class="w-3.5 h-3.5 {{ $templateActiveTab === 'ai_decomposer' ? 'text-atoma-blue' : 'text-gray-400' }}" />
                            <span>AI Generator</span>
                        </button>
                        <button type="button" 
                                wire:click="setTemplateTab('global_community')" 
                                class="px-4 py-2.5 text-xs font-black rounded-t-2xl border-b-2 transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $templateActiveTab === 'global_community' ? 'text-atoma-blue border-atoma-blue bg-white dark:bg-gray-800 shadow-xs' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 border-transparent' }}">
                            <x-icon.globe class="w-3.5 h-3.5 {{ $templateActiveTab === 'global_community' ? 'text-atoma-blue' : 'text-gray-400' }}" />
                            <span>Global Komunitas</span>
                        </button>
                        <button type="button" 
                                wire:click="setTemplateTab('my_templates')" 
                                class="px-4 py-2.5 text-xs font-black rounded-t-2xl border-b-2 transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $templateActiveTab === 'my_templates' ? 'text-atoma-blue border-atoma-blue bg-white dark:bg-gray-800 shadow-xs' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 border-transparent' }}">
                            <x-icon.users class="w-3.5 h-3.5 {{ $templateActiveTab === 'my_templates' ? 'text-atoma-blue' : 'text-gray-400' }}" />
                            <span>Template Saya & Teman</span>
                            @if(count($this->receivedSharedTemplates) > 0)
                                <span class="w-4 h-4 rounded-full bg-rose-500 text-white text-[9px] font-bold flex items-center justify-center">
                                    {{ count($this->receivedSharedTemplates) }}
                                </span>
                            @endif
                        </button>
                    </div>

                    <!-- Scrollable Content Body -->
                    <div class="p-5 sm:p-7 space-y-6 overflow-y-auto hide-scrollbar flex-1">

                        <!-- TAB 1: PRESETS ATOMA -->
                        @if($templateActiveTab === 'presets')
                            <div>
                                <!-- Category Filter Pills -->
                                <div class="flex items-center gap-1.5 mb-4 overflow-x-auto hide-scrollbar pb-1">
                                    @php
                                        $catOptions = [
                                            'all' => ['label' => 'Semua', 'icon' => 'sparkles'],
                                            'Work' => ['label' => 'Work & Coding', 'icon' => 'code'],
                                            'Study' => ['label' => 'Study & Skill', 'icon' => 'book-open'],
                                            'Health' => ['label' => 'Health', 'icon' => 'activity'],
                                            'Finance' => ['label' => 'Finance', 'icon' => 'wallet'],
                                            'Personal' => ['label' => 'Personal & Reset', 'icon' => 'sprout'],
                                        ];
                                    @endphp
                                    @foreach($catOptions as $cKey => $cData)
                                        <button type="button" 
                                                wire:click="setTemplateCategoryFilter('{{ $cKey }}')" 
                                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5 {{ $templateCategoryFilter === $cKey ? 'bg-atoma-blue text-white shadow-xs shadow-atoma-blue/30' : 'bg-gray-100 dark:bg-gray-700/60 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                                            <x-dynamic-component :component="'icon.' . $cData['icon']" class="w-3.5 h-3.5 {{ $templateCategoryFilter === $cKey ? 'text-white' : 'text-gray-500 dark:text-gray-400' }}" />
                                            <span>{{ $cData['label'] }}</span>
                                        </button>
                                    @endforeach
                                </div>

                                <!-- Preset Cards Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                                    @foreach($this->getCuratedPresets() as $pKey => $preset)
                                        @if($templateCategoryFilter === 'all' || $preset['category'] === $templateCategoryFilter)
                                            <div class="p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 bg-white dark:bg-gray-900/60 hover:border-atoma-blue dark:hover:border-atoma-cyan transition-all shadow-xs flex flex-col justify-between group">
                                                <div>
                                                    <div class="flex items-center justify-between gap-2 mb-2">
                                                        <span class="inline-flex items-center gap-1.5 text-[10px] font-black px-2.5 py-0.5 rounded-full bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan border border-atoma-aqua/40">
                                                            <x-dynamic-component :component="'icon.' . ($preset['icon'] ?? 'sparkles')" class="w-3 h-3 text-atoma-blue dark:text-atoma-cyan" />
                                                            <span>{{ $preset['badge'] }}</span>
                                                        </span>
                                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-gray-400 dark:text-gray-500">
                                                            <x-icon.clock class="w-3 h-3 text-gray-400" />
                                                            <span>{{ $preset['estimated_minutes'] }}m</span>
                                                        </span>
                                                    </div>

                                                    <h4 class="font-extrabold text-sm text-gray-900 dark:text-white group-hover:text-atoma-blue transition-colors">
                                                        {{ $preset['title'] }}
                                                    </h4>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2 leading-relaxed">
                                                        {{ $preset['description'] }}
                                                    </p>

                                                    <!-- Subtasks Preview Pills -->
                                                    <div class="mt-3 space-y-1">
                                                        @foreach(array_slice($preset['subtasks'], 0, 3) as $st)
                                                            <div class="flex items-center gap-1.5 text-[11px] text-gray-600 dark:text-gray-400">
                                                                <span class="w-1.5 h-1.5 rounded-full bg-atoma-blue shrink-0"></span>
                                                                <span class="truncate">{{ $st }}</span>
                                                            </div>
                                                        @endforeach
                                                        @if(count($preset['subtasks']) > 3)
                                                            <div class="text-[10px] text-gray-400 pl-3 font-medium">
                                                                +{{ count($preset['subtasks']) - 3 }} langkah lainnya
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- Actions -->
                                                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                                                    <button type="button" 
                                                            wire:click="previewPreset('{{ $pKey }}')" 
                                                            class="flex-1 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-atoma-ice dark:hover:bg-atoma-blue/20 text-gray-700 dark:text-gray-300 hover:text-atoma-blue font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center justify-center gap-1.5 text-center">
                                                        <x-icon.pencil class="w-3 h-3" />
                                                        <span>Kustomisasi</span>
                                                    </button>
                                                    <button type="button" 
                                                            wire:click="applyTaskTemplate('{{ $pKey }}')" 
                                                            class="flex-1 py-1.5 bg-atoma-blue hover:bg-[#3455dc] text-white font-bold text-xs rounded-xl shadow-xs transition-all active:scale-95 cursor-pointer flex items-center justify-center gap-1.5 text-center">
                                                        <x-icon.plus class="w-3.5 h-3.5" />
                                                        <span>Pakai</span>
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- TAB 2: AI DECOMPOSER GENERATOR -->
                        @if($templateActiveTab === 'ai_decomposer')
                            <div class="space-y-6">
                                <div class="bg-gradient-to-r from-atoma-ice/60 to-white dark:from-atoma-blue/10 dark:to-gray-800/40 p-5 rounded-3xl border border-atoma-aqua/60 dark:border-atoma-blue/30 space-y-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-2xl bg-atoma-blue/10 dark:bg-atoma-blue/25 border border-atoma-aqua/50 dark:border-atoma-blue/40 flex items-center justify-center text-atoma-blue dark:text-atoma-cyan shadow-xs shrink-0">
                                            <x-icon.bot class="w-5 h-5 text-atoma-blue dark:text-atoma-cyan" />
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-black text-gray-900 dark:text-white">AI Smart Task Decomposer</h4>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Tuliskan ide besar, project, atau target belajarmu. AI akan memecahnya jadi 3-5 subtask presisi.</p>
                                        </div>
                                    </div>

                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <input type="text" 
                                               wire:model="aiDecomposePrompt" 
                                               wire:keydown.enter="decomposeAiTask"
                                               placeholder="Ketik tujuan: misal 'Belajar Docker dari Nol', 'Renovasi Kamar', 'Audit Tagihan'..." 
                                               class="flex-1 px-4 py-3 text-xs sm:text-sm rounded-2xl bg-white dark:bg-gray-900 border border-atoma-aqua/60 dark:border-atoma-blue/40 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-atoma-blue">
                                        <button type="button" 
                                                wire:click="decomposeAiTask" 
                                                wire:loading.attr="disabled"
                                                wire:target="decomposeAiTask"
                                                x-data="{ clicking: false }"
                                                @click="if (clicking) return false; clicking = true; setTimeout(() => clicking = false, 8000);"
                                                class="px-6 py-3 bg-gradient-to-r from-atoma-blue to-[#284fd6] hover:bg-[#3455dc] text-white font-black text-xs sm:text-sm rounded-2xl shadow-md shadow-atoma-blue/30 transition-all active:scale-95 cursor-pointer shrink-0 flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:pointer-events-none"
                                                {{ $isGeneratingAiTask ? 'disabled' : '' }}>
                                            <span wire:loading.remove wire:target="decomposeAiTask" class="inline-flex items-center gap-2">
                                                <x-icon.sparkles class="w-4 h-4 text-white" />
                                                <span>Pecah dengan AI</span>
                                            </span>
                                            <span wire:loading wire:target="decomposeAiTask" class="inline-flex items-center gap-2">
                                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                <span>Menganalisis...</span>
                                            </span>
                                        </button>
                                    </div>

                                    <!-- Suggestion Chips -->
                                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                        <span class="text-[11px] font-bold text-gray-400">Inspirasi Cepat:</span>
                                        @foreach(['Belajar Kubernetes Pods & Deploy', 'Persiapan Ujian Semester', 'Audit Pengeluaran Bulanan', 'Latihan Maraton 5K', 'Bikin Pitch Deck Investor'] as $sug)
                                            <button type="button" 
                                                    wire:click="$set('aiDecomposePrompt', '{{ $sug }}')" 
                                                    class="px-2.5 py-1 rounded-lg bg-white/80 dark:bg-gray-800 text-[11px] font-bold text-gray-700 dark:text-gray-300 border border-gray-200/80 dark:border-gray-700 hover:border-atoma-blue cursor-pointer">
                                                {{ $sug }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- TAB 3: GLOBAL KOMUNITAS -->
                        @if($templateActiveTab === 'global_community')
                            <div class="space-y-4">
                                <!-- Search & Notice Bar -->
                                <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                                    <div class="relative flex-1 w-full">
                                        <input type="text" 
                                               wire:model.live.debounce.300ms="templateSearchQuery" 
                                               placeholder="Cari template komunitas (misal: React, Gym, Skripsi)..." 
                                               class="w-full text-xs rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 pl-9 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-atoma-blue">
                                        <x-icon.search class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" />
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 font-medium shrink-0">
                                        <x-icon.globe class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan" />
                                        <span>{{ count($this->communityTemplates) }} Template Publik</span>
                                    </span>
                                </div>

                                @if(count($this->communityTemplates) === 0)
                                    <div class="text-center py-12 bg-gray-50 dark:bg-gray-900/50 rounded-3xl border border-gray-200 dark:border-gray-700 space-y-2">
                                        <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto text-gray-400">
                                            <x-icon.globe class="w-6 h-6" />
                                        </div>
                                        <h4 class="font-black text-sm text-gray-800 dark:text-white">Belum Ada Template Komunitas yang Cocok</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">Jadilah yang pertama membagikan alur kerja produktifmu! Klik "Simpan Jadi Template" pada tugasmu.</p>
                                    </div>
                                @else
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                                        @foreach($this->communityTemplates as $ct)
                                            <div class="p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 bg-white dark:bg-gray-900/60 hover:border-atoma-blue transition-all shadow-xs flex flex-col justify-between">
                                                <div>
                                                    <div class="flex items-center justify-between gap-2 mb-2">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-6 h-6 rounded-full bg-gradient-to-r from-atoma-blue to-atoma-cyan text-white text-[10px] font-black flex items-center justify-center shrink-0">
                                                                {{ strtoupper(substr($ct->user?->name ?? 'A', 0, 1)) }}
                                                            </div>
                                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 truncate max-w-[120px]">
                                                                {{ $ct->user?->name ?? 'Komunitas' }}
                                                            </span>
                                                        </div>
                                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50">
                                                            <x-icon.flame class="w-3 h-3 text-amber-500" />
                                                            <span>{{ $ct->clones_count }}x Dipakai</span>
                                                        </span>
                                                    </div>

                                                    <h4 class="font-black text-sm text-gray-900 dark:text-white">
                                                        {{ $ct->title }}
                                                    </h4>
                                                    @if($ct->description)
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2 leading-relaxed">
                                                            {{ $ct->description }}
                                                        </p>
                                                    @endif

                                                    <!-- Subtasks Preview -->
                                                    <div class="mt-3 space-y-1">
                                                        @foreach(array_slice((array)$ct->subtasks, 0, 3) as $st)
                                                            <div class="flex items-center gap-1.5 text-[11px] text-gray-600 dark:text-gray-400">
                                                                <span class="w-1.5 h-1.5 rounded-full bg-atoma-blue shrink-0"></span>
                                                                <span class="truncate">{{ $st }}</span>
                                                            </div>
                                                        @endforeach
                                                        @if(count((array)$ct->subtasks) > 3)
                                                            <div class="text-[10px] text-gray-400 pl-3 font-medium">
                                                                +{{ count((array)$ct->subtasks) - 3 }} langkah lainnya
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                                                    <button type="button" 
                                                            wire:click="previewDatabaseTemplate({{ $ct->id }})" 
                                                            class="flex-1 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-atoma-ice text-gray-700 dark:text-gray-300 font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center justify-center gap-1.5 text-center">
                                                        <x-icon.pencil class="w-3 h-3" />
                                                        <span>Kustomisasi</span>
                                                    </button>
                                                    <button type="button" 
                                                            wire:click="applyTaskTemplate({{ $ct->id }})" 
                                                            class="flex-1 py-1.5 bg-atoma-blue hover:bg-[#3455dc] text-white font-bold text-xs rounded-xl shadow-xs transition-all active:scale-95 cursor-pointer flex items-center justify-center gap-1.5 text-center">
                                                        <x-icon.plus class="w-3.5 h-3.5" />
                                                        <span>Pakai</span>
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- TAB 4: TEMPLATE SAYA & TEMAN -->
                        @if($templateActiveTab === 'my_templates')
                            <div class="space-y-6">

                                <!-- Section: Kiriman dari Teman -->
                                @if(count($this->receivedSharedTemplates) > 0)
                                    <div class="space-y-3">
                                        <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-atoma-blue dark:text-atoma-cyan">
                                            <x-icon.gift class="w-4 h-4 text-atoma-blue dark:text-atoma-cyan" />
                                            <span>Template Dibagikan oleh Teman:</span>
                                        </div>

                                        <div class="space-y-2">
                                            @foreach($this->receivedSharedTemplates as $share)
                                                <div class="p-4 rounded-2xl bg-gradient-to-r from-atoma-ice/60 to-white dark:from-atoma-blue/15 dark:to-gray-800/80 border border-atoma-aqua/60 dark:border-atoma-blue/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                                    <div>
                                                        <div class="flex items-center gap-2 text-xs font-bold text-atoma-blue dark:text-atoma-cyan">
                                                            <span>Dari: @ {{ $share->sender?->name }}</span>
                                                            @if($share->note)
                                                                <span class="text-gray-500 font-normal">"{{ $share->note }}"</span>
                                                            @endif
                                                        </div>
                                                        <h4 class="font-extrabold text-sm text-gray-900 dark:text-white mt-1">
                                                            {{ $share->template?->title }}
                                                        </h4>
                                                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                                            {{ count((array)$share->template?->subtasks) }} langkah • {{ $share->template?->estimated_minutes }} menit
                                                        </p>
                                                    </div>

                                                    <button type="button" 
                                                            wire:click="acceptTemplateShare({{ $share->id }})" 
                                                            class="px-4 py-2 bg-atoma-blue hover:bg-[#3455dc] text-white text-xs font-black rounded-xl shadow-xs transition-all active:scale-95 cursor-pointer shrink-0">
                                                        ✓ Terima & Tambahkan ke Jadwal
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Section: Template Pribadi Saya -->
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-xs font-black uppercase tracking-wider text-gray-600 dark:text-gray-400 flex items-center gap-1.5">
                                            <x-icon.folder class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan" />
                                            <span>Template Koleksi Pribadi:</span>
                                        </h4>
                                        <span class="text-xs text-gray-400 font-medium">
                                            {{ count($this->myTemplates) }} Template Tersimpan
                                        </span>
                                    </div>

                                    @if(count($this->myTemplates) === 0)
                                        <div class="text-center py-10 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-200 dark:border-gray-700 space-y-2">
                                            <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto text-gray-400">
                                                <x-icon.file-text class="w-6 h-6" />
                                            </div>
                                            <h4 class="font-bold text-sm text-gray-700 dark:text-gray-300">Belum Ada Template Pribadi</h4>
                                            <p class="text-xs text-gray-500 max-w-sm mx-auto">Gunakan opsi "Simpan Jadi Template" pada tugas harianmu untuk menyimpannya sebagai template berulang di sini.</p>
                                        </div>
                                    @else
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                                            @foreach($this->myTemplates as $myTpl)
                                                <div class="p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 bg-white dark:bg-gray-900/60 flex flex-col justify-between">
                                                    <div>
                                                        <div class="flex items-center justify-between gap-2 mb-2">
                                                            <button type="button" 
                                                                    wire:click="togglePublicTemplate({{ $myTpl->id }})" 
                                                                    class="inline-flex items-center gap-1 text-[10px] font-black px-2 py-0.5 rounded-full cursor-pointer transition-colors {{ $myTpl->is_public ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200' }}">
                                                                @if($myTpl->is_public)
                                                                    <x-icon.globe class="w-2.5 h-2.5 text-emerald-700 dark:text-emerald-300" />
                                                                    <span>Publik (Global)</span>
                                                                @else
                                                                    <x-icon.lock class="w-2.5 h-2.5 text-gray-500 dark:text-gray-400" />
                                                                    <span>Pribadi</span>
                                                                @endif
                                                            </button>

                                                            <div class="flex items-center gap-1.5">
                                                                <button type="button" 
                                                                        wire:click="openShareWithFriendModal({{ $myTpl->id }})" 
                                                                        class="p-1.5 text-atoma-blue hover:text-blue-700 hover:bg-atoma-ice rounded-lg transition-colors cursor-pointer"
                                                                        title="Kirim ke Teman">
                                                                    <x-icon.share-2 class="w-3.5 h-3.5" />
                                                                </button>
                                                                <button type="button" 
                                                                        wire:click="deleteCustomTemplate({{ $myTpl->id }})" 
                                                                        wire:confirm="Hapus template ini?" 
                                                                        class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                                        title="Hapus">
                                                                    <x-icon.trash class="w-3.5 h-3.5" />
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <h4 class="font-extrabold text-sm text-gray-900 dark:text-white">
                                                            {{ $myTpl->title }}
                                                        </h4>
                                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                                            {{ count((array)$myTpl->subtasks) }} langkah • {{ $myTpl->estimated_minutes }} menit • {{ $myTpl->category }}
                                                        </p>
                                                    </div>

                                                    <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                                                        <button type="button" 
                                                                wire:click="previewDatabaseTemplate({{ $myTpl->id }})" 
                                                                class="flex-1 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-atoma-ice text-gray-700 dark:text-gray-300 font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center justify-center gap-1.5 text-center">
                                                            <x-icon.pencil class="w-3 h-3" />
                                                            <span>Kustomisasi</span>
                                                        </button>
                                                        <button type="button" 
                                                                wire:click="applyTaskTemplate({{ $myTpl->id }})" 
                                                                class="flex-1 py-1.5 bg-atoma-blue hover:bg-[#3455dc] text-white font-bold text-xs rounded-xl shadow-xs transition-all active:scale-95 cursor-pointer flex items-center justify-center gap-1.5 text-center">
                                                            <x-icon.plus class="w-3.5 h-3.5" />
                                                            <span>Pakai</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                            </div>
                        @endif

                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-gray-50 dark:bg-gray-900 px-6 py-3.5 border-t border-gray-200/80 dark:border-gray-700/80 flex items-center justify-between shrink-0">
                        <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400 font-medium hidden sm:inline-flex">
                            <x-icon.info class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan shrink-0" />
                            <span>Tip: Kamu bisa membuat template dari tugas mana saja melalui menu tugas "Simpan Jadi Template".</span>
                        </span>
                        <button type="button" wire:click="closeAiTemplateModal" class="px-5 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800 cursor-pointer ml-auto">
                            Tutup
                        </button>
                    </div>
                @endif

            </div>
        </div>
    @endif

    <!-- MODAL: Share Template to Friend -->
    @if($showShareModal)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 max-w-md w-full shadow-2xl border border-gray-200 dark:border-gray-700 space-y-4 animate-in fade-in zoom-in duration-150">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan flex items-center justify-center shrink-0">
                            <x-icon.gift class="w-4 h-4" />
                        </div>
                        <h3 class="text-base font-black text-gray-900 dark:text-white">Bagikan Template ke Teman</h3>
                    </div>
                    <button type="button" wire:click="closeShareWithFriendModal" class="text-gray-400 hover:text-gray-600 text-base cursor-pointer">✕</button>
                </div>

                <div class="p-3 bg-atoma-ice/60 dark:bg-atoma-blue/15 rounded-2xl border border-atoma-aqua/40 text-xs">
                    <span class="text-gray-400 font-medium">Template:</span>
                    <strong class="text-atoma-blue dark:text-atoma-cyan block truncate">{{ $selectedTemplateToShareTitle }}</strong>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Pilih Teman:</label>
                    <select wire:model="shareFriendId" class="w-full text-xs font-bold rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-atoma-blue">
                        <option value="">-- Pilih dari daftar pertemanan --</option>
                        @foreach($this->userFriends as $fr)
                            <option value="{{ $fr->id }}">{{ $fr->name }} ({{ $fr->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Pesan Tambahan (Opsional):</label>
                    <input type="text" wire:model="shareFriendNote" placeholder="misal: Coba alur sprint ini, cocok banget buat proyek kita!" class="w-full text-xs rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-atoma-blue">
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <button type="button" wire:click="closeShareWithFriendModal" class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-300 font-bold text-xs rounded-xl cursor-pointer">
                        Batal
                    </button>
                    <button type="button" wire:click="sendTemplateToFriend" class="flex-1 py-2.5 bg-atoma-blue hover:bg-[#3455dc] text-white font-bold text-xs rounded-xl shadow-md shadow-atoma-blue/25 transition-all cursor-pointer">
                        Kirim Template
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: Active Workout Live Session Tracker (Redesigned per User Requirements & Atoma Design System) -->
    @if($showWorkoutModal)
        <div x-data="{
            isMinimized: false,
            elapsedSeconds: @js($activeWorkoutDurationSeconds ?? 0),
            timerInterval: null,
            restSecondsLeft: 0,
            restTotalSeconds: 90,
            restTimerRunning: false,
            restInterval: null,
            presetMuscle: 'Dada',
            showPresetPicker: false,
            customExerciseName: '',
            searchQuery: '',
            exerciseLibrary: {
                'Dada': ['Bench Press (Barbell)', 'Incline Dumbbell Press', 'Dumbbell Chest Fly', 'Push Up', 'Parallel Bar Dips', 'Cable Chest Fly'],
                'Punggung': ['Pull Up', 'Barbell Row', 'Lat Pulldown', 'Deadlift', 'Seated Cable Row', 'Chin Up', 'T-Bar Row'],
                'Kaki': ['Barbell Squat', 'Leg Press', 'Romanian Deadlift', 'Bulgarian Split Squat', 'Calf Raise', 'Leg Extension', 'Hamstring Curl'],
                'Bahu': ['Overhead Shoulder Press', 'Dumbbell Lateral Raise', 'Face Pull', 'Front Raise', 'Pike Push Up', 'Reverse Pec Deck'],
                'Lengan': ['Bicep Barbell Curl', 'Dumbbell Hammer Curl', 'Tricep Rope Pushdown', 'Skull Crusher', 'Preacher Curl', 'Dips (Trisep)'],
                'Perut': ['Plank', 'Hanging Leg Raise', 'Ab Wheel Rollout', 'Russian Twist', 'L-Sit', 'Bicycle Crunch'],
                'Kardio': [
                    'Lari Santai 5K (Easy Run)',
                    'Lari Pagi 30 Menit (Easy Jog)',
                    'Interval Run (4x400m Sprint Laps)',
                    'Tempo Run (20 Menit Threshold)',
                    'Treadmill Running (Steady State)',
                    'Lari Jarak Jauh 10K (Long Run)',
                    'HIIT Sprints (8 Rounds Interval)',
                    'Jump Rope Cardio (15 Menit)',
                    'Bersepeda Outdoor (Cycling)'
                ]
            },
            init() {
                this.elapsedSeconds = @js($activeWorkoutDurationSeconds ?? 0);
                if (this.timerInterval) clearInterval(this.timerInterval);
                this.timerInterval = setInterval(() => {
                    this.elapsedSeconds++;
                }, 1000);

                window.addEventListener('start-rest-timer', (e) => {
                    let secs = e.detail && e.detail.seconds ? e.detail.seconds : 90;
                    this.startRestTimer(secs);
                });
            },
            formatTime(secs) {
                let m = Math.floor(secs / 60);
                let s = secs % 60;
                return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            },
            startRestTimer(secs) {
                this.restTotalSeconds = secs;
                this.restSecondsLeft = secs;
                this.restTimerRunning = true;
                if (this.restInterval) clearInterval(this.restInterval);
                this.restInterval = setInterval(() => {
                    if (this.restSecondsLeft > 0) {
                        this.restSecondsLeft--;
                    } else {
                        this.finishRestTimer();
                    }
                }, 1000);
            },
            addRestTime(secs) {
                this.restSecondsLeft = Math.max(0, this.restSecondsLeft + secs);
            },
            skipRestTimer() {
                this.restTimerRunning = false;
                if (this.restInterval) clearInterval(this.restInterval);
            },
            finishRestTimer() {
                this.skipRestTimer();
                window.dispatchEvent(new CustomEvent('play-sound', { detail: { type: 'complete' } }));
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Waktu istirahat selesai! Siap untuk set berikutnya.', type: 'success' } }));
                if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
            },
            destroy() {
                if (this.timerInterval) clearInterval(this.timerInterval);
                if (this.restInterval) clearInterval(this.restInterval);
            }
        }">
            <!-- Full Workout Modal (when NOT minimized) -->
            <div x-show="!isMinimized" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4 bg-gray-950/85 backdrop-blur-md overflow-hidden"
                 aria-modal="true" role="dialog">
                
                <div class="bg-gray-50 dark:bg-[#0b0f19] sm:rounded-3xl max-w-2xl w-full h-full sm:h-auto sm:max-h-[92dvh] shadow-2xl border-0 sm:border border-gray-200 dark:border-gray-800 flex flex-col overflow-hidden relative animate-in fade-in zoom-in-95 duration-200">
                
                <!-- Header (Top Bar) -->
                <div class="px-4 sm:px-6 py-3.5 border-b border-gray-100 dark:border-gray-800/80 flex items-center justify-between bg-white dark:bg-gray-900 shrink-0 shadow-xs">
                    <!-- Left: Checkmark & Sesi Title -->
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-7 h-7 rounded-xl bg-orange-500/10 text-orange-500 flex items-center justify-center font-black text-xs shrink-0">
                            <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm sm:text-base font-black text-gray-900 dark:text-white truncate">
                                {{ $activeWorkoutTitle }}
                            </h3>
                            @if($activeWorkoutTaskTitle)
                                <span class="text-[10px] font-bold text-indigo-500 dark:text-indigo-400 truncate block">
                                    Tugas: {{ $activeWorkoutTaskTitle }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Right: Live Metric Chips & Finish Button -->
                    <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                        @php
                            $liveMetrics = $this->getLiveWorkoutSummaryMetrics();
                        @endphp

                        @if($liveMetrics['has_cardio'] && !$liveMetrics['has_strength'])
                            <!-- Hanya Kardio / Lari: Tampilkan Total Jarak -->
                            <span class="inline-flex items-center gap-1 sm:gap-1.5 px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-xl bg-cyan-50 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 text-xs font-black border border-cyan-200/60 dark:border-cyan-800/60 shadow-xs" title="Total Jarak Kardio">
                                <svg class="w-3.5 h-3.5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="16" cy="4" r="2" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21l3-6 4 2 3-5M10 9l3 2-2 4M6 11l4-2 3 3"></path></svg>
                                <span>{{ number_format($liveMetrics['distance_km'], 2, ',', '.') }} km</span>
                            </span>
                        @elseif($liveMetrics['has_strength'] && !$liveMetrics['has_cardio'])
                            <!-- Hanya Angkat Beban / Kalistenik: Tampilkan Total Set Selesai -->
                            <span class="inline-flex items-center gap-1 sm:gap-1.5 px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 text-xs font-black border border-amber-200/60 dark:border-amber-800/60 shadow-xs" title="Total Set Latihan Selesai">
                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 5v14M18 5v14M3 8v8M21 8v8M6 12h12"></path></svg>
                                <span>{{ $liveMetrics['completed_strength_sets'] }} Set</span>
                            </span>
                        @elseif($liveMetrics['has_cardio'] && $liveMetrics['has_strength'])
                            <!-- Keduanya: Tampilkan Total Jarak & Total Set -->
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-xl bg-cyan-50 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 text-xs font-black border border-cyan-200/60 dark:border-cyan-800/60 shadow-xs" title="Total Jarak Kardio">
                                <svg class="w-3.5 h-3.5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="16" cy="4" r="2" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21l3-6 4 2 3-5M10 9l3 2-2 4M6 11l4-2 3 3"></path></svg>
                                <span>{{ number_format($liveMetrics['distance_km'], 2, ',', '.') }} km</span>
                            </span>
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 text-xs font-black border border-amber-200/60 dark:border-amber-800/60 shadow-xs" title="Total Set Selesai">
                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 5v14M18 5v14M3 8v8M21 8v8M6 12h12"></path></svg>
                                <span>{{ $liveMetrics['completed_strength_sets'] }} Set</span>
                            </span>
                        @else
                            <!-- Default: Belum ada latihan -->
                            <span class="inline-flex items-center gap-1 sm:gap-1.5 px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-xs font-black" title="Total Set Selesai">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 5v14M18 5v14M3 8v8M21 8v8M6 12h12"></path></svg>
                                <span>0 Set</span>
                            </span>
                        @endif

                        <!-- Finish Button with SVG (Zero Emojis) -->
                        <button type="button" 
                                @click="destroy(); $wire.finishWorkout(elapsedSeconds);" 
                                class="px-3.5 py-1.5 sm:px-4 sm:py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-black text-xs shadow-md shadow-emerald-500/20 transition-all cursor-pointer flex items-center gap-1.5 shrink-0">
                            <span>Selesai</span>
                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 3v18M5 4h14l-2 5 2 5H5"></path></svg>
                        </button>

                        <!-- Minimize Button -->
                        <button type="button" 
                                @click="isMinimized = true" 
                                class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-gray-900 dark:hover:text-white flex items-center justify-center transition-all cursor-pointer text-xs"
                                title="Kecilkan (Minimize)">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                        </button>

                        <!-- Close / Batal Button -->
                        <button type="button" 
                                @click="destroy(); $wire.closeWorkoutModal();" 
                                class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-400 hover:text-red-500 dark:hover:text-red-400 flex items-center justify-center transition-all cursor-pointer text-xs"
                                title="Tutup Sesi">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Exercise List Body -->
                <div class="p-3.5 sm:p-5 overflow-y-auto space-y-4 flex-1 overscroll-contain pb-28">
                    
                    @forelse($workoutExercises as $eIdx => $exercise)
                        @php
                            $isCardio = (($exercise['exercise_type'] ?? '') === 'cardio' || 
                                         ($exercise['unit'] ?? '') === 'km' || 
                                         $this->isCardioExercise($exercise['name'] ?? '', $exercise['muscle'] ?? null));
                            $cardioMode = $exercise['cardio_mode'] ?? 'standard';
                            $displayRekor = $exercise['previous_best'] ?? '';
                            if (empty($displayRekor) || ($isCardio && str_contains($displayRekor, 'kg'))) {
                                $displayRekor = $this->getExercisePreviousBestText($exercise['name'] ?? '', $isCardio);
                            }
                        @endphp

                        <!-- Exercise Card (Modular Design per User Requirements) -->
                        <div class="bg-white dark:bg-gray-900 rounded-3xl p-4 sm:p-5 border border-gray-100 dark:border-gray-800 shadow-xs space-y-3.5 relative hover:shadow-md transition-shadow">
                            
                            <!-- Card Header: Title, Muscle Pill, Mode/Unit Switcher & Options Menu (···) -->
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-base sm:text-lg font-black text-gray-900 dark:text-white tracking-tight">
                                            {{ $exercise['name'] }}
                                        </span>
                                        <span class="px-2 py-0.5 text-[9px] font-black rounded-lg bg-orange-100 dark:bg-orange-950/60 text-orange-700 dark:text-orange-300 uppercase tracking-wider">
                                            {{ $exercise['muscle'] ?? ($isCardio ? 'Kardio' : 'Full Body') }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-gray-400 dark:text-gray-500 font-medium mt-0.5">
                                        <span>Rekor: <span class="font-bold text-gray-600 dark:text-gray-300">{{ !empty($displayRekor) ? $displayRekor : 'Sesi perdana' }}</span></span>
                                    </div>
                                </div>

                                <!-- Right Controls: Unit Switcher (for Strength) & Menu (···) -->
                                <div class="flex items-center gap-2 shrink-0">
                                    @if(!$isCardio)
                                        <!-- Strength Unit Switcher Pill: kg / lbs / BW -->
                                        <div class="flex items-center bg-gray-100 dark:bg-gray-800 p-0.5 rounded-xl border border-gray-200/60 dark:border-gray-700/60 text-[10px] font-black">
                                            <button type="button" 
                                                    wire:click="setExerciseUnit({{ $eIdx }}, 'kg')" 
                                                    class="px-2 py-0.5 rounded-lg transition-all cursor-pointer {{ ($exercise['unit'] ?? 'kg') === 'kg' ? 'bg-orange-500 text-white shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                                                    title="Satuan Kilogram">
                                                kg
                                            </button>
                                            <button type="button" 
                                                    wire:click="setExerciseUnit({{ $eIdx }}, 'lbs')" 
                                                    class="px-2 py-0.5 rounded-lg transition-all cursor-pointer {{ ($exercise['unit'] ?? 'kg') === 'lbs' ? 'bg-orange-500 text-white shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                                                    title="Satuan Pounds">
                                                lbs
                                            </button>
                                            <button type="button" 
                                                    wire:click="setExerciseUnit({{ $eIdx }}, 'bw')" 
                                                    class="px-2 py-0.5 rounded-lg transition-all cursor-pointer {{ ($exercise['unit'] ?? 'kg') === 'bw' ? 'bg-orange-500 text-white shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                                                    title="Bodyweight (Berat Badan Sendiri)">
                                                BW
                                            </button>
                                        </div>
                                    @endif

                                    <!-- Action Menu (···) -->
                                    <div class="relative" x-data="{ openMenu: false }">
                                        <button type="button" 
                                                @click="openMenu = !openMenu" 
                                                class="w-8 h-8 rounded-xl flex items-center justify-center text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all cursor-pointer">
                                            <span class="text-sm font-black">···</span>
                                        </button>
                                        
                                        <div x-show="openMenu" 
                                             @click.away="openMenu = false" 
                                             class="absolute right-0 mt-1 w-44 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 py-1.5 z-20 text-xs font-bold" 
                                             style="display: none;">
                                            <button type="button" 
                                                    wire:click="prepareSwapExercise({{ $eIdx }})" 
                                                    @click="openMenu = false; showPresetPicker = true;" 
                                                    class="w-full px-3 py-2 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2 cursor-pointer">
                                                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                                <span>Ganti Gerakan</span>
                                            </button>
                                            <button type="button" 
                                                    wire:click="removeExerciseFromSession({{ $eIdx }})" 
                                                    @click="openMenu = false" 
                                                    class="w-full px-3 py-2 text-left text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 flex items-center gap-2 cursor-pointer">
                                                <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                <span>Hapus Latihan</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Set / Lap Table -->
                            <div class="w-full">
                                @if($isCardio)
                                    <!-- Cardio Table Header: Set | Jarak (km) | Waktu (mm:ss) | Pace | Aksi -->
                                    <div class="grid grid-cols-12 gap-2 text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 pb-2 px-1 border-b border-gray-100 dark:border-gray-800">
                                        <span class="col-span-2 text-left">SET</span>
                                        <span class="col-span-3 text-center">JARAK (KM)</span>
                                        <span class="col-span-3 text-center">WAKTU</span>
                                        <span class="col-span-2 text-center">PACE</span>
                                        <span class="col-span-2 text-right">AKSI</span>
                                    </div>

                                    <!-- Cardio Rows -->
                                    <div class="divide-y divide-gray-50 dark:divide-gray-800/60">
                                        @foreach($exercise['sets'] as $sIdx => $set)
                                            <div class="grid grid-cols-12 gap-2 items-center py-2 px-1 rounded-xl transition-colors {{ !empty($set['is_completed']) ? 'bg-cyan-50/40 dark:bg-cyan-950/20' : '' }}">
                                                <!-- Lap/Set Number Button -->
                                                <div class="col-span-2 flex items-center gap-1">
                                                    <button type="button" 
                                                            wire:click="cycleSetType({{ $eIdx }}, {{ $sIdx }})" 
                                                            class="w-7 h-7 rounded-xl text-xs font-black flex items-center justify-center transition-all cursor-pointer {{ !empty($set['is_completed']) ? 'bg-cyan-500 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                                                            title="Tipe: {{ $set['set_type'] ?? 'normal' }}">
                                                        {{ $set['set_number'] ?? ($sIdx + 1) }}
                                                    </button>
                                                    @if(($set['set_type'] ?? 'normal') === 'warmup')
                                                        <span class="text-[8px] font-black text-amber-500 bg-amber-100 dark:bg-amber-950 px-1 py-0.5 rounded leading-none">W</span>
                                                    @elseif(($set['set_type'] ?? 'normal') === 'interval')
                                                        <span class="text-[8px] font-black text-purple-500 bg-purple-100 dark:bg-purple-950 px-1 py-0.5 rounded leading-none">INT</span>
                                                    @elseif(($set['set_type'] ?? 'normal') === 'tempo')
                                                        <span class="text-[8px] font-black text-orange-500 bg-orange-100 dark:bg-orange-950 px-1 py-0.5 rounded leading-none">TMP</span>
                                                    @endif
                                                </div>

                                                <!-- Jarak (km) Column -->
                                                <div class="col-span-3 flex items-center justify-center">
                                                    @if(!empty($set['is_completed']))
                                                        <div class="text-xs font-black text-gray-900 dark:text-white">
                                                            {{ (float)($set['distance_km'] ?? ($set['weight_kg'] > 0 && $set['weight_kg'] <= 50 && $set['weight_kg'] != 20 ? $set['weight_kg'] : 5.0)) }} <span class="text-[10px] text-gray-400 font-bold">km</span>
                                                        </div>
                                                    @else
                                                        <div class="flex items-center gap-1 justify-center">
                                                            <input type="number" 
                                                                   step="0.01" 
                                                                   min="0" 
                                                                   wire:model.lazy="workoutExercises.{{ $eIdx }}.sets.{{ $sIdx }}.distance_km" 
                                                                   wire:change="recalculateCardioPace({{ $eIdx }}, {{ $sIdx }})" 
                                                                   placeholder="5.0" 
                                                                   class="w-13 sm:w-16 text-center font-bold text-xs py-1 px-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500">
                                                            <span class="text-[10px] font-bold text-gray-400">km</span>
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- Waktu Column -->
                                                <div class="col-span-3 flex items-center justify-center">
                                                    @if(!empty($set['is_completed']))
                                                        <div class="text-xs font-black text-gray-900 dark:text-white font-mono">
                                                            {{ !empty($set['duration_formatted']) ? $set['duration_formatted'] : ((!empty($set['duration_seconds']) && $set['duration_seconds'] > 0) ? sprintf('%02d:%02d', floor($set['duration_seconds'] / 60), $set['duration_seconds'] % 60) : '30:00') }}
                                                        </div>
                                                    @else
                                                        <div class="flex items-center gap-1 justify-center">
                                                            <input type="text" 
                                                                   wire:model.lazy="workoutExercises.{{ $eIdx }}.sets.{{ $sIdx }}.duration_formatted" 
                                                                   wire:change="recalculateCardioPace({{ $eIdx }}, {{ $sIdx }})" 
                                                                   placeholder="25:00" 
                                                                   class="w-14 sm:w-16 text-center font-mono font-bold text-xs py-1 px-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500"
                                                                   title="Format mm:ss atau menit">
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- Pace Column (Auto-calculated / Dynamic) -->
                                                <div class="col-span-2 flex items-center justify-center">
                                                    @php
                                                        $paceDisplay = !empty($set['pace']) && $set['pace'] !== '-' ? $set['pace'] : (!empty($set['distance_km']) && !empty($set['duration_seconds']) ? $this->calculatePaceString($set['distance_km'], $set['duration_seconds']) : '-');
                                                    @endphp
                                                    <span class="px-1.5 py-0.5 rounded-md bg-orange-100 dark:bg-orange-950/60 text-orange-700 dark:text-orange-300 font-mono font-black text-[10px]">
                                                        {{ $paceDisplay !== '-' ? $paceDisplay . '/km' : '-' }}
                                                    </span>
                                                </div>

                                                <!-- Aksi Column -->
                                                <div class="col-span-2 flex items-center justify-end gap-1.5">
                                                    @if(!empty($set['is_completed']))
                                                        <button type="button" 
                                                                wire:click="toggleSetCompleted({{ $eIdx }}, {{ $sIdx }})" 
                                                                class="h-7 px-2 rounded-lg bg-cyan-500 hover:bg-cyan-600 text-white flex items-center gap-1 text-xs font-black shadow-xs transition-all cursor-pointer shrink-0"
                                                                title="Lap selesai (Klik untuk mengedit kembali)">
                                                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                                            @if(!empty($set['is_pr']))
                                                                <svg class="w-3 h-3 text-amber-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 21h8m-4-4v4m-5-8a5 5 0 0110 0v1a5 5 0 01-10 0v-1zm0 0H5a2 2 0 01-2-2V8a2 2 0 012-2h2m10 5h2a2 2 0 002-2V8a2 2 0 00-2-2h-2"></path></svg>
                                                            @endif
                                                        </button>
                                                    @else
                                                        <button type="button" 
                                                                wire:click="toggleSetCompleted({{ $eIdx }}, {{ $sIdx }})" 
                                                                class="w-7 h-7 rounded-lg bg-orange-500 hover:bg-orange-600 text-white flex items-center justify-center text-xs font-black shadow-xs transition-all cursor-pointer shrink-0"
                                                                title="Centang untuk selesaikan lap/set">
                                                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                                        </button>
                                                    @endif

                                                    @if(count($exercise['sets']) > 1)
                                                        <button type="button" 
                                                                wire:click="removeSetFromExercise({{ $eIdx }}, {{ $sIdx }})" 
                                                                class="w-6 h-6 flex items-center justify-center rounded-lg text-gray-300 dark:text-gray-600 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 transition-all cursor-pointer"
                                                                title="Hapus baris set">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                        </button>
                                                    @else
                                                        <span class="w-6"></span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <!-- Inline + Buttons below cardio table: Standard Set and Interval Option -->
                                    <div class="pt-2 flex items-center gap-2 sm:gap-3 flex-wrap">
                                        <button type="button" 
                                                wire:click="addSetToExercise({{ $eIdx }})" 
                                                class="text-orange-500 hover:text-orange-600 dark:text-orange-400 text-xs font-black py-1 px-1 cursor-pointer flex items-center gap-1.5 hover:translate-x-0.5 transition-transform">
                                            <span class="text-base leading-none font-bold">+</span>
                                            <span>Tambah Set</span>
                                        </button>

                                        <span class="text-gray-300 dark:text-gray-700 text-xs">|</span>

                                        <button type="button" 
                                                wire:click="addIntervalSetToExercise({{ $eIdx }})" 
                                                class="text-purple-600 hover:text-purple-700 dark:text-purple-400 text-xs font-black py-1 px-2 rounded-lg bg-purple-50 dark:bg-purple-950/40 border border-purple-200/60 dark:border-purple-800/40 hover:bg-purple-100 dark:hover:bg-purple-900/60 cursor-pointer flex items-center gap-1.5 transition-all shadow-2xs"
                                                title="Tambah Set Interval Sprint (Default: 0.4 km • 01:30)">
                                            <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                            <span>+ Interval (0.4k • 1.30m)</span>
                                        </button>
                                    </div>
                                @else
                                    <!-- Strength Set Table: Set | Beban | Reps | Aksi -->
                                    <div class="grid grid-cols-12 gap-2 text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 pb-2 px-1 border-b border-gray-100 dark:border-gray-800">
                                        <span class="col-span-2 text-left">Set</span>
                                        <span class="col-span-4 text-center">Beban ({{ strtoupper($exercise['unit'] ?? 'kg') }})</span>
                                        <span class="col-span-3 text-center">Reps</span>
                                        <span class="col-span-3 text-right">Aksi</span>
                                    </div>

                                    <!-- Strength Set Rows -->
                                    <div class="divide-y divide-gray-50 dark:divide-gray-800/60">
                                        @foreach($exercise['sets'] as $sIdx => $set)
                                            <div class="grid grid-cols-12 gap-2 items-center py-2 px-1 rounded-xl transition-colors {{ !empty($set['is_completed']) ? 'bg-emerald-50/40 dark:bg-emerald-950/20' : '' }}">
                                                
                                                <!-- Set Column: Number + Type Badge -->
                                                <div class="col-span-2 flex items-center gap-1">
                                                    <button type="button" 
                                                            wire:click="cycleSetType({{ $eIdx }}, {{ $sIdx }})" 
                                                            class="w-7 h-7 rounded-xl text-xs font-black flex items-center justify-center transition-all cursor-pointer {{ !empty($set['is_completed']) ? 'bg-emerald-500 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                                                            title="Klik untuk ganti tipe set (Normal / Warmup / Drop / Failure)">
                                                        {{ $set['set_number'] ?? ($sIdx + 1) }}
                                                    </button>
                                                    @if(($set['set_type'] ?? 'normal') === 'warmup')
                                                        <span class="text-[8px] font-black text-amber-500 bg-amber-100 dark:bg-amber-950 px-1 py-0.5 rounded leading-none">W</span>
                                                    @elseif(($set['set_type'] ?? 'normal') === 'drop')
                                                        <span class="text-[8px] font-black text-purple-500 bg-purple-100 dark:bg-purple-950 px-1 py-0.5 rounded leading-none">D</span>
                                                    @elseif(($set['set_type'] ?? 'normal') === 'failure')
                                                        <span class="text-[8px] font-black text-red-500 bg-red-100 dark:bg-red-950 px-1 py-0.5 rounded leading-none">F</span>
                                                    @endif
                                                </div>

                                                <!-- Beban Column -->
                                                <div class="col-span-4 flex items-center justify-center">
                                                    @if(!empty($set['is_completed']))
                                                        <div class="text-xs font-black text-gray-900 dark:text-white">
                                                            @if(($exercise['unit'] ?? 'kg') === 'bw')
                                                                {{ (float)$set['weight_kg'] > 0 ? 'BW +' . (float)$set['weight_kg'] . ' kg' : 'BW' }}
                                                            @else
                                                                {{ (float)$set['weight_kg'] }} <span class="text-[10px] text-gray-400 font-bold">{{ $exercise['unit'] ?? 'kg' }}</span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        @if(($exercise['unit'] ?? 'kg') === 'bw')
                                                            <div class="flex items-center gap-1 justify-center">
                                                                <span class="px-1.5 py-0.5 rounded-md bg-orange-100 dark:bg-orange-950/60 text-orange-600 dark:text-orange-400 font-black text-[10px]">BW</span>
                                                                <span class="text-[10px] text-gray-400 font-bold">+</span>
                                                                <input type="number" 
                                                                       step="0.5" 
                                                                       min="0" 
                                                                       wire:model.lazy="workoutExercises.{{ $eIdx }}.sets.{{ $sIdx }}.weight_kg" 
                                                                       placeholder="0" 
                                                                       class="w-11 sm:w-13 text-center font-bold text-xs py-1 px-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500"
                                                                       title="Beban tambahan jika ada (kg)">
                                                            </div>
                                                        @else
                                                            <div class="flex items-center gap-1 justify-center">
                                                                <input type="number" 
                                                                       step="0.5" 
                                                                       min="0" 
                                                                       wire:model.lazy="workoutExercises.{{ $eIdx }}.sets.{{ $sIdx }}.weight_kg" 
                                                                       placeholder="0" 
                                                                       class="w-13 sm:w-16 text-center font-bold text-xs py-1 px-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500">
                                                                <span class="text-[10px] font-bold text-gray-400">{{ $exercise['unit'] ?? 'kg' }}</span>
                                                            </div>
                                                        @endif
                                                    @endif
                                                </div>

                                                <!-- Reps Column -->
                                                <div class="col-span-3 flex items-center justify-center">
                                                    @if(!empty($set['is_completed']))
                                                        <div class="text-xs font-black text-gray-900 dark:text-white">
                                                            {{ (int)$set['reps'] }} <span class="text-[10px] text-gray-400 font-bold">reps</span>
                                                        </div>
                                                    @else
                                                        <div class="flex items-center gap-1 justify-center">
                                                            <input type="number" 
                                                                   step="1" 
                                                                   min="1" 
                                                                   wire:model.lazy="workoutExercises.{{ $eIdx }}.sets.{{ $sIdx }}.reps" 
                                                                   placeholder="10" 
                                                                   class="w-12 sm:w-14 text-center font-bold text-xs py-1 px-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500">
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- Aksi Column: Centang (Check) / PR / Hapus -->
                                                <div class="col-span-3 flex items-center justify-end gap-1.5">
                                                    @if(!empty($set['is_completed']))
                                                        <button type="button" 
                                                                wire:click="toggleSetCompleted({{ $eIdx }}, {{ $sIdx }})" 
                                                                class="h-7 px-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white flex items-center gap-1 text-xs font-black shadow-xs transition-all cursor-pointer shrink-0"
                                                                title="Set selesai (Klik untuk mengedit kembali)">
                                                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                                            @if(!empty($set['is_pr']))
                                                                <svg class="w-3 h-3 text-amber-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 21h8m-4-4v4m-5-8a5 5 0 0110 0v1a5 5 0 01-10 0v-1zm0 0H5a2 2 0 01-2-2V8a2 2 0 012-2h2m10 5h2a2 2 0 002-2V8a2 2 0 00-2-2h-2"></path></svg>
                                                            @endif
                                                        </button>
                                                    @else
                                                        <button type="button" 
                                                                wire:click="toggleSetCompleted({{ $eIdx }}, {{ $sIdx }})" 
                                                                class="w-7 h-7 rounded-lg bg-orange-500 hover:bg-orange-600 text-white flex items-center justify-center text-xs font-black shadow-xs transition-all cursor-pointer shrink-0"
                                                                title="Centang untuk selesaikan set">
                                                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                                        </button>
                                                    @endif

                                                    @if(count($exercise['sets']) > 1)
                                                        <button type="button" 
                                                                wire:click="removeSetFromExercise({{ $eIdx }}, {{ $sIdx }})" 
                                                                class="w-6 h-6 flex items-center justify-center rounded-lg text-gray-300 dark:text-gray-600 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 transition-all cursor-pointer"
                                                                title="Hapus baris set">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                        </button>
                                                    @else
                                                        <span class="w-6"></span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <!-- Inline + Button below set table -->
                                    <div class="pt-2 flex items-center">
                                        <button type="button" 
                                                wire:click="addSetToExercise({{ $eIdx }})" 
                                                class="text-orange-500 hover:text-orange-600 dark:text-orange-400 text-sm font-black py-1 px-1 cursor-pointer flex items-center gap-1.5 hover:translate-x-0.5 transition-transform">
                                            <span class="text-base leading-none font-bold">+</span>
                                            <span class="text-[11px] font-bold">Tambah Set</span>
                                        </button>
                                    </div>
                                @endif

                                <!-- Divider & Notes / Form Cue Input -->
                                <div class="pt-2 mt-1 border-t border-gray-100 dark:border-gray-800">
                                    <input type="text" 
                                           wire:model.lazy="workoutExercises.{{ $eIdx }}.notes" 
                                           placeholder="Notes / Catatan teknik atau rute lari..." 
                                           class="w-full bg-transparent border-none p-0 text-xs text-gray-500 dark:text-gray-400 placeholder-gray-400/60 focus:ring-0 italic">
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 space-y-3">
                            <div class="w-12 h-12 rounded-2xl bg-orange-50 dark:bg-orange-950/50 text-orange-500 mx-auto flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 5v14M18 5v14M3 8v8M21 8v8M6 12h12"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-gray-900 dark:text-white">Belum Ada Gerakan Latihan</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Tekan tombol + di pojok kanan bawah untuk memilih latihan pertama Anda.</p>
                            </div>
                            <button type="button" 
                                    @click="showPresetPicker = true" 
                                    class="px-4 py-2 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs shadow-md transition-all cursor-pointer">
                                + Pilih Gerakan
                            </button>
                        </div>
                    @endforelse

                    <!-- Inline Wide Button: + Tambah Gerakan Latihan -->
                    <div class="pt-1">
                        <button type="button" 
                                @click="showPresetPicker = true" 
                                class="w-full py-3.5 px-4 rounded-2xl border-2 border-dashed border-orange-300 dark:border-orange-800/60 hover:border-orange-500 hover:bg-orange-50/50 dark:hover:bg-orange-950/20 text-orange-600 dark:text-orange-400 font-black text-xs transition-all flex items-center justify-center gap-2 cursor-pointer group">
                            <span class="w-5 h-5 rounded-full bg-orange-500 text-white flex items-center justify-center text-sm font-bold group-hover:scale-110 transition-transform">+</span>
                            <span>Tambah Gerakan Latihan</span>
                        </button>
                    </div>

                    <!-- Session Overall Notes -->
                    <div class="pt-2">
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">Catatan Sesi Keseluruhan (Opsional):</label>
                        <textarea wire:model="activeWorkoutNotes" rows="2" placeholder="Cth: Lari santai pagi hari dengan tempo stabil, cuaca sejuk..." class="w-full text-xs rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-3 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-orange-500"></textarea>
                    </div>

                </div>

                <!-- Floating Action Button (FAB) `+` -->
                <button type="button" 
                        @click="showPresetPicker = true" 
                        class="absolute bottom-20 right-5 sm:right-6 z-30 w-12 h-12 sm:w-13 sm:h-13 aspect-square rounded-full bg-gradient-to-tr from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white shadow-xl shadow-orange-500/40 flex items-center justify-center text-2xl font-bold hover:scale-105 active:scale-95 transition-all cursor-pointer"
                        title="Tambah Gerakan Latihan">
                    +
                </button>

                <!-- Exercise Library & Preset Picker Modal / Sheet -->
                <div x-show="showPresetPicker" 
                     x-transition 
                     class="absolute inset-0 z-40 bg-gray-950/70 backdrop-blur-sm flex flex-col justify-end sm:justify-center p-0 sm:p-4" 
                     style="display: none;">
                    
                    <div class="bg-white dark:bg-gray-900 rounded-t-3xl sm:rounded-3xl max-w-lg w-full mx-auto p-4 sm:p-6 shadow-2xl border border-gray-100 dark:border-gray-800 space-y-3.5 max-h-[85vh] flex flex-col">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-wider">
                                    <span x-text="$wire.swapTargetExerciseIndex !== null ? 'Ganti Gerakan Latihan' : 'Tambah Gerakan Latihan'"></span>
                                </h4>
                                <p class="text-[11px] text-gray-400">Pilih dari pustaka gerakan atau ketik sendiri</p>
                            </div>
                            <button type="button" @click="showPresetPicker = false" class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 hover:text-gray-600 text-xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <!-- Muscle Group Tabs -->
                        <div class="flex gap-1.5 overflow-x-auto pb-1 hide-scrollbar">
                            <template x-for="m in Object.keys(exerciseLibrary)" :key="m">
                                <button type="button" 
                                        @click="presetMuscle = m" 
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer"
                                        :class="presetMuscle === m ? 'bg-orange-500 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200'">
                                    <span x-text="m"></span>
                                </button>
                            </template>
                        </div>

                        <!-- Preset Chips -->
                        <div class="flex flex-wrap gap-1.5 overflow-y-auto max-h-48 py-1">
                            <template x-for="item in exerciseLibrary[presetMuscle]" :key="item">
                                <button type="button" 
                                        @click="if ($wire.swapTargetExerciseIndex !== null) { $wire.swapExercise($wire.swapTargetExerciseIndex, item, presetMuscle); } else { $wire.addExerciseToSession(item, presetMuscle); } showPresetPicker = false;" 
                                        class="px-3 py-2 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700 text-xs font-bold text-gray-800 dark:text-gray-200 hover:border-orange-500 hover:text-orange-500 transition-all cursor-pointer">
                                    + <span x-text="item"></span>
                                </button>
                            </template>
                        </div>

                        <!-- Custom Exercise Name Input -->
                        <div class="pt-2 border-t border-gray-100 dark:border-gray-800 flex gap-2">
                            <input type="text" 
                                   x-model="customExerciseName" 
                                   @keydown.enter.prevent="if (customExerciseName.trim()) { if ($wire.swapTargetExerciseIndex !== null) { $wire.swapExercise($wire.swapTargetExerciseIndex, customExerciseName, presetMuscle); } else { $wire.addExerciseToSession(customExerciseName, presetMuscle); } customExerciseName = ''; showPresetPicker = false; }" 
                                   placeholder="Atau ketik gerakan kustom..." 
                                   class="flex-1 text-xs rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3.5 py-2 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-orange-500">
                            <button type="button" 
                                    @click="if (customExerciseName.trim()) { if ($wire.swapTargetExerciseIndex !== null) { $wire.swapExercise($wire.swapTargetExerciseIndex, customExerciseName, presetMuscle); } else { $wire.addExerciseToSession(customExerciseName, presetMuscle); } customExerciseName = ''; showPresetPicker = false; }" 
                                    class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs rounded-xl transition-all cursor-pointer">
                                Tambah
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sticky Bottom Bar (Total Duration & Rest Timer) -->
                <div class="px-4 sm:px-6 py-3 border-t border-gray-100 dark:border-gray-800/80 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md flex items-center justify-between shrink-0">
                    <!-- Left: Duration Stopwatch -->
                    <div class="flex items-center gap-1.5 text-xs sm:text-sm font-mono font-black text-gray-800 dark:text-gray-200">
                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="13" r="8" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4l2.5 2.5M12 5V2M10 2h4"></path></svg>
                        <span x-text="formatTime(elapsedSeconds)">00:00</span>
                    </div>

                    <!-- Right: Rest Timer Dock -->
                    <div class="flex items-center gap-2">
                        <!-- When Running: Glowing Countdown with +30s and skip -->
                        <div x-show="restTimerRunning" 
                             class="flex items-center gap-2 bg-orange-500/10 dark:bg-orange-950/40 border border-orange-500/30 px-3 py-1 rounded-2xl text-orange-600 dark:text-orange-400 text-xs font-mono font-bold animate-pulse" 
                             style="display: none;">
                            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3h12M6 21h12M8 3v4a4 4 0 002 3.46l.5.34a2 2 0 010 3.4L10 14.54A4 4 0 008 18v3m8-18v4a4 4 0 01-2 3.46l-.5.34a2 2 0 000 3.4l.5.2a4 4 0 012 3.46v3"></path></svg>
                            <span x-text="formatTime(restSecondsLeft)" class="font-black text-sm">01:30</span>
                            <button type="button" @click="addRestTime(30)" class="px-1.5 py-0.5 rounded bg-orange-500 text-white text-[10px] font-bold cursor-pointer hover:bg-orange-600">+30s</button>
                            <button type="button" @click="skipRestTimer()" class="text-gray-400 hover:text-gray-600 text-xs cursor-pointer ml-0.5" title="Lewati">✕</button>
                        </div>

                        <!-- When Idle: Clean button to start rest anytime -->
                        <div x-show="!restTimerRunning">
                            <button type="button" 
                                    @click="startRestTimer(90)" 
                                    class="text-[11px] font-bold text-gray-500 dark:text-gray-400 hover:text-orange-500 flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-all cursor-pointer">
                                <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="13" r="8" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4l2.5 2.5M12 5V2M10 2h4"></path></svg>
                                <span>Rest 90s</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
            </div>

            <!-- Minimized Floating Mini Dock -->
            <div x-show="isMinimized" 
                 x-transition:enter="transition ease-out duration-300 transform" 
                 x-transition:enter-start="opacity-0 translate-y-8 scale-90" 
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100" 
                 x-transition:leave="transition ease-in duration-200 transform" 
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-8 scale-90" 
                 class="fixed bottom-20 right-4 sm:bottom-6 sm:right-6 z-50 flex items-center gap-3 p-3 sm:px-4 sm:py-3 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md rounded-2xl sm:rounded-3xl shadow-2xl border border-gray-200 dark:border-gray-800 ring-1 ring-black/5 dark:ring-white/10 select-none" 
                 style="display: none;">
                
                <!-- Left: Status indicator with pulse -->
                <div class="relative flex items-center justify-center shrink-0">
                    <span class="w-2.5 h-2.5 rounded-full bg-orange-500 animate-ping absolute"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-orange-500 relative"></span>
                </div>

                <!-- Info: Workout title & Live Timers -->
                <div class="flex flex-col min-w-0 pr-1 cursor-pointer" @click="isMinimized = false">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-black text-gray-900 dark:text-white truncate max-w-[140px] sm:max-w-[200px]">
                            {{ $activeWorkoutTitle }}
                        </span>
                        <span class="text-[10px] font-bold text-orange-500 bg-orange-50 dark:bg-orange-950/60 px-1.5 py-0.5 rounded-md">
                            Live
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400 font-mono font-bold mt-0.5">
                        <!-- Elapsed Timer -->
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="13" r="8" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4l2.5 2.5M12 5V2M10 2h4"></path></svg>
                            <span x-text="formatTime(elapsedSeconds)" class="text-gray-900 dark:text-white font-bold">00:00</span>
                        </span>

                        <!-- Active Rest Timer if running -->
                        <span x-show="restTimerRunning" class="flex items-center gap-1 text-orange-600 dark:text-orange-400 animate-pulse">
                            <span>•</span>
                            <svg class="w-3 h-3 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3h12M6 21h12M8 3v4a4 4 0 002 3.46l.5.34a2 2 0 010 3.4L10 14.54A4 4 0 008 18v3m8-18v4a4 4 0 01-2 3.46l-.5.34a2 2 0 000 3.4l.5.2a4 4 0 012 3.46v3"></path></svg>
                            <span x-text="formatTime(restSecondsLeft)" class="font-bold">01:30</span>
                        </span>
                    </div>
                </div>

                <!-- Action Buttons: Expand & Finish -->
                <div class="flex items-center gap-1.5 shrink-0 pl-1 border-l border-gray-200 dark:border-gray-800">
                    <!-- Expand Button -->
                    <button type="button" 
                            @click="isMinimized = false" 
                            class="px-3 py-1.5 rounded-xl bg-orange-500/10 hover:bg-orange-500/20 text-orange-600 dark:text-orange-400 font-bold text-xs flex items-center gap-1 transition-all cursor-pointer" 
                            title="Buka Layar Penuh">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 3 21 3 21 9"></polyline>
                            <polyline points="9 21 3 21 3 15"></polyline>
                            <line x1="21" y1="3" x2="14" y2="10"></line>
                            <line x1="3" y1="21" x2="10" y2="14"></line>
                        </svg>
                        <span class="hidden sm:inline">Buka</span>
                    </button>

                    <!-- Quick Finish -->
                    <button type="button" 
                            @click="destroy(); $wire.finishWorkout(elapsedSeconds);" 
                            class="p-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 transition-all cursor-pointer" 
                            title="Selesaikan Workout">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 3v18M5 4h14l-2 5 2 5H5"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: Workout Summary Celebration -->
    @if($showWorkoutSummaryModal && $workoutSummaryData)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in duration-200">
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-gray-200 dark:border-gray-700 text-center space-y-5 animate-in zoom-in-95 duration-200 relative z-10">
                <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-amber-400 to-amber-600 text-white flex items-center justify-center mx-auto shadow-lg shadow-amber-500/40">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M8 21h8m-4-4v4m-5-8a5 5 0 0110 0v1a5 5 0 01-10 0v-1zm0 0H5a2 2 0 01-2-2V8a2 2 0 012-2h2m10 5h2a2 2 0 002-2V8a2 2 0 00-2-2h-2"></path></svg>
                </div>

                <div>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Sesi Latihan Selesai!</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-1">{{ $workoutSummaryData['title'] }}</p>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 gap-2.5 text-left">
                    <div class="p-3 bg-gray-50 dark:bg-gray-900/60 rounded-2xl border border-gray-100 dark:border-gray-700/60">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center gap-1 mb-1">
                            <svg class="w-3 h-3 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="13" r="8" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4l2.5 2.5M12 5V2M10 2h4"></path></svg>
                            Durasi
                        </span>
                        <strong class="text-base font-black text-gray-900 dark:text-white font-mono">{{ $workoutSummaryData['duration_formatted'] }}</strong>
                    </div>

                    @if(!empty($workoutSummaryData['has_cardio']) || (!empty($workoutSummaryData['total_distance_raw']) && (float)$workoutSummaryData['total_distance_raw'] > 0))
                        <div class="p-3 bg-gray-50 dark:bg-gray-900/60 rounded-2xl border border-gray-100 dark:border-gray-700/60">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center gap-1 mb-1">
                                <svg class="w-3 h-3 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="16" cy="4" r="2" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21l3-6 4 2 3-5M10 9l3 2-2 4M6 11l4-2 3 3"></path></svg>
                                Total Jarak
                            </span>
                            <strong class="text-base font-black text-cyan-500">{{ $workoutSummaryData['total_distance_km'] }} km</strong>
                        </div>
                    @else
                        <div class="p-3 bg-gray-50 dark:bg-gray-900/60 rounded-2xl border border-gray-100 dark:border-gray-700/60">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center gap-1 mb-1">
                                <svg class="w-3 h-3 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 5v14M18 5v14M3 8v8M21 8v8M6 12h12"></path></svg>
                                Total Volume
                            </span>
                            <strong class="text-base font-black text-amber-500">{{ $workoutSummaryData['total_volume_kg'] }} kg</strong>
                        </div>
                    @endif

                    <div class="p-3 bg-gray-50 dark:bg-gray-900/60 rounded-2xl border border-gray-100 dark:border-gray-700/60">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center gap-1 mb-1">
                            <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                            Total Set
                        </span>
                        <strong class="text-base font-black text-emerald-500">{{ $workoutSummaryData['total_sets'] }} Set</strong>
                    </div>

                    <div class="p-3 bg-gray-50 dark:bg-gray-900/60 rounded-2xl border border-gray-100 dark:border-gray-700/60">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center gap-1 mb-1">
                            <svg class="w-3 h-3 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 21h8m-4-4v4m-5-8a5 5 0 0110 0v1a5 5 0 01-10 0v-1zm0 0H5a2 2 0 01-2-2V8a2 2 0 012-2h2m10 5h2a2 2 0 002-2V8a2 2 0 00-2-2h-2"></path></svg>
                            Personal Record
                        </span>
                        <strong class="text-base font-black text-purple-500">{{ $workoutSummaryData['pr_count'] }} PR Baru</strong>
                    </div>
                </div>

                <!-- Gamification Rewards Banner -->
                <div class="p-3.5 bg-gradient-to-r from-amber-50 to-indigo-50 dark:from-amber-950/30 dark:to-indigo-950/30 rounded-2xl border border-amber-200/60 dark:border-indigo-800/40 text-xs">
                    <div class="flex items-center justify-between font-black">
                        <span class="text-amber-600 dark:text-amber-400 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            Reward: +{{ $workoutSummaryData['xp_earned'] }} EXP
                        </span>
                        <span class="text-indigo-600 dark:text-indigo-400 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            RPG: Focus & Execution
                        </span>
                    </div>
                    @if($workoutSummaryData['task_title'])
                        <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-bold mt-1.5 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            <span>Tugas "{{ $workoutSummaryData['task_title'] }}" otomatis selesai!</span>
                        </div>
                    @endif
                </div>

                <button type="button" 
                        wire:click="closeWorkoutSummaryModal" 
                        class="w-full py-3 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-extrabold text-xs rounded-2xl shadow-lg shadow-amber-500/30 transition-all cursor-pointer">
                    Lanjutkan & Tutup
                </button>
            </div>
        </div>
    @endif

    <script>
        (function() {
            function initTaskStore() {
                if (window.Alpine && !Alpine.store('taskConfig')) {
                    Alpine.store('taskConfig', {
                        autoHide: localStorage.getItem('atoma_task_autohide') !== 'false',
                        inputMode: localStorage.getItem('atoma_task_input_mode') || 'both',
                        mobileFab: localStorage.getItem('atoma_task_mobile_fab') || 'center',
                        subtaskMode: localStorage.getItem('atoma_task_subtask_mode') || 'list',
                        toggleAutoHide() {
                            this.autoHide = !this.autoHide;
                            localStorage.setItem('atoma_task_autohide', this.autoHide);
                        },
                        setInputMode(mode) {
                            this.inputMode = mode;
                            localStorage.setItem('atoma_task_input_mode', mode);
                        },
                        setMobileFab(pos) {
                            this.mobileFab = pos;
                            localStorage.setItem('atoma_task_mobile_fab', pos);
                        },
                        setSubtaskMode(mode) {
                            this.subtaskMode = mode;
                            localStorage.setItem('atoma_task_subtask_mode', mode);
                        }
                    });
                }
            }

            document.addEventListener('alpine:init', initTaskStore);
            initTaskStore();
        })();

        function taskNotificationScheduler(tasksData) {
            return {
                tasks: tasksData || [],
                init() {
                    if ('Notification' in window && Notification.permission === 'default') {
                        setTimeout(() => {
                            Notification.requestPermission().catch(() => {});
                        }, 2500);
                    }

                    // Event-driven listener: immediately purge/update task status when toggled or cancelled
                    window.addEventListener('cancel-reminder', (e) => {
                        const raw = e.detail;
                        const detail = Array.isArray(raw) ? (raw[0] || {}) : (raw || {});
                        const id = detail.id || detail;
                        if (id && this.tasks) {
                            const found = this.tasks.find(t => String(t.id) === String(id));
                            if (found) found.is_completed = true;
                        }
                    });

                    window.addEventListener('task-toggled', (e) => {
                        const raw = e.detail;
                        const detail = Array.isArray(raw) ? (raw[0] || {}) : (raw || {});
                        const id = detail.id || detail;
                        const completed = detail.completed !== undefined ? detail.completed : true;
                        if (id && this.tasks) {
                            const found = this.tasks.find(t => String(t.id) === String(id));
                            if (found) found.is_completed = completed;
                        }
                    });

                    this.checkNotifications();
                    setInterval(() => {
                        this.checkNotifications();
                    }, 25000);
                },
                checkNotifications() {
                    if (!this.tasks || this.tasks.length === 0) return;
                    const now = new Date();
                    const nowHours = now.getHours();
                    const nowMinutes = now.getMinutes();
                    const currentTotalMin = nowHours * 60 + nowMinutes;
                    const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

                    this.tasks.forEach(task => {
                        if (task.is_completed) return;
                        if (task.date && task.date !== todayStr) return;

                        // 1. Check Start Time (10 min before)
                        if (task.start_time) {
                            const parts = task.start_time.split(':').map(Number);
                            const startTotalMin = parts[0] * 60 + (parts[1] || 0);
                            const diffStart = startTotalMin - currentTotalMin;
                            const startKey = `notified_start_${task.id}_${todayStr}`;

                            if (diffStart >= 0 && diffStart <= 10 && !sessionStorage.getItem(startKey)) {
                                sessionStorage.setItem(startKey, '1');
                                const msg = `🔔 Pengingat: Tugas "${task.title}" akan dimulai dalam ${diffStart === 0 ? 'sekarang' : diffStart + ' menit'}!`;
                                window.dispatchEvent(new CustomEvent('toast', { detail: { message: msg, type: 'nudge' } }));
                                window.dispatchEvent(new CustomEvent('play-sound', { detail: { type: 'nudge' } }));

                                if ('Notification' in window && Notification.permission === 'granted') {
                                    new Notification('🔔 Pengingat Mulai Tugas', {
                                        body: `Tugas "${task.title}" dijadwalkan mulai pukul ${task.start_time.slice(0, 5)} (${diffStart}m lagi)`,
                                        icon: '/images/atoma_icon.png'
                                    });
                                }
                            }
                        }

                        // 2. Check Deadline (10 min before & Expired) with Overnight (+1 day) detection
                        if (task.deadline) {
                            const parts = task.deadline.split(':').map(Number);
                            let deadlineTotalMin = parts[0] * 60 + (parts[1] || 0);

                            // Overnight task detection (e.g. 22:00 - 04:00)
                            if (task.start_time) {
                                const sParts = task.start_time.split(':').map(Number);
                                const startTotalMin = sParts[0] * 60 + (sParts[1] || 0);
                                if (deadlineTotalMin <= startTotalMin) {
                                    deadlineTotalMin += 1440; // Next morning (+24h)
                                }
                            }

                            const diffDeadline = deadlineTotalMin - currentTotalMin;
                            const warnKey = `notified_warn_${task.id}_${todayStr}`;
                            const expKey = `notified_exp_${task.id}_${todayStr}`;

                            if (diffDeadline >= 0 && diffDeadline <= 10 && !sessionStorage.getItem(warnKey)) {
                                sessionStorage.setItem(warnKey, '1');
                                const msg = `⚠️ Peringatan: Deadline tugas "${task.title}" tersisa ${diffDeadline === 0 ? '< 1' : diffDeadline} menit!`;
                                window.dispatchEvent(new CustomEvent('toast', { detail: { message: msg, type: 'nudge' } }));
                                window.dispatchEvent(new CustomEvent('play-sound', { detail: { type: 'nudge' } }));

                                if ('Notification' in window && Notification.permission === 'granted') {
                                    new Notification('⚠️ Peringatan Deadline!', {
                                        body: `Tugas "${task.title}" tersisa ${diffDeadline} menit sebelum batas waktu ${task.deadline.slice(0, 5)}!`,
                                        icon: '/images/atoma_icon.png'
                                    });
                                }
                            } else if (diffDeadline < 0 && diffDeadline >= -15 && !sessionStorage.getItem(expKey)) {
                                sessionStorage.setItem(expKey, '1');
                                const msg = `🚨 Deadline tugas "${task.title}" telah lewat (Gagal)!`;
                                window.dispatchEvent(new CustomEvent('toast', { detail: { message: msg, type: 'error' } }));

                                if ('Notification' in window && Notification.permission === 'granted') {
                                    new Notification('🚨 Deadline Telah Lewat!', {
                                        body: `Batas waktu pengerjaan tugas "${task.title}" telah lewat.`,
                                        icon: '/images/atoma_icon.png'
                                    });
                                }
                            }
                        }
                    });
                }
            };
        }
    </script>
</div>

