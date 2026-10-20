<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Task;
use App\Models\Journal;
use App\Models\AiInsight;
use App\Models\FocusSession;
use App\Models\WorkoutSession;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use App\Services\AtomaInsightService;
use App\Services\LeagueService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    public $activeTab = 'overview'; // 'overview', 'fitness'
    public $fitnessPeriod = 'week'; // 'week', 'month', 'year'

    // Workout Logging Modal
    public $showWorkoutModal = false;
    public $workoutDate = '';
    public $workoutTitle = 'Lari';
    public $workoutType = 'cardio'; // 'cardio', 'bodyweight', 'gym'
    public $workoutDistanceKm = 5.0;
    public $workoutDurationMinutes = 30;
    public $workoutSets = 4;
    public $workoutReps = 25;
    public $workoutWeight = 0;
    public $workoutNotes = '';

    public $filterPeriod = '7days'; // 7days, month
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
    public $deletedOverdueCount = 0;
    public $deletedOverdueTasks = [];
    public $dismissedOverdueCount = 0;
    public $dismissedOverdueTasks = [];

    public $financeBalance = 0;
    public $journalsWritten = 0;
    public $mostFrequentMood = 'neutral';

    // Peak Productivity & Radar
    public $morningTasksCount = 0;
    public $afternoonTasksCount = 0;
    public $eveningTasksCount = 0;
    public $nightTasksCount = 0;
    public $goldenTimeLabel = 'Belum Terdeteksi';
    public $bestDayName = '-';
    public $bestDayRate = 0;

    // Life Balance & Category Distribution
    public $categoryBreakdown = [];
    public $lifeBalanceNotice = '';
    public $lifeBalanceScore = 0;

    // RPG Gamification & Weekly Persona
    public $weeklyPersona = [
        'title' => 'The Deep Thinker',
        'badge' => '',
        'svg' => 'images/icons/feature_zen.svg',
        'archetype' => 'Deep Work & Reflective',
        'desc' => 'Kamu unggul dalam fokus mendalam dan refleksi diri. Sesi fokus panjang dan kejernihan pikiran adalah senjatamu.',
        'tag' => 'Fokus Tinggi',
    ];
    public $userLevel = 1;
    public $userXp = 0;
    public $levelProgress = 0;
    public $userCoins = 0;
    public $leagueInfo = [];

    // Productivity RPG Stats & Prestige System
    public $rpgStats = ['focus' => 0, 'consistency' => 0, 'execution' => 0, 'organization' => 0];
    public $prestigeTier = [];
    public $prestigeLevel = 1;
    public $levelProgressData = ['current_xp' => 0, 'required_xp' => 100, 'percentage' => 0];
    public $seasonalProgress = ['level' => 1, 'xp' => 0, 'season_name' => ''];
    public $lifetimeXp = 0;
    public $memberSince = null;
    public $totalTasksCompletedCount = 0;

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

    // Weekly ATOMA Says & Monthly Capstone
    public $weeklyInsight = null;
    public $monthlyInsight = null;
    public $insightLastGeneratedAt = null;
    public $monthlyLastGeneratedAt = null;
    public $canRefreshWeekly = true;
    public $canRefreshMonthly = true;
    public $isLoadingMonthly = false;

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

        $cacheKey = "insights_stats_v2_{$user->id}_{$this->filterPeriod}";
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if ($cached && is_array($cached)) {
            foreach ($cached as $key => $val) {
                $this->{$key} = $val;
            }
        } else {
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

            $this->deletedOverdueCount = Task::onlyTrashed()
                ->where('user_id', $user->id)
                ->where('is_deleted_overdue', true)
                ->count();

            $this->dismissedOverdueCount = $user->tasks()
                ->where('is_completed', false)
                ->where('is_overdue_dismissed', true)
                ->where('date', '<', $today->toDateString())
                ->count();

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

            // 9. Weekly Productivity Persona
            $this->determineProductivityPersona();

            // Save to Cache for 15 minutes
            $toStore = [
                'totalTasksCount' => $this->totalTasksCount,
                'tasksDoneCount' => $this->tasksDoneCount,
                'completionRate' => $this->completionRate,
                'completionRateDelta' => $this->completionRateDelta,
                'tasksDoneDelta' => $this->tasksDoneDelta,
                'totalFocusMinutes' => $this->totalFocusMinutes,
                'totalFocusHours' => $this->totalFocusHours,
                'focusDeltaHours' => $this->focusDeltaHours,
                'totalFocusSessions' => $this->totalFocusSessions,
                'overdueCount' => $this->overdueCount,
                'overdueDelta' => $this->overdueDelta,
                'deletedOverdueCount' => $this->deletedOverdueCount,
                'dismissedOverdueCount' => $this->dismissedOverdueCount,
                'financeBalance' => $this->financeBalance,
                'journalsWritten' => $this->journalsWritten,
                'mostFrequentMood' => $this->mostFrequentMood,
                'morningTasksCount' => $this->morningTasksCount,
                'afternoonTasksCount' => $this->afternoonTasksCount,
                'eveningTasksCount' => $this->eveningTasksCount,
                'nightTasksCount' => $this->nightTasksCount,
                'goldenTimeLabel' => $this->goldenTimeLabel,
                'bestDayName' => $this->bestDayName,
                'bestDayRate' => $this->bestDayRate,
                'categoryBreakdown' => $this->categoryBreakdown,
                'lifeBalanceNotice' => $this->lifeBalanceNotice,
                'lifeBalanceScore' => $this->lifeBalanceScore,
                'weeklyPersona' => $this->weeklyPersona,
            ];
            \Illuminate\Support\Facades\Cache::put($cacheKey, $toStore, 900);
        }

        // Fast live properties (from current user session/model)
        $this->streak = $user->current_streak ?? 0;
        $this->longestStreak = $user->longest_streak ?? $this->streak;
        $this->userLevel = $user->level ?? 1;
        $this->userXp = $user->xp ?? 0;
        $this->levelProgress = $this->userXp % 100;
        $this->userCoins = $user->coins ?? 0;

        // RPG Stats & Prestige System
        $this->rpgStats = \App\Services\RpgStatsService::calculateAllStats($user);
        $this->prestigeTier = $user->prestige_tier_details;
        $this->prestigeLevel = $user->prestige_level ?? 1;
        $this->levelProgressData = $user->level_progress;
        $this->seasonalProgress = [
            'level' => $user->seasonal_level ?? 1,
            'xp' => $user->seasonal_xp ?? 0,
            'season_name' => \App\Services\PrestigeService::getCurrentSeasonName(),
        ];
        $this->lifetimeXp = $user->lifetime_xp ?? $user->xp;
        $this->memberSince = $user->member_since ? $user->member_since->format('d M Y') : $user->created_at->format('d M Y');
        $this->totalTasksCompletedCount = $user->total_tasks_completed ?? $user->tasks()->where('is_completed', true)->count();

        // League info
        $leagueDetails = $user->league_details ?? LeagueService::getLeagueDetails('bronze_3');
        $weeklyXp = $user->weekly_xp ?? 0;
        $minXp = $leagueDetails['min_weekly_xp'] ?? 200;
        $this->leagueInfo = [
            'name' => $leagueDetails['name'] ?? 'Bronze III',
            'icon' => $leagueDetails['icon'] ?? '',
            'svg' => $leagueDetails['svg'] ?? ('images/badges/league/league_' . strtolower($leagueDetails['tier'] ?? 'bronze') . '.svg'),
            'tier' => $leagueDetails['tier'] ?? 'Bronze',
            'weekly_xp' => $weeklyXp,
            'min_weekly_xp' => $minXp,
            'is_promoted' => $weeklyXp >= $minXp,
            'gap_xp' => max(0, $minXp - $weeklyXp),
            'class' => $leagueDetails['class'] ?? '',
        ];
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
            $this->lifeBalanceNotice = __('Belum ada data tugas pada periode ini. Mulai tambahkan target dan selesaikan tugasmu untuk melihat analisa Life Balance!');
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
            $catLabel = __($topCat['name']);
            $this->lifeBalanceNotice = __('Fokusmu sangat didominasi oleh :cat (:pct%). Sempatkan jeda dan olahraga agar tidak burnout!', ['cat' => $catLabel, 'pct' => $topCat['percentage']]);
            $this->lifeBalanceScore = 70;
        } elseif ($healthCat && $healthCat['percentage'] < 10) {
            $this->lifeBalanceNotice = __('Aspek Kesehatan & Olahraga baru :pct%. Luangkan 15 menit jalan kaki atau peregangan hari ini.', ['pct' => $healthCat['percentage']]);
            $this->lifeBalanceScore = 80;
        } else {
            $this->lifeBalanceNotice = __('Keseimbangan aktivitasmu sangat optimal antara pekerjaan, pengembangan diri, dan kesehatan!');
            $this->lifeBalanceScore = 95;
        }
    }

    protected function determineProductivityPersona()
    {
        if ($this->totalFocusMinutes >= 120 && $this->journalsWritten >= 3) {
            $this->weeklyPersona = [
                'title' => 'The Deep Thinker',
                'badge' => '',
                'svg' => 'images/icons/feature_zen.svg',
                'archetype' => 'Deep Work & Reflective',
                'desc' => 'Kamu unggul dalam fokus mendalam dan refleksi diri. Sesi fokus panjang dan kejernihan pikiran adalah senjatamu.',
                'tag' => 'Fokus Tinggi',
            ];
        } elseif ($this->morningTasksCount >= 5 && $this->morningTasksCount > $this->afternoonTasksCount) {
            $this->weeklyPersona = [
                'title' => 'The Speed Sprinter',
                'badge' => '',
                'svg' => 'images/icons/feature_system.svg',
                'archetype' => 'Early Bird & Fast Execution',
                'desc' => 'Kamu menyelesaikan misi-misi krusial di pagi hari dengan kecepatan tinggi sebelum kebisingan hari dimulai.',
                'tag' => 'Eksekusi Cepat',
            ];
        } elseif ($this->streak >= 7) {
            $this->weeklyPersona = [
                'title' => 'The Streak Guardian',
                'badge' => '',
                'svg' => 'images/icons/streak_saver.svg',
                'archetype' => 'Unshakable Consistency',
                'desc' => 'Konsistensi adalah kekuatan supermu. Rantai kebiasaan harianmu tidak pernah terputus satu hari pun.',
                'tag' => 'Disiplin Baja',
            ];
        } elseif ($this->tasksDoneCount >= 8) {
            $this->weeklyPersona = [
                'title' => 'The Quest Crusher',
                'badge' => '',
                'svg' => 'images/prestige/tier_2_practitioner.svg',
                'archetype' => 'High Output Conqueror',
                'desc' => 'Setiap rintangan berhasil kamu taklukkan. Jumlah tugas yang kamu selesaikan pekan ini luar biasa masif!',
                'tag' => 'Output Masif',
            ];
        } else {
            $this->weeklyPersona = [
                'title' => 'The Steady Climber',
                'badge' => '',
                'svg' => 'images/prestige/tier_1_novice.svg',
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
            ->where('is_overdue_dismissed', false)
            ->where('date', '<', Carbon::today()->toDateString())
            ->orderBy('date', 'asc')
            ->take(10)
            ->get();

        $this->deletedOverdueCount = Task::onlyTrashed()
            ->where('user_id', $user->id)
            ->where('is_deleted_overdue', true)
            ->count();

        $this->deletedOverdueTasks = Task::onlyTrashed()
            ->where('user_id', $user->id)
            ->where('is_deleted_overdue', true)
            ->latest('deleted_at')
            ->take(10)
            ->get();

        $this->dismissedOverdueCount = $user->tasks()
            ->where('is_completed', false)
            ->where('is_overdue_dismissed', true)
            ->where('date', '<', Carbon::today()->toDateString())
            ->count();

        $this->dismissedOverdueTasks = $user->tasks()
            ->with(['skillTarget', 'skill'])
            ->where('is_completed', false)
            ->where('is_overdue_dismissed', true)
            ->where('date', '<', Carbon::today()->toDateString())
            ->latest('updated_at')
            ->take(10)
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
        $this->autoRescheduleTomorrow = false;
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

    public function clearInsightsCache()
    {
        $userId = auth()->id();
        if ($userId) {
            \Illuminate\Support\Facades\Cache::forget("insights_stats_v2_{$userId}_7days");
            \Illuminate\Support\Facades\Cache::forget("insights_stats_v2_{$userId}_month");
            \Illuminate\Support\Facades\Cache::forget("insights_charts_v2_{$userId}_7days");
            \Illuminate\Support\Facades\Cache::forget("insights_charts_v2_{$userId}_month");
        }
    }

    public function saveReason()
    {
        if (!$this->selectedOverdueTaskId) return;

        $task = auth()->user()->tasks()->with(['skillTarget', 'skill'])->findOrFail($this->selectedOverdueTaskId);
        $reason = $this->chosenReason ?: 'Lainnya';
        
        $task->update([
            'skip_reason' => $reason,
            'skip_reason_note' => $this->customReasonNote,
        ]);

        $msg = "Alasan '{$reason}' berhasil dicatat!";

        $this->dispatch('toast', message: $msg, type: 'success');
        $this->closeReasonModal();
        $this->clearInsightsCache();
        $this->loadStats();
        $this->loadOverdueTasks();
    }

    public function markDone($taskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $isOverdue = $task->isOverdue();
        $task->update([
            'is_completed' => true,
            'is_completed_overdue' => $isOverdue,
        ]);

        if ($task->skill_target_id && $target = $task->skillTarget) {
            $target->update(['status' => 'completed']);
            if ($target->day_number === $target->skill->current_day && $target->skill->current_day < $target->skill->target_days) {
                $target->skill->increment('current_day');
            }
        }

        $user = auth()->user();
        if (!$isOverdue) {
            $user = \App\Services\StreakEvaluatorService::evaluate($user);
        }
        $evaluation = \App\Services\TaskXpService::evaluateTaskCompletion($task, $user);

        $this->dispatch('toast', message: $evaluation['message'], type: $evaluation['type']);
        $this->dispatch('profile-updated');
        $this->clearInsightsCache();
        $this->loadStats();
        $this->loadOverdueTasks();
        $this->dispatch('refresh-charts');
    }

    public function dismissOverdueTask($taskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $task->update(['is_overdue_dismissed' => true]);
        $this->dispatch('toast', message: 'Tugas diabaikan dari notifikasi. Tugas tetap tersimpan di tanggal aslinya.', type: 'info');
        $this->clearInsightsCache();
        $this->loadStats();
        $this->loadOverdueTasks();
    }

    public function restoreDismissedOverdue($taskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        $task->update(['is_overdue_dismissed' => false]);
        $this->dispatch('toast', message: 'Tugas dikembalikan ke notifikasi.', type: 'success');
        $this->clearInsightsCache();
        $this->loadStats();
        $this->loadOverdueTasks();
    }

    public function rescheduleTask($taskId)
    {
        return $this->dismissOverdueTask($taskId);
    }

    public function deleteTask($taskId)
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);
        if ($task->isOverdue()) {
            $task->is_deleted_overdue = true;
            $task->save();
        }
        $task->delete();
        $this->dispatch('toast', message: 'Tugas berhasil dihapus', type: 'nudge');
        $this->clearInsightsCache();
        $this->loadStats();
        $this->loadOverdueTasks();
    }

    public function loadSummaries()
    {
        $user = auth()->user();
        if (!$user) return;

        $insights = AiInsight::where('user_id', $user->id)
            ->latest('generated_at')
            ->get();

        $this->weeklyInsight = $insights->first(function ($item) {
            if ($item->archetype === 'Weekly Sprint' || str_contains($item->archetype ?? '', 'Sprint') || str_contains($item->archetype ?? '', 'Executor') || str_contains($item->archetype ?? '', 'Scholar') || str_contains($item->archetype ?? '', 'Builder')) {
                return true;
            }
            $start = Carbon::parse($item->period_start);
            $end = Carbon::parse($item->period_end);
            return $start->diffInDays($end) <= 10;
        });

        if ($this->weeklyInsight) {
            $this->insightLastGeneratedAt = $this->weeklyInsight->generated_at->diffForHumans();
            $this->canRefreshWeekly = !$this->weeklyInsight->generated_at->isToday();
        } else {
            $this->canRefreshWeekly = true;
        }

        $this->monthlyInsight = $insights->first(function ($item) {
            $start = Carbon::parse($item->period_start);
            $end = Carbon::parse($item->period_end);
            return $start->diffInDays($end) >= 20;
        });

        if ($this->monthlyInsight) {
            $this->monthlyLastGeneratedAt = $this->monthlyInsight->generated_at->diffForHumans();
            $this->canRefreshMonthly = !$this->monthlyInsight->generated_at->isToday();
        } else {
            $this->canRefreshMonthly = true;
        }
    }

    public function generateWeeklyInsight(AtomaInsightService $insightService)
    {
        if (!$this->canRefreshWeekly && $this->weeklyInsight) {
            $this->dispatch('toast', message: 'ATOMA Says hanya dapat diperbarui 1x per hari.', type: 'warning');
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
            $this->isLoadingWeekly = true;
            $res = $insightService->generateWeeklyInsight(auth()->user());
            $this->weeklyInsight = $res['insight'];
            $this->insightLastGeneratedAt = $this->weeklyInsight->generated_at->diffForHumans();
            $this->canRefreshWeekly = false;
            $this->dispatch('toast', message: 'ATOMA Says berhasil diperbarui!', type: 'success');
        } finally {
            $this->isLoadingWeekly = false;
            $lock->release();
        }
    }

    public function generateMonthlyReport(AtomaInsightService $insightService)
    {
        if (!$this->canRefreshMonthly && $this->monthlyInsight) {
            $this->dispatch('toast', message: 'Monthly Capstone hanya dapat diperbarui 1x per hari.', type: 'warning');
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
            $this->dispatch('toast', message: 'Batas permintaan AI tercapai. Silakan coba lagi nanti.', type: 'warning');
            return;
        }
        \Illuminate\Support\Facades\Cache::put($rateKey, $requests + 1, 60);

        try {
            $this->isLoadingMonthly = true;
            $res = $insightService->generateMonthlyReport(auth()->user());
            $this->monthlyInsight = $res['insight'];
            $this->monthlyLastGeneratedAt = $this->monthlyInsight->generated_at->diffForHumans();
            $this->canRefreshMonthly = false;
            $this->dispatch('toast', message: 'Monthly Capstone Retrospective berhasil dirangkum!', type: 'success');
        } finally {
            $this->isLoadingMonthly = false;
            $lock->release();
        }
    }

    public function setInsightsTab($tab)
    {
        $this->activeTab = in_array($tab, ['overview', 'fitness']) ? $tab : 'overview';
    }

    public function setFitnessPeriod($period)
    {
        $this->fitnessPeriod = in_array($period, ['week', 'month', 'year']) ? $period : 'week';
    }

    public function openWorkoutModal()
    {
        $this->workoutDate = Carbon::today()->toDateString();
        $this->workoutTitle = 'Lari';
        $this->workoutType = 'cardio';
        $this->workoutDistanceKm = 5.0;
        $this->workoutDurationMinutes = 30;
        $this->workoutSets = 4;
        $this->workoutReps = 25;
        $this->workoutWeight = 0;
        $this->workoutNotes = '';
        $this->showWorkoutModal = true;
    }

    public function closeWorkoutModal()
    {
        $this->showWorkoutModal = false;
    }

    public function setQuickExercise($name, $type, $defaultMetric = null)
    {
        $this->workoutTitle = $name;
        $this->workoutType = $type;
        if ($type === 'cardio' && $defaultMetric) {
            $this->workoutDistanceKm = (float)$defaultMetric;
        } elseif ($type === 'bodyweight' && $defaultMetric) {
            $this->workoutReps = (int)$defaultMetric;
        }
    }

    public function saveWorkoutLog()
    {
        $this->validate([
            'workoutTitle' => 'required|string|min:2|max:100',
            'workoutDate' => 'required|date',
            'workoutDurationMinutes' => 'nullable|numeric|min:0|max:1440',
            'workoutDistanceKm' => 'nullable|numeric|min:0|max:500',
            'workoutSets' => 'nullable|integer|min:1|max:50',
            'workoutReps' => 'nullable|integer|min:1|max:2000',
            'workoutWeight' => 'nullable|numeric|min:0|max:1000',
            'workoutNotes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        if (!$user) return;

        $completedAt = Carbon::parse($this->workoutDate)->setTimeFrom(now());
        $durSecs = ((int)($this->workoutDurationMinutes ?: 0)) * 60;

        $totalVolKg = 0;
        $unit = 'bw';
        $muscle = 'Full Body';

        if ($this->workoutType === 'cardio') {
            $unit = 'km';
            $muscle = 'Cardio';
            $totalVolKg = (float)($this->workoutDistanceKm ?: 0);
        } elseif ($this->workoutType === 'gym') {
            $unit = 'kg';
            $muscle = 'Strength';
            $totalVolKg = ((float)($this->workoutWeight ?: 0)) * ((int)($this->workoutSets ?: 1)) * ((int)($this->workoutReps ?: 1));
        } else {
            $unit = 'bw';
            $muscle = 'Calisthenics';
            $totalVolKg = ((int)($this->workoutSets ?: 1)) * ((int)($this->workoutReps ?: 1));
        }

        $earnedXp = (int) min(250, max(50, 40 + ($totalVolKg / 10) + ($durSecs / 120)));

        $session = WorkoutSession::create([
            'user_id' => $user->id,
            'title' => $this->workoutTitle,
            'notes' => $this->workoutNotes,
            'duration_seconds' => $durSecs,
            'total_volume_kg' => $totalVolKg,
            'xp_earned' => $earnedXp,
            'completed_at' => $completedAt,
        ]);

        $exercise = WorkoutExercise::create([
            'workout_session_id' => $session->id,
            'exercise_name' => $this->workoutTitle,
            'muscle_group' => $muscle,
            'weight_unit' => $unit,
            'order' => 1,
        ]);

        $setsCount = max(1, (int)($this->workoutSets ?: 1));
        if ($this->workoutType === 'cardio') {
            WorkoutSet::create([
                'workout_exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight_kg' => (float)($this->workoutDistanceKm ?: 0),
                'reps' => 1,
                'set_type' => 'normal',
                'is_completed' => true,
            ]);
        } else {
            $weightKg = ($this->workoutType === 'gym') ? (float)($this->workoutWeight ?: 0) : 0;
            $repsPerSet = max(1, (int)($this->workoutReps ?: 1));
            for ($i = 1; $i <= $setsCount; $i++) {
                WorkoutSet::create([
                    'workout_exercise_id' => $exercise->id,
                    'set_number' => $i,
                    'weight_kg' => $weightKg,
                    'reps' => $repsPerSet,
                    'set_type' => 'normal',
                    'is_completed' => true,
                ]);
            }
        }

        $user->addPendingXp($earnedXp, 'workout', "Sesi Olahraga: {$this->workoutTitle}", null, $session->id);
        $user->save();

        $this->closeWorkoutModal();
        $this->dispatch('toast', message: "💪 Sesi olahraga '{$this->workoutTitle}' berhasil dicatat! (+{$earnedXp} XP)", type: 'success');
        $this->dispatch('play-sound', preset: 'fanfare');
        $this->dispatch('trigger-confetti');
        $this->dispatch('profile-updated');
    }

    public function deleteWorkoutSession($sessionId)
    {
        $session = auth()->user()->workoutSessions()->find($sessionId);
        if ($session) {
            $session->delete();
            $this->dispatch('toast', message: 'Sesi latihan berhasil dihapus.', type: 'info');
        }
    }

    public function fitnessAnalytics(): array
    {
        $user = auth()->user();
        if (!$user) return [];

        $today = Carbon::today();
        
        $allSessions = $user->workoutSessions()
            ->with(['exercises.sets'])
            ->orderBy('completed_at', 'desc')
            ->get();

        // 1. SEMINGGU (Monday to Sunday)
        $startOfWeek = $today->copy()->startOfWeek();
        $endOfWeek = $today->copy()->endOfWeek();
        $weekSessions = $allSessions->filter(function($s) use ($startOfWeek, $endOfWeek) {
            $date = Carbon::parse($s->completed_at ?? $s->created_at);
            return $date->between($startOfWeek, $endOfWeek);
        });

        $weekDays = [];
        $dayNamesId = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        for ($i = 0; $i < 7; $i++) {
            $dayDate = $startOfWeek->copy()->addDays($i);
            $dayStr = $dayDate->toDateString();
            $daySessions = $weekSessions->filter(function($s) use ($dayStr) {
                return Carbon::parse($s->completed_at ?? $s->created_at)->toDateString() === $dayStr;
            });

            $exercisesList = [];
            foreach ($daySessions as $ds) {
                foreach ($ds->exercises as $ex) {
                    $unit = $ex->weight_unit;
                    if ($unit === 'km') {
                        $km = $ex->sets->first()?->weight_kg ?? ($ds->total_volume_kg ?: 0);
                        $dur = round($ds->duration_seconds / 60);
                        $exercisesList[] = "🏃‍♂️ {$ex->exercise_name} {$km} km" . ($dur > 0 ? " ({$dur} mnt)" : "");
                    } elseif ($unit === 'bw') {
                        $totalReps = $ex->sets->where('is_completed', true)->sum('reps');
                        $setCount = $ex->sets->count();
                        $exercisesList[] = "💪 {$ex->exercise_name} {$totalReps} reps" . ($setCount > 1 ? " ({$setCount} set)" : "");
                    } else {
                        $totalVol = $ex->sets->where('is_completed', true)->reduce(function($carry, $set) {
                            return $carry + ($set->weight_kg * $set->reps);
                        }, 0);
                        $setCount = $ex->sets->count();
                        $exercisesList[] = "🏋️ {$ex->exercise_name} ({$setCount} set, Vol " . number_format($totalVol, 0, ',', '.') . " kg)";
                    }
                }
            }

            $weekDays[] = [
                'day_name' => $dayNamesId[$i],
                'date_formatted' => $dayDate->format('d M'),
                'is_today' => $dayDate->isSameDay($today),
                'has_workout' => $daySessions->isNotEmpty(),
                'sessions_count' => $daySessions->count(),
                'exercises' => $exercisesList,
            ];
        }

        $weekTotalDistance = 0.0;
        $weekTotalReps = 0;
        $weekTotalDurationMin = 0;
        foreach ($weekSessions as $s) {
            $weekTotalDurationMin += round($s->duration_seconds / 60);
            foreach ($s->exercises as $ex) {
                if ($ex->weight_unit === 'km' || strtolower($ex->muscle_group) === 'kardio') {
                    $km = (float) $ex->sets->where('is_completed', true)->sum(fn($st) => (float)($st->distance_km ?: $st->weight_kg ?: 0));
                    if ($km == 0 && (float)($s->total_distance_km ?? 0) > 0) $km = (float)$s->total_distance_km;
                    if ($km == 0 && (float)($ex->sets->first()?->weight_kg ?? 0) > 0) $km = (float)$ex->sets->first()->weight_kg;
                    $weekTotalDistance += $km;
                } elseif ($ex->weight_unit === 'bw') {
                    $weekTotalReps += (int)$ex->sets->where('is_completed', true)->sum('reps');
                }
            }
        }

        // 2. SEBULAN (4 Weeks: W1, W2, W3, W4)
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();
        $monthSessions = $allSessions->filter(function($s) use ($startOfMonth, $endOfMonth) {
            $date = Carbon::parse($s->completed_at ?? $s->created_at);
            return $date->between($startOfMonth, $endOfMonth);
        });

        $monthWeeks = [
            1 => ['label' => 'Minggu 1 (1-7 ' . $today->translatedFormat('M') . ')', 'sessions' => 0, 'distance' => 0.0, 'reps' => 0, 'volume' => 0.0],
            2 => ['label' => 'Minggu 2 (8-14 ' . $today->translatedFormat('M') . ')', 'sessions' => 0, 'distance' => 0.0, 'reps' => 0, 'volume' => 0.0],
            3 => ['label' => 'Minggu 3 (15-21 ' . $today->translatedFormat('M') . ')', 'sessions' => 0, 'distance' => 0.0, 'reps' => 0, 'volume' => 0.0],
            4 => ['label' => 'Minggu 4 (22-' . $endOfMonth->day . ' ' . $today->translatedFormat('M') . ')', 'sessions' => 0, 'distance' => 0.0, 'reps' => 0, 'volume' => 0.0],
        ];

        $exerciseProgression = [];

        foreach ($monthSessions as $s) {
            $date = Carbon::parse($s->completed_at ?? $s->created_at);
            $dayNum = $date->day;
            $wIndex = 4;
            if ($dayNum <= 7) $wIndex = 1;
            elseif ($dayNum <= 14) $wIndex = 2;
            elseif ($dayNum <= 21) $wIndex = 3;

            $monthWeeks[$wIndex]['sessions']++;

            foreach ($s->exercises as $ex) {
                $name = trim($ex->exercise_name);
                if (!isset($exerciseProgression[$name])) {
                    $exerciseProgression[$name] = [
                        'name' => $name,
                        'unit' => $ex->weight_unit,
                        'weeks' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
                        'sessions_count' => 0,
                    ];
                }
                $exerciseProgression[$name]['sessions_count']++;

                if ($ex->weight_unit === 'km' || strtolower($ex->muscle_group) === 'kardio') {
                    $km = (float) $ex->sets->where('is_completed', true)->sum(fn($st) => (float)($st->distance_km ?: $st->weight_kg ?: 0));
                    if ($km == 0 && (float)($s->total_distance_km ?? 0) > 0) $km = (float)$s->total_distance_km;
                    if ($km == 0 && (float)($ex->sets->first()?->weight_kg ?? 0) > 0) $km = (float)$ex->sets->first()->weight_kg;
                    $monthWeeks[$wIndex]['distance'] += $km;
                    $exerciseProgression[$name]['weeks'][$wIndex] += $km;
                } elseif ($ex->weight_unit === 'bw') {
                    $reps = (int)$ex->sets->where('is_completed', true)->sum('reps');
                    $monthWeeks[$wIndex]['reps'] += $reps;
                    $exerciseProgression[$name]['weeks'][$wIndex] += $reps;
                } else {
                    $vol = (float)$ex->sets->where('is_completed', true)->reduce(function($carry, $set) {
                        return $carry + ($set->weight_kg * $set->reps);
                    }, 0);
                    $monthWeeks[$wIndex]['volume'] += $vol;
                    $exerciseProgression[$name]['weeks'][$wIndex] += $vol;
                }
            }
        }

        $progressInsights = [];
        foreach ($exerciseProgression as $name => $data) {
            $w1 = $data['weeks'][1];
            $w2 = $data['weeks'][2];
            $w3 = $data['weeks'][3];
            $w4 = $data['weeks'][4];
            
            $initialVal = $w1 > 0 ? $w1 : ($w2 > 0 ? $w2 : $w3);
            $latestVal = $w4 > 0 ? $w4 : ($w3 > 0 ? $w3 : ($w2 > 0 ? $w2 : $w1));
            
            $pctChange = 0;
            if ($initialVal > 0) {
                $pctChange = round((($latestVal - $initialVal) / $initialVal) * 100);
            }

            $trend = 'stable';
            $badge = '🎯 Konsisten';
            if ($pctChange >= 15) {
                $trend = 'up';
                $badge = "🚀 Progress Naik +{$pctChange}%";
            } elseif ($pctChange <= -15 && $latestVal > 0) {
                $trend = 'down';
                $badge = "⚠️ Menurun {$pctChange}%";
            }

            $unitLabel = ($data['unit'] === 'km') ? 'km' : (($data['unit'] === 'bw') ? 'reps' : 'kg vol');

            $progressInsights[] = [
                'name' => $name,
                'unit' => $data['unit'],
                'unit_label' => $unitLabel,
                'weeks' => [
                    'w1' => $w1,
                    'w2' => $w2,
                    'w3' => $w3,
                    'w4' => $w4,
                ],
                'pct_change' => $pctChange,
                'trend' => $trend,
                'badge' => $badge,
                'total_sessions' => $data['sessions_count'],
            ];
        }

        // 3. SETAHUN (12 Months)
        $startOfYear = $today->copy()->startOfYear();
        $endOfYear = $today->copy()->endOfYear();
        $yearSessions = $allSessions->filter(function($s) use ($startOfYear, $endOfYear) {
            $date = Carbon::parse($s->completed_at ?? $s->created_at);
            return $date->between($startOfYear, $endOfYear);
        });

        $yearMonths = [];
        $monthNamesId = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        for ($m = 1; $m <= 12; $m++) {
            $mSessions = $yearSessions->filter(function($s) use ($m) {
                return Carbon::parse($s->completed_at ?? $s->created_at)->month === $m;
            });
            $mKm = 0.0;
            $mReps = 0;
            foreach ($mSessions as $s) {
                foreach ($s->exercises as $ex) {
                    if ($ex->weight_unit === 'km' || strtolower($ex->muscle_group) === 'kardio') {
                        $km = (float) $ex->sets->where('is_completed', true)->sum(fn($st) => (float)($st->distance_km ?: $st->weight_kg ?: 0));
                        if ($km == 0 && (float)($s->total_distance_km ?? 0) > 0) $km = (float)$s->total_distance_km;
                        if ($km == 0 && (float)($ex->sets->first()?->weight_kg ?? 0) > 0) $km = (float)$ex->sets->first()->weight_kg;
                        $mKm += $km;
                    } elseif ($ex->weight_unit === 'bw') {
                        $mReps += (int)$ex->sets->where('is_completed', true)->sum('reps');
                    }
                }
            }

            $yearMonths[] = [
                'month_num' => $m,
                'month_name' => $monthNamesId[$m - 1],
                'sessions_count' => $mSessions->count(),
                'distance_km' => round($mKm, 1),
                'reps' => $mReps,
                'is_current' => ($m === $today->month),
            ];
        }

        // PRs
        $longestRun = 0.0;
        $maxDuration = 0;
        $maxRepsInSession = 0;
        $maxWeightLifted = 0.0;

        foreach ($allSessions as $s) {
            $maxDuration = max($maxDuration, (int)$s->duration_seconds);
            foreach ($s->exercises as $ex) {
                if ($ex->weight_unit === 'km' || strtolower($ex->muscle_group) === 'kardio') {
                    $km = (float) $ex->sets->where('is_completed', true)->sum(fn($st) => (float)($st->distance_km ?: $st->weight_kg ?: 0));
                    if ($km == 0 && (float)($s->total_distance_km ?? 0) > 0) $km = (float)$s->total_distance_km;
                    if ($km == 0 && (float)($ex->sets->first()?->weight_kg ?? 0) > 0) $km = (float)$ex->sets->first()->weight_kg;
                    $longestRun = max($longestRun, $km);
                } elseif ($ex->weight_unit === 'bw') {
                    $reps = (int)$ex->sets->where('is_completed', true)->sum('reps');
                    $maxRepsInSession = max($maxRepsInSession, $reps);
                } else {
                    foreach ($ex->sets as $st) {
                        if ($st->is_completed) {
                            $maxWeightLifted = max($maxWeightLifted, (float)$st->weight_kg);
                        }
                    }
                }
            }
        }

        return [
            'week_sessions_count' => $weekSessions->count(),
            'week_distance_km' => round($weekTotalDistance, 1),
            'week_reps' => $weekTotalReps,
            'week_duration_min' => $weekTotalDurationMin,
            'week_days' => $weekDays,

            'month_sessions_count' => $monthSessions->count(),
            'month_weeks' => $monthWeeks,
            'progress_insights' => $progressInsights,

            'year_sessions_count' => $yearSessions->count(),
            'year_months' => $yearMonths,
            'year_total_distance' => round($yearSessions->reduce(function($carry, $s) {
                $km = 0;
                foreach ($s->exercises as $ex) {
                    if ($ex->weight_unit === 'km' || strtolower($ex->muscle_group) === 'kardio') {
                        $subKm = (float) $ex->sets->where('is_completed', true)->sum(fn($st) => (float)($st->distance_km ?: $st->weight_kg ?: 0));
                        if ($subKm == 0 && (float)($s->total_distance_km ?? 0) > 0) $subKm = (float)$s->total_distance_km;
                        if ($subKm == 0 && (float)($ex->sets->first()?->weight_kg ?? 0) > 0) $subKm = (float)$ex->sets->first()->weight_kg;
                        $km += $subKm;
                    }
                }
                return $carry + $km;
            }, 0), 1),
            'year_total_reps' => $yearSessions->reduce(function($carry, $s) {
                $reps = 0;
                foreach ($s->exercises as $ex) {
                    if ($ex->weight_unit === 'bw') $reps += (int)$ex->sets->where('is_completed', true)->sum('reps');
                }
                return $carry + $reps;
            }, 0),

            'pr_longest_run_km' => round($longestRun, 1),
            'pr_max_duration_min' => round($maxDuration / 60),
            'pr_max_reps' => $maxRepsInSession,
            'pr_max_weight_kg' => round($maxWeightLifted, 1),

            'recent_sessions' => $allSessions->take(15),
        ];
    }

    public function with(): array
    {
        $user = auth()->user();
        if (!$user) {
            return [
                'chartDays' => [],
                'fitnessData' => [],
            ];
        }

        $cacheKey = "insights_charts_v2_{$user->id}_{$this->filterPeriod}";
        $chartData = \Illuminate\Support\Facades\Cache::remember($cacheKey, 900, function() use ($user) {
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

            return [
                'chartDays' => $chartDays,
            ];
        });

        return [
            'chartDays' => $chartData['chartDays'] ?? [],
            'fitnessData' => $this->fitnessAnalytics(),
        ];
    }
}; ?>

<div class="relative pb-24 max-w-5xl mx-auto px-0 sm:px-1"
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
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 sticky top-12 sm:top-14 bg-gray-50/95 dark:bg-gray-900/95 backdrop-blur-md z-20 py-3 sm:py-4 border-b border-gray-200/50 dark:border-gray-800/50">
        <div>
            <h2 class="text-2xl sm:text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-atoma-blue to-atoma-cyan tracking-tight flex items-center gap-2.5">
                <div class="w-8 h-8 flex items-center justify-center p-1 rounded-xl bg-atoma-ice/60 dark:bg-atoma-blue/20 border border-atoma-aqua/40 shrink-0">
                    <img src="{{ asset('images/icons/feature_insights.svg') }}" alt="Insights" class="w-full h-full object-contain">
                </div>
                <span>ATOMA Insights</span>
            </h2>
            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium">{{ __('Analisis cerdas ritme kerja, deep work, & evolusi kebiasaanmu') }}</p>
        </div>

        <!-- Actions: Infographic & Period Switcher -->
        @if($activeTab === 'overview')
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button @click="$dispatch('open-infographic')" type="button" class="px-3.5 py-2.5 bg-gradient-to-r from-atoma-blue to-[#284fd6] hover:bg-[#3455dc] text-white rounded-2xl text-xs font-black shadow-md shadow-atoma-blue/25 transition-all active:scale-95 flex items-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>{{ __('Kartu Laporan PNG') }}</span>
                </button>

                <div class="inline-flex p-1 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-gray-700/60 shadow-xs shrink-0 flex-1 sm:flex-initial justify-stretch">
                    <button type="button" 
                            wire:click="setFilterPeriod('7days')" 
                            class="flex-1 sm:flex-initial px-4 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer {{ $filterPeriod === '7days' ? 'bg-atoma-blue text-white shadow-md shadow-atoma-blue/25' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                        {{ __('Minggu Ini') }}
                    </button>
                    <button type="button" 
                            wire:click="setFilterPeriod('month')" 
                            class="flex-1 sm:flex-initial px-4 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer {{ $filterPeriod === 'month' ? 'bg-atoma-blue text-white shadow-md shadow-atoma-blue/25' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                        {{ __('Bulan Ini') }}
                    </button>
                </div>
            </div>
        @else
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button type="button"
                        wire:click="openWorkoutModal"
                        class="px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 active:scale-95 text-white rounded-2xl text-xs font-black shadow-md shadow-emerald-500/25 transition-all flex items-center gap-2 cursor-pointer">
                    <span class="text-sm">➕</span>
                    <span>{{ __('Catat Olahraga') }}</span>
                </button>
            </div>
        @endif
    </div>

    <!-- Main Insights Sub-Navigation Tabs: Productivity vs Fitness Hub -->
    <div class="flex items-center gap-2 mb-6 p-1.5 bg-gray-200/70 dark:bg-gray-800/80 rounded-2xl border border-gray-200 dark:border-gray-700/80 w-full sm:w-fit shadow-xs">
        <button type="button"
                wire:click="setInsightsTab('overview')"
                class="flex-1 sm:flex-initial flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-black transition-all cursor-pointer {{ $activeTab === 'overview' ? 'bg-white dark:bg-gray-700 text-atoma-blue dark:text-atoma-cyan shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
            <span>📊</span>
            <span>{{ __('Produktivitas & Habit') }}</span>
        </button>
        <button type="button"
                wire:click="setInsightsTab('fitness')"
                class="flex-1 sm:flex-initial flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-black transition-all cursor-pointer {{ $activeTab === 'fitness' ? 'bg-gradient-to-r from-emerald-500 to-teal-600 text-white shadow-md shadow-emerald-500/25' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
            <span>🏃‍♂️</span>
            <span>{{ __('Analisis & Catatan Olahraga') }}</span>
            <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black {{ $activeTab === 'fitness' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' }}">NEW</span>
        </button>
    </div>

    @if($activeTab === 'overview')

    <!-- BAGIAN 1 — HEADLINE METRIC CARDS & GROWTH DELTAS (6 Kartu Mobile Optimized) -->
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-5 mb-8">
        
        <!-- Card 1: Completion Rate + Delta -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 relative overflow-hidden flex flex-col justify-between transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">Completion Rate</span>
                <div class="w-8 h-8 rounded-xl bg-atoma-ice dark:bg-atoma-blue/20 flex items-center justify-center p-1.5 shrink-0 border border-atoma-aqua/40">
                    <img src="{{ asset('images/icons/insights_target.svg') }}" alt="Target" class="w-full h-full object-contain">
                </div>
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
                <span>{{ __(':done dari :total tugas selesai', ['done' => $tasksDoneCount, 'total' => $totalTasksCount]) }}</span>
                <span class="font-bold text-atoma-blue">{{ $filterPeriod === 'month' ? __('30 Hari') : __('7 Hari') }}</span>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full mt-2.5 overflow-hidden">
                <div class="bg-gradient-to-r from-atoma-blue to-atoma-cyan h-full rounded-full transition-all duration-700" style="width: {{ min(100, $completionRate) }}%"></div>
            </div>
        </div>

        <!-- Card 2: Deep Work & Pomodoro Focus + Delta -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 relative overflow-hidden flex flex-col justify-between transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deep Work Focus</span>
                <div class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/40 flex items-center justify-center p-1.5 shrink-0 border border-rose-200 dark:border-rose-800/60">
                    <img src="{{ asset('images/icons/insights_focus.svg') }}" alt="Focus" class="w-full h-full object-contain">
                </div>
            </div>
            
            <div class="flex items-baseline gap-2 mb-2">
                <div class="text-2xl sm:text-3xl font-black text-atoma-blue dark:text-atoma-cyan">
                    {{ $totalFocusHours }} <span class="text-sm sm:text-base font-bold text-gray-500 dark:text-gray-400">{{ __('Jam') }}</span>
                </div>
                @if($focusDeltaHours != 0)
                    <div class="text-[10px] sm:text-xs font-black {{ $focusDeltaHours > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        <span>{{ $focusDeltaHours > 0 ? '▲ +' : '▼ ' }}{{ $focusDeltaHours }}j</span>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                <span>{{ __(':count sesi fokus tercatat', ['count' => $totalFocusSessions]) }}</span>
                <span class="font-bold text-atoma-blue">{{ __(':countm total', ['count' => $totalFocusMinutes]) }}</span>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full mt-2.5 overflow-hidden">
                <div class="bg-gradient-to-r from-rose-500 to-amber-500 h-full rounded-full transition-all duration-700" style="width: {{ min(100, $totalFocusHours * 10) }}%"></div>
            </div>
        </div>

        <!-- Card 3: Daily Streak Consistency -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 relative overflow-hidden flex flex-col justify-between transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">Daily Streak</span>
                <div class="w-8 h-8 rounded-xl bg-orange-50 dark:bg-orange-950/40 flex items-center justify-center p-1.5 shrink-0 border border-orange-200 dark:border-orange-800/60">
                    <svg class="w-5 h-5 text-orange-500 animate-pulse" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c-.5 2-2 3.5-3.5 5C6.5 9 5 11.5 5 14.5 5 18.64 8.36 22 12.5 22s7.5-3.36 7.5-7.5c0-4-3-6.5-4.5-9-1 2-2.5 2.5-3.5 2.5 0-3-1-7-1-7z"/></svg>
                </div>
            </div>
            
            <div class="flex items-baseline gap-2 mb-2">
                <div class="text-2xl sm:text-3xl font-black text-orange-500">
                    {{ $streak }} <span class="text-sm sm:text-base font-bold text-gray-500 dark:text-gray-400">{{ __('Hari') }}</span>
                </div>
                <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-orange-100 dark:bg-orange-950/60 text-orange-600 dark:text-orange-400">
                    {{ __('Aktif') }}
                </span>
            </div>

            <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                <span>{{ __('Rekor terbaikmu') }}</span>
                <span class="font-bold text-orange-500">{{ __(':count Hari', ['count' => $longestStreak]) }}</span>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full mt-2.5 overflow-hidden">
                <div class="bg-gradient-to-r from-orange-500 to-amber-400 h-full rounded-full transition-all duration-700" style="width: {{ min(100, $streak * 10) }}%"></div>
            </div>
        </div>

        <!-- Card 4: Overdue Action Radar -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 relative overflow-hidden flex flex-col justify-between transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">Overdue Tasks</span>
                <div class="w-8 h-8 rounded-xl {{ $overdueCount > 0 ? 'bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-500' : 'bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-500' }} flex items-center justify-center p-1.5 shrink-0">
                    @if($overdueCount > 0)
                        <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @else
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                </div>
            </div>
            
            <div class="flex items-baseline gap-2 mb-2">
                <div class="text-2xl sm:text-3xl font-black {{ $overdueCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    {{ $overdueCount }} <span class="text-sm sm:text-base font-bold text-gray-500 dark:text-gray-400">{{ __('Tugas') }}</span>
                </div>
                @if($overdueDelta != 0)
                    <div class="text-[10px] sm:text-xs font-black {{ $overdueDelta < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        <span>{{ $overdueDelta < 0 ? '▼ ' : '▲ +' }}{{ $overdueDelta }}</span>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                <span>{{ $overdueCount > 0 ? __('Perlu tindakan cepat') : __('Semua tugas aman terkendali') }}</span>
                <a href="#overdue-section" class="text-atoma-blue font-bold hover:underline">{{ __('Periksa') }} &darr;</a>
            </div>
            @if($deletedOverdueCount > 0)
                <div class="mt-2 flex items-center justify-between px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700/60 text-[10px] font-bold text-gray-600 dark:text-gray-300">
                    <span class="flex items-center gap-1">
                        <span>🗑️</span> {{ __(':count Overdue Dihapus', ['count' => $deletedOverdueCount]) }}
                    </span>
                    <a href="#overdue-section" class="text-atoma-blue hover:underline">{{ __('Riwayat') }} &darr;</a>
                </div>
            @endif
            @if($dismissedOverdueCount > 0)
                <div class="mt-1.5 flex items-center justify-between px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200/60 dark:border-amber-900/40 text-[10px] font-bold text-amber-700 dark:text-amber-300">
                    <span class="flex items-center gap-1">
                        <span>👁️</span> {{ __(':count Overdue Diabaikan', ['count' => $dismissedOverdueCount]) }}
                    </span>
                    <a href="#overdue-section" class="text-atoma-blue hover:underline">{{ __('Lihat') }} &darr;</a>
                </div>
            @endif
            <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full mt-2.5 overflow-hidden">
                <div class="{{ $overdueCount > 0 ? 'bg-rose-500' : 'bg-emerald-500' }} h-full rounded-full" style="width: {{ min(100, $overdueCount * 20) }}%"></div>
            </div>
        </div>

        <!-- Card 5: RPG Level & Coins Velocity -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 relative overflow-hidden flex flex-col justify-between transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Level & Koin RPG') }}</span>
                <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 flex items-center justify-center p-1.5 shrink-0 text-amber-500">
                    <svg class="w-4 h-4 fill-amber-400 stroke-amber-500" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                </div>
            </div>
            
            <div class="flex items-baseline gap-2 mb-2">
                <div class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                    Lv. {{ $userLevel }}
                </div>
                <span class="text-xs font-black text-amber-500 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10" stroke="#b45309" stroke-width="1.5"/><circle cx="12" cy="12" r="6" fill="#fef08a" stroke="#d97706" stroke-width="1"/><path d="M12 9v6m-2-4h4" stroke="#b45309" stroke-width="1.5" stroke-linecap="round"/></svg>
                    {{ number_format($userCoins) }}
                </span>
            </div>

            <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                <span>{{ __('Total XP: :xp', ['xp' => number_format($userXp)]) }}</span>
                <span class="font-bold text-atoma-blue">{{ __(':progress% ke Lv. :level', ['progress' => $levelProgress, 'level' => $userLevel + 1]) }}</span>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full mt-2.5 overflow-hidden">
                <div class="bg-gradient-to-r from-atoma-blue to-atoma-cyan h-full rounded-full transition-all duration-700" style="width: {{ $levelProgress }}%"></div>
            </div>
        </div>

        <!-- Card 6: Dominant Mood & Finance Net Balance -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 relative overflow-hidden flex flex-col justify-between transition-all group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Mood & Arus Kas') }}</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-center justify-center p-1.5 shrink-0">
                    <img src="{{ asset('images/icons/insights_cashflow.svg') }}" alt="Cashflow" class="w-full h-full object-contain">
                </div>
            </div>
            
            <div class="flex items-baseline gap-2 mb-2">
                <div class="text-xl sm:text-2xl font-black {{ $financeBalance >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }} truncate">
                    Rp {{ number_format($financeBalance, 0, ',', '.') }}
                </div>
            </div>

            <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                <span class="capitalize flex items-center gap-1.5 font-bold text-gray-700 dark:text-gray-300">
                    @php
                        $moodDot = match($mostFrequentMood) {
                            'happy' => 'bg-emerald-500',
                            'excited' => 'bg-amber-500',
                            'sad' => 'bg-blue-500',
                            'tired' => 'bg-purple-500',
                            default => 'bg-atoma-blue',
                        };
                    @endphp
                    <span class="w-2 h-2 rounded-full {{ $moodDot }} shrink-0"></span>
                    <span>{{ __('Mood:') }} {{ __(ucfirst($mostFrequentMood)) }}</span>
                </span>
                <span class="font-bold text-gray-400">{{ __(':count jurnal', ['count' => $journalsWritten]) }}</span>
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
                <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800/60 p-1 flex items-center justify-center shrink-0">
                        <img src="{{ asset('images/icons/insights_trends.svg') }}" alt="Trends" class="w-full h-full object-contain">
                    </div>
                    <span>Activity & Deep Work Trends</span>
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-0.5">{{ __('Korelasi antara tugas selesai dan menit fokus harian') }}</p>
            </div>
            <div class="flex items-center gap-3 text-xs font-bold">
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-atoma-blue"></span>
                    <span class="text-gray-600 dark:text-gray-400">{{ __('Tasks Selesai') }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-atoma-cyan"></span>
                    <span class="text-gray-600 dark:text-gray-400">{{ __('Waktu Fokus (Menit)') }}</span>
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
                        <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800/60 p-1 flex items-center justify-center shrink-0">
                                <img src="{{ asset('images/icons/insights_balance.svg') }}" alt="Life Balance" class="w-full h-full object-contain">
                            </div>
                            <span>{{ __('Life Balance Wheel') }}</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-0.5">{{ __('Distribusi energi & proporsi kategori tugas') }}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-black bg-atoma-ice text-atoma-blue border border-atoma-aqua/50">
                        {{ __('Score') }}: {{ $lifeBalanceScore }}/100
                    </span>
                </div>

                @if(count($categoryBreakdown) === 0)
                    <div class="py-8 flex flex-col items-center justify-center text-center my-4">
                        <div class="w-14 h-14 rounded-2xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600/60 flex items-center justify-center mb-3">
                            <img src="{{ asset('images/icons/insights_balance.svg') }}" alt="Life Balance" class="w-7 h-7 opacity-40">
                        </div>
                        <div class="text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Belum Ada Tugas Tercatat') }}</div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 max-w-xs leading-relaxed">{{ __('Mulai buat dan selesaikan tugas harianmu untuk melihat distribusi proporsi kategori dan skor keseimbangan hidup!') }}</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center my-4">
                        <div class="h-44 sm:h-48 relative flex items-center justify-center">
                            <canvas id="categoryDoughnutChart"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-xs font-bold text-gray-400">{{ __('Kategori') }}</span>
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
                                        <span class="font-bold text-gray-700 dark:text-gray-300">{{ __($cat['name']) }}</span>
                                    </div>
                                    <span class="font-black text-gray-900 dark:text-white">{{ $cat['percentage'] }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- AI Life Balance Insight Notice -->
            <div class="p-3.5 rounded-2xl bg-atoma-ice/60 dark:bg-atoma-blue/15 border border-atoma-aqua/50 dark:border-atoma-blue/30 text-xs text-gray-800 dark:text-gray-200 font-medium flex items-start gap-2.5 mt-2">
                <div class="w-5 h-5 shrink-0 mt-0.5">
                    <img src="{{ asset('images/icons/insights_bulb.svg') }}" alt="Tip" class="w-full h-full object-contain">
                </div>
                <p class="leading-relaxed">{{ $lifeBalanceNotice }}</p>
            </div>
        </div>

        <!-- Right: Peak Productivity Radar & Best Day Analysis -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800/60 p-1 flex items-center justify-center shrink-0">
                                <img src="{{ asset('images/icons/insights_radar_clock.svg') }}" alt="Peak Radar" class="w-full h-full object-contain">
                            </div>
                            <span>{{ __('Peak Productivity Radar') }}</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-0.5">{{ __('Kapan otak & energimu berada di titik optimal') }}</p>
                    </div>
                    @if($bestDayName !== '-' && $bestDayRate > 0)
                        <span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/40">
                            {{ __($bestDayName) }} ({{ $bestDayRate }}% {{ __('Selesai') }})
                        </span>
                    @else
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
                            {{ __('Belum Cukup Data') }}
                        </span>
                    @endif
                </div>

                <!-- Golden Hour Highlight Card -->
                <div class="p-4 rounded-2xl bg-gradient-to-r from-atoma-blue/15 via-atoma-cyan/10 to-transparent border border-atoma-blue/30 mb-5 flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-black uppercase text-atoma-blue tracking-wider">{{ __('Jam Emas Produktifmu') }}</div>
                        <div class="text-lg sm:text-xl font-black text-gray-900 dark:text-white mt-0.5">
                            {{ __($goldenTimeLabel) }}
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $bestDayName !== '-' ? __('Waktu terbaik untuk tugas berat & deep work') : __('Selesaikan tugas pertama untuk mendeteksi jam produktif optimalmu') }}
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/50 border border-amber-300 dark:border-amber-700/60 p-2 flex items-center justify-center shrink-0 shadow-sm">
                        <img src="{{ asset('images/icons/insights_golden_hour.svg') }}" alt="Jam Emas" class="w-full h-full object-contain">
                    </div>
                </div>

                <!-- Time distribution bars -->
                @php
                    $actualTimeSlots = $morningTasksCount + $afternoonTasksCount + $eveningTasksCount + $nightTasksCount;
                    $morningPct = $actualTimeSlots > 0 ? round(($morningTasksCount / $actualTimeSlots) * 100) : 0;
                    $afternoonPct = $actualTimeSlots > 0 ? round(($afternoonTasksCount / $actualTimeSlots) * 100) : 0;
                    $eveningPct = $actualTimeSlots > 0 ? round(($eveningTasksCount / $actualTimeSlots) * 100) : 0;
                @endphp
                <div class="space-y-3">
                    <!-- Pagi -->
                    <div>
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                <img src="{{ asset('images/icons/insights_sun_morning.svg') }}" alt="Pagi" class="w-4 h-4 object-contain">
                                <span>{{ __('Pagi') }} (06:00 - 12:00)</span>
                            </span>
                            <span class="text-atoma-blue">{{ $morningTasksCount }} {{ __('Tugas') }} ({{ $morningPct }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                            <div class="bg-atoma-blue h-full rounded-full transition-all duration-500" style="width: {{ $morningPct }}%"></div>
                        </div>
                    </div>

                    <!-- Siang / Sore -->
                    <div>
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                <img src="{{ asset('images/icons/insights_sun_midday.svg') }}" alt="Siang" class="w-4 h-4 object-contain">
                                <span>{{ __('Siang & Sore') }} (12:00 - 18:00)</span>
                            </span>
                            <span class="text-atoma-cyan">{{ $afternoonTasksCount }} {{ __('Tugas') }} ({{ $afternoonPct }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                            <div class="bg-atoma-cyan h-full rounded-full transition-all duration-500" style="width: {{ $afternoonPct }}%"></div>
                        </div>
                    </div>

                    <!-- Malam -->
                    <div>
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                <img src="{{ asset('images/icons/insights_moon_night.svg') }}" alt="Malam" class="w-4 h-4 object-contain">
                                <span>{{ __('Malam') }} (18:00 - 24:00)</span>
                            </span>
                            <span class="text-indigo-500">{{ $eveningTasksCount }} {{ __('Tugas') }} ({{ $eveningPct }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                            <div class="bg-indigo-500 h-full rounded-full transition-all duration-500" style="width: {{ $eveningPct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-4 leading-relaxed pl-1 flex items-start gap-1.5">
                <img src="{{ asset('images/icons/insights_bulb.svg') }}" alt="Tips" class="w-3.5 h-3.5 object-contain shrink-0 mt-0.5">
                @if($bestDayName !== '-' && $bestDayRate > 0)
                    <span><strong>{{ __('Tips:') }}</strong> {{ __('Hari :day adalah hari paling efektifmu. Gunakan hari ini untuk menuntaskan milestone penting.', ['day' => __($bestDayName)]) }}</span>
                @else
                    <span><strong>{{ __('Tips:') }}</strong> {{ __('Selesaikan tugas harian secara konsisten untuk mulai menganalisis jam emas dan hari paling produktifmu.') }}</span>
                @endif
            </p>
        </div>

    </div>

    <!-- BAGIAN 4: PRODUCTIVITY RPG STATS (RADAR) & PRESTIGE ASCENSION -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
        
        <!-- Left: Productivity RPG Stats (4-Axis Radar Chart) -->
        <div class="lg:col-span-7 bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800/60 p-1 flex items-center justify-center shrink-0">
                                <img src="{{ asset('images/icons/insights_shield_rpg.svg') }}" alt="RPG Stats" class="w-full h-full object-contain">
                            </div>
                            <span>{{ __('Productivity RPG Stats (Radar)') }}</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-0.5">{{ __('Kekuatan produktivitasmu dalam 4 dimensi fundamental') }}</p>
                    </div>
                    @php
                        $avgStat = round(array_sum($rpgStats) / 4);
                    @endphp
                    <span class="px-3 py-1 rounded-full text-xs font-black bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan border border-atoma-aqua/40">
                        OVR: {{ $avgStat }} / 100
                    </span>
                </div>

                <!-- Radar Chart Visualizer (SVG) -->
                @php
                    $focusVal = max(8, min(100, (int) ($rpgStats['focus'] ?? 0)));
                    $consVal  = max(8, min(100, (int) ($rpgStats['consistency'] ?? 0)));
                    $execVal  = max(8, min(100, (int) ($rpgStats['execution'] ?? 0)));
                    $orgVal   = max(8, min(100, (int) ($rpgStats['organization'] ?? 0)));

                    $cx = 140;
                    $cy = 135;
                    $maxR = 85;

                    $topX = $cx;
                    $topY = $cy - ($focusVal / 100 * $maxR);

                    $rightX = $cx + ($consVal / 100 * $maxR);
                    $rightY = $cy;

                    $bottomX = $cx;
                    $bottomY = $cy + ($execVal / 100 * $maxR);

                    $leftX = $cx - ($orgVal / 100 * $maxR);
                    $leftY = $cy;

                    $polygonPoints = "{$topX},{$topY} {$rightX},{$rightY} {$bottomX},{$bottomY} {$leftX},{$leftY}";
                @endphp

                <div class="flex flex-col sm:flex-row items-center gap-6 my-2">
                    <div class="relative w-72 h-64 shrink-0 flex items-center justify-center">
                        <svg viewBox="0 0 280 270" class="w-full h-full drop-shadow-sm">
                            <defs>
                                <linearGradient id="radarGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#3E64FF" stop-opacity="0.5"/>
                                    <stop offset="100%" stop-color="#5EDFFF" stop-opacity="0.25"/>
                                </linearGradient>
                            </defs>

                            <!-- Background Rings -->
                            <!-- 100% Ring -->
                            <polygon points="140,50 225,135 140,220 55,135" fill="none" stroke="currentColor" class="text-gray-200 dark:text-gray-700" stroke-width="1.5" stroke-dasharray="3,3"/>
                            <!-- 75% Ring -->
                            <polygon points="140,71 204,135 140,199 76,135" fill="none" stroke="currentColor" class="text-gray-200 dark:text-gray-700" stroke-width="1" stroke-dasharray="2,2"/>
                            <!-- 50% Ring -->
                            <polygon points="140,92 183,135 140,178 97,135" fill="none" stroke="currentColor" class="text-gray-200 dark:text-gray-700" stroke-width="1"/>
                            <!-- 25% Ring -->
                            <polygon points="140,114 161,135 140,156 119,135" fill="none" stroke="currentColor" class="text-gray-200 dark:text-gray-700" stroke-width="1"/>

                            <!-- Crosshair Axes -->
                            <line x1="140" y1="45" x2="140" y2="225" stroke="currentColor" class="text-gray-300 dark:text-gray-600" stroke-width="1"/>
                            <line x1="50" y1="135" x2="230" y2="135" stroke="currentColor" class="text-gray-300 dark:text-gray-600" stroke-width="1"/>

                            <!-- Dynamic Radar Polygon -->
                            <polygon points="{{ $polygonPoints }}" fill="url(#radarGradient)" stroke="#3E64FF" stroke-width="2.5" class="transition-all duration-700"/>

                            <!-- Vertex Dots -->
                            <circle cx="{{ $topX }}" cy="{{ $topY }}" r="4" fill="#3E64FF" class="stroke-white dark:stroke-gray-800" stroke-width="2"/>
                            <circle cx="{{ $rightX }}" cy="{{ $rightY }}" r="4" fill="#3E64FF" class="stroke-white dark:stroke-gray-800" stroke-width="2"/>
                            <circle cx="{{ $bottomX }}" cy="{{ $bottomY }}" r="4" fill="#3E64FF" class="stroke-white dark:stroke-gray-800" stroke-width="2"/>
                            <circle cx="{{ $leftX }}" cy="{{ $leftY }}" r="4" fill="#3E64FF" class="stroke-white dark:stroke-gray-800" stroke-width="2"/>

                            <!-- Axis Labels -->
                            <text x="140" y="32" text-anchor="middle" class="text-[11px] font-black fill-current text-gray-800 dark:text-gray-200">Focus ({{ $rpgStats['focus'] }})</text>
                            <text x="235" y="139" text-anchor="start" class="text-[11px] font-black fill-current text-gray-800 dark:text-gray-200">Consistency ({{ $rpgStats['consistency'] }})</text>
                            <text x="140" y="248" text-anchor="middle" class="text-[11px] font-black fill-current text-gray-800 dark:text-gray-200">Execution ({{ $rpgStats['execution'] }})</text>
                            <text x="45" y="139" text-anchor="end" class="text-[11px] font-black fill-current text-gray-800 dark:text-gray-200">Organization ({{ $rpgStats['organization'] }})</text>
                        </svg>
                    </div>

                    <!-- Stat Breakdown Cards (4 Axes) -->
                    <div class="flex-1 w-full space-y-2.5">
                        <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-6 h-6 rounded-lg bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800/50 p-1 flex items-center justify-center shrink-0">
                                    <img src="{{ asset('images/icons/insights_target.svg') }}" alt="Focus" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-gray-900 dark:text-white">{{ __('Focus (Kekuatan Fokus)') }}</div>
                                    <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('Total jam sesi Pomodoro') }}</div>
                                </div>
                            </div>
                            <span class="text-xs font-black text-atoma-blue">{{ $rpgStats['focus'] }}/100</span>
                        </div>

                        <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-6 h-6 rounded-lg bg-orange-50 dark:bg-orange-950/50 border border-orange-200 dark:border-orange-800/50 p-1 flex items-center justify-center shrink-0">
                                    <img src="{{ asset('images/icons/streak_saver.svg') }}" alt="Consistency" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-gray-900 dark:text-white">{{ __('Consistency (Ketahanan)') }}</div>
                                    <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('Streak & kehadiran 30 hari') }}</div>
                                </div>
                            </div>
                            <span class="text-xs font-black text-orange-500">{{ $rpgStats['consistency'] }}/100</span>
                        </div>

                        <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-6 h-6 rounded-lg bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/50 p-1 flex items-center justify-center shrink-0">
                                    <img src="{{ asset('images/icons/insights_golden_hour.svg') }}" alt="Execution" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-gray-900 dark:text-white">{{ __('Execution (Ketepatan)') }}</div>
                                    <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('Tepat waktu vs deadline') }}</div>
                                </div>
                            </div>
                            <span class="text-xs font-black text-emerald-600 dark:text-emerald-400">{{ $rpgStats['execution'] }}/100</span>
                        </div>

                        <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-6 h-6 rounded-lg bg-purple-50 dark:bg-purple-950/50 border border-purple-200 dark:border-purple-800/50 p-1 flex items-center justify-center shrink-0">
                                    <img src="{{ asset('images/icons/insights_notepad.svg') }}" alt="Organization" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-gray-900 dark:text-white">{{ __('Organization (Kerapian)') }}</div>
                                    <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('Subtask, kategori, & estimasi') }}</div>
                                </div>
                            </div>
                            <span class="text-xs font-black text-purple-600 dark:text-purple-400">{{ $rpgStats['organization'] }}/100</span>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-4 leading-relaxed pl-1 flex items-start gap-1.5">
                <img src="{{ asset('images/icons/insights_bulb.svg') }}" alt="Info" class="w-3.5 h-3.5 object-contain shrink-0 mt-0.5">
                <span>{{ __('Stat RPG dihitung otomatis dari perilaku produktivitas nyatamu dan terus berkembang seiring waktu.') }}</span>
            </p>
        </div>

        <!-- Right: Prestige / Ascension & Seasonal Pass Card -->
        <div class="lg:col-span-5 bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/60 p-1 flex items-center justify-center shrink-0">
                                <img src="{{ asset('images/icons/insights_crown.svg') }}" alt="Prestige" class="w-full h-full object-contain">
                            </div>
                            <span>{{ __('Prestige & Ascension') }}</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-0.5">{{ __('Sistem rank multi-tier ATOMA') }}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-black bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 border border-amber-300">
                        Tier {{ $prestigeTier['tier'] ?? 1 }} / 5
                    </span>
                </div>

                <!-- Prestige Crest & Current Level -->
                <div class="p-4 rounded-2xl bg-gradient-to-br {{ $prestigeTier['gradient'] ?? 'from-emerald-500 to-emerald-600' }} text-white shadow-md mb-4 flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center p-2.5 shadow-inner shrink-0 border border-white/30">
                        <img src="{{ asset($prestigeTier['svg'] ?? 'images/prestige/tier_1_novice.svg') }}" alt="{{ $prestigeTier['name'] ?? 'Tier' }}" class="w-full h-full object-contain drop-shadow">
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-widest font-black text-white/80">Tier {{ $prestigeTier['tier'] ?? 1 }}</div>
                        <h4 class="text-2xl font-black">{{ $prestigeTier['name'] ?? 'Novice' }}</h4>
                        <p class="text-xs font-bold text-white/90">Level {{ $prestigeLevel }} <span class="text-white/70 font-normal">/ 50 ({{ __('Maks per Tier') }})</span></p>
                    </div>
                </div>

                <!-- Exponential XP Progress Bar -->
                <div class="space-y-2 mb-5">
                    <div class="flex justify-between text-xs font-bold">
                        <span class="text-gray-600 dark:text-gray-400">{{ __('Progres Level Ini (Kurva Eksponensial)') }}</span>
                        <span class="text-atoma-blue font-black">{{ number_format($levelProgressData['current_xp']) }} / {{ number_format($levelProgressData['required_xp']) }} XP</span>
                    </div>
                    <div class="w-full bg-gray-100 dark:bg-gray-700 h-3 rounded-full overflow-hidden">
                        <div class="bg-gradient-to-r from-atoma-blue to-atoma-cyan h-full rounded-full transition-all duration-700" style="width: {{ $levelProgressData['percentage'] }}%"></div>
                    </div>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400">
                        {{ __('Mencapai Level 50 memicu otomatis promosi') }} <strong class="text-amber-500">Ascension</strong> {{ __('ke tier berikutnya!') }}
                    </p>
                </div>

                <!-- Seasonal Rank & Legacy Info -->
                <div class="grid grid-cols-2 gap-2.5 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-100 dark:border-gray-700 text-left">
                        <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400">{{ __('Musim Berjalan') }}</div>
                        <div class="text-xs font-black text-gray-900 dark:text-white mt-0.5">{{ $seasonalProgress['season_name'] }}</div>
                        <div class="text-[10px] text-atoma-blue font-bold mt-1">Lvl {{ $seasonalProgress['level'] }} &bull; {{ number_format($seasonalProgress['xp']) }} XP</div>
                    </div>

                    <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-100 dark:border-gray-700 text-left">
                        <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400">{{ __('Lifetime Legacy') }}</div>
                        <div class="text-xs font-black text-gray-900 dark:text-white mt-0.5">{{ number_format($lifetimeXp) }} XP</div>
                        <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold mt-1">{{ __('Sejak') }} {{ $memberSince }}</div>
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-4 leading-relaxed pl-1 flex items-start gap-1.5">
                <img src="{{ asset('images/icons/insights_trophy.svg') }}" alt="Lifetime Legacy" class="w-3.5 h-3.5 object-contain shrink-0 mt-0.5">
                <span>{{ __('Lifetime XP abadi dan tidak pernah direset, sedangkan level musiman diperbarui tiap kuartal.') }}</span>
            </p>
        </div>

    </div>

    <!-- BAGIAN 4B: WEEKLY PERSONA & LEAGUE STANDING (2 COLS) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        
        <!-- Left: Weekly Productivity Persona -->
        <div class="bg-gradient-to-br from-atoma-blue/20 via-atoma-cyan/10 to-white dark:to-gray-800 rounded-3xl p-5 sm:p-6 border border-atoma-blue/40 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] sm:text-xs font-black text-atoma-blue uppercase tracking-wider">
                        Weekly Persona Archetype
                    </span>
                    <span class="px-3 py-1 rounded-full text-xs font-black bg-white dark:bg-gray-800 text-atoma-blue shadow-xs border border-atoma-aqua/40">
                        {{ __($weeklyPersona['tag']) }}
                    </span>
                </div>

                <div class="flex items-start gap-4 mb-4">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-white dark:bg-gray-800 shadow-md border border-atoma-blue/30 flex items-center justify-center p-2.5 shrink-0">
                        @if(!empty($weeklyPersona['svg']))
                            <img src="{{ asset($weeklyPersona['svg']) }}" alt="{{ $weeklyPersona['title'] }}" class="w-full h-full object-contain">
                        @else
                            <img src="{{ asset('images/icons/feature_zen.svg') }}" alt="{{ $weeklyPersona['title'] }}" class="w-full h-full object-contain">
                        @endif
                    </div>
                    <div>
                        <h3 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white">
                            {{ __($weeklyPersona['title']) }}
                        </h3>
                        <p class="text-xs font-bold text-atoma-blue mt-0.5">{{ __($weeklyPersona['archetype']) }}</p>
                    </div>
                </div>

                <p class="text-xs sm:text-sm text-gray-700 dark:text-gray-300 leading-relaxed font-medium">
                    "{{ __($weeklyPersona['desc']) }}"
                </p>
            </div>

            <div class="mt-4 pt-3 border-t border-atoma-blue/20 flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __('Dihitung otomatis tiap akhir pekan') }}</span>
                <span class="font-black text-atoma-blue">{{ __('Level :level', ['level' => $userLevel]) }}</span>
            </div>
        </div>

        <!-- Right: Weekly League Standing & Promotion Target -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/60 p-1 flex items-center justify-center shrink-0">
                                <img src="{{ asset('images/icons/insights_trophy.svg') }}" alt="League" class="w-full h-full object-contain">
                            </div>
                            <span>{{ __('League Status & Target') }}</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-0.5">{{ __('Sistem liga mingguan ATOMA') }}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-black inline-flex items-center gap-1.5 {{ $leagueInfo['is_promoted'] ? 'bg-emerald-50 text-emerald-600 border border-emerald-300' : 'bg-amber-50 text-amber-600 border border-amber-300' }}">
                        @if($leagueInfo['is_promoted'])
                            <img src="{{ asset('images/icons/insights_rocket.svg') }}" alt="Promosi" class="w-3.5 h-3.5 object-contain">
                            <span>{{ __('Di Jalur Promosi') }}</span>
                        @else
                            <img src="{{ asset('images/icons/insights_focus.svg') }}" alt="Target" class="w-3.5 h-3.5 object-contain">
                            <span>{{ __('Butuh +:gap XP', ['gap' => $leagueInfo['gap_xp']]) }}</span>
                        @endif
                    </span>
                </div>

                <div class="flex items-center gap-3 p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200/70 dark:border-gray-700/60 mb-4">
                    <div class="w-11 h-11 rounded-xl bg-white dark:bg-gray-800 p-1.5 shadow-xs border border-gray-200 dark:border-gray-700 flex items-center justify-center shrink-0">
                        <img src="{{ asset($leagueInfo['svg'] ?? ('images/badges/league/league_' . strtolower($leagueInfo['tier'] ?? 'bronze') . '.svg')) }}" alt="{{ $leagueInfo['name'] }}" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <h4 class="font-black text-base text-gray-900 dark:text-white">{{ $leagueInfo['name'] }}</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ __('Tier: :tier', ['tier' => $leagueInfo['tier']]) }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex justify-between text-xs font-bold">
                        <span class="text-gray-600 dark:text-gray-400">{{ __('Progres XP Mingguan') }}</span>
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
                {{ __('Settlement liga dilakukan setiap hari Senin pukul 00:00 WIB. Pertahankan posisi untuk klaim hadiah bonus koin!') }}
            </p>
        </div>

    </div>

    <!-- BAGIAN 5: OVERDUE ACTION CENTER (ID: overdue-section) -->
    <div id="overdue-section" class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 mb-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800/60 p-1 flex items-center justify-center shrink-0">
                        <img src="{{ asset('images/icons/insights_alert.svg') }}" alt="Overdue Alert" class="w-full h-full object-contain">
                    </div>
                    <span>Overdue Action Center</span>
                    @if(count($overdueTasks) > 0)
                        <span class="ml-2 px-2.5 py-0.5 bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 rounded-full text-xs font-black">
                            {{ __(':count Overdue', ['count' => count($overdueTasks)]) }}
                        </span>
                    @endif
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-0.5">{{ __('Tindakan langsung tanpa perlu berpindah halaman') }}</p>
            </div>
            
            <a href="{{ route('tasks') }}" wire:navigate class="text-xs font-bold text-atoma-blue hover:underline">
                {{ __('Kelola Semua') }} &rarr;
            </a>
        </div>

        @if(count($overdueTasks) === 0)
            <div class="text-center py-8 bg-emerald-50/60 dark:bg-emerald-950/30 rounded-2xl border border-emerald-200 dark:border-emerald-800/40">
                <div class="w-12 h-12 mx-auto mb-2 p-1">
                    <img src="{{ asset('images/icons/insights_celebrate.svg') }}" alt="Semua Aman" class="w-full h-full object-contain">
                </div>
                <h4 class="font-bold text-sm text-emerald-900 dark:text-emerald-200">{{ __('Semua Tugas Aman!') }}</h4>
                <p class="text-xs text-emerald-700 dark:text-emerald-400">{{ __('Tidak ada tugas yang menunggak saat ini. Pertahankan fokusmu!') }}</p>
            </div>
        @else
            @if(!empty($reasonBreakdown))
                <div class="mb-4 flex items-center gap-2 overflow-x-auto hide-scrollbar py-1">
                    <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400 shrink-0 flex items-center gap-1.5">
                        <img src="{{ asset('images/icons/insights_trends.svg') }}" alt="Data" class="w-3.5 h-3.5 object-contain">
                        <span>{{ __('Hambatan Terdata:') }}</span>
                    </span>
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
                                    <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full bg-atoma-blue text-white shadow-xs inline-flex items-center gap-1">
                                         <img src="{{ asset('images/icons/insights_target.svg') }}" alt="Milestone" class="w-3.5 h-3.5 object-contain">
                                         <span>{{ __('Milestone') }} @if($task->skillTarget)({{ __('Hari :day/30', ['day' => $task->skillTarget->day_number]) }})@endif</span>
                                     </span>
                                 @elseif($task->category)
                                     <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan border border-atoma-aqua/40">
                                         {{ $task->category }}
                                     </span>
                                 @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2 ml-4 text-xs">
                                <span class="text-rose-600 dark:text-rose-400 font-medium inline-flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <span>{{ __('Terlewat:') }} {{ $task->date ? Carbon::parse($task->date)->format('d M Y') : __('Belum selesai') }}</span>
                                </span>
                                @if($task->skip_reason)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 text-[10px] font-bold border border-amber-300 dark:border-amber-700">
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                        <span>{{ $task->skip_reason }}</span>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Action Buttons: Beri Alasan, Abaikan, Hapus -->
                        <div class="flex items-center gap-2">
                            <button type="button" 
                                    wire:click="openReasonModal({{ $task->id }})" 
                                    class="flex-1 sm:flex-initial px-3.5 py-2 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white font-bold text-xs rounded-xl shadow-xs shadow-amber-500/20 transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                <img src="{{ asset('images/icons/insights_notepad.svg') }}" alt="Alasan" class="w-3.5 h-3.5 object-contain">
                                <span>{{ $task->skip_reason ? __('Ubah Alasan') : __('Beri Alasan') }}</span>
                            </button>
                            <button type="button" 
                                    wire:click="dismissOverdueTask({{ $task->id }})" 
                                    class="flex-1 sm:flex-initial px-3 py-2 bg-gray-100 dark:bg-gray-700/80 hover:bg-gray-200 dark:hover:bg-gray-600 active:scale-95 text-gray-700 dark:text-gray-200 font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-1 cursor-pointer"
                                    title="{{ __('Abaikan dari notifikasi (tugas tetap tersimpan di tanggal aslinya)') }}">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path></svg>
                                <span>{{ __('Abaikan') }}</span>
                            </button>
                            <button type="button" 
                                    wire:click="deleteTask({{ $task->id }})" 
                                    wire:confirm="{{ __('Hapus tugas ini? Tugas akan dicatat di riwayat overdue yang dihapus.') }}" 
                                    class="p-2 text-rose-600 dark:text-rose-400 hover:text-rose-700 bg-rose-100 dark:bg-rose-950/60 hover:bg-rose-200 rounded-xl transition-colors shrink-0 cursor-pointer"
                                    title="{{ __('Hapus Tugas') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($overduePatternNotice)
                <div class="p-3.5 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-900/60 rounded-2xl text-xs text-amber-900 dark:text-amber-300 font-medium flex items-center gap-2.5">
                    <div class="w-6 h-6 rounded-lg bg-amber-100 dark:bg-amber-900/50 p-1 flex items-center justify-center shrink-0">
                        <img src="{{ asset('images/icons/insights_coach.svg') }}" alt="AI Coach" class="w-full h-full object-contain">
                    </div>
                    <span><strong>{{ __('Analisis Pola AI:') }}</strong> {{ $overduePatternNotice }}</span>
                </div>
            @endif
        @endif

        @if($deletedOverdueCount > 0)
            <div class="mt-6 pt-5 border-t border-gray-200 dark:border-gray-700/60" x-data="{ openDeleted: false }">
                <div class="flex items-center justify-between cursor-pointer select-none" @click="openDeleted = !openDeleted">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                        <h4 class="font-bold text-xs sm:text-sm text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                            <span>{{ __('Tugas Overdue yang Dihapus') }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50">
                                {{ $deletedOverdueCount }}
                            </span>
                        </h4>
                        <span class="text-[11px] text-gray-400 hidden sm:inline">&bull; {{ __('Transparansi rekam jejak') }}</span>
                    </div>
                    <button type="button" class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1 cursor-pointer">
                        <span x-text="openDeleted ? '{{ __('Sembunyikan') }}' : '{{ __('Lihat Rincian') }}'"></span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="openDeleted ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                </div>
                <div x-show="openDeleted" x-collapse x-cloak class="mt-3 space-y-2">
                    <p class="text-[11px] text-gray-400 dark:text-gray-500 italic mb-2">
                        {{ __('Tugas yang telah lewat deadline dan dihapus tetap dicatat di sini agar riwayat kedisiplinan dan transparansi progres tetap terjaga.') }}
                    </p>
                    @foreach($deletedOverdueTasks as $delTask)
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-200/80 dark:border-gray-800 flex items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="text-gray-400 text-sm">🗑️</span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-700 dark:text-gray-300 truncate line-through decoration-gray-400">
                                        {{ $delTask->title }}
                                    </p>
                                    <div class="flex items-center gap-2 text-[10px] text-gray-400 mt-0.5">
                                        <span>{{ __('Jadwal:') }} {{ $delTask->date ? Carbon::parse($delTask->date)->format('d M Y') : '-' }}</span>
                                        @if($delTask->deadline)
                                            <span>&bull; {{ __('Deadline:') }} {{ substr($delTask->deadline, 0, 5) }}</span>
                                        @endif
                                        @if($delTask->category)
                                            <span class="px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ $delTask->category }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-[10px] text-gray-400">
                                    {{ __('Dihapus') }} {{ $delTask->deleted_at ? $delTask->deleted_at->diffForHumans() : '' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($dismissedOverdueCount > 0)
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700/60" x-data="{ openDismissed: false }">
                <div class="flex items-center justify-between cursor-pointer select-none" @click="openDismissed = !openDismissed">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        <h4 class="font-bold text-xs sm:text-sm text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                            <span>{{ __('Tugas Overdue yang Diabaikan') }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-900/50">
                                {{ $dismissedOverdueCount }}
                            </span>
                        </h4>
                        <span class="text-[11px] text-gray-400 hidden sm:inline">&bull; {{ __('Tetap tersimpan di tanggal aslinya') }}</span>
                    </div>
                    <button type="button" class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1 cursor-pointer">
                        <span x-text="openDismissed ? '{{ __('Sembunyikan') }}' : '{{ __('Lihat Rincian') }}'"></span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="openDismissed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                </div>
                <div x-show="openDismissed" x-collapse x-cloak class="mt-3 space-y-2">
                    <p class="text-[11px] text-gray-400 dark:text-gray-500 italic mb-2">
                        {{ __('Tugas-tugas ini telah diabaikan dari notifikasi/tray hari ini, namun tetap tersimpan pada tanggal aslinya dan dapat dipulihkan kembali ke notifikasi sewaktu-waktu.') }}
                    </p>
                    @foreach($dismissedOverdueTasks as $disTask)
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-200/80 dark:border-gray-800 flex items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="text-gray-400 text-sm">👁️</span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-700 dark:text-gray-300 truncate">
                                        {{ $disTask->title }}
                                    </p>
                                    <div class="flex items-center gap-2 text-[10px] text-gray-400 mt-0.5">
                                        <span>{{ __('Jadwal:') }} {{ $disTask->date ? Carbon::parse($disTask->date)->format('d M Y') : '-' }}</span>
                                        @if($disTask->skip_reason)
                                            <span class="px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                                                💬 {{ $disTask->skip_reason }}
                                            </span>
                                        @endif
                                        @if($disTask->category)
                                            <span class="px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ $disTask->category }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button"
                                        wire:click="restoreDismissedOverdue({{ $disTask->id }})"
                                        class="px-2 py-1 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 hover:bg-amber-200 rounded-lg text-[10px] font-bold transition-colors cursor-pointer"
                                        title="{{ __('Tampilkan kembali di notifikasi tugas hari ini') }}">
                                    {{ __('Pulihkan Notif') }}
                                </button>
                                <button type="button" 
                                        wire:click="deleteTask({{ $disTask->id }})" 
                                        wire:confirm="{{ __('Hapus tugas ini?') }}" 
                                        class="p-1.5 text-rose-600 dark:text-rose-400 hover:text-rose-700 rounded-lg transition-colors cursor-pointer"
                                        title="{{ __('Hapus Tugas') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- BAGIAN 6: ATOMA SAYS & PERIODIC STRATEGIC COACHING -->
    <div class="space-y-6">

        <!-- Weekly ATOMA Says -->
        <div class="bg-gradient-to-r from-[#1733a0] via-[#1b3cb8] to-[#122675] rounded-3xl p-6 sm:p-8 text-white shadow-xl border border-white/20 relative overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md p-1.5 border border-white/30 flex items-center justify-center shadow-md shrink-0">
                        <img src="{{ asset('images/icons/insights_coach.svg') }}" alt="ATOMA Coach" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-atoma-aqua/20 text-atoma-aqua border border-atoma-aqua/30">
                                7-Day Sprint
                            </span>
                            @if($weeklyInsight && $weeklyInsight->archetype)
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400/20 text-amber-300 border border-amber-400/30">
                                    {{ $weeklyInsight->archetype }}
                                </span>
                            @endif
                        </div>
                        <h3 class="text-xl sm:text-2xl font-black tracking-tight text-white flex items-center gap-2 mt-0.5">
                            <span>ATOMA Says</span>
                            <img src="{{ asset('images/icons/feature_insights.svg') }}" alt="Sparkle" class="w-5 h-5 object-contain">
                        </h3>
                        <p class="text-xs text-white/80 font-medium">{{ __('Analisis & Panduan Personal Life Coach Mingguan') }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    @if($insightLastGeneratedAt)
                        <span class="text-xs text-white/80 font-medium">{{ __('Diperbarui:') }} {{ $insightLastGeneratedAt }}</span>
                    @endif

                    <button type="button" 
                            wire:click="generateWeeklyInsight" 
                            wire:loading.attr="disabled"
                            wire:target="generateWeeklyInsight"
                            x-data="{ clicking: false }"
                            @click="if (clicking) return false; clicking = true; setTimeout(() => clicking = false, 8000);"
                            class="px-5 py-2.5 bg-white text-atoma-blue hover:bg-atoma-ice disabled:bg-white/40 disabled:text-white/60 font-black text-xs rounded-xl shadow-md transition-all flex items-center justify-center cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none" 
                            {{ (!$canRefreshWeekly || $isLoadingWeekly) ? 'disabled' : '' }}>
                        <span wire:loading.remove wire:target="generateWeeklyInsight" class="inline-flex items-center gap-1.5">
                            @if($canRefreshWeekly)
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                <span>{{ __('Refresh Insight') }}</span>
                            @else
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                <span>{{ __('Diperbarui Hari Ini') }}</span>
                            @endif
                        </span>
                        <span wire:loading wire:target="generateWeeklyInsight" class="flex items-center">
                            <svg class="animate-spin h-3.5 w-3.5 mr-1.5 text-atoma-blue" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ __('Menganalisis...') }}
                        </span>
                    </button>
                </div>
            </div>

            @if($weeklyInsight)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                    <!-- Observasi -->
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 space-y-2">
                        <div class="flex items-center space-x-2 text-atoma-aqua font-extrabold text-sm">
                            <div class="w-5 h-5 shrink-0">
                                <img src="{{ asset('images/icons/insights_bulb.svg') }}" alt="Observasi" class="w-full h-full object-contain">
                            </div>
                            <span>{{ __('Observasi Mingguan') }}</span>
                        </div>
                        <p class="text-xs sm:text-sm text-white/95 leading-relaxed font-medium whitespace-pre-line">
                            {{ $weeklyInsight->observasi }}
                        </p>
                    </div>

                    <!-- Tantangan -->
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 space-y-2">
                        <div class="flex items-center space-x-2 text-amber-300 font-extrabold text-sm">
                            <div class="w-5 h-5 shrink-0">
                                <img src="{{ asset('images/icons/insights_alert.svg') }}" alt="Tantangan" class="w-full h-full object-contain">
                            </div>
                            <span>{{ __('Tantangan & Solusi') }}</span>
                        </div>
                        <p class="text-xs sm:text-sm text-white/95 leading-relaxed font-medium whitespace-pre-line">
                            {{ $weeklyInsight->tantangan }}
                        </p>
                    </div>

                    <!-- Afirmasi -->
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 space-y-2">
                        <div class="flex items-center space-x-2 text-emerald-300 font-extrabold text-sm">
                            <div class="w-5 h-5 shrink-0">
                                <img src="{{ asset('images/icons/feature_insights.svg') }}" alt="Afirmasi" class="w-full h-full object-contain">
                            </div>
                            <span>{{ __('Afirmasi & Motivasi') }}</span>
                        </div>
                        <p class="text-xs sm:text-sm text-white/95 leading-relaxed font-medium whitespace-pre-line">
                            {{ $weeklyInsight->afirmasi }}
                        </p>
                    </div>
                </div>
            @else
                <div class="text-center py-8 bg-white/5 rounded-2xl border border-white/10 space-y-2">
                    <div class="w-12 h-12 mx-auto mb-1">
                        <img src="{{ asset('images/icons/insights_crystal.svg') }}" alt="Insight" class="w-full h-full object-contain">
                    </div>
                    <h4 class="text-base font-bold text-white">{{ __('Belum Ada Weekly Insight') }}</h4>
                    <p class="text-xs text-white/80 max-w-md mx-auto">{{ __('Klik tombol Refresh Insight di atas untuk meminta ATOMA menganalisis pola produktivitasmu selama 7 hari terakhir.') }}</p>
                </div>
            @endif
        </div>

        <!-- SECTION: Monthly Capstone Retrospective (The "Spotify Wrapped" of Productivity) -->
        <div class="bg-gradient-to-br from-indigo-950 via-slate-900 to-purple-950 rounded-3xl p-6 sm:p-8 text-white shadow-xl border border-purple-500/30 relative overflow-hidden space-y-6">
            <div class="absolute top-0 right-0 -mt-10 -mr-10 w-48 h-48 bg-purple-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 -mb-10 -ml-10 w-48 h-48 bg-atoma-blue/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/10 pb-5">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-purple-500/20 border border-purple-400/40 p-2.5 flex items-center justify-center shrink-0 shadow-inner">
                        <img src="{{ asset('images/icons/insights_crystal.svg') }}" alt="Monthly Capstone" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-400/20 text-purple-300 border border-purple-400/30">
                                30-Day Retrospective
                            </span>
                            @if($monthlyInsight && $monthlyInsight->archetype)
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400/20 text-amber-300 border border-amber-400/30">
                                    {{ $monthlyInsight->archetype }}
                                </span>
                            @endif
                        </div>
                        <h3 class="text-xl sm:text-2xl font-black text-white tracking-tight mt-0.5">
                            {{ __('Monthly Capstone Retrospective') }}
                        </h3>
                        <p class="text-xs text-purple-200/80 font-medium">
                            {{ __('Sintesis Makro 30 Hari: Personal Archetype, Jam Fokus, & Blueprint Strategis') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    @if($monthlyLastGeneratedAt)
                        <span class="text-xs text-white/70 font-medium">{{ __('Diperbarui:') }} {{ $monthlyLastGeneratedAt }}</span>
                    @endif

                    <button type="button" 
                            wire:click="generateMonthlyReport" 
                            wire:loading.attr="disabled"
                            wire:target="generateMonthlyReport"
                            x-data="{ clicking: false }"
                            @click="if (clicking) return false; clicking = true; setTimeout(() => clicking = false, 8000);"
                            class="px-5 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-500 hover:from-purple-600 hover:to-indigo-600 text-white font-black text-xs rounded-xl shadow-lg shadow-purple-500/25 transition-all flex items-center justify-center cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none" 
                            {{ (!$canRefreshMonthly || $isLoadingMonthly) ? 'disabled' : '' }}>
                        <span wire:loading.remove wire:target="generateMonthlyReport" class="inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            <span>{{ $monthlyInsight ? __('Update Capstone') : __('Buka Laporan 30 Hari') }}</span>
                        </span>
                        <span wire:loading wire:target="generateMonthlyReport" class="flex items-center">
                            <svg class="animate-spin h-3.5 w-3.5 mr-1.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ __('Menganalisis 30 Hari...') }}
                        </span>
                    </button>
                </div>
            </div>

            @if($monthlyInsight)
                <!-- Archetype Showcase Hero Banner -->
                @if($monthlyInsight->archetype)
                    <div class="bg-gradient-to-r from-purple-900/50 via-indigo-900/40 to-slate-900/50 rounded-2xl p-5 border border-purple-400/30 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-4 text-center sm:text-left">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-400 to-purple-600 p-0.5 shrink-0 shadow-lg shadow-purple-500/30">
                                <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center text-2xl">
                                    👑
                                </div>
                            </div>
                            <div>
                                <span class="text-[11px] font-bold text-purple-300 uppercase tracking-wider block">Your 30-Day Productivity Archetype</span>
                                <h4 class="text-xl sm:text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-200 via-white to-purple-200">
                                    {{ $monthlyInsight->archetype }}
                                </h4>
                            </div>
                        </div>
                        <div class="px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-purple-200 shrink-0">
                            ✨ Evaluasi Capstone Personal
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                    <!-- Observasi Makro -->
                    <div class="bg-white/5 backdrop-blur-md rounded-2xl p-5 border border-white/15 space-y-2 hover:border-purple-400/40 transition-all">
                        <div class="flex items-center space-x-2 text-atoma-aqua font-extrabold text-sm">
                            <div class="w-5 h-5 shrink-0">
                                <img src="{{ asset('images/icons/insights_bulb.svg') }}" alt="Observasi" class="w-full h-full object-contain">
                            </div>
                            <span>{{ __('Observasi Makro 30 Hari') }}</span>
                        </div>
                        <p class="text-xs sm:text-sm text-white/95 leading-relaxed font-medium whitespace-pre-line">
                            {{ $monthlyInsight->observasi }}
                        </p>
                    </div>

                    <!-- Bottleneck & Solusi -->
                    <div class="bg-white/5 backdrop-blur-md rounded-2xl p-5 border border-white/15 space-y-2 hover:border-amber-400/40 transition-all">
                        <div class="flex items-center space-x-2 text-amber-300 font-extrabold text-sm">
                            <div class="w-5 h-5 shrink-0">
                                <img src="{{ asset('images/icons/insights_alert.svg') }}" alt="Bottleneck" class="w-full h-full object-contain">
                            </div>
                            <span>{{ __('Bottleneck & Kebocoran Waktu') }}</span>
                        </div>
                        <p class="text-xs sm:text-sm text-white/95 leading-relaxed font-medium whitespace-pre-line">
                            {{ $monthlyInsight->tantangan }}
                        </p>
                    </div>

                    <!-- Blueprint 30 Hari Depan -->
                    <div class="bg-white/5 backdrop-blur-md rounded-2xl p-5 border border-white/15 space-y-2 hover:border-emerald-400/40 transition-all">
                        <div class="flex items-center space-x-2 text-emerald-300 font-extrabold text-sm">
                            <div class="w-5 h-5 shrink-0">
                                <img src="{{ asset('images/icons/feature_insights.svg') }}" alt="Blueprint" class="w-full h-full object-contain">
                            </div>
                            <span>{{ __('Blueprint 30 Hari Ke Depan') }}</span>
                        </div>
                        <p class="text-xs sm:text-sm text-white/95 leading-relaxed font-medium whitespace-pre-line">
                            {{ $monthlyInsight->afirmasi }}
                        </p>
                    </div>
                </div>
            @else
                <div class="text-center py-10 bg-white/5 rounded-2xl border border-white/10 space-y-3">
                    <div class="w-14 h-14 mx-auto mb-1 p-2 rounded-2xl bg-purple-500/20 border border-purple-400/30 flex items-center justify-center">
                        <img src="{{ asset('images/icons/insights_crystal.svg') }}" alt="Capstone" class="w-8 h-8 object-contain">
                    </div>
                    <h4 class="text-lg font-bold text-white">{{ __('Belum Ada Monthly Capstone Report') }}</h4>
                    <p class="text-xs text-purple-200/80 max-w-md mx-auto">
                        {{ __('Klik tombol "Buka Laporan 30 Hari" di atas untuk menganalisis seluruh data tugas, jam fokus Pomodoro, dan tren performamu menjadi Spotify-Wrapped produktivitas personal.') }}
                    </p>
                </div>
            @endif
        </div>
    @else
        <!-- FITNESS HUB -->
        @include('livewire.partials.insights-fitness-hub')
    @endif

    </div>

    <!-- MODAL: Beri Alasan Tugas Tertunda -->
    @if($showReasonModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl border border-gray-200 dark:border-gray-700 space-y-4 animate-in fade-in zoom-in duration-150">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/60 p-1 flex items-center justify-center shrink-0">
                            <img src="{{ asset('images/icons/insights_notepad.svg') }}" alt="Alasan" class="w-full h-full object-contain">
                        </div>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white">{{ __('Alasan Tertunda') }}</h3>
                    </div>
                    <button type="button" wire:click="closeReasonModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg font-bold cursor-pointer">✕</button>
                </div>

                <div class="p-3 bg-gray-50 dark:bg-gray-700/40 rounded-2xl border border-gray-200 dark:border-gray-700 text-xs">
                    <span class="text-gray-400">{{ __('Tugas:') }}</span>
                    <strong class="text-gray-900 dark:text-white block truncate">{{ $selectedOverdueTaskTitle }}</strong>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300">{{ __('Pilih Faktor Penyebab Utama:') }}</label>
                    @php
                        $presetReasons = [
                            'Kelelahan / Burnout' => [
                                'desc' => 'Kurang energi, butuh istirahat',
                                'icon' => 'insights_focus.svg',
                            ],
                            'Waktu Tidak Cukup' => [
                                'desc' => 'Jadwal bentrok atau ada urusan lain',
                                'icon' => 'insights_radar_clock.svg',
                            ],
                            'Terlalu Rumit / Bingung' => [
                                'desc' => 'Belum tahu cara memulai langkah awal',
                                'icon' => 'insights_brain.svg',
                            ],
                            'Terdistraksi / Prokrastinasi' => [
                                'desc' => 'Kehilangan fokus / menunda-nunda',
                                'icon' => 'insights_target.svg',
                            ],
                            'Urusan Darurat' => [
                                'desc' => 'Ada keadaan mendesak di luar kendali',
                                'icon' => 'insights_alert.svg',
                            ],
                            'Lainnya' => [
                                'desc' => 'Alasan lainnya...',
                                'icon' => 'insights_notepad.svg',
                            ],
                        ];
                    @endphp

                    <div class="space-y-1.5">
                        @foreach($presetReasons as $rKey => $rData)
                            <label class="flex items-center gap-2.5 p-2.5 rounded-xl border cursor-pointer transition-all {{ $chosenReason === $rKey ? 'bg-atoma-ice dark:bg-atoma-blue/20 border-atoma-blue text-atoma-blue font-bold shadow-xs' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-700 dark:text-gray-300 text-xs' }}">
                                <input type="radio" wire:model.live="chosenReason" value="{{ $rKey }}" class="text-atoma-blue focus:ring-atoma-blue">
                                <div class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-700 p-1 flex items-center justify-center shrink-0">
                                    <img src="{{ asset('images/icons/' . $rData['icon']) }}" alt="{{ $rKey }}" class="w-full h-full object-contain">
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs font-bold">{{ __($rKey) }}</div>
                                    <div class="text-[10px] text-gray-400 font-normal">{{ __($rData['desc']) }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Catatan Tambahan (Opsional):') }}</label>
                    <textarea wire:model="customReasonNote" placeholder="{{ __('Tuliskan kendala spesifikmu jika ada...') }}" rows="2" class="w-full text-xs bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 rounded-xl p-2.5 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-atoma-blue"></textarea>
                </div>

                @if($selectedOverdueTaskIsMilestone)
                    <div class="p-3.5 bg-atoma-ice dark:bg-atoma-blue/15 rounded-2xl border border-atoma-aqua/50 dark:border-atoma-blue/30 flex items-start gap-2.5 text-xs">
                        <div class="w-5 h-5 shrink-0 mt-0.5">
                            <img src="{{ asset('images/icons/insights_target.svg') }}" alt="Milestone" class="w-full h-full object-contain">
                        </div>
                        <p class="text-gray-800 dark:text-gray-200 leading-relaxed font-medium">
                            <strong>{{ __('Target Milestone') }} @if($selectedOverdueTaskDayNumber)({{ __('Hari :day/30', ['day' => $selectedOverdueTaskDayNumber]) }})@endif:</strong> {{ __('Alasan dicatat khusus untuk evaluasi & analisis AI Coach tanpa merubah urutan pathway 1-30 hari.') }}
                        </p>
                    </div>
                @else
                    <div class="p-3 bg-atoma-ice/60 dark:bg-atoma-blue/10 rounded-xl border border-atoma-aqua/40 flex items-center gap-2 text-xs">
                        <span class="text-atoma-blue">💡</span>
                        <span class="text-gray-700 dark:text-gray-300 font-medium">
                            {{ __('Alasan dicatat untuk analisis pola AI. Tanggal tugas tetap tersimpan di tanggal aslinya.') }}
                        </span>
                    </div>
                @endif

                <div class="flex items-center gap-2 pt-2">
                    <button type="button" wire:click="closeReasonModal" class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-bold text-xs rounded-xl cursor-pointer">
                        {{ __('Batal') }}
                    </button>
                    <button type="button" wire:click="saveReason" class="flex-1 py-2.5 bg-atoma-blue hover:bg-[#3455dc] text-white font-bold text-xs rounded-xl shadow-md shadow-atoma-blue/25 cursor-pointer">
                        {{ __('Simpan & Analisis') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
 
    <!-- MODAL: Catat Olahraga Cepat -->
    @include('livewire.partials.insights-workout-modal')

</div>
