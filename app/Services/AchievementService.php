<?php

namespace App\Services;

use App\Models\User;
use App\Models\FocusSession;
use App\Models\Skill;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\DB;

class AchievementService
{
    public static array $catalog = [
        // --- 1. TUGAS & PRODUKTIVITAS ---
        'first_task' => [
            'code' => 'first_task',
            'title' => 'Langkah Perdana',
            'description' => 'Selesaikan task pertama kamu di ATOMA.',
            'category' => 'tasks',
            'category_name' => 'Tugas',
            'icon' => '🥇',
            'svg_path' => 'images/badges/achievements/achieve_task_first.svg',
            'bonus_xp' => 50,
            'bonus_coins' => 5,
            'target_value' => 1,
            'unit' => 'Tugas',
        ],
        'tasks_50' => [
            'code' => 'tasks_50',
            'title' => 'Task Specialist',
            'description' => 'Selesaikan total 50 task produktif.',
            'category' => 'tasks',
            'category_name' => 'Tugas',
            'icon' => '⚡',
            'svg_path' => 'images/badges/achievements/achieve_task_50.svg',
            'bonus_xp' => 200,
            'bonus_coins' => 25,
            'target_value' => 50,
            'unit' => 'Tugas',
        ],
        'tasks_100' => [
            'code' => 'tasks_100',
            'title' => 'Centurion Task',
            'description' => 'Tuntaskan total 100 task dengan penuh dedikasi.',
            'category' => 'tasks',
            'category_name' => 'Tugas',
            'icon' => '⚔️',
            'svg_path' => 'images/badges/achievements/achieve_task_100.svg',
            'bonus_xp' => 400,
            'bonus_coins' => 50,
            'target_value' => 100,
            'unit' => 'Tugas',
        ],

        // --- 2. STREAK & KONSISTENSI ---
        'streak_7' => [
            'code' => 'streak_7',
            'title' => 'Streak Titan I',
            'description' => 'Pertahankan streak produktivitas selama 7 hari berturut-turut.',
            'category' => 'streak',
            'category_name' => 'Streak',
            'icon' => '🔥',
            'svg_path' => 'images/badges/achievements/achieve_streak_7.svg',
            'bonus_xp' => 150,
            'bonus_coins' => 20,
            'target_value' => 7,
            'unit' => 'Hari',
        ],
        'streak_14' => [
            'code' => 'streak_14',
            'title' => 'Streak Titan II',
            'description' => 'Pertahankan streak produktivitas selama 14 hari berturut-turut.',
            'category' => 'streak',
            'category_name' => 'Streak',
            'icon' => '🔥',
            'svg_path' => 'images/badges/achievements/achieve_streak_14.svg',
            'bonus_xp' => 300,
            'bonus_coins' => 40,
            'target_value' => 14,
            'unit' => 'Hari',
        ],
        'streak_30' => [
            'code' => 'streak_30',
            'title' => 'Streak Titan III',
            'description' => 'Pertahankan streak produktivitas tanpa henti selama 30 hari.',
            'category' => 'streak',
            'category_name' => 'Streak',
            'icon' => '👑',
            'svg_path' => 'images/badges/achievements/achieve_streak_30.svg',
            'bonus_xp' => 600,
            'bonus_coins' => 80,
            'target_value' => 30,
            'unit' => 'Hari',
        ],
        'streak_100' => [
            'code' => 'streak_100',
            'title' => 'Legenda Konsistensi',
            'description' => 'Capai tonggak legendaris 100 hari streak produktivitas tanpa terputus.',
            'category' => 'streak',
            'category_name' => 'Streak',
            'icon' => '👑',
            'svg_path' => 'images/badges/achievements/achieve_streak_100.svg',
            'bonus_xp' => 1500,
            'bonus_coins' => 250,
            'target_value' => 100,
            'unit' => 'Hari',
        ],

        // --- 3. FOKUS & WAKTU ---
        'pomodoro_5h' => [
            'code' => 'pomodoro_5h',
            'title' => 'Deep Focus Inisiat',
            'description' => 'Akumulasikan total 5 jam (300 menit) sesi fokus Pomodoro.',
            'category' => 'focus',
            'category_name' => 'Fokus',
            'icon' => '🍅',
            'svg_path' => 'images/badges/achievements/achieve_focus_5h.svg',
            'bonus_xp' => 150,
            'bonus_coins' => 25,
            'target_value' => 300,
            'unit' => 'Menit',
        ],
        'flowtime_60m' => [
            'code' => 'flowtime_60m',
            'title' => 'Flowstate Voyager',
            'description' => 'Selesaikan sesi Flowtime deep work minimal 60 menit berturut-turut.',
            'category' => 'focus',
            'category_name' => 'Fokus',
            'icon' => '🌊',
            'svg_path' => 'images/badges/achievements/achieve_flowtime.svg',
            'bonus_xp' => 200,
            'bonus_coins' => 30,
            'target_value' => 60,
            'unit' => 'Menit',
        ],

        // --- 4. MILESTONE COMPLETED RATE > 80% ---
        'milestone_30d' => [
            'code' => 'milestone_30d',
            'title' => 'Master Milestone 30D',
            'description' => 'Tuntaskan target milestone 30 hari dengan completion rate > 80%.',
            'category' => 'milestone',
            'category_name' => 'Milestone',
            'icon' => '🎯',
            'svg_path' => 'images/badges/milestones/milestone_30d.svg',
            'bonus_xp' => 300,
            'bonus_coins' => 50,
            'target_value' => 80,
            'unit' => '% Rate',
        ],
        'milestone_90d' => [
            'code' => 'milestone_90d',
            'title' => 'Master Kuartal 90D',
            'description' => 'Tuntaskan target milestone 90 hari dengan completion rate > 80%.',
            'category' => 'milestone',
            'category_name' => 'Milestone',
            'icon' => '💎',
            'svg_path' => 'images/badges/milestones/milestone_90d.svg',
            'bonus_xp' => 600,
            'bonus_coins' => 100,
            'target_value' => 80,
            'unit' => '% Rate',
        ],
        'milestone_180d' => [
            'code' => 'milestone_180d',
            'title' => 'Ksatria Semester 180D',
            'description' => 'Tuntaskan target milestone 180 hari dengan completion rate > 80%.',
            'category' => 'milestone',
            'category_name' => 'Milestone',
            'icon' => '🏆',
            'svg_path' => 'images/badges/milestones/milestone_180d.svg',
            'bonus_xp' => 1000,
            'bonus_coins' => 200,
            'target_value' => 80,
            'unit' => '% Rate',
        ],
        'milestone_365d' => [
            'code' => 'milestone_365d',
            'title' => 'Legenda Tahunan 365D',
            'description' => 'Tuntaskan target milestone 365 hari penuh dengan completion rate > 80%.',
            'category' => 'milestone',
            'category_name' => 'Milestone',
            'icon' => '👑',
            'svg_path' => 'images/badges/milestones/milestone_365d.svg',
            'bonus_xp' => 2500,
            'bonus_coins' => 500,
            'target_value' => 80,
            'unit' => '% Rate',
        ],

        // --- 5. FITNESS & WORKOUT ---
        'workout_pr' => [
            'code' => 'workout_pr',
            'title' => 'Iron Will',
            'description' => 'Tuntaskan sesi latihan workout fisik dan catat rekor pribadimu.',
            'category' => 'workout',
            'category_name' => 'Workout',
            'icon' => '🏋️',
            'svg_path' => 'images/badges/achievements/achieve_workout_pr.svg',
            'bonus_xp' => 150,
            'bonus_coins' => 25,
            'target_value' => 1,
            'unit' => 'Sesi',
        ],

        // --- 6. REFLEKSI & HABIT ---
        'journals_10' => [
            'code' => 'journals_10',
            'title' => 'Pena Reflektif',
            'description' => 'Tulis total 10 catatan Jurnal Refleksi produktivitas.',
            'category' => 'reflection',
            'category_name' => 'Refleksi',
            'icon' => '✍️',
            'svg_path' => 'images/badges/achievements/achieve_reflective_pen.svg',
            'bonus_xp' => 100,
            'bonus_coins' => 15,
            'target_value' => 10,
            'unit' => 'Jurnal',
        ],
        'themes_3' => [
            'code' => 'themes_3',
            'title' => 'Kolektor Tema',
            'description' => 'Buka total 3 tema tampilan di Reward Store ATOMA.',
            'category' => 'reflection',
            'category_name' => 'Refleksi',
            'icon' => '🎨',
            'svg_path' => 'images/badges/achievements/achieve_theme_collector.svg',
            'bonus_xp' => 150,
            'bonus_coins' => 25,
            'target_value' => 3,
            'unit' => 'Tema',
        ],

        // --- 7. SOSIAL & DUEL ---
        'senggol_10' => [
            'code' => 'senggol_10',
            'title' => 'Senggol Spirit',
            'description' => 'Kirim total 10 kali senggolan semangat ke teman.',
            'category' => 'social',
            'category_name' => 'Sosial',
            'icon' => '👈',
            'svg_path' => 'images/badges/achievements/achieve_social_nudge.svg',
            'bonus_xp' => 100,
            'bonus_coins' => 15,
            'target_value' => 10,
            'unit' => 'Senggol',
        ],
        'kebal_bacok' => [
            'code' => 'kebal_bacok',
            'title' => 'Pantang Tumbang',
            'description' => 'Buktikan produktivitas dengan bereskan tugas setelah disenggol teman.',
            'category' => 'social',
            'category_name' => 'Sosial',
            'icon' => '🛡️',
            'svg_path' => 'images/badges/achievements/achieve_shield_defense.svg',
            'bonus_xp' => 100,
            'bonus_coins' => 15,
            'target_value' => 1,
            'unit' => 'Tugas',
        ],
        'raja_duel' => [
            'code' => 'raja_duel',
            'title' => 'Raja Duel',
            'description' => 'Menangkan total 3 duel produktivitas 24 jam melawan teman.',
            'category' => 'social',
            'category_name' => 'Sosial',
            'icon' => '⚔️',
            'svg_path' => 'images/badges/achievements/achieve_duel_king.svg',
            'bonus_xp' => 150,
            'bonus_coins' => 25,
            'target_value' => 3,
            'unit' => 'Duel',
        ],
    ];

