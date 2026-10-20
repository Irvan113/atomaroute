<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeagueService
{
    public const LEAGUES = [
        'bronze_3' => [
            'name' => 'Bronze III',
            'icon' => '🥉',
            'tier' => 'Bronze',
            'min_weekly_xp' => 200,
            'reward_coins' => 15,
            'class' => 'bg-[#f0dfd2] dark:bg-[#452b1e] text-[#8c4b27] dark:text-[#f3b592] border border-[#e0c2ae] dark:border-[#633a25]',
        ],
        'bronze_2' => [
            'name' => 'Bronze II',
            'icon' => '🥉',
            'tier' => 'Bronze',
            'min_weekly_xp' => 350,
            'reward_coins' => 25,
            'class' => 'bg-[#f0dfd2] dark:bg-[#452b1e] text-[#8c4b27] dark:text-[#f3b592] border border-[#e0c2ae] dark:border-[#633a25]',
        ],
        'bronze_1' => [
            'name' => 'Bronze I',
            'icon' => '🥉',
            'tier' => 'Bronze',
            'min_weekly_xp' => 500,
            'reward_coins' => 35,
            'class' => 'bg-[#f0dfd2] dark:bg-[#452b1e] text-[#8c4b27] dark:text-[#f3b592] border border-[#e0c2ae] dark:border-[#633a25]',
        ],
        'silver_3' => [
            'name' => 'Silver III',
            'icon' => '🥈',
            'tier' => 'Silver',
            'min_weekly_xp' => 700,
            'reward_coins' => 50,
            'class' => 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600',
        ],
        'silver_2' => [
            'name' => 'Silver II',
            'icon' => '🥈',
            'tier' => 'Silver',
            'min_weekly_xp' => 900,
            'reward_coins' => 65,
            'class' => 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600',
        ],
        'silver_1' => [
            'name' => 'Silver I',
            'icon' => '🥈',
            'tier' => 'Silver',
            'min_weekly_xp' => 1150,
            'reward_coins' => 80,
            'class' => 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600',
        ],
        'gold_3' => [
            'name' => 'Gold III',
            'icon' => '🏆',
            'tier' => 'Gold',
            'min_weekly_xp' => 1400,
            'reward_coins' => 100,
            'class' => 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
        ],
        'gold_2' => [
            'name' => 'Gold II',
            'icon' => '🏆',
            'tier' => 'Gold',
            'min_weekly_xp' => 1650,
            'reward_coins' => 125,
            'class' => 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
        ],
        'gold_1' => [
            'name' => 'Gold I',
            'icon' => '🏆',
            'tier' => 'Gold',
            'min_weekly_xp' => 1900,
            'reward_coins' => 150,
            'class' => 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
        ],
        'diamond_2' => [
            'name' => 'Diamond II',
            'icon' => '💎',
            'tier' => 'Diamond',
            'min_weekly_xp' => 2200,
            'reward_coins' => 200,
            'class' => 'bg-cyan-100 dark:bg-cyan-950/60 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800',
        ],
        'diamond_1' => [
            'name' => 'Diamond I',
            'icon' => '💎',
            'tier' => 'Diamond',
            'min_weekly_xp' => 2500,
            'reward_coins' => 250,
            'class' => 'bg-cyan-100 dark:bg-cyan-950/60 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800',
        ],
        'master' => [
            'name' => 'Master',
            'icon' => '👑',
            'tier' => 'Master',
            'min_weekly_xp' => 2900,
            'reward_coins' => 400,
            'class' => 'bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 shadow-[0_0_15px_rgba(168,85,247,0.35)]',
        ],
    ];

    public static function getLeagueDetails(?string $leagueKey): array
    {
        $key = $leagueKey ?? 'bronze_3';
        if (!isset(self::LEAGUES[$key])) {
            $key = 'bronze_3';
        }
        $info = self::LEAGUES[$key];
        $info['key'] = $key;
        $info['pill'] = $info['name'] . ' ' . $info['icon'];
        $info['svg'] = 'images/badges/league/league_' . strtolower($info['tier']) . '.svg';
        return $info;
    }

    public static function getNextLeague(string $leagueKey): ?string
    {
        $keys = array_keys(self::LEAGUES);
        $index = array_search($leagueKey, $keys, true);
        if ($index !== false && isset($keys[$index + 1])) {
            return $keys[$index + 1];
        }
        return null;
    }

    public static function getPreviousLeague(string $leagueKey): ?string
    {
        $keys = array_keys(self::LEAGUES);
        $index = array_search($leagueKey, $keys, true);
        if ($index !== false && $index > 0 && isset($keys[$index - 1])) {
            return $keys[$index - 1];
        }
        return null;
    }

    public static function getPromotionProgress(User $user): array
    {
        $currentLeagueKey = $user->league ?? 'bronze_3';
        $currentLeague = self::getLeagueDetails($currentLeagueKey);
        $nextLeagueKey = self::getNextLeague($currentLeagueKey);
        $nextLeague = $nextLeagueKey ? self::getLeagueDetails($nextLeagueKey) : null;

        $weeklyXp = (int) ($user->weekly_xp ?? 0);
        $targetXp = $currentLeague['min_weekly_xp'];
        $percentage = min(100, (int) round(($weeklyXp / max(1, $targetXp)) * 100));
        $isQualified = $weeklyXp >= $targetXp;
        $gapXp = max(0, $targetXp - $weeklyXp);

        $progressText = $isQualified 
            ? "Terkualifikasi ({$weeklyXp}/{$targetXp} XP)" 
            : "{$weeklyXp} / {$targetXp} XP (" . $gapXp . " XP lagi)";

        return [
            'current_league' => $currentLeague,
            'next_league' => $nextLeague,
            'weekly_xp' => $weeklyXp,
            'target_xp' => $targetXp,
            'gap_xp' => $gapXp,
            'percentage' => $percentage,
            'progress_text' => $progressText,
            'is_qualified' => $isQualified,
            'is_max_league' => $currentLeagueKey === 'master',
            'can_promote_now' => false, // Promosi diproses 1x per minggu saat settlement Senin 12:00 WIB
            'can_claim_reward' => !$user->league_season_claimed && ($user->league_promoted_at !== null),
        ];
    }

    /**
     * Get all 12 league tiers along with unlock/progress status for the given user.
     */
    public static function getAllLeaguesWithProgress(?User $user = null): array
    {
        $currentLeagueKey = $user ? ($user->league ?? 'bronze_3') : 'bronze_3';
        $keys = array_keys(self::LEAGUES);
        $currentIndex = array_search($currentLeagueKey, $keys, true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $result = [];
        foreach (self::LEAGUES as $key => $league) {
            $index = array_search($key, $keys, true);
            $isCurrent = ($key === $currentLeagueKey);
            $isUnlocked = ($index <= $currentIndex);
            $isNext = ($index === $currentIndex + 1);

            $info = self::getLeagueDetails($key);
            $info['is_current'] = $isCurrent;
            $info['is_unlocked'] = $isUnlocked;
            $info['is_next'] = $isNext;
            $info['tier_order'] = $index + 1;
            $info['svg'] = 'images/badges/league/league_' . strtolower($info['tier']) . '.svg';

            $result[$key] = $info;
        }

        return $result;
    }

    /**
     * Instant promotion: allows user to rank up mid-week immediately once weekly XP target is reached.
     */
    public static function promoteUserToNextLeague(User $user): array
    {
        $currentLeagueKey = $user->league ?? 'bronze_3';
        $progress = self::getPromotionProgress($user);

        if (!$progress['can_promote_now']) {
            return [
                'success' => false,
                'message' => 'XP mingguan belum memenuhi target promosi liga (' . $progress['weekly_xp'] . '/' . $progress['target_xp'] . ' XP). Terus kerjakan tugas!',
            ];
        }

        $nextLeagueKey = $progress['next_league']['key'];
        $rewardCoins = self::LEAGUES[$nextLeagueKey]['reward_coins'] ?? 25;

        DB::transaction(function () use ($user, $currentLeagueKey, $nextLeagueKey, $rewardCoins) {
            $user->prev_league = $currentLeagueKey;
            $user->prev_week_xp = $user->weekly_xp;
            $user->league = $nextLeagueKey;
            $user->league_promoted_at = now();
            $user->league_season_claimed = true;
            $user->coins = ($user->coins ?? 0) + $rewardCoins;

            // Carry over any surplus weekly XP toward the new league
            $targetXp = self::LEAGUES[$currentLeagueKey]['min_weekly_xp'] ?? 0;
            $user->weekly_xp = max(0, (int) $user->weekly_xp - $targetXp);
            $user->save();
        });

        return [
            'success' => true,
            'new_league' => self::getLeagueDetails($nextLeagueKey),
            'reward_coins' => $rewardCoins,
        ];
    }

    /**
     * Settle Weekly Leagues on Mondays at 12:00 PM GMT+7 (Asia/Jakarta)
     * Handles H+1 grace period where all Sunday tasks have converted to settled XP.
     */
    public static function settleWeeklyLeagues(): array
    {
        $promotedCount = 0;
        $demotedCount = 0;
        $maintainedCount = 0;
        $masterCount = 0;

        $isEndOfMonth = now('Asia/Jakarta')->day <= 7 && now('Asia/Jakarta')->isMonday();

        // Get Top 100 Monthly User IDs if end of month
        $top100MonthlyIds = [];
        if ($isEndOfMonth) {
            $top100MonthlyIds = User::where('monthly_xp', '>', 0)
                ->orderBy('monthly_xp', 'desc')
                ->limit(100)
                ->pluck('id')
                ->toArray();
        }

        DB::transaction(function () use (&$promotedCount, &$demotedCount, &$maintainedCount, &$masterCount, $isEndOfMonth, $top100MonthlyIds) {
            $users = User::all();

            foreach ($users as $user) {
                $currentLeagueKey = $user->league ?? 'bronze_3';
                $weeklyXp = (int) ($user->weekly_xp ?? 0);
                $targetXp = self::LEAGUES[$currentLeagueKey]['min_weekly_xp'] ?? 200;

                $newLeagueKey = $currentLeagueKey;
                $action = 'maintained';

                // Master League Logic
                if ($isEndOfMonth && in_array($user->id, $top100MonthlyIds, true)) {
                    $newLeagueKey = 'master';
                    $action = 'promoted';
                    $masterCount++;
                } elseif ($currentLeagueKey === 'master' && $isEndOfMonth && !in_array($user->id, $top100MonthlyIds, true)) {
                    // Fall back to Diamond I
                    $newLeagueKey = 'diamond_1';
                    $action = 'demoted';
                } elseif ($weeklyXp >= $targetXp) {
                    // Standard Rank Up (Max 1 League per Week at Monday Settlement)
                    $next = self::getNextLeague($currentLeagueKey);
                    if ($next && $next !== 'master') {
                        $newLeagueKey = $next;
                        $action = 'promoted';
                    }
                } elseif ($weeklyXp < ($targetXp * 0.5) && $currentLeagueKey !== 'bronze_3') {
                    // Degradasi: Demote 1 tier if weekly XP is less than 50% of the weekly target
                    $prev = self::getPreviousLeague($currentLeagueKey);
                    if ($prev) {
                        $newLeagueKey = $prev;
                        $action = 'demoted';
                    }
                }

                if ($action === 'promoted') {
                    $promotedCount++;
                } elseif ($action === 'demoted') {
                    $demotedCount++;
                } else {
                    $maintainedCount++;
                }

                $user->prev_league = $currentLeagueKey;
                $user->prev_week_xp = $weeklyXp;
                $user->league = $newLeagueKey;
                $user->league_promoted_at = now();
                $user->league_season_claimed = false; // Trigger celebration modal
                $user->weekly_xp = 0;
                $user->weekly_xp_reset_at = now();
                $user->save();

                // Save snapshot to history
                if ($weeklyXp > 0) {
                    \App\Models\LeaderboardSeasonHistory::create([
                        'user_id' => $user->id,
                        'period_type' => 'weekly',
                        'period_key' => now('Asia/Jakarta')->subDays(1)->format('Y-\WW'),
                        'xp' => $weeklyXp,
                        'rank' => 1,
                        'league' => $currentLeagueKey,
                    ]);
                }
            }
        });

        return [
            'promoted' => $promotedCount,
            'demoted' => $demotedCount,
            'maintained' => $maintainedCount,
            'master' => $masterCount,
        ];
    }
}
