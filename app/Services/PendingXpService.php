<?php

namespace App\Services;

use App\Models\User;
use App\Models\PendingXpLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PendingXpService
{
    /**
     * Source definitions with metadata for presentation.
     */
    const SOURCES = [
        'achievement' => [
            'label' => 'Pencapaian',
            'icon' => 'trophy',
            'color' => 'amber',
            'bg_light' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30',
            'bar_color' => 'bg-amber-500',
        ],
        'task' => [
            'label' => 'Tugas Utama',
            'icon' => 'check-circle',
            'color' => 'blue',
            'bg_light' => 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/30',
            'bar_color' => 'bg-blue-500',
        ],
        'subtask' => [
            'label' => 'Subtugas',
            'icon' => 'layers',
            'color' => 'teal',
            'bg_light' => 'bg-teal-500/10 text-teal-700 dark:text-teal-300 border-teal-500/30',
            'bar_color' => 'bg-teal-500',
        ],
        'pomodoro' => [
            'label' => 'Fokus Pomodoro',
            'icon' => 'clock',
            'color' => 'red',
            'bg_light' => 'bg-red-500/10 text-red-700 dark:text-red-300 border-red-500/30',
            'bar_color' => 'bg-red-500',
        ],
        'workout' => [
            'label' => 'Latihan Fisik',
            'icon' => 'activity',
            'color' => 'violet',
            'bg_light' => 'bg-violet-500/10 text-violet-700 dark:text-violet-300 border-violet-500/30',
            'bar_color' => 'bg-violet-500',
        ],
        'flashcard' => [
            'label' => 'Flashcards SRS',
            'icon' => 'cards',
            'color' => 'indigo',
            'bg_light' => 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-500/30',
            'bar_color' => 'bg-indigo-500',
        ],
        'journal' => [
            'label' => 'Jurnal Refleksi',
            'icon' => 'book-open',
            'color' => 'emerald',
            'bg_light' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
            'bar_color' => 'bg-emerald-500',
        ],
        'duel' => [
            'label' => 'Duel Bacok',
            'icon' => 'sword',
            'color' => 'rose',
            'bg_light' => 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/30',
            'bar_color' => 'bg-rose-500',
        ],
        'skill' => [
            'label' => 'Milestone Skill',
            'icon' => 'target',
            'color' => 'cyan',
            'bg_light' => 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border-cyan-500/30',
            'bar_color' => 'bg-cyan-500',
        ],
        'hidden_mission' => [
            'label' => 'Misi Rahasia',
            'icon' => 'sparkles',
            'color' => 'purple',
            'bg_light' => 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/30',
            'bar_color' => 'bg-purple-500',
        ],
        'other' => [
            'label' => 'Aktivitas Lain',
            'icon' => 'zap',
            'color' => 'gray',
            'bg_light' => 'bg-gray-500/10 text-gray-700 dark:text-gray-300 border-gray-500/30',
            'bar_color' => 'bg-gray-500',
        ],
    ];

    /**
     * Get detailed breakdown of pending XP for a user, reconciling any legacy or untracked XP.
     */
    public static function getBreakdown(User $user): array
    {
        self::reconcilePendingXp($user);

        $logs = PendingXpLog::where('user_id', $user->id)
            ->whereNull('settled_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalPending = (int)$user->pending_xp;
        $categoryBreakdown = [];

        foreach (self::SOURCES as $sourceKey => $meta) {
            $matchingLogs = $logs->where('source', $sourceKey);
            $sum = $matchingLogs->sum('amount');
            $count = $matchingLogs->count();

            if ($sum > 0) {
                $categoryBreakdown[$sourceKey] = [
                    'key' => $sourceKey,
                    'label' => $meta['label'],
                    'icon' => $meta['icon'],
                    'color' => $meta['color'],
                    'bg_light' => $meta['bg_light'],
                    'bar_color' => $meta['bar_color'],
                    'total' => $sum,
                    'count' => $count,
                    'percentage' => $totalPending > 0 ? min(100, (int)round(($sum / $totalPending) * 100)) : 0,
                ];
            }
        }

        // Sort categories by total descending
        uasort($categoryBreakdown, fn($a, $b) => $b['total'] <=> $a['total']);

        return [
            'total_pending' => $totalPending,
            'categories' => array_values($categoryBreakdown),
            'items' => $logs->map(function ($log) {
                $sourceMeta = self::SOURCES[$log->source] ?? self::SOURCES['other'];
                $cleanTitle = preg_replace('/^(Pencapaian|Tugas|Subtugas|Flashcard|Jurnal|Latihan Fisik|Misi Rahasia|Duel):\s*/i', '', $log->title);
                return [
                    'id' => $log->id,
                    'amount' => $log->amount,
                    'source' => $log->source,
                    'source_label' => $sourceMeta['label'],
                    'source_icon' => $sourceMeta['icon'],
                    'bg_light' => $sourceMeta['bg_light'],
                    'title' => $log->title,
                    'clean_title' => $cleanTitle ?: $log->title,
                    'description' => $log->description,
                    'time_formatted' => $log->created_at ? $log->created_at->format('H:i') : '-',
                    'date_formatted' => $log->created_at ? $log->created_at->translatedFormat('d M') : '-',
                    'human_time' => $log->created_at ? $log->created_at->diffForHumans() : '',
                ];
            })->all(),
            'count' => $logs->count(),
        ];
    }

    /**
     * Intelligently backfill and reconcile pending XP logs if user has pending XP
     * without corresponding log records (e.g. historical data or achievements unlocked earlier).
     */
    public static function reconcilePendingXp(User $user): void
    {
        $pending = (int)$user->pending_xp;
        if ($pending <= 0) {
            return;
        }

        $loggedSum = (int)PendingXpLog::where('user_id', $user->id)
            ->whereNull('settled_at')
            ->sum('amount');

        if ($loggedSum >= $pending) {
            return;
        }

        $needed = $pending - $loggedSum;

        // 1. Check Unlocked Achievements that might not have a pending log
        $userAchievements = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->get();

        $catalog = AchievementService::$catalog ?? [];

        foreach ($userAchievements as $ach) {
            if ($needed <= 0) {
                break;
            }

            $badge = $catalog[$ach->achievement_code] ?? null;
            if (!$badge || empty($badge['bonus_xp'])) {
                continue;
            }

            $bonusXp = (int)$badge['bonus_xp'];
            $badgeTitle = $badge['title'] ?? $badge['name'] ?? $ach->achievement_code;

            $alreadyLogged = PendingXpLog::where('user_id', $user->id)
                ->where('source', 'achievement')
                ->where('title', 'like', "%{$badgeTitle}%")
                ->exists();

            if (!$alreadyLogged) {
                $amountToLog = min($bonusXp, $needed);
                PendingXpLog::create([
                    'user_id' => $user->id,
                    'amount' => $amountToLog,
                    'source' => 'achievement',
                    'title' => "Pencapaian: {$badgeTitle}",
                    'description' => $badge['description'] ?? 'Bonus EXP dari pencapaian yang terbuka',
                    'settled_at' => null,
                    'created_at' => $ach->unlocked_at ? Carbon::parse($ach->unlocked_at) : now(),
                ]);
                $needed -= $amountToLog;
            }
        }

        // 2. Check Tasks with unsettled earned_xp
        if ($needed > 0) {
            $unsettledTasks = $user->tasks()
                ->where('is_completed', true)
                ->whereNull('xp_settled_at')
                ->where('earned_xp', '>', 0)
                ->orderBy('updated_at', 'desc')
                ->get();

            foreach ($unsettledTasks as $task) {
                if ($needed <= 0) {
                    break;
                }

                $alreadyLogged = PendingXpLog::where('user_id', $user->id)
                    ->where('source', 'task')
                    ->where('reference_id', $task->id)
                    ->exists();

                if (!$alreadyLogged) {
                    $amountToLog = min((int)$task->earned_xp, $needed);
                    PendingXpLog::create([
                        'user_id' => $user->id,
                        'amount' => $amountToLog,
                        'source' => 'task',
                        'title' => "Tugas: {$task->title}",
                        'description' => "EXP penyelesaian tugas harian",
                        'reference_id' => $task->id,
                        'settled_at' => null,
                        'created_at' => $task->updated_at ?? now(),
                    ]);
                    $needed -= $amountToLog;
                }
            }
        }

        // 3. Any remaining gap: log as general daily routine activities
        if ($needed > 0) {
            PendingXpLog::create([
                'user_id' => $user->id,
                'amount' => $needed,
                'source' => 'other',
                'title' => 'Aktivitas Harian Terakumulasi',
                'description' => 'Akumulasi EXP pending dari rutinitas dan pencapaian hari ini',
                'settled_at' => null,
                'created_at' => now(),
            ]);
        }
    }
}