    public static function getBadgeDetails(string $code): ?array
    {
        if (isset(self::$catalog[$code])) {
            $badge = self::$catalog[$code];
            $badge['title'] = __($badge['title']);
            $badge['description'] = __($badge['description']);
            $badge['category_name'] = __($badge['category_name']);
            if (isset($badge['unit'])) {
                $badge['unit'] = __($badge['unit']);
            }
            return $badge;
        }

        // Check if it's a league or prestige badge
        if (str_starts_with($code, 'league_') || str_starts_with($code, 'bronze_') || str_starts_with($code, 'silver_') || str_starts_with($code, 'gold_') || str_starts_with($code, 'diamond_') || $code === 'master') {
            $lKey = str_replace('league_', '', $code);
            $lFile = match(true) {
                str_starts_with($lKey, 'bronze') => 'league_bronze.svg',
                str_starts_with($lKey, 'silver') => 'league_silver.svg',
                str_starts_with($lKey, 'gold') => 'league_gold.svg',
                str_starts_with($lKey, 'diamond') => 'league_diamond.svg',
                default => 'league_master.svg',
            };
            return [
                'code' => $code,
                'title' => __('Liga') . ' ' . ucfirst(__($lKey)),
                'description' => __('Perisai kasta liga mingguan.'),
                'category' => 'league',
                'category_name' => __('Liga'),
                'icon' => '🏆',
                'svg_path' => "images/badges/league/{$lFile}",
            ];
        }

        if (str_starts_with($code, 'prestige_')) {
            $pTier = (int) str_replace('prestige_', '', $code);
            $pTier = max(1, min(5, $pTier));
            return [
                'code' => $code,
                'title' => 'Prestige Tier ' . $pTier,
                'description' => __('Monogram origami masteri karakter.'),
                'category' => 'prestige',
                'category_name' => 'Prestige',
                'icon' => '👑',
                'svg_path' => "images/badges/prestige/prestige_tier_{$pTier}.svg",
            ];
        }

        return null;
    }

