<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use App\Services\PrestigeService;

class TaskXpService
{
    const DAILY_TASK_XP_CAP = 250; // Maximum XP obtainable from standard tasks in a day

    /**
     * Calculate and evaluate XP for a completed task with anti-exploit heuristics.
     *
     * @param Task $task
     * @param User $user
     * @return array ['xp' => int, 'message' => string, 'reason' => string, 'type' => string]
     */
    public static function evaluateTaskCompletion(Task $task, User $user): array
    {
        $userTz = $user->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::today($userTz)->toDateString();

        // 0. Rule: Prevent re-earning XP on already settled tasks
        if ($task->xp_settled_at !== null) {
            return [
                'xp' => 0,
                'message' => 'Tugas selesai! (EXP tugas ini sudah pernah dicairkan)',
                'reason' => 'already_settled',
                'type' => 'info',
            ];
        }

        // 1. Reset daily XP if new day
        if ($user->last_xp_date !== $today) {
            $user->daily_xp_earned = 0;
            $user->last_xp_date = $today;
        }

        // 2. Check Daily XP Cap (250 EXP)
        $remainingDailyCap = max(0, self::DAILY_TASK_XP_CAP - (int)$user->daily_xp_earned);
        if ($remainingDailyCap <= 0) {
            return [
                'xp' => 0,
                'message' => 'Tugas selesai! (Batas 250 EXP harian tercapai, istirahatlah!)',
                'reason' => 'daily_cap_reached',
                'type' => 'info',
            ];
        }

        // 3. Rule: Minimum Title Length Validation (Anti-Spam / Anti-Garbage)
        $cleanTitle = trim($task->title ?? '');
        if (mb_strlen($cleanTitle) < 4) {
            return [
                'xp' => 0,
                'message' => 'Tugas selesai! (Judul < 4 karakter tidak memicu EXP)',
                'reason' => 'title_too_short',
                'type' => 'warning',
            ];
        }

        // 4. Rule: Duplicate Detection
        // Anti-exploit: prevents spamming the same task multiple times on the same day.
        // Recurring tasks or tasks scheduled for different dates (e.g. Day+1, today vs yesterday)
        // are legitimate routines/planning and MUST NOT be penalized as duplicates.
        $taskDateStr = $task->date ? Carbon::parse($task->date)->toDateString() : Carbon::today($userTz)->toDateString();

        if ($task->is_recurring) {
            // For recurring tasks: Only duplicate if another task with the same title was ALREADY completed for the EXACT SAME scheduled date.
            $hasDuplicate = Task::withTrashed()
                ->where('user_id', $user->id)
                ->where('id', '!=', $task->id)
                ->where('is_completed', true)
                ->where('title', $task->title)
                ->whereDate('date', $taskDateStr)
                ->exists();
        } else {
            // For standard tasks:
            // If the task has an explicit scheduled date, only duplicate if completed on the same scheduled date.
            // If no date was assigned, check if an identical task was completed within the last 24 hours.
            if ($task->date) {
                $hasDuplicate = Task::withTrashed()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $task->id)
                    ->where('is_completed', true)
                    ->where('title', $task->title)
                    ->whereDate('date', $taskDateStr)
                    ->exists();
            } else {
                $hasDuplicate = Task::withTrashed()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $task->id)
                    ->where('is_completed', true)
                    ->where('title', $task->title)
                    ->where('updated_at', '>=', now()->subHours(24))
                    ->exists();
            }
        }

        if ($hasDuplicate) {
            return [
                'xp' => 0,
                'message' => 'Tugas selesai! (Duplikat task pada hari yang sama tidak memicu EXP)',
                'reason' => 'duplicate_spam',
                'type' => 'warning',
            ];
        }

        // Detect overdue: task was scheduled before today OR today's deadline has passed
        $isOverdue = (bool)$task->is_completed_overdue || $task->isOverdue();

        if ($isOverdue) {
            // OVERDUE RULE: 0 EXP. Pure checklist record without streak or XP rewards.
            $task->earned_xp = 0;
            $task->save();

            return [
                'xp' => 0,
                'message' => 'Tugas selesai lewat deadline (0 EXP & tidak menambah streak)',
                'reason' => 'overdue_zero_xp',
                'type' => 'warning',
            ];
        }

        // 5. Count today's completed tasks (excluding this one) for Diminishing Returns
        $effectiveDateStr = $taskDateStr;

        $completedTodayCount = Task::withTrashed()
            ->where('user_id', $user->id)
            ->where('id', '!=', $task->id)
            ->where('is_completed', true)
            ->where(function ($q) use ($effectiveDateStr) {
                $q->whereDate('date', $effectiveDateStr)
                  ->orWhere(function ($sq) use ($effectiveDateStr) {
                      $sq->whereNull('date')->whereDate('created_at', $effectiveDateStr);
                  });
            })
            ->count();

        // Priority-based Base EXP:
        // High: 35 EXP
        // Medium: 30 EXP
        // Low / Default: 25 EXP
        $priority = strtolower($task->priority ?? 'low');
        $priorityBaseXp = match($priority) {
            'high' => 35,
            'medium' => 30,
            default => 25,
        };

        // Diminishing Returns Tier (Accommodates 8-10+ tasks to reach 250 EXP):
        // Task 1-5: 100% of priority base EXP (25 - 35 EXP)
        // Task 6-8: 60% of priority base EXP (15 - 21 EXP)
        // Task 9-12: 30% of priority base EXP (8 - 11 EXP)
        // Task 13+: 0 EXP
        $currentTaskNumber = $completedTodayCount + 1;
        if ($currentTaskNumber <= 5) {
            $baseXp = $priorityBaseXp;
        } elseif ($currentTaskNumber <= 8) {
            $baseXp = (int) round($priorityBaseXp * 0.6);
        } elseif ($currentTaskNumber <= 12) {
            $baseXp = (int) round($priorityBaseXp * 0.3);
        } else {
            return [
                'xp' => 0,
                'message' => 'Tugas selesai! (Target task harian tercapai, EXP diminishing 0)',
                'reason' => 'diminishing_tier_limit',
                'type' => 'info',
            ];
        }

        $notes = [];

        // 6. Rule: Anti-Instant / Task Age Delay (< 5 minutes from creation)
            // Recurring tasks are pre-scheduled routines/habits, exempt from anti-instant penalty.
            $isCreatedJustNow = !$task->is_recurring && $task->created_at && $task->created_at->diffInMinutes(now($userTz)) < 5;
            if ($isCreatedJustNow) {
                $baseXp = min($baseXp, 1);
                $notes[] = '🛡️ Anti-Instant (< 5 menit)';
            }

            // 7. Rule: Planning Bonus (+5 EXP if planned in advance / H-1)
            if (!$isCreatedJustNow && $task->created_at && $task->created_at->startOfDay()->lt(Carbon::today($userTz)) && $task->created_at->diffInMinutes(now($userTz)) >= 120) {
                $baseXp += 5;
                $notes[] = '✨ +5 Planning Bonus';
            }

            // 8. Rule: Pomodoro / Focus Session Bonus (+15 EXP per completed session)
            if ($task->pomodoro_sessions > 0) {
                $pomodoroBonus = min(30, (int)$task->pomodoro_sessions * 15);
                $baseXp += $pomodoroBonus;
                $notes[] = "🍅 +{$pomodoroBonus} Focus Bonus";
            }

            // 9. Progressive Overload — Quality Multiplier & Plain Task Reduction
            $qualityMultiplier = PrestigeService::getQualityMultiplier($user, $task);
            $plainFactor = PrestigeService::getPlainTaskFactor($user, $task);

            if ($qualityMultiplier > 1.0) {
                $baseXp = (int) round($baseXp * $qualityMultiplier);
                $bonusPercent = (int) round(($qualityMultiplier - 1.0) * 100);
                $notes[] = "⭐ +{$bonusPercent}% Quality Bonus";
            } elseif ($plainFactor < 1.0) {
                $baseXp = max(1, (int) round($baseXp * $plainFactor));
                $notes[] = "📝 Tambahkan subtask/fokus untuk EXP lebih besar";
            }

            // 10. Apply XP Multiplier (booster from store)
            if ($user->xp_multiplier && $user->xp_multiplier > 1.0 && $user->xp_multiplier_expires_at && $user->xp_multiplier_expires_at->isFuture()) {
                $baseXp = (int) round($baseXp * $user->xp_multiplier);
                $notes[] = "🚀 x{$user->xp_multiplier} Booster";
            }

        // 11. Apply remaining daily cap
        $finalXp = min($baseXp, $remainingDailyCap);

        // Apply to User as Pending XP (settled at midnight H+1)
        $prestigeResult = [
            'leveled_up' => false,
            'ascended' => false,
            'new_tier' => $user->prestige_tier ?? 1,
            'new_level' => $user->prestige_level ?? 1,
            'pending_xp' => (int)($user->pending_xp ?? 0) + $finalXp,
        ];

        if ($finalXp > 0) {
            $user->daily_xp_earned = (int)$user->daily_xp_earned + $finalXp;
            $user->addPendingXp($finalXp, 'task', "Tugas: {$task->title}", !empty($notes) ? implode(' • ', $notes) : null, $task->id);
            $user->total_tasks_completed = ((int)$user->total_tasks_completed) + 1;
            $user->save();

            $task->earned_xp = $finalXp;
            $task->save();
        }

        $noteString = !empty($notes) ? ' (' . implode(', ', $notes) . ')' : '';
        $msg = "✨ Tugas selesai! +{$finalXp} EXP{$noteString}";

        return [
            'xp' => $finalXp,
            'message' => $msg,
            'reason' => 'success',
            'type' => 'success',
            'prestige' => $prestigeResult,
        ];
    }

    /**
     * Subtract XP when a task is unchecked/uncompleted.
     */
    public static function revertTaskCompletion(Task $task, User $user): void
    {
        // If task was already settled at midnight, its XP is locked permanently.
        // We do NOT deduct from pending_xp (which would be 0 anyway) and we do NOT reset earned_xp to 0,
        // so that re-checking it cannot re-award XP.
        if ($task->xp_settled_at !== null) {
            return;
        }

        $earned = (int) ($task->earned_xp ?? 0);
        if ($earned > 0) {
            $user->daily_xp_earned = max(0, (int)$user->daily_xp_earned - $earned);
            $user->subtractPendingXp($earned, 'task', $task->id);
            $user->total_tasks_completed = max(0, ((int)$user->total_tasks_completed) - 1);
            $user->save();
            
            $task->earned_xp = 0;
            $task->save();
        }
    }
}
