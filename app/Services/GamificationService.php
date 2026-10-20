<?php

namespace App\Services;

use App\Models\User;
use App\Models\Journal;
use App\Models\SubTask;
use App\Models\FocusSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class GamificationService
{
    public const DAILY_FLASHCARD_XP_CAP = 50;  // 5 XP per card, max 10 cards/day
    public const DAILY_JOURNAL_XP_CAP   = 25;  // 25 XP per journal reflection, max 1x/day
    public const DAILY_SUBTASK_XP_CAP   = 25;  // 5 XP per subtask, max 5 subtasks/day
    public const DAILY_WORKOUT_XP_CAP   = 100; // Max 100 XP for standalone workout/day
    public const HIDDEN_MISSION_OVERACHIEVER_XP = 100; // +100 Bonus EXP for Overachiever Hidden Mission

    /**
     * Get user's today date string based on their configured timezone.
     */
    public static function getUserToday(User $user): string
    {
        $userTz = $user->timezone ?? config('app.timezone', 'Asia/Jakarta');
        return Carbon::today($userTz)->toDateString();
    }

    /**
     * Award XP for flashcard review sessions with daily cap.
     */
    public static function awardFlashcardXp(User $user, int $cardsReviewed = 1): int
    {
        if ($cardsReviewed <= 0) return 0;

        $today = self::getUserToday($user);
        $cacheKey = "user_{$user->id}_flashcard_xp_{$today}";
        $todayEarned = (int) Cache::get($cacheKey, 0);

        $remainingCap = max(0, self::DAILY_FLASHCARD_XP_CAP - $todayEarned);
        if ($remainingCap <= 0) return 0;

        $xpAttempt = $cardsReviewed * 5;
        $awarded = min($xpAttempt, $remainingCap);

        if ($awarded > 0) {
            Cache::put($cacheKey, $todayEarned + $awarded, now()->addDays(2));
            $user->addPendingXp($awarded, 'flashcard', "Flashcard: {$cardsReviewed} kartu dipelajari");
        }

        return $awarded;
    }

    /**
     * Award XP for writing a thoughtful journal entry (>= 50 chars).
     */
    public static function awardJournalXp(User $user, Journal $journal): int
    {
        $content = strip_tags($journal->content ?? '');
        if (mb_strlen(trim($content)) < 50) {
            return 0;
        }

        $today = self::getUserToday($user);
        $cacheKey = "user_{$user->id}_journal_xp_{$today}";
        $todayEarned = (int) Cache::get($cacheKey, 0);

        if ($todayEarned >= self::DAILY_JOURNAL_XP_CAP) {
            return 0;
        }

        $awarded = self::DAILY_JOURNAL_XP_CAP;
        Cache::put($cacheKey, $awarded, now()->addDays(2));
        $user->addPendingXp($awarded, 'journal', 'Jurnal: Refleksi harian ditulis');

        return $awarded;
    }

    /**
     * Award XP for completing an individual subtask.
     * Protected against done/undone spamming: subtask tracks earned_xp and xp_settled_at.
     */
    public static function awardSubTaskXp(User $user, SubTask $subTask): int
    {
        // 1. Anti-exploit: Cannot re-earn XP if already settled at midnight
        if ($subTask->xp_settled_at !== null) {
            return 0;
        }

        // 2. Anti-exploit: Cannot re-earn XP if this specific subtask already earned XP
        if ((int)$subTask->earned_xp > 0) {
            return 0;
        }

        $today = self::getUserToday($user);
        $cacheKey = "user_{$user->id}_subtask_xp_{$today}";
        $todayEarned = (int) Cache::get($cacheKey, 0);

        $remainingCap = max(0, self::DAILY_SUBTASK_XP_CAP - $todayEarned);
        if ($remainingCap <= 0) return 0;

        $awarded = min(5, $remainingCap);
        if ($awarded > 0) {
            $subTask->earned_xp = $awarded;
            $subTask->save();

            Cache::put($cacheKey, $todayEarned + $awarded, now()->addDays(2));
            $user->addPendingXp($awarded, 'subtask', "Subtugas: {$subTask->title}", null, $subTask->id);
        }

        return $awarded;
    }

    /**
     * Revert awarded subtask XP when unchecking or deleting a completed subtask.
     */
    public static function revertSubTaskXp(User $user, SubTask $subTask): void
    {
        // Cannot revert after midnight settlement (XP is locked permanently)
        if ($subTask->xp_settled_at !== null) {
            return;
        }

        $earned = (int)$subTask->earned_xp;
        if ($earned > 0) {
            $today = self::getUserToday($user);
            $cacheKey = "user_{$user->id}_subtask_xp_{$today}";
            $todayEarned = (int) Cache::get($cacheKey, 0);

            Cache::put($cacheKey, max(0, $todayEarned - $earned), now()->addDays(2));
            $user->subtractPendingXp($earned, 'subtask', $subTask->id);

            $subTask->earned_xp = 0;
            $subTask->save();
        }
    }

    /**
     * Award XP for standalone workout session (not attached to a task).
     */
    public static function awardWorkoutXp(User $user, int $calculatedXp): int
    {
        $today = self::getUserToday($user);
        $cacheKey = "user_{$user->id}_workout_xp_{$today}";
        $todayEarned = (int) Cache::get($cacheKey, 0);

        $remainingCap = max(0, self::DAILY_WORKOUT_XP_CAP - $todayEarned);
        if ($remainingCap <= 0) return 0;

        $awarded = min($calculatedXp, $remainingCap);
        if ($awarded > 0) {
            Cache::put($cacheKey, $todayEarned + $awarded, now()->addDays(2));
            $user->addPendingXp($awarded, 'workout', 'Latihan Fisik: Sesi workout mandiri');
        }

        return $awarded;
    }

    /**
     * 10 Secret Combinations for the Dynamic Daily Randomized Hidden Mission.
     */
    public const HIDDEN_MISSIONS_POOL = [
        [
            'id' => 'deep_work_titan',
            'name' => 'Deep Work Titan',
            'icon' => '⚡',
            'criteria' => ['task', 'pomodoro'],
            'criteria_text' => '250 Task EXP & 1 Jam Fokus Pomodoro',
            'description' => 'Mencapai target 250 Task EXP dan 60+ menit sesi Pomodoro dalam satu hari.',
        ],
        [
            'id' => 'scholar_of_atoma',
            'name' => 'Scholar of Atoma',
            'icon' => '🧠',
            'criteria' => ['task', 'flashcard'],
            'criteria_text' => '250 Task EXP & 10 Flashcard Review',
            'description' => 'Mencapai target 250 Task EXP dan menuntaskan 10 kartu flashcard.',
        ],
        [
            'id' => 'philosophers_focus',
            'name' => "Philosopher's Focus",
            'icon' => '📜',
            'criteria' => ['pomodoro', 'journal'],
            'criteria_text' => '1 Jam Pomodoro Fokus & 1 Catatan Jurnal',
            'description' => 'Fokus 60+ menit Pomodoro dan menuangkan refleksi harian di jurnal.',
        ],
        [
            'id' => 'master_of_details',
            'name' => 'Master of Details',
            'icon' => '🧩',
            'criteria' => ['task', 'subtask'],
            'criteria_text' => '250 Task EXP & 5 Subtask Selesai',
            'description' => 'Mencapai target 250 Task EXP dan memecah 5 subtask hingga tuntas.',
        ],
        [
            'id' => 'hyper_focus_memory',
            'name' => 'Hyper-Focus Memory',
            'icon' => '🎯',
            'criteria' => ['pomodoro', 'flashcard'],
            'criteria_text' => '1 Jam Pomodoro Fokus & 10 Flashcard Review',
            'description' => 'Fokus 60+ menit Pomodoro dan menuntaskan 10 kartu flashcard.',
        ],
        [
            'id' => 'mindful_architect',
            'name' => 'Mindful Architect',
            'icon' => '🏛️',
            'criteria' => ['subtask', 'journal'],
            'criteria_text' => '5 Subtask Selesai & 1 Catatan Jurnal',
            'description' => 'Menuntaskan 5 subtask terperinci dan menuliskan refleksi di jurnal.',
        ],
        [
            'id' => 'rapid_learner',
            'name' => 'Rapid Learner',
            'icon' => '🚀',
            'criteria' => ['flashcard', 'subtask'],
            'criteria_text' => '10 Flashcard Review & 5 Subtask Selesai',
            'description' => 'Mereview 10 kartu flashcard aktif dan menyelesaikan 5 subtask.',
        ],
        [
            'id' => 'reflective_achiever',
            'name' => 'Reflective Achiever',
            'icon' => '🌟',
            'criteria' => ['task', 'journal'],
            'criteria_text' => '250 Task EXP & 1 Catatan Jurnal',
            'description' => 'Mencapai target 250 Task EXP dan menuliskan refleksi harian di jurnal.',
        ],
        [
            'id' => 'zen_master',
            'name' => 'Zen Master',
            'icon' => '🧘',
            'criteria' => ['pomodoro', 'subtask'],
            'criteria_text' => '1 Jam Pomodoro Fokus & 5 Subtask Selesai',
            'description' => 'Fokus 60+ menit Pomodoro sambil menuntaskan 5 subtask.',
        ],
        [
            'id' => 'memory_sage',
            'name' => 'Memory Sage',
            'icon' => '🔮',
            'criteria' => ['flashcard', 'journal'],
            'criteria_text' => '10 Flashcard Review & 1 Catatan Jurnal',
            'description' => 'Menuntaskan 10 kartu flashcard dan mengabadikan pembelajaran di jurnal.',
        ],
    ];

    /**
     * Get today's secret hidden mission for a specific user.
     * Deterministically randomized per day & user.
     */
    public static function getDailyHiddenMission(User $user): array
    {
        $today = self::getUserToday($user);
        $seed = sprintf('%u', crc32("atoma_mystery_mission_{$user->id}_{$today}"));
        $index = $seed % count(self::HIDDEN_MISSIONS_POOL);
        $mission = self::HIDDEN_MISSIONS_POOL[$index];

        $claimedKey = "user_{$user->id}_hidden_mission_{$today}";
        $isUnlocked = Cache::has($claimedKey);

        // Evaluate criteria progress
        $statusCriteria = [];
        foreach ($mission['criteria'] as $crit) {
            $statusCriteria[$crit] = self::evaluateSingleCriterion($user, $crit, $today);
        }

        $allMet = !in_array(false, array_column($statusCriteria, 'met'), true);

        return array_merge($mission, [
            'today' => $today,
            'is_unlocked' => $isUnlocked,
            'all_criteria_met' => $allMet,
            'criteria_status' => $statusCriteria,
        ]);
    }

    /**
     * Helper to evaluate whether a single gamification criterion is met today.
     */
    private static function evaluateSingleCriterion(User $user, string $criterion, string $today): array
    {
        switch ($criterion) {
            case 'task':
                $current = ($user->last_xp_date === $today) ? (int)$user->daily_xp_earned : 0;
                $target = \App\Services\TaskXpService::DAILY_TASK_XP_CAP;
                return [
                    'label' => 'Task Harian',
                    'current' => $current,
                    'target' => $target,
                    'unit' => 'EXP',
                    'met' => $current >= $target,
                ];

            case 'pomodoro':
                $minutes = (int) FocusSession::where('user_id', $user->id)
                    ->whereDate('created_at', $today)
                    ->whereIn('mode', ['work', 'flowtime'])
                    ->sum('minutes');
                return [
                    'label' => 'Fokus Pomodoro',
                    'current' => $minutes,
                    'target' => 60,
                    'unit' => 'Menit',
                    'met' => $minutes >= 60,
                ];

            case 'flashcard':
                $xp = (int) Cache::get("user_{$user->id}_flashcard_xp_{$today}", 0);
                $cards = (int) round($xp / 5);
                return [
                    'label' => 'Review Flashcard',
                    'current' => $cards,
                    'target' => 10,
                    'unit' => 'Kartu',
                    'met' => $xp >= self::DAILY_FLASHCARD_XP_CAP,
                ];

            case 'journal':
                $xp = (int) Cache::get("user_{$user->id}_journal_xp_{$today}", 0);
                $met = $xp >= self::DAILY_JOURNAL_XP_CAP || Journal::where('user_id', $user->id)->whereDate('created_at', $today)->exists();
                return [
                    'label' => 'Refleksi Jurnal',
                    'current' => $met ? 1 : 0,
                    'target' => 1,
                    'unit' => 'Catatan',
                    'met' => $met,
                ];

            case 'subtask':
                $xp = (int) Cache::get("user_{$user->id}_subtask_xp_{$today}", 0);
                $count = (int) round($xp / 5);
                return [
                    'label' => 'Subtask Selesai',
                    'current' => $count,
                    'target' => 5,
                    'unit' => 'Subtask',
                    'met' => $xp >= self::DAILY_SUBTASK_XP_CAP,
                ];

            default:
                return ['label' => $criterion, 'current' => 0, 'target' => 1, 'unit' => '', 'met' => false];
        }
    }

    /**
     * Check and award the Dynamic Daily Randomized Hidden Mission (+100 EXP bonus).
     */
    public static function checkDailyHiddenMission(User $user): ?array
    {
        $today = self::getUserToday($user);
        $cacheKey = "user_{$user->id}_hidden_mission_{$today}";

        if (Cache::has($cacheKey)) {
            return null;
        }

        $missionInfo = self::getDailyHiddenMission($user);

        if (!$missionInfo['all_criteria_met']) {
            return null;
        }

        // Both secret criteria satisfied!
        Cache::put($cacheKey, true, now()->addDays(2));
        $bonusXp = self::HIDDEN_MISSION_OVERACHIEVER_XP;
        $user->addPendingXp($bonusXp, 'hidden_mission', "Misi Rahasia: {$missionInfo['name']}", $missionInfo['description'] ?? null);

        return [
            'unlocked' => true,
            'bonus_xp' => $bonusXp,
            'mission_id' => $missionInfo['id'],
            'mission_name' => $missionInfo['name'],
            'criteria_text' => $missionInfo['criteria_text'],
            'description' => $missionInfo['description'],
            'message' => "⚡ MISI RAHASIA TERBUKA: {$missionInfo['name']}! (+{$bonusXp} EXP Bonus atas dedikasi menyelesaikan {$missionInfo['criteria_text']} hari ini!)",
        ];
    }

    /**
     * Backward-compatible alias for checkDailyHiddenMission.
     */
    public static function checkOverachieverHiddenMission(User $user): ?array
    {
        return self::checkDailyHiddenMission($user);
    }

    /**
     * Comprehensive Daily EXP breakdown across all 5 categories + Total Routine + Hidden Mission.
     */
    public static function getDailyExpBreakdown(User $user): array
    {
        $today = self::getUserToday($user);

        // 1. Task
        $taskXp = ($user->last_xp_date === $today) ? (int)$user->daily_xp_earned : 0;
        $taskCap = \App\Services\TaskXpService::DAILY_TASK_XP_CAP;

        // 2. Pomodoro
        $pomodoroXp = (int) FocusSession::where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->sum('xp_earned');
        $pomodoroMinutes = (int) FocusSession::where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->whereIn('mode', ['work', 'flowtime'])
            ->sum('minutes');
        $pomodoroCap = 150;

        // 3. Flashcard
        $flashcardXp = (int) Cache::get("user_{$user->id}_flashcard_xp_{$today}", 0);
        $flashcardCap = self::DAILY_FLASHCARD_XP_CAP;

        // 4. Journal
        $journalXp = (int) Cache::get("user_{$user->id}_journal_xp_{$today}", 0);
        $journalCap = self::DAILY_JOURNAL_XP_CAP;

        // 5. Subtask
        $subtaskXp = (int) Cache::get("user_{$user->id}_subtask_xp_{$today}", 0);
        $subtaskCap = self::DAILY_SUBTASK_XP_CAP;

        $totalRoutine = min(500, $taskXp + min($pomodoroCap, $pomodoroXp) + $flashcardXp + $journalXp + $subtaskXp);

        return [
            'today' => $today,
            'task' => [
                'current' => $taskXp,
                'cap' => $taskCap,
                'percentage' => $taskCap > 0 ? min(100, (int) round(($taskXp / $taskCap) * 100)) : 0,
            ],
            'pomodoro' => [
                'current' => min($pomodoroCap, $pomodoroXp),
                'minutes' => $pomodoroMinutes,
                'cap' => $pomodoroCap,
                'percentage' => $pomodoroCap > 0 ? min(100, (int) round((min($pomodoroCap, $pomodoroXp) / $pomodoroCap) * 100)) : 0,
            ],
            'flashcard' => [
                'current' => $flashcardXp,
                'cards' => (int) round($flashcardXp / 5),
                'cap' => $flashcardCap,
                'percentage' => $flashcardCap > 0 ? min(100, (int) round(($flashcardXp / $flashcardCap) * 100)) : 0,
            ],
            'journal' => [
                'current' => $journalXp,
                'cap' => $journalCap,
                'percentage' => $journalCap > 0 ? min(100, (int) round(($journalXp / $journalCap) * 100)) : 0,
            ],
            'subtask' => [
                'current' => $subtaskXp,
                'count' => (int) round($subtaskXp / 5),
                'cap' => $subtaskCap,
                'percentage' => $subtaskCap > 0 ? min(100, (int) round(($subtaskXp / $subtaskCap) * 100)) : 0,
            ],
            'total_routine' => [
                'current' => $totalRoutine,
                'cap' => 500,
                'percentage' => min(100, (int) round(($totalRoutine / 500) * 100)),
            ],
            'hidden_mission' => self::getDailyHiddenMission($user),
        ];
    }
}

