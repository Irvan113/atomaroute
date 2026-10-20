<?php

namespace App\Services;

use App\Models\User;
use App\Models\StreakHistory;
use Carbon\Carbon;

class StreakEvaluatorService
{
    /**
     * Automatic Streak Inspector & Evaluator.
     * Evaluates missed days, applies streak protection coupons if available,
     * or resets streak to 0 if missed without coupons.
     */
    public static function evaluate(User $user): User
    {
        $userTz = $user->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::today($userTz);
        
        // Ensure completed tasks are synchronized into StreakHistory
        static::syncTasksToStreakHistory($user);

        // Find last completed or frozen record
        $lastHistory = StreakHistory::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'frozen'])
            ->orderBy('date', 'desc')
            ->first();

        if ($lastHistory) {
            $lastDate = Carbon::parse($lastHistory->date)->startOfDay();
            
            // Calculate gap days between last completed/frozen date and today
            $diff = (int)$lastDate->diffInDays($today);

            if ($diff > 1) {
                $daysMissed = (int)($diff - 1);

                if ($user->streak_coupons >= $daysMissed) {
                    // Freeze missed days using available coupons
                    for ($i = 1; $i <= $daysMissed; $i++) {
                        $missedDate = $lastDate->copy()->addDays($i);
                        StreakHistory::firstOrCreate(
                            [
                                'user_id' => $user->id,
                                'date' => $missedDate->toDateString()
                            ],
                            [
                                'status' => 'frozen'
                            ]
                        );
                    }
                    $user->streak_coupons -= $daysMissed;
                    $user->save();
                } else {
                    // Check Secret Protection condition:
                    // 1. Exactly 1 missed day
                    // 2. User has 0 coupons and insufficient coins (< 500)
                    // 3. Last recorded day was completed by user (NOT after another coupon/freeze)
                    // 4. Max 1x per month (30 days cooldown)
                    $canUseSecretProtection = false;
                    if ($daysMissed === 1 
                        && $user->streak_coupons <= 0 
                        && (int)($user->coins ?? 0) < 500
                        && $lastHistory->status === 'completed'
                    ) {
                        $lastSecretDate = $user->last_secret_protection_at 
                            ? Carbon::parse($user->last_secret_protection_at)->startOfDay() 
                            : null;
                        
                        if (!$lastSecretDate || $lastSecretDate->diffInDays($today) >= 30) {
                            $canUseSecretProtection = true;
                        }
                    }

                    if ($canUseSecretProtection) {
                        // Activate Secret Protection for the single missed day
                        $missedDate = $lastDate->copy()->addDays(1);
                        StreakHistory::firstOrCreate(
                            [
                                'user_id' => $user->id,
                                'date' => $missedDate->toDateString()
                            ],
                            [
                                'status' => 'frozen'
                            ]
                        );

                        $user->last_secret_protection_at = $today->toDateString();
                        $user->save();

                        session()->flash('secret_protection_activated', true);
                    } else {
                        // Not eligible -> reset streak to 0
                        $user->current_streak = 0;
                        $user->save();
                    }
                }
            }
        } else {
            $hasAnyActive = StreakHistory::where('user_id', $user->id)
                ->whereIn('status', ['completed', 'frozen'])
                ->exists();

            if (!$hasAnyActive && $user->current_streak !== 0) {
                $user->current_streak = 0;
                $user->save();
            }
        }

        // Calculate and sync streak numbers
        $calculatedStreak = static::calculateStreak($user);
        if ($calculatedStreak > $user->current_streak) {
            $user->current_streak = $calculatedStreak;
        }

        if ($user->current_streak > ($user->longest_streak ?? 0)) {
            $user->longest_streak = $user->current_streak;
        }

        $lastCompleted = StreakHistory::where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('date', 'desc')
            ->first();

        if ($lastCompleted) {
            $user->last_task_completed_date = Carbon::parse($lastCompleted->date)->toDateString();
        }

        $user->save();

        return $user;
    }

    /**
     * Recalculates the user's active streak based on their daily history.
     * Guaranteed single source of truth without drift or double-decrement.
     */
    public static function recalculateStreak(User $user): User
    {
        $calculatedStreak = static::calculateStreak($user);
        $user->current_streak = $calculatedStreak;

        if ($user->current_streak > ($user->longest_streak ?? 0)) {
            $user->longest_streak = $user->current_streak;
        }

        $lastCompleted = StreakHistory::where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('date', 'desc')
            ->first();

        if ($lastCompleted) {
            $user->last_task_completed_date = Carbon::parse($lastCompleted->date)->toDateString();
        }

        $user->save();

        return $user;
    }

    /**
     * Calculates the length of unbroken daily streak ending today (if active today)
     * or ending yesterday (if today has no completed task yet).
     * Every day with 'completed' OR 'frozen' status counts as +1 towards streak.
     */
    public static function calculateStreak(User $user): int
    {
        $userTz = $user->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::today($userTz);

        // 1. Fetch all active dates from StreakHistory (both completed and frozen)
        $historyDates = StreakHistory::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'frozen'])
            ->get()
            ->map(fn($h) => Carbon::parse($h->date)->toDateString())
            ->unique()
            ->flip()
            ->toArray();

        // 2. Fetch all dates with completed tasks to guarantee detection
        // Anti-manja: Overdue tasks completed on a later day must NOT count towards the past day's streak!
        $taskDates = $user->tasks()
            ->where('is_completed', true)
            ->whereNotNull('date')
            ->get()
            ->filter(function ($t) {
                if ($t->updated_at && $t->updated_at->startOfDay()->isAfter(Carbon::parse($t->date)->startOfDay())) {
                    return false;
                }
                return true;
            })
            ->map(fn($t) => Carbon::parse($t->date)->toDateString())
            ->unique()
            ->flip()
            ->toArray();

        $activeDates = array_merge($historyDates, $taskDates);

        if (empty($activeDates)) {
            return 0;
        }

        $streak = 0;
        $todayStr = $today->toDateString();

        if (isset($activeDates[$todayStr])) {
            // Today is completed: count backwards starting from today
            $checkDate = $today->copy();
            while (isset($activeDates[$checkDate->toDateString()])) {
                $streak++;
                $checkDate->subDay();
            }
        } else {
            // Today is not completed yet: streak is preserved from yesterday pending today
            $checkDate = $today->copy()->subDay();
            while (isset($activeDates[$checkDate->toDateString()])) {
                $streak++;
                $checkDate->subDay();
            }
        }

        return $streak;
    }

    /**
     * Synchronizes completed tasks into StreakHistory records.
     */
    public static function syncTasksToStreakHistory(User $user): void
    {
        $completedTasks = $user->tasks()
            ->where('is_completed', true)
            ->whereNotNull('date')
            ->get();

        foreach ($completedTasks as $task) {
            $taskDate = Carbon::parse($task->date)->startOfDay();

            // Anti-manja: If a task was completed after its scheduled date (overdue completion),
            // it must NEVER credit or revive streak history for that past day!
            if ($task->updated_at && $task->updated_at->startOfDay()->isAfter($taskDate)) {
                continue;
            }

            $taskDateStr = $taskDate->toDateString();
            
            $history = StreakHistory::where('user_id', $user->id)
                ->whereDate('date', $taskDateStr)
                ->first();

            if (!$history) {
                StreakHistory::create([
                    'user_id' => $user->id,
                    'date' => $taskDateStr,
                    'status' => 'completed',
                ]);
            } elseif ($history->status === 'frozen') {
                $history->status = 'completed';
                $history->save();
            }
        }
    }
}
