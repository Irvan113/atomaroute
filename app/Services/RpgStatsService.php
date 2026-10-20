<?php

namespace App\Services;

use App\Models\User;
use App\Models\Task;
use App\Models\FocusSession;
use Carbon\Carbon;

class RpgStatsService
{
    /**
     * Calculate all 4 RPG stats for a user (each 0-100).
     */
    public static function calculateAllStats(User $user): array
    {
        $stats = [
            'focus' => self::calculateFocusStat($user),
            'consistency' => self::calculateConsistencyStat($user),
            'execution' => self::calculateExecutionStat($user),
            'organization' => self::calculateOrganizationStat($user),
        ];

        // Persist stats to user
        $user->stat_focus = $stats['focus'];
        $user->stat_consistency = $stats['consistency'];
        $user->stat_execution = $stats['execution'];
        $user->stat_organization = $stats['organization'];
        $user->save();

        return $stats;
    }

    /**
     * Focus Stat (0-100): Based on total Focus Timer / Pomodoro hours.
     *
     * Scale: 0 hrs = 0, 5 hrs = 20, 25 hrs = 50, 100 hrs = 80, 200+ hrs = 100
     */
    public static function calculateFocusStat(User $user): int
    {
        $totalMinutes = FocusSession::where('user_id', $user->id)
            ->where('mode', 'work')
            ->sum('minutes');

        $totalHours = $totalMinutes / 60;

        // Logarithmic scale: stat = min(100, 18.5 × ln(hours + 1))
        if ($totalHours <= 0) return 0;

        $stat = (int) round(18.5 * log($totalHours + 1));
        return min(100, max(0, $stat));
    }

    /**
     * Consistency Stat (0-100): Based on streak length and daily attendance.
     *
     * 50% from current streak, 50% from 30-day attendance rate.
     * Max streak contribution at 30+ days. Max attendance at 100%.
     */
    public static function calculateConsistencyStat(User $user): int
    {
        // Streak component (0-50): current_streak / 30 × 50, capped at 50
        $currentStreak = (int) ($user->current_streak ?? 0);
        $streakScore = min(50, (int) round(($currentStreak / 30) * 50));

        // Attendance component (0-50): unique active days in last 30 days
        $thirtyDaysAgo = Carbon::now()->subDays(30)->toDateString();
        $activeDays = Task::where('user_id', $user->id)
            ->where('is_completed', true)
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->selectRaw('COUNT(DISTINCT DATE(updated_at)) as days')
            ->value('days');

        $attendanceScore = min(50, (int) round(($activeDays / 30) * 50));

        return min(100, $streakScore + $attendanceScore);
    }

    /**
     * Execution Stat (0-100): Based on on-time task completion rate.
     *
     * Tasks completed on or before their scheduled date vs total completed tasks (last 60 days).
     */
    public static function calculateExecutionStat(User $user): int
    {
        $sixtyDaysAgo = Carbon::now()->subDays(60)->toDateString();

        $completedTasks = Task::where('user_id', $user->id)
            ->where('is_completed', true)
            ->where('updated_at', '>=', $sixtyDaysAgo)
            ->whereNotNull('date')
            ->get(['date', 'updated_at']);

        if ($completedTasks->isEmpty()) return 0;

        $onTimeCount = $completedTasks->filter(function ($task) {
            $dueDate = Carbon::parse($task->date)->endOfDay();
            $completedAt = Carbon::parse($task->updated_at);
            return $completedAt->lte($dueDate);
        })->count();

        $rate = $onTimeCount / $completedTasks->count();
        return min(100, (int) round($rate * 100));
    }

    /**
     * Organization Stat (0-100): Based on usage of subtasks, categories, projects, and time estimates.
     *
     * Measures % of tasks (last 60 days) that have:
     * - Subtasks (25 points)
     * - Category assigned (25 points)
     * - Priority set (25 points)
     * - Estimated minutes set (25 points)
     */
    public static function calculateOrganizationStat(User $user): int
    {
        $sixtyDaysAgo = Carbon::now()->subDays(60)->toDateString();

        $tasks = Task::where('user_id', $user->id)
            ->where('created_at', '>=', $sixtyDaysAgo)
            ->get();

        if ($tasks->isEmpty()) return 0;

        $total = $tasks->count();

        // Subtask usage: % of tasks that have at least 1 subtask
        $withSubtasks = $tasks->filter(fn($t) => $t->subTasks()->count() > 0)->count();
        $subtaskScore = ($withSubtasks / $total) * 25;

        // Category usage
        $withCategory = $tasks->filter(fn($t) => !empty($t->category))->count();
        $categoryScore = ($withCategory / $total) * 25;

        // Priority usage (tasks with non-default priority)
        $withPriority = $tasks->filter(fn($t) => !empty($t->priority) && $t->priority !== 'normal')->count();
        $priorityScore = ($withPriority / $total) * 25;

        // Estimated time usage
        $withEstimate = $tasks->filter(fn($t) => ($t->estimated_minutes ?? 0) > 0)->count();
        $estimateScore = ($withEstimate / $total) * 25;

        return min(100, (int) round($subtaskScore + $categoryScore + $priorityScore + $estimateScore));
    }

    /**
     * Get stat labels and icons for display.
     */
    public static function getStatMeta(): array
    {
        return [
            'focus' => ['label' => 'Focus', 'icon' => '🎯', 'description' => 'Kekuatan Fokus — dari jam menggunakan Focus Timer'],
            'consistency' => ['label' => 'Consistency', 'icon' => '🔥', 'description' => 'Ketahanan — dari panjang streak & kehadiran harian'],
            'execution' => ['label' => 'Execution', 'icon' => '⚡', 'description' => 'Ketepatan — menyelesaikan task tepat waktu'],
            'organization' => ['label' => 'Organization', 'icon' => '📋', 'description' => 'Kerapian — penggunaan subtask, kategori, & estimasi'],
        ];
    }
}
