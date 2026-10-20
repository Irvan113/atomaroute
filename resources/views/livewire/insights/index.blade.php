<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Task;
use App\Models\Journal;
use App\Models\DailySummary;
use App\Models\AiInsight;
use App\Models\FocusSession;
use App\Services\AtomaInsightService;
use App\Services\LeagueService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    public $filterPeriod = '7days'; // 7days, month
    public $isLoadingDaily = false;
    public $isLoadingWeekly = false;

    // Headline Stats & Deltas
    public $completionRate = 0;
    public $completionRateDelta = 0;
    public $tasksDoneCount = 0;
    public $tasksDoneDelta = 0;
    public $totalTasksCount = 0;

    public $totalFocusMinutes = 0;
    public $totalFocusHours = 0.0;
    public $focusDeltaHours = 0.0;
    public $totalFocusSessions = 0;

    public $streak = 0;
    public $longestStreak = 0;

    public $overdueCount = 0;
    public $overdueDelta = 0;

    public $financeBalance = 0;
    public $journalsWritten = 0;
    public $mostFrequentMood = 'neutral';

    // Peak Productivity & Radar
    public $morningTasksCount = 0;
    public $afternoonTasksCount = 0;
    public $eveningTasksCount = 0;
    public $nightTasksCount = 0;
    public $goldenTimeLabel = '09:00 - 11:30 WIB';
    public $bestDayName = 'Rabu';
    public $bestDayRate = 85;

    // Life Balance & Category Distribution
    public $categoryBreakdown = [];
    public $lifeBalanceNotice = '';
    public $lifeBalanceScore = 90;

    // Mood vs Productivity Correlation
    public $moodStats = [];
    public $moodCorrelationSummary = '';

    // RPG Gamification & Weekly Persona
    public $weeklyPersona = [
        'title' => 'The Deep Thinker',
        'badge' => '🧘',
        'archetype' => 'Deep Work & Reflective',
        'desc' => 'Kamu unggul dalam fokus mendalam dan refleksi diri. Sesi fokus panjang dan kejernihan pikiran adalah senjatamu.',
        'tag' => 'Fokus Tinggi',
    ];
    public $userLevel = 1;
    public $userXp = 0;
    public $levelProgress = 0;
    public $userCoins = 0;
    public $leagueInfo = [];

    // Overdue tasks list & Reason Modal
    public $overdueTasks = [];
    public $overduePatternNotice = '';
    public $showReasonModal = false;
    public $selectedOverdueTaskId = null;
    public $selectedOverdueTaskTitle = '';
    public $selectedOverdueTaskIsMilestone = false;
    public $selectedOverdueTaskDayNumber = null;
    public $chosenReason = 'Kelelahan / Burnout';
    public $customReasonNote = '';
    public $autoRescheduleTomorrow = true;
    public $reasonBreakdown = [];
    public $topOverdueReason = '';

    // AI Daily Summary & Weekly ATOMA Says
    public $todaySummary = null;
    public $weeklyInsight = null;
    public $summaryLastGeneratedAt = null;
    public $insightLastGeneratedAt = null;
    public $canRefreshWeekly = true;

    public function mount(AtomaInsightService $insightService)
    {
        $this->loadStats();
        $this->loadOverdueTasks();
        $this->loadSummaries();
    }

    public function setFilterPeriod($period)
    {
        $this->filterPeriod = $period;
        $this->loadStats();
        $this->dispatch('refresh-charts');
    }

    public function loadStats()
    {
        $user = auth()->user();
        if (!$user) return;

        $subDays = $this->filterPeriod === 'month' ? 30 : 7;
        $today = Carbon::today();
        $startDate = $today->copy()->subDays($subDays);
        $prevStartDate = $today->copy()->subDays($subDays * 2);
        $prevEndDate = $today->copy()->subDays($subDays);

        // 1. Tasks & Completion Rate (Current vs Previous Period)
        $currentTasksQuery = $user->tasks()->where(function($q) use ($startDate, $today) {
            $q->whereBetween('date', [$startDate->toDateString(), $today->toDateString()])
              ->orWhereBetween('created_at', [$startDate->startOfDay(), $today->endOfDay()]);
        });
        $this->totalTasksCount = (clone $currentTasksQuery)->count();
        $this->tasksDoneCount = (clone $currentTasksQuery)->where('is_completed', true)->count();
        $this->completionRate = $this->totalTasksCount > 0 ? round(($this->tasksDoneCount / $this->totalTasksCount) * 100) : 0;

        $prevTasksQuery = $user->tasks()->where(function($q) use ($prevStartDate, $prevEndDate) {
            $q->whereBetween('date', [$prevStartDate->toDateString(), $prevEndDate->toDateString()])
              ->orWhereBetween('created_at', [$prevStartDate->startOfDay(), $prevEndDate->endOfDay()]);
        });
        $prevTotal = (clone $prevTasksQuery)->count();
        $prevDone = (clone $prevTasksQuery)->where('is_completed', true)->count();
        $prevRate = $prevTotal > 0 ? round(($prevDone / $prevTotal) * 100) : 0;

        $this->completionRateDelta = $this->completionRate - $prevRate;
        $this->tasksDoneDelta = $this->tasksDoneCount - $prevDone;

        // 2. Focus Time & Deep Work (FocusSession + Task fallback)
        $currentFocusMins = (int) $user->focusSessions()
            ->where('created_at', '>=', $startDate->startOfDay())
            ->sum('minutes');
        $currentFocusSessionsCount = $user->focusSessions()
            ->where('created_at', '>=', $startDate->startOfDay())
            ->count();

        if ($currentFocusMins === 0) {
            $taskActualMins = (int) (clone $currentTasksQuery)->where('is_completed', true)->sum('actual_minutes');
            $taskPomoSessions = (int) (clone $currentTasksQuery)->where('is_completed', true)->sum('pomodoro_sessions');
            $currentFocusMins = $taskActualMins > 0 ? $taskActualMins : ($taskPomoSessions * 25);
            $currentFocusSessionsCount = $taskPomoSessions > 0 ? $taskPomoSessions : ($this->tasksDoneCount > 0 ? round($this->tasksDoneCount * 0.8) : 0);
        }

        $prevFocusMins = (int) $user->focusSessions()
            ->whereBetween('created_at', [$prevStartDate->startOfDay(), $prevEndDate->endOfDay()])
            ->sum('minutes');

        $this->totalFocusMinutes = $currentFocusMins;
        $this->totalFocusHours = round($currentFocusMins / 60, 1);
        $prevFocusHours = round($prevFocusMins / 60, 1);
        $this->focusDeltaHours = round($this->totalFocusHours - $prevFocusHours, 1);
        $this->totalFocusSessions = $currentFocusSessionsCount;

        // 3. Streak & RPG Levels
        $this->streak = $user->current_streak ?? 0;
        $this->longestStreak = $user->longest_streak ?? $this->streak;
        $this->userLevel = $user->level ?? 1;
        $this->userXp = $user->xp ?? 0;
        $this->levelProgress = $this->userXp % 100;
        $this->userCoins = $user->coins ?? 0;

        // League info
        $leagueDetails = $user->league_details ?? LeagueService::getLeagueDetails('bronze_3');
        $weeklyXp = $user->weekly_xp ?? 0;
        $minXp = $leagueDetails['min_weekly_xp'] ?? 200;
        $this->leagueInfo = [
            'name' => $leagueDetails['name'] ?? 'Bronze III',
            'icon' => $leagueDetails['icon'] ?? '🥉',
            'tier' => $leagueDetails['tier'] ?? 'Bronze',
            'weekly_xp' => $weeklyXp,
            'min_weekly_xp' => $minXp,
            'is_promoted' => $weeklyXp >= $minXp,
            'gap_xp' => max(0, $minXp - $weeklyXp),
            'class' => $leagueDetails['class'] ?? '',
        ];

        // 4. Overdue Tasks Count
        $this->overdueCount = $user->tasks()
            ->where('is_completed', false)
            ->where('date', '<', $today->toDateString())
            ->count();
        
        $prevOverdueCount = $user->tasks()
            ->where('is_completed', false)
            ->where('date', '<', $startDate->toDateString())
            ->count();
        $this->overdueDelta = $this->overdueCount - $prevOverdueCount;

        // 5. Finance Balance (Current Period)
        $income = $user->financeTransactions()->where('date', '>=', $startDate->toDateString())->where('type', 'income')->sum('amount');
        $expense = $user->financeTransactions()->where('date', '>=', $startDate->toDateString())->where('type', 'expense')->sum('amount');
        $this->financeBalance = $income - $expense;

        // 6. Total Journals & Dominant Mood
        $this->journalsWritten = $user->journals()->count();
        $moodCounts = $user->journals()->select('mood', DB::raw('count(*) as total'))->groupBy('mood')->orderByDesc('total')->first();
        $this->mostFrequentMood = $moodCounts ? $moodCounts->mood : 'neutral';

        // 7. Peak Productivity Hours & Best Day Analysis
        $this->analyzePeakHours($user, $startDate);

        // 8. Life Balance / Category Distribution
        $this->analyzeLifeBalance($user, $startDate);

        // 9. Mood vs Productivity Correlation
        $this->analyzeMoodCorrelation($user);

        // 10. Weekly Productivity Persona
        $this->determineProductivityPersona();
    }

    protected function analyzePeakHours($user, $startDate)
    {
        $completedTasks = $user->tasks()
            ->where('is_completed', true)
            ->where('date', '>=', $startDate->toDateString())
            ->get();

        $morning = 0;   // 06:00 - 11:59
        $afternoon = 0; // 12:00 - 17:59
        $evening = 0;   // 18:00 - 23:59
        $night = 0;     // 00:00 - 05:59

        $dayCounts = [
            'Senin' => 0, 'Selasa' => 0, 'Rabu' => 0,
            'Kamis' => 0, 'Jumat' => 0, 'Sabtu' => 0, 'Minggu' => 0
        ];

        $dayMap = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
            4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'
        ];

        foreach ($completedTasks as $task) {
            $time = $task->updated_at ?? $task->created_at;
            if ($time) {
                $hour = $time->hour;
                if ($hour >= 6 && $hour < 12) $morning++;
                elseif ($hour >= 12 && $hour < 18) $afternoon++;
                elseif ($hour >= 18 && $hour < 24) $evening++;
                else $night++;
            }

            if ($task->date) {
                $dayOfWeek = Carbon::parse($task->date)->dayOfWeek;
                $dayName = $dayMap[$dayOfWeek] ?? 'Senin';
                $dayCounts[$dayName]++;
            }
        }

        $totalCompleted = $completedTasks->count();
        if ($totalCompleted === 0) {
            $this->morningTasksCount = 0;
            $this->afternoonTasksCount = 0;
            $this->eveningTasksCount = 0;
            $this->nightTasksCount = 0;
            $this->goldenTimeLabel = 'Belum Terdeteksi';
            $this->bestDayName = '-';
            $this->bestDayRate = 0;
            return;
        }

        $this->morningTasksCount = $morning;
        $this->afternoonTasksCount = $afternoon;
        $this->eveningTasksCount = $evening;
        $this->nightTasksCount = $night;

        if ($morning >= $afternoon && $morning >= $evening) {
            $this->goldenTimeLabel = '08:00 - 11:30 WIB (Pagi)';
        } elseif ($afternoon >= $morning && $afternoon >= $evening) {
            $this->goldenTimeLabel = '13:00 - 16:30 WIB (Siang / Sore)';
        } else {
            $this->goldenTimeLabel = '19:00 - 22:00 WIB (Malam)';
        }

        arsort($dayCounts);
        $topDay = array_key_first($dayCounts);
        $topDayCount = $dayCounts[$topDay] ?? 0;
        $this->bestDayName = $topDay ?: '-';
        $this->bestDayRate = $totalCompleted > 0 ? round(($topDayCount / max(1, $totalCompleted)) * 100) : 0;
    }

    protected function analyzeLifeBalance($user, $startDate)
    {
        $tasks = $user->tasks()
            ->where('date', '>=', $startDate->toDateString())
            ->get();

        $catGroups = [
            'Kerja / Proyek' => 0,
            'Belajar & Skill' => 0,
            'Kesehatan & Olahraga' => 0,
            'Pribadi & Rutinitas' => 0,
        ];

        foreach ($tasks as $task) {
            $cat = strtolower($task->category ?? 'general');
            if (str_contains($cat, 'kerja') || str_contains($cat, 'work') || str_contains($cat, 'project') || str_contains($cat, 'kantor')) {
                $catGroups['Kerja / Proyek']++;
            } elseif (str_contains($cat, 'belajar') || str_contains($cat, 'study') || str_contains($cat, 'skill') || str_contains($cat, 'buku') || str_contains($cat, 'kursus')) {
                $catGroups['Belajar & Skill']++;
            } elseif (str_contains($cat, 'sehat') || str_contains($cat, 'olahraga') || str_contains($cat, 'health') || str_contains($cat, 'gym') || str_contains($cat, 'makan') || str_contains($cat, 'tidur')) {
                $catGroups['Kesehatan & Olahraga']++;
            } else {
                $catGroups['Pribadi & Rutinitas']++;
            }
        }

        $total = array_sum($catGroups);
        if ($total === 0) {
            $this->categoryBreakdown = [];
            $this->lifeBalanceScore = 0;
            $this->lifeBalanceNotice = "Belum ada data tugas pada periode ini. Mulai buat target dan selesaikan tugas harianmu!";
            return;
        }

        $breakdown = [];
        foreach ($catGroups as $name => $cnt) {
            $pct = round(($cnt / $total) * 100);
            $breakdown[] = [
                'name' => $name,
                'count' => $cnt,
                'percentage' => $pct,
            ];
        }

        $this->categoryBreakdown = $breakdown;

        // Life Balance AI Notice
        $topCat = collect($breakdown)->sortByDesc('percentage')->first();
        $healthCat = collect($breakdown)->firstWhere('name', 'Kesehatan & Olahraga');

        if ($topCat && $topCat['percentage'] >= 65) {
            $this->lifeBalanceNotice = "⚠️ Fokusmu sangat didominasi oleh {$topCat['name']} ({$topCat['percentage']}%). Sempatkan jeda dan olahraga agar tidak burnout!";
            $this->lifeBalanceScore = 70;
        } elseif ($healthCat && $healthCat['percentage'] < 10) {
            $this->lifeBalanceNotice = "💡 Aspek Kesehatan & Olahraga baru {$healthCat['percentage']}%. Luangkan 15 menit jalan kaki atau peregangan hari ini.";
            $this->lifeBalanceScore = 80;
        } else {
            $this->lifeBalanceNotice = "✨ Keseimbangan aktivitasmu sangat optimal antara pekerjaan, pengembangan diri, dan kesehatan!";
            $this->lifeBalanceScore = 95;
        }
    }

    protected function analyzeMoodCorrelation($user)
    {
        $past30DaysJournals = $user->journals()
            ->where('created_at', '>=', Carbon::today()->subDays(30)->startOfDay())
            ->get();

        $moodTaskCounts = [
            'happy' => ['journals' => 0, 'tasks_done' => 0],
            'excited' => ['journals' => 0, 'tasks_done' => 0],
            'neutral' => ['journals' => 0, 'tasks_done' => 0],
            'sad' => ['journals' => 0, 'tasks_done' => 0],
            'tired' => ['journals' => 0, 'tasks_done' => 0],
        ];

        foreach ($past30DaysJournals as $j) {
            $mood = $j->mood ?? 'neutral';
            if (!isset($moodTaskCounts[$mood])) {
                $mood = 'neutral';
            }
            $moodTaskCounts[$mood]['journals']++;
            $dateStr = $j->created_at->toDateString();
            $tasksDone = $user->tasks()
                ->where('is_completed', true)
                ->where(function($q) use ($dateStr) {
                    $q->whereDate('date', $dateStr)->orWhereDate('updated_at', $dateStr);
                })->count();
            $moodTaskCounts[$mood]['tasks_done'] += $tasksDone;
        }

        $stats = [];
        foreach ($moodTaskCounts as $m => $d) {
            $avg = $d['journals'] > 0 ? round($d['tasks_done'] / $d['journals'], 1) : 0;
            $stats[$m] = [
                'count' => $d['journals'],
                'avg_tasks' => $avg,
            ];
        }

        $this->moodStats = $stats;

        $highEnergyAvg = round((($stats['happy']['avg_tasks'] ?? 0) + ($stats['excited']['avg_tasks'] ?? 0)) / 2, 1);
        $lowEnergyAvg = round((($stats['tired']['avg_tasks'] ?? 0) + ($stats['sad']['avg_tasks'] ?? 0)) / 2, 1);

        if ($highEnergyAvg > 0 || $lowEnergyAvg > 0) {
            $this->moodCorrelationSummary = "Saat mood Happy atau Excited, rata-rata kamu menyelesaikan " . ($highEnergyAvg ?: 3.5) . " tugas per hari. Saat lelah (Tired), produktivitas turun menjadi " . ($lowEnergyAvg ?: 1.2) . " tugas.";
        } else {
            $this->moodCorrelationSummary = "Tingkatkan rutinitas menulis jurnal harian untuk melihat korelasi psikologis antara mood dan ritme produktivitasmu!";
        }
    }

    protected function determineProductivityPersona()
    {
        if ($this->totalFocusMinutes >= 120 && $this->journalsWritten >= 3) {
            $this->weeklyPersona = [
                'title' => 'The Deep Thinker',
                'badge' => '🧘',
                'archetype' => 'Deep Work & Reflective',
                'desc' => 'Kamu unggul dalam fokus mendalam dan refleksi diri. Sesi fokus panjang dan kejernihan pikiran adalah senjatamu.',
                'tag' => 'Fokus Tinggi',
            ];
        } elseif ($this->morningTasksCount >= 5 && $this->morningTasksCount > $this->afternoonTasksCount) {
            $this->weeklyPersona = [
                'title' => 'The Speed Sprinter',
                'badge' => '⚡',
                'archetype' => 'Early Bird & Fast Execution',
                'desc' => 'Kamu menyelesaikan misi-misi krusial di pagi hari dengan kecepatan tinggi sebelum kebisingan hari dimulai.',
                'tag' => 'Eksekusi Cepat',
            ];
        } elseif ($this->streak >= 7) {
            $this->weeklyPersona = [
                'title' => 'The Streak Guardian',
                'badge' => '🛡️',
                'archetype' => 'Unshakable Consistency',
                'desc' => 'Konsistensi adalah kekuatan supermu. Rantai kebiasaan harianmu tidak pernah terputus satu hari pun.',
                'tag' => 'Disiplin Baja',
            ];
        } elseif ($this->tasksDoneCount >= 8) {
            $this->weeklyPersona = [
                'title' => 'The Quest Crusher',
                'badge' => '⚔️',
                'archetype' => 'High Output Conqueror',
                'desc' => 'Setiap rintangan berhasil kamu taklukkan. Jumlah tugas yang kamu selesaikan pekan ini luar biasa masif!',
                'tag' => 'Output Masif',
            ];
        } else {
            $this->weeklyPersona = [
                'title' => 'The Steady Climber',
                'badge' => '🌱',
                'archetype' => 'Continuous Growth',
                'desc' => 'Langkah demi langkah menuju mastery. Setiap progres kecil membawamu selangkah lebih dekat ke puncak.',
                'tag' => 'Pertumbuhan Stabil',
            ];
        }
    }

    public function loadOverdueTasks()
    {
        $user = auth()->user();
        if (!$user) return;

        $this->overdueTasks = $user->tasks()
            ->with(['skillTarget', 'skill'])
            ->where('is_completed', false)
            ->where('date', '<', Carbon::today()->toDateString())
            ->orderBy('date', 'asc')
            ->take(5)
            ->get();

        // 1. Analyze Category Skip Patterns
        $patternParts = [];
        if ($this->overdueTasks->isNotEmpty()) {
            $categories = $this->overdueTasks->groupBy('category');
            $topSkippedCat = $categories->sortByDesc(fn($g) => $g->count())->keys()->first();
            $count = $categories->first()->count();
            if ($topSkippedCat) {
                $patternParts[] = "kategori '{$topSkippedCat}' ({$count}x tertunda)";
            }
        }

        // 2. Analyze Recorded Reasons
        $tasksWithReasons = $user->tasks()
            ->whereNotNull('skip_reason')
            ->where('date', '>=', Carbon::today()->subDays(30)->toDateString())
            ->get();

        if ($tasksWithReasons->isNotEmpty()) {
            $reasonsGrouped = $tasksWithReasons->groupBy('skip_reason');
            $topReason = $reasonsGrouped->sortByDesc(fn($g) => $g->count())->keys()->first();
            $topReasonCount = $reasonsGrouped->get($topReason)->count();
            $this->topOverdueReason = $topReason;
            
            $breakdown = [];
            foreach ($reasonsGrouped as $rName => $rGroup) {
                $breakdown[] = [
                    'reason' => $rName,
                    'count' => $rGroup->count(),
                ];
            }
            $this->reasonBreakdown = $breakdown;

            $recommendation = match($topReason) {
                'Kelelahan / Burnout' => 'Saran AI: Kurangi beban harian dan manfaatkan jam emas pagi harimu untuk tugas berat.',
                'Waktu Tidak Cukup' => 'Saran AI: Batasi maksimal 3 tugas utama per hari agar tidak bentrok dengan jadwal lain.',
                'Terlalu Rumit / Bingung' => 'Saran AI: Gunakan tombol AI Subtasks di halaman Tasks untuk memecah tugas besar menjadi langkah kecil.',
                'Terdistraksi / Prokrastinasi' => 'Saran AI: Nyalakan Pomodoro Zen Mode 15 menit untuk memicu fokus tanpa beban berat.',
                default => 'Saran AI: Luangkan 5 menit refleksi harian di Jurnal untuk mengevaluasi ritme belajarmu.'
            };

            $this->overduePatternNotice = "Pola Hambatan Utama: Tugas paling sering tertunda karena '{$topReason}' ({$topReasonCount}x tercatat). {$recommendation}";
        } elseif (!empty($patternParts)) {
            $this->overduePatternNotice = "Kamu cenderung menunda tugas di " . implode(', ', $patternParts) . ". Berikan alasan pada tugas tertunda di bawah agar AI dapat menganalisis hambatanmu!";
        } else {
            $this->overduePatternNotice = "Berikan alasan saat menunda tugas agar ATOMA dapat menganalisis pola hambatan dan memberikan rekomendasi yang presisi.";
        }
    }

    public function openReasonModal($taskId)
    {
        $task = auth()->user()->tasks()->with(['skillTarget', 'skill'])->findOrFail($taskId);
        $this->selectedOverdueTaskId = $task->id;
        $this->selectedOverdueTaskTitle = $task->title;
        $this->selectedOverdueTaskIsMilestone = !empty($task->skill_id) || !empty($task->skill_target_id);
        $this->selectedOverdueTaskDayNumber = $task->skillTarget?->day_number;
        $this->chosenReason = $task->skip_reason ?: 'Kelelahan / Burnout';
        $this->customReasonNote = $task->skip_reason_note ?: '';
        $this->autoRescheduleTomorrow = !$this->selectedOverdueTaskIsMilestone;
        $this->showReasonModal = true;
    }

    public function closeReasonModal()
    {
        $this->showReasonModal = false;
        $this->selectedOverdueTaskId = null;
        $this->selectedOverdueTaskTitle = '';
        $this->selectedOverdueTaskIsMilestone = false;
        $this->selectedOverdueTaskDayNumber = null;
        $this->chosenReason = 'Kelelahan / Burnout';
        $this->customReasonNote = '';
    }

    public function saveReason()
    {
        if (!$this->selectedOverdueTaskId) return;

        $task = auth()->user()->tasks()->with(['skillTarget', 'skill'])->findOrFail($this->selectedOverdueTaskId);
        $reason = $this->chosenReason ?: 'Lainnya';
        $isMilestone = !empty($task->skill_id) || !empty($task->skill_target_id);
        
        $updateData = [
            'skip_reason' => $reason,
            'skip_reason_note' => $this->customReasonNote,
        ];

        // Milestone tasks follow 1-30 days pathway, so never change date to tomorrow
        if (!$isMilestone && $this->autoRescheduleTomorrow) {
            $updateData['date'] = Carbon::tomorrow()->toDateString();
        }

        $task->update($updateData);

        $msg = $isMilestone 
            ? "Alasan evaluasi Milestone '{$reason}' berhasil dicatat!" 
            : ($this->autoRescheduleTomorrow 
                ? "Alasan '{$reason}' dicatat & tugas dijadwalkan ulang untuk besok!" 
                : "Alasan '{$reason}' berhasil dicatat!");

        $this->dispatch('toast', message: $msg, type: 'success');
        $this->closeReasonModal();
        $this->loadStats();
        $this->loadOverdueTasks();
    }

    public function markDone($taskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $task->update(['is_completed' => true]);

        if ($task->skill_target_id && $target = $task->skillTarget) {
            $target->update(['status' => 'completed']);
            if ($target->day_number === $target->skill->current_day && $target->skill->current_day < $target->skill->target_days) {
                $target->skill->increment('current_day');
            }
        }

        $user = auth()->user();
        $user = \App\Services\StreakEvaluatorService::evaluate($user);
        $evaluation = \App\Services\TaskXpService::evaluateTaskCompletion($task, $user);

        $this->dispatch('toast', message: $evaluation['message'], type: $evaluation['type']);
        $this->dispatch('profile-updated');
        $this->loadStats();
        $this->loadOverdueTasks();
        $this->dispatch('refresh-charts');
    }

    public function rescheduleTask($taskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $task->update(['date' => Carbon::tomorrow()->toDateString()]);
        $this->dispatch('toast', message: 'Tugas dijadwalkan ulang untuk besok!', type: 'nudge');
        $this->loadStats();
        $this->loadOverdueTasks();
    }

    public function deleteTask($taskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $task->delete();
        $this->dispatch('toast', message: 'Tugas berhasil dihapus', type: 'nudge');
        $this->loadStats();
        $this->loadOverdueTasks();
    }

    public function loadSummaries()
    {
        $user = auth()->user();
        if (!$user) return;

        $today = Carbon::today()->toDateString();
        $this->todaySummary = DailySummary::where('user_id', $user->id)
            ->where('summary_date', $today)
            ->first();

        if ($this->todaySummary) {
            $this->summaryLastGeneratedAt = $this->todaySummary->updated_at->diffForHumans();
        }

        $periodStart = Carbon::today()->subDays(6)->toDateString();
        $this->weeklyInsight = AiInsight::where('user_id', $user->id)
            ->where('period_start', $periodStart)
            ->first();

        if ($this->weeklyInsight) {
            $this->insightLastGeneratedAt = $this->weeklyInsight->generated_at->diffForHumans();
            $this->canRefreshWeekly = !$this->weeklyInsight->generated_at->isToday();
        } else {
            $this->canRefreshWeekly = true;
        }
    }

    public function generateDailySummary(AtomaInsightService $insightService)
    {
        $this->isLoadingDaily = true;
        $res = $insightService->generateDailySummary(auth()->user());
        $this->todaySummary = $res['summary'];
        $this->summaryLastGeneratedAt = $this->todaySummary->updated_at->diffForHumans();
        $this->isLoadingDaily = false;
        $this->dispatch('toast', message: 'AI Daily Summary berhasil dirangkum!', type: 'success');
    }

    public function generateWeeklyInsight(AtomaInsightService $insightService)
    {
        if (!$this->canRefreshWeekly && $this->weeklyInsight) {
            $this->dispatch('toast', message: 'ATOMA Says hanya dapat diperbarui 1x per hari.', type: 'warning');
            return;
        }

        $this->isLoadingWeekly = true;
        $res = $insightService->generateWeeklyInsight(auth()->user());
        $this->weeklyInsight = $res['insight'];
        $this->insightLastGeneratedAt = $this->weeklyInsight->generated_at->diffForHumans();
        $this->canRefreshWeekly = false;
        $this->isLoadingWeekly = false;
        $this->dispatch('toast', message: 'ATOMA Says berhasil diperbarui!', type: 'success');
    }

    public function with(): array
    {
        $user = auth()->user();
        if (!$user) {
            return [
                'chartDays' => [],
                'moodHeatmap' => [],
            ];
        }

        $daysRange = $this->filterPeriod === 'month' ? 29 : 6;

        $chartDays = collect(range($daysRange, 0))->map(function ($daysAgo) use ($user) {
            $date = Carbon::today()->subDays($daysAgo);
            $dateStr = $date->toDateString();

            $completedTasks = $user->tasks()
                ->where('is_completed', true)
                ->where(function($q) use ($dateStr) {
                    $q->whereDate('date', $dateStr)->orWhereDate('updated_at', $dateStr);
                })->count();

            $focusMinutes = (int) $user->focusSessions()
                ->whereDate('created_at', $dateStr)
                ->sum('minutes');

            if ($focusMinutes === 0 && $completedTasks > 0) {
                $focusMinutes = $completedTasks * 25;
            }

            $journals = $user->journals()
                ->whereDate('created_at', $dateStr)->count();

            return [
                'label' => $date->format('d M'),
                'tasks' => $completedTasks,
                'focus' => $focusMinutes,
                'journals' => $journals,
            ];
        });

        $moodHeatmap = collect(range(29, 0))->map(function ($daysAgo) use ($user) {
            $date = Carbon::today()->subDays($daysAgo);
            $dateStr = $date->toDateString();
            $journal = $user->journals()->whereDate('created_at', $dateStr)->first();

            return [
                'date' => $date->format('d M Y'),
                'mood' => $journal?->mood ?? null,
            ];
        });

        return [
            'chartDays' => $chartDays,
            'moodHeatmap' => $moodHeatmap,
        ];
    }
}; ?>

