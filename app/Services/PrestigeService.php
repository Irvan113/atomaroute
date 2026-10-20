<?php

namespace App\Services;

use App\Models\User;

class PrestigeService
{
    /**
     * Tier definitions: 5 tiers × 50 levels each.
     */
    public const TIERS = [
        1 => ['name' => 'Novice',       'icon' => '🌱', 'svg' => 'images/prestige/tier_1_novice.svg', 'color' => 'emerald',  'gradient' => 'from-emerald-500 to-emerald-600',  'border' => 'border-emerald-400',  'bg' => 'bg-emerald-50 dark:bg-emerald-950/40',  'text' => 'text-emerald-700 dark:text-emerald-300'],
        2 => ['name' => 'Practitioner', 'icon' => '⚔️',  'svg' => 'images/prestige/tier_2_practitioner.svg', 'color' => 'blue',     'gradient' => 'from-blue-500 to-blue-600',       'border' => 'border-blue-400',     'bg' => 'bg-blue-50 dark:bg-blue-950/40',        'text' => 'text-blue-700 dark:text-blue-300'],
        3 => ['name' => 'Master',       'icon' => '🔥', 'svg' => 'images/prestige/tier_3_master.svg', 'color' => 'amber',    'gradient' => 'from-amber-500 to-orange-600',     'border' => 'border-amber-400',    'bg' => 'bg-amber-50 dark:bg-amber-950/40',      'text' => 'text-amber-700 dark:text-amber-300'],
        4 => ['name' => 'Grandmaster',  'icon' => '💎', 'svg' => 'images/prestige/tier_4_grandmaster.svg', 'color' => 'purple',   'gradient' => 'from-purple-500 to-violet-600',    'border' => 'border-purple-400',   'bg' => 'bg-purple-50 dark:bg-purple-950/40',    'text' => 'text-purple-700 dark:text-purple-300'],
        5 => ['name' => 'Ascended',     'icon' => '👑', 'svg' => 'images/prestige/tier_5_ascended.svg', 'color' => 'yellow',   'gradient' => 'from-yellow-400 via-amber-500 to-orange-500', 'border' => 'border-yellow-400', 'bg' => 'bg-gradient-to-r from-yellow-50 to-amber-50 dark:from-yellow-950/40 dark:to-amber-950/40', 'text' => 'text-yellow-700 dark:text-amber-300'],
    ];

    public const MAX_LEVEL = 50;
    public const MAX_TIER = 5;

    /**
     * Exponential XP required to advance FROM a given level TO the next.
     * Formula: 100 × level^1.5
     */
    public static function xpRequiredForLevel(?int $level = 1): int
    {
        return (int) round(100 * pow(max(1, (int) $level), 1.5));
    }

    /**
     * Get tier details for a given tier number.
     */
    public static function getTierDetails(int $tier): array
    {
        $tier = max(1, min(self::MAX_TIER, $tier));
        $info = self::TIERS[$tier];
        $info['tier'] = $tier;
        return $info;
    }

    /**
     * Get the user's prestige title string (e.g., "🌱 Novice Lv.12").
     */
    public static function getPrestigeTitle(User $user): string
    {
        $tier = self::getTierDetails($user->prestige_tier ?? 1);
        $level = $user->prestige_level ?? 1;
        return "{$tier['icon']} {$tier['name']} Lv.{$level}";
    }

    /**
     * Get unified global level (1 - 250 scale across all 5 tiers).
     */
    public static function getGlobalLevel(User $user): int
    {
        $tier = max(1, min(self::MAX_TIER, (int)($user->prestige_tier ?? 1)));
        $level = max(1, min(self::MAX_LEVEL, (int)($user->prestige_level ?? 1)));
        return (($tier - 1) * self::MAX_LEVEL) + $level;
    }