    public function checkAndUnlock(User $user): array
    {
        $newlyUnlocked = [];
        $existing = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->pluck('achievement_code')
            ->toArray();

        // 1. Tasks: first_task, tasks_50, tasks_100
        $completedTasksCount = $user->tasks()->where('is_completed', true)->count();
        if (!in_array('first_task', $existing) && $completedTasksCount >= 1) {
            $this->unlock($user, 'first_task');
            $newlyUnlocked[] = self::$catalog['first_task'];
        }
        if (!in_array('tasks_50', $existing) && $completedTasksCount >= 50) {
            $this->unlock($user, 'tasks_50');
            $newlyUnlocked[] = self::$catalog['tasks_50'];
        }
        if (!in_array('tasks_100', $existing) && $completedTasksCount >= 100) {
            $this->unlock($user, 'tasks_100');
            $newlyUnlocked[] = self::$catalog['tasks_100'];
        }

        // 2. Streak: streak_7, streak_14, streak_30, streak_100
        $streak = (int) ($user->current_streak ?? 0);
        if (!in_array('streak_7', $existing) && $streak >= 7) {
            $this->unlock($user, 'streak_7');
            $newlyUnlocked[] = self::$catalog['streak_7'];
        }
        if (!in_array('streak_14', $existing) && $streak >= 14) {
            $this->unlock($user, 'streak_14');
            $newlyUnlocked[] = self::$catalog['streak_14'];
        }
        if (!in_array('streak_30', $existing) && $streak >= 30) {
            $this->unlock($user, 'streak_30');
            $newlyUnlocked[] = self::$catalog['streak_30'];
        }
        if (!in_array('streak_100', $existing) && $streak >= 100) {
            $this->unlock($user, 'streak_100');
            $newlyUnlocked[] = self::$catalog['streak_100'];
        }

        // 3. Focus & Waktu: pomodoro_5h, flowtime_60m
        $totalPomodoroMins = (int) $user->tasks()->sum('actual_minutes');
        if (!in_array('pomodoro_5h', $existing) && $totalPomodoroMins >= 300) {
            $this->unlock($user, 'pomodoro_5h');
            $newlyUnlocked[] = self::$catalog['pomodoro_5h'];
        }

        if (!in_array('flowtime_60m', $existing)) {
            $hasLongFlow = FocusSession::where('user_id', $user->id)
                ->where('mode', 'flowtime')
                ->where('minutes', '>=', 60)
                ->exists();
            if ($hasLongFlow) {
                $this->unlock($user, 'flowtime_60m');
                $newlyUnlocked[] = self::$catalog['flowtime_60m'];
            }
        }

        // 4. Milestone Completed Rate > 80% (30, 90, 180, 365)
        $skills = Skill::where('user_id', $user->id)->get();
        foreach ([30 => 'milestone_30d', 90 => 'milestone_90d', 180 => 'milestone_180d', 365 => 'milestone_365d'] as $days => $code) {
            if (!in_array($code, $existing)) {
                $qualified = $skills->first(function ($s) use ($days) {
                    return $s->target_days >= $days && $s->calculated_progress >= 80;
                });
                if ($qualified) {
                    $this->unlock($user, $code);
                    $newlyUnlocked[] = self::$catalog[$code];
                }
            }
        }

        // 5. Workout PR
        if (!in_array('workout_pr', $existing)) {
            $hasWorkout = WorkoutSession::where('user_id', $user->id)
                ->whereNotNull('completed_at')
                ->exists();
            if ($hasWorkout) {
                $this->unlock($user, 'workout_pr');
                $newlyUnlocked[] = self::$catalog['workout_pr'];
            }
        }

        // 6. Reflection: journals_10, themes_3
        if (!in_array('journals_10', $existing) && $user->journals()->count() >= 10) {
            $this->unlock($user, 'journals_10');
            $newlyUnlocked[] = self::$catalog['journals_10'];
        }
        if (!in_array('themes_3', $existing) && count($user->unlocked_themes ?? ['default']) >= 3) {
            $this->unlock($user, 'themes_3');
            $newlyUnlocked[] = self::$catalog['themes_3'];
        }

        // 7. Social: senggol_10, raja_duel
        if (!in_array('senggol_10', $existing)) {
            $sentNudges = DB::table('friend_nudges')->where('sender_id', $user->id)->count();
            if ($sentNudges >= 10) {
                $this->unlock($user, 'senggol_10');
                $newlyUnlocked[] = self::$catalog['senggol_10'];
            }
        }
        if (!in_array('raja_duel', $existing)) {
            $wonDuels = DB::table('friend_nudges')->where('winner_id', $user->id)->count();
            if ($wonDuels >= 3) {
                $this->unlock($user, 'raja_duel');
                $newlyUnlocked[] = self::$catalog['raja_duel'];
            }
        }

        return $newlyUnlocked;
    }