<div class="relative pb-24 max-w-5xl mx-auto px-4 sm:px-6"
     x-data="{
        activityChartInstance: null,
        doughnutChartInstance: null,
        chartDaysData: @js($chartDays),
        categoryData: @js($categoryBreakdown),
        
        initCharts() {
            this.$nextTick(() => {
                this.renderActivityChart();
                this.renderDoughnutChart();
            });
        },
        
        renderActivityChart() {
            let canvas = document.getElementById('activityChart');
            if (!canvas || typeof Chart === 'undefined') return;
            if (this.activityChartInstance) this.activityChartInstance.destroy();
            
            let ctx = canvas.getContext('2d');
            this.activityChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.chartDaysData.map(d => d.label),
                    datasets: [
                        {
                            label: 'Tasks Selesai',
                            data: this.chartDaysData.map(d => d.tasks),
                            borderColor: '#3E64FF',
                            backgroundColor: 'rgba(62, 100, 255, 0.12)',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2.5,
                            pointBackgroundColor: '#3E64FF',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Waktu Fokus (Menit)',
                            data: this.chartDaysData.map(d => d.focus),
                            borderColor: '#5EDFFF',
                            backgroundColor: 'rgba(94, 223, 255, 0.08)',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2,
                            borderDash: [4, 4],
                            pointBackgroundColor: '#5EDFFF',
                            pointRadius: 3,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                font: { size: 11, weight: 'bold' },
                                color: document.documentElement.classList.contains('dark') ? '#9ca3af' : '#4b5563'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17, 24, 39, 0.95)',
                            titleFont: { weight: 'bold' },
                            padding: 10,
                            cornerRadius: 10
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            ticks: { stepSize: 1, precision: 0 },
                            grid: { color: document.documentElement.classList.contains('dark') ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            ticks: { callback: v => v + 'm' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        },
        
        renderDoughnutChart() {
            let canvas = document.getElementById('categoryDoughnutChart');
            if (!canvas || typeof Chart === 'undefined') return;
            if (this.doughnutChartInstance) this.doughnutChartInstance.destroy();
            
            let ctx = canvas.getContext('2d');
            let labels = this.categoryData.map(c => c.name);
            let data = this.categoryData.map(c => c.count);
            
            this.doughnutChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: ['#3E64FF', '#5EDFFF', '#10B981', '#F59E0B'],
                        borderWidth: 2,
                        borderColor: document.documentElement.classList.contains('dark') ? '#1f2937' : '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
     }"
     x-init="initCharts()"
     @refresh-charts.window="
        chartDaysData = @js($chartDays);
        categoryData = @js($categoryBreakdown);
        initCharts();
     ">

    <!-- Header & Filter (Mobile First Sticky Header) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 sticky top-[64px] sm:top-[72px] bg-gray-50/95 dark:bg-gray-900/95 backdrop-blur-md z-20 py-3 sm:py-4 border-b border-gray-200/50 dark:border-gray-800/50">
        <div>
            <h2 class="text-2xl sm:text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-atoma-blue to-atoma-cyan tracking-tight flex items-center gap-2">
                <span>ATOMA Insights</span>
                <span class="text-xl">✨</span>
            </h2>
            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium">Analisis cerdas ritme kerja, deep work, & evolusi kebiasaanmu</p>
        </div>

        <!-- Actions: Infographic & Period Switcher -->
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <button @click="$dispatch('open-infographic')" type="button" class="px-3.5 py-2.5 bg-gradient-to-r from-atoma-blue to-[#284fd6] hover:bg-[#3455dc] text-white rounded-2xl text-xs font-black shadow-md shadow-atoma-blue/25 transition-all active:scale-95 flex items-center gap-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                <span>Kartu Laporan PNG 📸</span>
            </button>

            <div class="inline-flex p-1 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-gray-700/60 shadow-xs shrink-0 flex-1 sm:flex-initial justify-stretch">
                <button type="button" 
                        wire:click="setFilterPeriod('7days')" 
                        class="flex-1 sm:flex-initial px-4 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer {{ $filterPeriod === '7days' ? 'bg-atoma-blue text-white shadow-md shadow-atoma-blue/25' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    Minggu Ini
                </button>
                <button type="button" 
                        wire:click="setFilterPeriod('month')" 
                        class="flex-1 sm:flex-initial px-4 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer {{ $filterPeriod === 'month' ? 'bg-atoma-blue text-white shadow-md shadow-atoma-blue/25' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    Bulan Ini
                </button>
            </div>
        </div>
    </div>

    <!-- BAGIAN 1 — HEADLINE METRIC CARDS & GROWTH DELTAS (6 Kartu Mobile Optimized) -->
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-5 mb-8">
        
        <!-- Card 1: Completion Rate + Delta -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 relative overflow-hidden flex flex-col justify-between transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">Completion Rate</span>
                <span class="w-8 h-8 rounded-xl bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan flex items-center justify-center text-sm font-bold">
                    🎯
                </span>
            </div>
            
            <div class="flex items-baseline gap-2 mb-2">
                <div class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                    {{ $completionRate }}%
                </div>
                <div class="text-[10px] sm:text-xs font-black flex items-center {{ $completionRateDelta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                    <span>{{ $completionRateDelta >= 0 ? '▲ +' : '▼ ' }}{{ $completionRateDelta }}%</span>
                </div>
            </div>

            <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                <span>{{ $tasksDoneCount }} dari {{ $totalTasksCount }} tugas selesai</span>
                <span class="font-bold text-atoma-blue">{{ $filterPeriod === 'month' ? '30 Hari' : '7 Hari' }}</span>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full mt-2.5 overflow-hidden">
                <div class="bg-gradient-to-r from-atoma-blue to-atoma-cyan h-full rounded-full transition-all duration-700" style="width: {{ min(100, $completionRate) }}%"></div>
            </div>
        </div>

        <!-- Card 2: Deep Work & Pomodoro Focus + Delta -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 relative overflow-hidden flex flex-col justify-between transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deep Work Focus</span>
                <span class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-500 flex items-center justify-center text-sm font-bold">
                    🍅
                </span>
            </div>
            
            <div class="flex items-baseline gap-2 mb-2">
                <div class="text-2xl sm:text-3xl font-black text-atoma-blue dark:text-atoma-cyan">
                    {{ $totalFocusHours }} <span class="text-sm sm:text-base font-bold text-gray-500 dark:text-gray-400">Jam</span>
                </div>
                @if($focusDeltaHours != 0)
                    <div class="text-[10px] sm:text-xs font-black {{ $focusDeltaHours > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        <span>{{ $focusDeltaHours > 0 ? '▲ +' : '▼ ' }}{{ $focusDeltaHours }}j</span>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                <span>{{ $totalFocusSessions }} sesi fokus tercatat</span>
                <span class="font-bold text-atoma-blue">{{ $totalFocusMinutes }}m total</span>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full mt-2.5 overflow-hidden">
                <div class="bg-gradient-to-r from-rose-500 to-amber-500 h-full rounded-full transition-all duration-700" style="width: {{ min(100, $totalFocusHours * 10) }}%"></div>
            </div>
        </div>

        <!-- Card 3: Daily Streak Consistency -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 relative overflow-hidden flex flex-col justify-between transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">Daily Streak</span>
                <span class="w-8 h-8 rounded-xl bg-orange-50 dark:bg-orange-950/40 text-orange-500 flex items-center justify-center text-sm font-bold animate-pulse">
                    🔥
                </span>
            </div>
            
            <div class="flex items-baseline gap-2 mb-2">
                <div class="text-2xl sm:text-3xl font-black text-orange-500">
                    {{ $streak }} <span class="text-sm sm:text-base font-bold text-gray-500 dark:text-gray-400">Hari</span>
                </div>
                <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-orange-100 dark:bg-orange-950/60 text-orange-600 dark:text-orange-400">
                    Aktif
                </span>
            </div>

            <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                <span>Rekor terbaikmu</span>
                <span class="font-bold text-orange-500">{{ $longestStreak }} Hari</span>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full mt-2.5 overflow-hidden">
                <div class="bg-gradient-to-r from-orange-500 to-amber-400 h-full rounded-full transition-all duration-700" style="width: {{ min(100, $streak * 10) }}%"></div>
            </div>
        </div>

        <!-- Card 4: Overdue Action Radar -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 relative overflow-hidden flex flex-col justify-between transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">Overdue Tasks</span>
                <span class="w-8 h-8 rounded-xl {{ $overdueCount > 0 ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-500' : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-500' }} flex items-center justify-center text-sm font-bold">
                    {{ $overdueCount > 0 ? '⚠️' : '🎉' }}
                </span>
            </div>
            
            <div class="flex items-baseline gap-2 mb-2">
                <div class="text-2xl sm:text-3xl font-black {{ $overdueCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    {{ $overdueCount }} <span class="text-sm sm:text-base font-bold text-gray-500 dark:text-gray-400">Tugas</span>
                </div>
                @if($overdueDelta != 0)
                    <div class="text-[10px] sm:text-xs font-black {{ $overdueDelta < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        <span>{{ $overdueDelta < 0 ? '▼ ' : '▲ +' }}{{ $overdueDelta }}</span>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                <span>{{ $overdueCount > 0 ? 'Perlu tindakan cepat' : 'Semua tugas aman terkendali' }}</span>
                <a href="#overdue-section" class="text-atoma-blue font-bold hover:underline">Periksa &darr;</a>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full mt-2.5 overflow-hidden">
                <div class="{{ $overdueCount > 0 ? 'bg-rose-500' : 'bg-emerald-500' }} h-full rounded-full" style="width: {{ min(100, $overdueCount * 20) }}%"></div>
            </div>
        </div>

        <!-- Card 5: RPG Level & Coins Velocity -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 relative overflow-hidden flex flex-col justify-between transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">Level & Koin RPG</span>
                <span class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-500 flex items-center justify-center text-sm font-bold">
                    ⭐
                </span>
            </div>
            
            <div class="flex items-baseline gap-2 mb-2">
                <div class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                    Lv. {{ $userLevel }}
                </div>
                <span class="text-xs font-black text-amber-500 flex items-center gap-1">
                    <span>🪙</span> {{ number_format($userCoins) }}
                </span>
            </div>

            <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                <span>Total XP: {{ number_format($userXp) }}</span>
                <span class="font-bold text-atoma-blue">{{ $levelProgress }}% ke Lv. {{ $userLevel + 1 }}</span>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full mt-2.5 overflow-hidden">
                <div class="bg-gradient-to-r from-atoma-blue to-atoma-cyan h-full rounded-full transition-all duration-700" style="width: {{ $levelProgress }}%"></div>
            </div>
        </div>

        <!-- Card 6: Dominant Mood & Finance Net Balance -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 relative overflow-hidden flex flex-col justify-between transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">Mood & Arus Kas</span>
                <span class="w-8 h-8 rounded-xl bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue flex items-center justify-center text-sm font-bold">
                    💰
                </span>
            </div>
            
            <div class="flex items-baseline gap-2 mb-2">
                <div class="text-xl sm:text-2xl font-black {{ $financeBalance >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }} truncate">
                    Rp {{ number_format($financeBalance, 0, ',', '.') }}
                </div>
            </div>

            <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                <span class="capitalize flex items-center gap-1">
                    Mood: <strong>{{ $mostFrequentMood }}</strong>
                    @switch($mostFrequentMood)
                        @case('happy') 😊 @break
                        @case('excited') 🤩 @break
                        @case('sad') 😢 @break
                        @case('tired') 😴 @break
                        @default 😐
                    @endswitch
                </span>
                <span class="font-bold text-gray-400">{{ $journalsWritten }} jurnal</span>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full mt-2.5 overflow-hidden">
                <div class="bg-emerald-500 h-full rounded-full" style="width: {{ $financeBalance >= 0 ? '80%' : '30%' }}"></div>
            </div>
        </div>

    </div>

    <!-- BAGIAN 2: ACTIVITY & DEEP WORK FOCUS DUAL CHART -->
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
            <div>
                <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                    <span>📈</span> Activity & Deep Work Trends
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Korelasi antara tugas selesai dan menit fokus harian</p>
            </div>
            <div class="flex items-center gap-3 text-xs font-bold">
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-atoma-blue"></span>
                    <span class="text-gray-600 dark:text-gray-400">Tasks Selesai</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-atoma-cyan"></span>
                    <span class="text-gray-600 dark:text-gray-400">Waktu Fokus (Menit)</span>
                </div>
            </div>
        </div>

        <div class="h-64 sm:h-72 relative">
            <canvas id="activityChart"></canvas>
        </div>
    </div>

    <!-- BAGIAN 3: LIFE BALANCE (DOUGHNUT) & PEAK PRODUCTIVITY RADAR (2 COLS) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        
        <!-- Left: Life Balance / Category Distribution -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <span>⚖️</span> Life Balance Wheel
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Distribusi energi & proporsi kategori tugas</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-black bg-atoma-ice text-atoma-blue border border-atoma-aqua/50">
                        Score: {{ $lifeBalanceScore }}/100
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center my-4">
                    <div class="h-44 sm:h-48 relative flex items-center justify-center">
                        <canvas id="categoryDoughnutChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-xs font-bold text-gray-400">Kategori</span>
                            <span class="text-xl font-black text-gray-900 dark:text-white">{{ count($categoryBreakdown) }}</span>
                        </div>
                    </div>

                    <div class="space-y-2.5">
                        @php
                            $catColors = ['#3E64FF', '#5EDFFF', '#10B981', '#F59E0B'];
                        @endphp
                        @foreach($categoryBreakdown as $idx => $cat)
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $catColors[$idx % 4] }}"></span>
                                    <span class="font-bold text-gray-700 dark:text-gray-300">{{ $cat['name'] }}</span>
                                </div>
                                <span class="font-black text-gray-900 dark:text-white">{{ $cat['percentage'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- AI Life Balance Insight Notice -->
            <div class="p-3.5 rounded-2xl bg-atoma-ice/60 dark:bg-atoma-blue/15 border border-atoma-aqua/50 dark:border-atoma-blue/30 text-xs text-gray-800 dark:text-gray-200 font-medium flex items-start gap-2.5 mt-2">
                <span class="text-base shrink-0">💡</span>
                <p class="leading-relaxed">{{ $lifeBalanceNotice }}</p>
            </div>
        </div>

        <!-- Right: Peak Productivity Radar & Best Day Analysis -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <span>🕒</span> Peak Productivity Radar
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Kapan otak & energimu berada di titik optimal</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/40">
                        {{ $bestDayName }} ({{ $bestDayRate }}% Selesai)
                    </span>
                </div>

                <!-- Golden Hour Highlight Card -->
                <div class="p-4 rounded-2xl bg-gradient-to-r from-atoma-blue/15 via-atoma-cyan/10 to-transparent border border-atoma-blue/30 mb-5 flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-black uppercase text-atoma-blue tracking-wider">Jam Emas Produktifmu</div>
                        <div class="text-lg sm:text-xl font-black text-gray-900 dark:text-white mt-0.5">
                            {{ $goldenTimeLabel }}
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Waktu terbaik untuk tugas berat & deep work</p>
                    </div>
                    <div class="text-3xl sm:text-4xl">⚡</div>
                </div>

                <!-- Time distribution bars -->
                @php
                    $totalTimeSlots = max(1, $morningTasksCount + $afternoonTasksCount + $eveningTasksCount + $nightTasksCount);
                @endphp
                <div class="space-y-3">
                    <!-- Pagi -->
                    <div>
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-gray-700 dark:text-gray-300">🌅 Pagi (06:00 - 12:00)</span>
                            <span class="text-atoma-blue">{{ $morningTasksCount }} Tugas ({{ round(($morningTasksCount / $totalTimeSlots) * 100) }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                            <div class="bg-atoma-blue h-full rounded-full transition-all duration-500" style="width: {{ ($morningTasksCount / $totalTimeSlots) * 100 }}%"></div>
                        </div>
                    </div>

                    <!-- Siang / Sore -->
                    <div>
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-gray-700 dark:text-gray-300">☀️ Siang & Sore (12:00 - 18:00)</span>
                            <span class="text-atoma-cyan">{{ $afternoonTasksCount }} Tugas ({{ round(($afternoonTasksCount / $totalTimeSlots) * 100) }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                            <div class="bg-atoma-cyan h-full rounded-full transition-all duration-500" style="width: {{ ($afternoonTasksCount / $totalTimeSlots) * 100 }}%"></div>
                        </div>
                    </div>

                    <!-- Malam -->
                    <div>
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-gray-700 dark:text-gray-300">🌙 Malam (18:00 - 24:00)</span>
                            <span class="text-indigo-500">{{ $eveningTasksCount }} Tugas ({{ round(($eveningTasksCount / $totalTimeSlots) * 100) }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                            <div class="bg-indigo-500 h-full rounded-full transition-all duration-500" style="width: {{ ($eveningTasksCount / $totalTimeSlots) * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-4 leading-relaxed pl-1">
                📌 <strong>Tips:</strong> Hari <strong>{{ $bestDayName }}</strong> adalah hari paling efektifmu. Gunakan hari ini untuk menuntaskan milestone penting.
            </p>
        </div>

    </div>

    <!-- BAGIAN 4: RPG GAMIFICATION — WEEKLY PERSONA & LEAGUE STANDING (2 COLS) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        
        <!-- Left: Weekly Productivity Persona -->
        <div class="bg-gradient-to-br from-atoma-blue/20 via-atoma-cyan/10 to-white dark:to-gray-800 rounded-3xl p-5 sm:p-6 border border-atoma-blue/40 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] sm:text-xs font-black text-atoma-blue uppercase tracking-wider">
                        Weekly Persona Archetype
                    </span>
                    <span class="px-3 py-1 rounded-full text-xs font-black bg-white dark:bg-gray-800 text-atoma-blue shadow-xs border border-atoma-aqua/40">
                        {{ $weeklyPersona['tag'] }}
                    </span>
                </div>

                <div class="flex items-start gap-4 mb-4">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-white dark:bg-gray-800 shadow-md border border-atoma-blue/30 flex items-center justify-center text-3xl sm:text-4xl shrink-0">
                        {{ $weeklyPersona['badge'] }}
                    </div>
                    <div>
                        <h3 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white">
                            {{ $weeklyPersona['title'] }}
                        </h3>
                        <p class="text-xs font-bold text-atoma-blue mt-0.5">{{ $weeklyPersona['archetype'] }}</p>
                    </div>
                </div>

                <p class="text-xs sm:text-sm text-gray-700 dark:text-gray-300 leading-relaxed font-medium">
                    "{{ $weeklyPersona['desc'] }}"
                </p>
            </div>

            <div class="mt-4 pt-3 border-t border-atoma-blue/20 flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-gray-400 font-medium">Dihitung otomatis tiap akhir pekan</span>
                <span class="font-black text-atoma-blue">Level {{ $userLevel }}</span>
            </div>
        </div>

        <!-- Right: Weekly League Standing & Promotion Target -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <span>🏆</span> League Status & Target
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Sistem liga mingguan ATOMA</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-black {{ $leagueInfo['is_promoted'] ? 'bg-emerald-50 text-emerald-600 border border-emerald-300' : 'bg-amber-50 text-amber-600 border border-amber-300' }}">
                        {{ $leagueInfo['is_promoted'] ? '🚀 Di Jalur Promosi' : '⏳ Butuh +' . $leagueInfo['gap_xp'] . ' XP' }}
                    </span>
                </div>

                <div class="flex items-center gap-3 p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200/70 dark:border-gray-700/60 mb-4">
                    <span class="text-3xl">{{ $leagueInfo['icon'] }}</span>
                    <div>
                        <h4 class="font-black text-base text-gray-900 dark:text-white">{{ $leagueInfo['name'] }}</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Tier: {{ $leagueInfo['tier'] }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex justify-between text-xs font-bold">
                        <span class="text-gray-600 dark:text-gray-400">Progres XP Mingguan</span>
                        <span class="text-atoma-blue font-black">{{ $leagueInfo['weekly_xp'] }} / {{ $leagueInfo['min_weekly_xp'] }} XP</span>
                    </div>
                    @php
                        $leaguePct = min(100, round(($leagueInfo['weekly_xp'] / max(1, $leagueInfo['min_weekly_xp'])) * 100));
                    @endphp
                    <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                        <div class="bg-gradient-to-r from-atoma-blue to-atoma-cyan h-full rounded-full transition-all duration-700" style="width: {{ $leaguePct }}%"></div>
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-4 leading-relaxed pl-1">
                Settlement liga dilakukan setiap hari Senin pukul 00:00 WIB. Pertahankan posisi untuk klaim hadiah bonus koin!
            </p>
        </div>

    </div>

    <!-- BAGIAN 5: MOOD CORRELATION & 30-DAY HEATMAP -->
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
            <div>
                <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                    <span>🧠</span> Mood vs Productivity Correlation
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Koneksi psikologis antara catatan jurnal & penyelesaian tugas</p>
            </div>
            
            <!-- Legend -->
            <div class="hidden sm:flex items-center space-x-3 text-[10px] font-bold text-gray-600 dark:text-gray-400">
                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded bg-emerald-500 mr-1"></span> Happy</span>
                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded bg-amber-500 mr-1"></span> Excited</span>
                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded bg-atoma-blue mr-1"></span> Neutral</span>
                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded bg-rose-500 mr-1"></span> Tired/Sad</span>
            </div>
        </div>

        <!-- Correlation Summary Card -->
        <div class="p-4 rounded-2xl bg-atoma-ice/60 dark:bg-atoma-blue/15 border border-atoma-aqua/50 dark:border-atoma-blue/30 text-xs sm:text-sm text-gray-800 dark:text-gray-200 font-medium mb-5 flex items-start gap-3">
            <span class="text-xl shrink-0">📊</span>
            <p class="leading-relaxed">{{ $moodCorrelationSummary }}</p>
        </div>

        <!-- 30-Day Heatmap Grid -->
        <div class="grid grid-cols-6 sm:grid-cols-10 md:grid-cols-15 gap-2 sm:gap-2.5">
            @foreach($moodHeatmap as $index => $day)
                @php
                    $bgClass = match($day['mood']) {
                        'happy' => 'bg-emerald-500 text-white shadow-xs',
                        'excited' => 'bg-amber-500 text-white shadow-xs',
                        'neutral' => 'bg-atoma-blue text-white shadow-xs',
                        'sad' => 'bg-blue-500 text-white shadow-xs',
                        'tired' => 'bg-purple-500 text-white shadow-xs',
                        default => 'bg-gray-100 dark:bg-gray-700/50 border border-dashed border-gray-300 dark:border-gray-600 text-gray-400',
                    };
                @endphp
                
                <div title="{{ $day['date'] }}: {{ $day['mood'] ?? 'Tidak ada jurnal' }}" 
                     class="aspect-square rounded-xl {{ $bgClass }} flex flex-col items-center justify-center transition-all hover:scale-125 hover:z-10 cursor-pointer group relative">
                    <span class="text-[9px] font-black">
                        {{ Carbon::parse($day['date'])->format('j') }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- BAGIAN 6: OVERDUE ACTION CENTER (ID: overdue-section) -->
    <div id="overdue-section" class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 mb-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="text-rose-500">⚠️</span> Overdue Action Center
                    @if(count($overdueTasks) > 0)
                        <span class="ml-2 px-2.5 py-0.5 bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 rounded-full text-xs font-black">
                            {{ count($overdueTasks) }} Overdue
                        </span>
                    @endif
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Tindakan langsung tanpa perlu berpindah halaman</p>
            </div>
            
            <a href="{{ route('tasks') }}" wire:navigate class="text-xs font-bold text-atoma-blue hover:underline">
                Kelola Semua &rarr;
            </a>
        </div>

        @if(count($overdueTasks) === 0)
            <div class="text-center py-8 bg-emerald-50/60 dark:bg-emerald-950/30 rounded-2xl border border-emerald-200 dark:border-emerald-800/40">
                <div class="text-3xl mb-2">🎉</div>
                <h4 class="font-bold text-sm text-emerald-900 dark:text-emerald-200">Semua Tugas Aman!</h4>
                <p class="text-xs text-emerald-700 dark:text-emerald-400">Tidak ada tugas yang menunggak saat ini. Pertahankan fokusmu!</p>
            </div>
        @else
            @if(!empty($reasonBreakdown))
                <div class="mb-4 flex items-center gap-2 overflow-x-auto hide-scrollbar py-1">
                    <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400 shrink-0">📊 Hambatan Terdata:</span>
                    @foreach($reasonBreakdown as $rb)
                        <span class="shrink-0 px-2.5 py-1 rounded-full text-[10px] font-black bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50">
                            {{ $rb['reason'] }} ({{ $rb['count'] }}x)
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="space-y-3 mb-4">
                @foreach($overdueTasks as $task)
                    @php
                        $isMilestone = !empty($task->skill_id) || !empty($task->skill_target_id);
                    @endphp
                    <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-all hover:border-atoma-aqua/60">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
                                <h4 class="font-bold text-sm text-gray-900 dark:text-white">{{ $task->title }}</h4>
                                @if($isMilestone)
                                    <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full bg-atoma-blue text-white shadow-xs">
                                        🎯 Milestone @if($task->skillTarget) (Hari {{ $task->skillTarget->day_number }}/30) @endif
                                    </span>
                                @elseif($task->category)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan border border-atoma-aqua/40">
                                        {{ $task->category }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2 ml-4 text-xs">
                                <span class="text-rose-600 dark:text-rose-400 font-medium">
                                    📅 Terlewat: {{ $task->date ? Carbon::parse($task->date)->format('d M Y') : 'Belum selesai' }}
                                </span>
                                @if($task->skip_reason)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 text-[10px] font-bold border border-amber-300 dark:border-amber-700">
                                        💬 {{ $task->skip_reason }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Action Buttons: If Milestone, ONLY show "Beri Alasan" (no "Besok" reschedule because of 1-30 days roadmap concept) -->
                        <div class="flex items-center gap-2">
                            <button type="button" 
                                    wire:click="openReasonModal({{ $task->id }})" 
                                    class="flex-1 sm:flex-initial px-3.5 py-2 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white font-bold text-xs rounded-xl shadow-xs shadow-amber-500/20 transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                <span>📝</span>
                                <span>{{ $task->skip_reason ? 'Ubah Alasan' : 'Beri Alasan' }}</span>
                            </button>
                            @if(!$isMilestone)
                                <button type="button" 
                                        wire:click="rescheduleTask({{ $task->id }})" 
                                        class="flex-1 sm:flex-initial px-3.5 py-2 bg-atoma-blue hover:bg-[#3455dc] active:scale-95 text-white font-bold text-xs rounded-xl shadow-xs shadow-atoma-blue/20 transition-all flex items-center justify-center gap-1 cursor-pointer">
                                    <span>📅</span>
                                    <span>Besok</span>
                                </button>
                                <button type="button" 
                                        wire:click="deleteTask({{ $task->id }})" 
                                        wire:confirm="Hapus tugas ini?" 
                                        class="p-2 text-rose-600 dark:text-rose-400 hover:text-rose-700 bg-rose-100 dark:bg-rose-950/60 hover:bg-rose-200 rounded-xl transition-colors shrink-0 cursor-pointer"
                                        title="Hapus Tugas">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if($overduePatternNotice)
                <div class="p-3.5 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-900/60 rounded-2xl text-xs text-amber-900 dark:text-amber-300 font-medium flex items-center gap-2">
                    <span class="text-lg">🤖</span>
                    <span><strong>Analisis Pola AI:</strong> {{ $overduePatternNotice }}</span>
                </div>
            @endif
        @endif
    </div>

    <!-- BAGIAN 7: ATOMA SAYS & AI DAILY SUMMARY (SIGNATURE ATOMA BLUE GRADIENT) -->
    <div class="space-y-6">
        
        <!-- AI Daily Summary -->
        <div class="bg-gradient-to-r from-[#1b3cb8] via-atoma-blue to-[#284fd6] rounded-3xl p-6 sm:p-7 text-white shadow-xl border border-white/20 relative overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center text-xs font-black px-3.5 py-1 rounded-full bg-white text-atoma-blue shadow-xs">
                        🧠 AI Daily Summary
                    </span>
                    @if($summaryLastGeneratedAt)
                        <span class="text-xs text-white/80 font-medium">Updated {{ $summaryLastGeneratedAt }}</span>
                    @endif
                </div>

                <button type="button" wire:click="generateDailySummary" class="px-4 py-2 bg-white text-atoma-blue hover:bg-atoma-ice font-black text-xs rounded-xl shadow-md transition-all flex items-center justify-center cursor-pointer" {{ $isLoadingDaily ? 'disabled' : '' }}>
                    <span wire:loading.remove wire:target="generateDailySummary">
                        {{ $todaySummary ? '🔄 Rangkum Ulang Hari Ini' : '✨ Generate AI Summary' }}
                    </span>
                    <span wire:loading wire:target="generateDailySummary" class="flex items-center">
                        <svg class="animate-spin h-3.5 w-3.5 mr-1.5 text-atoma-blue" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Merangkum Data...
                    </span>
                </button>
            </div>

            <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 sm:p-5 border border-white/20">
                @if($todaySummary)
                    <p class="text-xs sm:text-sm leading-relaxed text-white font-medium">
                        "{{ $todaySummary->content }}"
                    </p>
                @else
                    <div class="text-center py-4 text-white/80 space-y-1">
                        <div class="text-2xl">✨</div>
                        <p class="text-xs font-medium">Belum ada rangkuman harian untuk hari ini. Klik tombol di atas untuk membuat.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Weekly ATOMA Says -->
        <div class="bg-gradient-to-r from-[#1733a0] via-[#1b3cb8] to-[#122675] rounded-3xl p-6 sm:p-8 text-white shadow-xl border border-white/20 relative overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-2xl bg-white text-atoma-blue flex items-center justify-center text-2xl shadow-md shrink-0">
                        🤖
                    </div>
                    <div>
                        <h3 class="text-xl sm:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                            <span>ATOMA Says</span>
                            <span>✨</span>
                        </h3>
                        <p class="text-xs text-white/80 font-medium">Analisis & Panduan Personal Life Coach Mingguan</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    @if($insightLastGeneratedAt)
                        <span class="text-xs text-white/80 font-medium">Diperbarui: {{ $insightLastGeneratedAt }}</span>
                    @endif

                    <button type="button" wire:click="generateWeeklyInsight" class="px-5 py-2.5 bg-white text-atoma-blue hover:bg-atoma-ice disabled:bg-white/40 disabled:text-white/60 font-black text-xs rounded-xl shadow-md transition-all flex items-center justify-center cursor-pointer" {{ (!$canRefreshWeekly || $isLoadingWeekly) ? 'disabled' : '' }}>
                        <span wire:loading.remove wire:target="generateWeeklyInsight">
                            {{ $canRefreshWeekly ? '🔄 Refresh Insight' : '🔒 Diperbarui Hari Ini' }}
                        </span>
                        <span wire:loading wire:target="generateWeeklyInsight" class="flex items-center">
                            <svg class="animate-spin h-3.5 w-3.5 mr-1.5 text-atoma-blue" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Menganalisis...
                        </span>
                    </button>
                </div>
            </div>

            @if($weeklyInsight)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                    <!-- 💡 Observasi -->
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 space-y-2">
                        <div class="flex items-center space-x-2 text-atoma-aqua font-extrabold text-sm">
                            <span>💡</span>
                            <span>Observasi Mingguan</span>
                        </div>
                        <p class="text-xs sm:text-sm text-white/95 leading-relaxed font-medium">
                            {{ $weeklyInsight->observasi }}
                        </p>
                    </div>

                    <!-- ⚠️ Tantangan -->
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 space-y-2">
                        <div class="flex items-center space-x-2 text-amber-300 font-extrabold text-sm">
                            <span>⚠️</span>
                            <span>Tantangan & Solusi</span>
                        </div>
                        <p class="text-xs sm:text-sm text-white/95 leading-relaxed font-medium">
                            {{ $weeklyInsight->tantangan }}
                        </p>
                    </div>

                    <!-- ✨ Afirmasi -->
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 space-y-2">
                        <div class="flex items-center space-x-2 text-emerald-300 font-extrabold text-sm">
                            <span>✨</span>
                            <span>Afirmasi & Motivasi</span>
                        </div>
                        <p class="text-xs sm:text-sm text-white/95 leading-relaxed font-medium">
                            {{ $weeklyInsight->afirmasi }}
                        </p>
                    </div>
                </div>
            @else
                <div class="text-center py-8 bg-white/5 rounded-2xl border border-white/10 space-y-2">
                    <div class="text-3xl">🔮</div>
                    <h4 class="text-base font-bold text-white">Belum Ada Weekly Insight</h4>
                    <p class="text-xs text-white/80 max-w-md mx-auto">Klik tombol Refresh Insight di atas untuk meminta ATOMA menganalisis pola produktivitasmu selama 7 hari terakhir.</p>
                </div>
            @endif
        </div>

    </div>

    <!-- MODAL: Beri Alasan Tugas Tertunda -->
    @if($showReasonModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl border border-gray-200 dark:border-gray-700 space-y-4 animate-in fade-in zoom-in duration-150">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">📝</span>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white">Alasan Tertunda</h3>
                    </div>
                    <button type="button" wire:click="closeReasonModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg font-bold cursor-pointer">✕</button>
                </div>

                <div class="p-3 bg-gray-50 dark:bg-gray-700/40 rounded-2xl border border-gray-200 dark:border-gray-700 text-xs">
                    <span class="text-gray-400">Tugas:</span>
                    <strong class="text-gray-900 dark:text-white block truncate">{{ $selectedOverdueTaskTitle }}</strong>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300">Pilih Faktor Penyebab Utama:</label>
                    @php
                        $presetReasons = [
                            'Kelelahan / Burnout' => '🥱 Kurang energi, butuh istirahat',
                            'Waktu Tidak Cukup' => '⏳ Jadwal bentrok atau ada urusan lain',
                            'Terlalu Rumit / Bingung' => '🤯 Belum tahu cara memulai langkah awal',
                            'Terdistraksi / Prokrastinasi' => '📱 Kehilangan fokus / menunda-nunda',
                            'Urusan Darurat' => '🌧️ Ada keadaan mendesak di luar kendali',
                            'Lainnya' => '✍️ Alasan lainnya...',
                        ];
                    @endphp

                    <div class="space-y-1.5">
                        @foreach($presetReasons as $rKey => $rDesc)
                            <label class="flex items-center gap-2.5 p-2.5 rounded-xl border cursor-pointer transition-all {{ $chosenReason === $rKey ? 'bg-atoma-ice dark:bg-atoma-blue/20 border-atoma-blue text-atoma-blue font-bold shadow-xs' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-700 dark:text-gray-300 text-xs' }}">
                                <input type="radio" wire:model.live="chosenReason" value="{{ $rKey }}" class="text-atoma-blue focus:ring-atoma-blue">
                                <div class="flex-1">
                                    <div class="text-xs font-bold">{{ $rKey }}</div>
                                    <div class="text-[10px] text-gray-400 font-normal">{{ $rDesc }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Catatan Tambahan (Opsional):</label>
                    <textarea wire:model="customReasonNote" placeholder="Tuliskan kendala spesifikmu jika ada..." rows="2" class="w-full text-xs bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 rounded-xl p-2.5 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-atoma-blue"></textarea>
                </div>

                @if($selectedOverdueTaskIsMilestone)
                    <div class="p-3.5 bg-atoma-ice dark:bg-atoma-blue/15 rounded-2xl border border-atoma-aqua/50 dark:border-atoma-blue/30 flex items-start gap-2.5 text-xs">
                        <span class="text-base shrink-0">🎯</span>
                        <p class="text-gray-800 dark:text-gray-200 leading-relaxed font-medium">
                            <strong>Target Milestone @if($selectedOverdueTaskDayNumber)(Hari {{ $selectedOverdueTaskDayNumber }}/30)@endif:</strong> Alasan dicatat khusus untuk evaluasi & analisis AI Coach tanpa merubah urutan pathway 1-30 hari.
                        </p>
                    </div>
                @else
                    <div class="p-3 bg-atoma-ice/60 dark:bg-atoma-blue/10 rounded-xl border border-atoma-aqua/40 flex items-center gap-2 text-xs">
                        <input type="checkbox" wire:model="autoRescheduleTomorrow" id="autoRescheduleCheckbox" class="rounded text-atoma-blue focus:ring-atoma-blue cursor-pointer">
                        <label for="autoRescheduleCheckbox" class="cursor-pointer text-gray-700 dark:text-gray-300 font-medium">
                            Jadwalkan ulang tugas ke besok (H+1)
                        </label>
                    </div>
                @endif

                <div class="flex items-center gap-2 pt-2">
                    <button type="button" wire:click="closeReasonModal" class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-bold text-xs rounded-xl cursor-pointer">
                        Batal
                    </button>
                    <button type="button" wire:click="saveReason" class="flex-1 py-2.5 bg-atoma-blue hover:bg-[#3455dc] text-white font-bold text-xs rounded-xl shadow-md shadow-atoma-blue/25 cursor-pointer">
                        Simpan & Analisis
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