    /**
     * Calculate XP progress within current level.
     * Returns [current_xp, required_xp, percentage].
     */
    public static function getLevelProgress(User $user): array
    {
        $currentXp = (int) ($user->prestige_xp ?? 0);
        $requiredXp = self::xpRequiredForLevel($user->prestige_level ?? 1);
        $percentage = min(100, (int) round(($currentXp / max(1, $requiredXp)) * 100));

        return [
            'current_xp' => $currentXp,
            'required_xp' => $requiredXp,
            'percentage' => $percentage,
        ];
    }

    /**
     * Add XP with prestige-aware level-up and auto-ascension.
     *
     * @return array ['leveled_up' => bool, 'ascended' => bool, 'new_tier' => int, 'new_level' => int, 'old_tier' => int, 'old_level' => int]
     */
    public static function addXp(User $user, int $amount): array
    {
        if ($amount <= 0) {
            return ['leveled_up' => false, 'ascended' => false, 'new_tier' => $user->prestige_tier, 'new_level' => $user->prestige_level, 'old_tier' => $user->prestige_tier, 'old_level' => $user->prestige_level];
        }

        $oldTier = $user->prestige_tier ?: 1;
        $oldLevel = $user->prestige_level ?: 1;
        $user->prestige_tier = $oldTier;
        $user->prestige_level = $oldLevel;

        $user->prestige_xp = ($user->prestige_xp ?? 0) + $amount;
        $user->lifetime_xp = ($user->lifetime_xp ?? 0) + $amount;
        $user->seasonal_xp = ($user->seasonal_xp ?? 0) + $amount;

        // Set current season if not set
        if (!$user->current_season) {
            $user->current_season = self::getCurrentSeasonKey();
        }

        $leveledUp = false;
        $ascended = false;

        // Process level-ups (potentially multiple in one go)
        while (true) {
            $requiredXp = self::xpRequiredForLevel($user->prestige_level);

            if ($user->prestige_xp >= $requiredXp) {
                $user->prestige_xp -= $requiredXp;
                $user->prestige_level++;
                $leveledUp = true;

                // Check for Ascension (reached max level + 1)
                if ($user->prestige_level > self::MAX_LEVEL) {
                    if ($user->prestige_tier < self::MAX_TIER) {
                        $user->prestige_tier++;
                        $user->prestige_level = 1;
                        // Preserve overflow XP into new tier progression
                        $ascended = true;
                    } else {
                        // Already at max tier - cap at MAX_LEVEL
                        $user->prestige_level = self::MAX_LEVEL;
                        // Keep overflow XP
                        break;
                    }
                }
            } else {
                break;
            }
        }

        // Update seasonal level (same curve, independent counter)
        $user->seasonal_level = self::calculateLevelFromXp($user->seasonal_xp);

        return [
            'leveled_up' => $leveledUp,
            'ascended' => $ascended,
            'new_tier' => $user->prestige_tier,
            'new_level' => $user->prestige_level,
            'old_tier' => $oldTier,
            'old_level' => $oldLevel,
        ];
    }

    /**
     * Safely subtract XP (e.g. if an administrative or rollback adjustment is made).
     * Prevents XP and level from going below Tier 1 Level 1 with 0 XP.
     */
    public static function subtractXp(User $user, int $amount): void
    {
        if ($amount <= 0) return;

        $currentXp = (int) ($user->prestige_xp ?? 0);
        if ($currentXp >= $amount) {
            $user->prestige_xp = $currentXp - $amount;
        } else {
            $remainder = $amount - $currentXp;
            $user->prestige_xp = 0;
            while ($remainder > 0 && ($user->prestige_level > 1 || $user->prestige_tier > 1)) {
                if ($user->prestige_level > 1) {
                    $user->prestige_level--;
                    $prevReq = self::xpRequiredForLevel($user->prestige_level);
                    if ($remainder < $prevReq) {
                        $user->prestige_xp = $prevReq - $remainder;
                        $remainder = 0;
                    } else {
                        $remainder -= $prevReq;
                    }
                } elseif ($user->prestige_tier > 1) {
                    $user->prestige_tier--;
                    $user->prestige_level = self::MAX_LEVEL;
                    $prevReq = self::xpRequiredForLevel($user->prestige_level);
                    if ($remainder < $prevReq) {
                        $user->prestige_xp = $prevReq - $remainder;
                        $remainder = 0;
                    } else {
                        $remainder -= $prevReq;
                    }
                }
            }
        }
        $user->lifetime_xp = max(0, (int)($user->lifetime_xp ?? 0) - $amount);
        $user->seasonal_xp = max(0, (int)($user->seasonal_xp ?? 0) - $amount);
        $user->seasonal_level = self::calculateLevelFromXp($user->seasonal_xp ?? 0);
        $user->save();
    }