    public function unlock(User $user, string $code)
    {
        DB::table('user_achievements')->insertOrIgnore([
            'user_id' => $user->id,
            'achievement_code' => $code,
            'unlocked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $badge = self::$catalog[$code] ?? null;
        if ($badge) {
            if (isset($badge['bonus_xp']) && $badge['bonus_xp'] > 0) {
                $badgeTitle = $badge['title'] ?? $badge['name'] ?? 'Pencapaian';
                $user->addPendingXp($badge['bonus_xp'], 'achievement', "Pencapaian: {$badgeTitle}", $badge['description'] ?? 'Pencapaian berhasil dibuka');
            }
            if (isset($badge['bonus_coins']) && $badge['bonus_coins'] > 0) {
                $user->increment('coins', $badge['bonus_coins']);
            }
        }
    }

    public function getProgress(User $user): array
    {
        $existingRecords = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('achievement_code');

        $completedTasksCount = $user->tasks()->where('is_completed', true)->count();
        $streak = (int) ($user->current_streak ?? 0);
        $pomodoroMins = (int) $user->tasks()->sum('actual_minutes');
        $maxFlowtime = (int) (FocusSession::where('user_id', $user->id)->where('mode', 'flowtime')->max('minutes') ?? 0);
        $journalCount = $user->journals()->count();
        $themesCount = count($user->unlocked_themes ?? ['default']);
        $sentNudges = DB::table('friend_nudges')->where('sender_id', $user->id)->count();
        $wonDuels = DB::table('friend_nudges')->where('winner_id', $user->id)->count();
        $hasWorkout = WorkoutSession::where('user_id', $user->id)->whereNotNull('completed_at')->count();

        $skills = Skill::where('user_id', $user->id)->get();
        $maxRate30 = (int) ($skills->where('target_days', '>=', 30)->max(fn($s) => $s->calculated_progress) ?? 0);
        $maxRate90 = (int) ($skills->where('target_days', '>=', 90)->max(fn($s) => $s->calculated_progress) ?? 0);
        $maxRate180 = (int) ($skills->where('target_days', '>=', 180)->max(fn($s) => $s->calculated_progress) ?? 0);
        $maxRate365 = (int) ($skills->where('target_days', '>=', 365)->max(fn($s) => $s->calculated_progress) ?? 0);

        $results = [];
        foreach (self::$catalog as $code => $badge) {
            $isUnlocked = isset($existingRecords[$code]);
            $unlockedAt = $isUnlocked ? $existingRecords[$code]->unlocked_at : null;

            $currentVal = match($code) {
                'first_task', 'tasks_50', 'tasks_100' => $completedTasksCount,
                'streak_7', 'streak_14', 'streak_30', 'streak_100' => $streak,
                'pomodoro_5h' => $pomodoroMins,
                'flowtime_60m' => $maxFlowtime,
                'milestone_30d' => $maxRate30,
                'milestone_90d' => $maxRate90,
                'milestone_180d' => $maxRate180,
                'milestone_365d' => $maxRate365,
                'workout_pr' => $hasWorkout,
                'journals_10' => $journalCount,
                'themes_3' => $themesCount,
                'senggol_10' => $sentNudges,
                'kebal_bacok' => $isUnlocked ? 1 : 0,
                'raja_duel' => $wonDuels,
                default => 0,
            };

            $targetVal = $badge['target_value'] ?? 1;
            $percent = $isUnlocked ? 100 : min(100, (int) round(($currentVal / max(1, $targetVal)) * 100));

            $equippedBadges = $user->equipped_badges;
            if (!is_array($equippedBadges)) {
                $equippedBadges = !empty($user->equipped_badge) ? [$user->equipped_badge] : [];
            }
            $isEquipped = in_array($code, $equippedBadges);
            $slotIndex = $isEquipped ? (array_search($code, $equippedBadges) + 1) : null;

            $badge['unlocked'] = $isUnlocked;
            $badge['unlocked_at'] = $unlockedAt;
            $badge['current_value'] = $isUnlocked ? $targetVal : $currentVal;
            $badge['progress_percent'] = $percent;
            $badge['is_equipped'] = $isEquipped;
            $badge['equipped_slot'] = $slotIndex;
            $badge['title'] = __($badge['title']);
            $badge['description'] = __($badge['description']);
            $badge['category_name'] = __($badge['category_name']);
            if (isset($badge['unit'])) {
                $badge['unit'] = __($badge['unit']);
            }

            $results[] = $badge;
        }

        return $results;
    }
}