    /**
     * Calculate what level corresponds to a given total XP amount (for seasonal level).
     */
    public static function calculateLevelFromXp(int $totalXp): int
    {
        $level = 1;
        $remaining = $totalXp;
        while ($level < self::MAX_LEVEL) {
            $required = self::xpRequiredForLevel($level);
            if ($remaining < $required) break;
            $remaining -= $required;
            $level++;
        }
        return $level;
    }

    /**
     * Get the current season key (e.g., "2026-Q3").
     */
    public static function getCurrentSeasonKey(): string
    {
        $quarter = (int) ceil(now()->month / 3);
        return now()->year . '-Q' . $quarter;
    }

    /**
     * Get the current season display name.
     */
    public static function getCurrentSeasonName(): string
    {
        $quarter = (int) ceil(now()->month / 3);
        $names = [1 => 'Spring', 2 => 'Summer', 3 => 'Autumn', 4 => 'Winter'];
        return $names[$quarter] . ' ' . now()->year;
    }

    /**
     * Calculate progressive overload quality multiplier based on user's tier and level.
     */
    public static function getQualityMultiplier(User $user, $task): float
    {
        $tier = $user->prestige_tier ?? 1;
        $level = $user->prestige_level ?? 1;
        $effectiveLevel = (($tier - 1) * self::MAX_LEVEL) + $level; // 1–250 global scale

        // Phase 1: Building Habits (effective level 1-10) — no quality requirements
        if ($effectiveLevel <= 10) {
            return 1.0;
        }

        $multiplier = 1.0;

        // Subtask bonus: tasks with ≥2 subtasks
        $subtaskCount = method_exists($task, 'subTasks') ? $task->subTasks()->count() : 0;
        if ($subtaskCount >= 2) {
            $multiplier *= 1.3;
        }

        // Estimation bonus: tasks with estimated_minutes ≥ 45
        if (($task->estimated_minutes ?? 0) >= 45) {
            $multiplier *= 1.2;
        }

        // Focus session bonus: tasks that had a pomodoro/focus session
        if (($task->pomodoro_sessions ?? 0) > 0) {
            $multiplier *= 1.5;
        }

        return $multiplier;
    }

    /**
     * Calculate the XP reduction factor for "plain" tasks at higher levels.
     * Returns a factor between 0.4 and 1.0.
     */
    public static function getPlainTaskFactor(User $user, $task): float
    {
        $tier = $user->prestige_tier ?? 1;
        $level = $user->prestige_level ?? 1;
        $effectiveLevel = (($tier - 1) * self::MAX_LEVEL) + $level;

        // No reduction for early phase
        if ($effectiveLevel <= 10) {
            return 1.0;
        }

        // Check if task is "plain" (no subtasks, no estimation, no focus)
        $subtaskCount = method_exists($task, 'subTasks') ? $task->subTasks()->count() : 0;
        $hasEstimation = ($task->estimated_minutes ?? 0) >= 30;
        $hasFocus = ($task->pomodoro_sessions ?? 0) > 0;

        $isPlain = ($subtaskCount < 2 && !$hasEstimation && !$hasFocus);

        if (!$isPlain) {
            return 1.0; // Quality task — no reduction
        }

        // Gradual reduction for plain tasks:
        // Level 11-20: 0.85x
        // Level 21-50: 0.7x
        // Tier 2+: 0.5x
        if ($effectiveLevel <= 20) {
            return 0.85;
        } elseif ($effectiveLevel <= 50) {
            return 0.7;
        } else {
            return 0.5;
        }
    }
}
