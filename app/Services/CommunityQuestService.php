<?php

namespace App\Services;

use App\Models\FocusSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class CommunityQuestService
{
    public const TARGET_HOURS = 50;
    public const TARGET_MINUTES = 3000; // 50 hours
    public const REWARD_COINS = 50;
    public const REWARD_XP = 100;

    /**
     * Get current weekly community quest data.
     */
    public static function getWeeklyQuest(?User $user = null): array
    {
        $now = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek();
        $endOfWeek = $now->copy()->endOfWeek();
        $weekKey = $now->format('Y_W');

        // Total community focus minutes this week
        $communityMinutes = (int) FocusSession::where('created_at', '>=', $startOfWeek)
            ->whereIn('mode', ['work', 'flowtime'])
            ->sum('minutes');

        $totalContributors = FocusSession::where('created_at', '>=', $startOfWeek)
            ->whereIn('mode', ['work', 'flowtime'])
            ->distinct('user_id')
            ->count('user_id');

        $progressPercent = min(100, round(($communityMinutes / self::TARGET_MINUTES) * 100, 1));
        $isCompleted = $communityMinutes >= self::TARGET_MINUTES;
        $daysLeft = max(0, (int) $now->diffInDays($endOfWeek, false) + 1);

        $userMinutes = 0;
        $userContributed = false;
        $userClaimed = false;

        if ($user) {
            $userMinutes = (int) FocusSession::where('user_id', $user->id)
                ->where('created_at', '>=', $startOfWeek)
                ->whereIn('mode', ['work', 'flowtime'])
                ->sum('minutes');

            $userContributed = $userMinutes >= 15; // At least one focused work session
            $userClaimed = (bool) Cache::get("community_quest_claimed_{$user->id}_{$weekKey}", false);
        }

        return [
            'week_key' => $weekKey,
            'title' => 'Pekan Sinergi Fokus',
            'badge' => '🛡️ Quest Komunitas',
            'description' => 'Satukan fokus komunitas ATOMA: raih akumulasi 50 jam fokus Pomodoro & Flowtime bersama minggu ini!',
            'target_hours' => self::TARGET_HOURS,
            'current_hours' => round($communityMinutes / 60, 1),
            'target_minutes' => self::TARGET_MINUTES,
            'current_minutes' => $communityMinutes,
            'progress_percent' => $progressPercent,
            'total_contributors' => max(1, $totalContributors),
            'days_left' => $daysLeft,
            'reward_coins' => self::REWARD_COINS,
            'reward_xp' => self::REWARD_XP,
            'is_completed' => $isCompleted,
            'user_minutes' => $userMinutes,
            'user_hours' => round($userMinutes / 60, 1),
            'user_contributed' => $userContributed,
            'user_claimed' => $userClaimed,
            'can_claim' => $isCompleted && $userContributed && !$userClaimed,
        ];
    }

    /**
     * Claim weekly community quest reward for the user.
     */
    public static function claimReward(User $user): array
    {
        $quest = self::getWeeklyQuest($user);

        if ($quest['user_claimed']) {
            return ['success' => false, 'message' => 'Hadiah quest minggu ini sudah kamu klaim!'];
        }

        if (!$quest['is_completed']) {
            return ['success' => false, 'message' => 'Target komunitas minggu ini belum tercapai. Ayo lanjutkan fokus!'];
        }

        if (!$quest['user_contributed']) {
            return ['success' => false, 'message' => 'Kamu perlu berkontribusi minimal 1 sesi fokus (15 menit) minggu ini untuk mengklaim reward.'];
        }

        // Award reward
        $user->increment('coins', self::REWARD_COINS);
        $user->increment('xp', self::REWARD_XP);

        // Mark as claimed for this week (expires in 8 days)
        Cache::put("community_quest_claimed_{$user->id}_{$quest['week_key']}", true, now()->addDays(8));

        return [
            'success' => true,
            'coins_awarded' => self::REWARD_COINS,
            'xp_awarded' => self::REWARD_XP,
            'message' => "🎉 Selamat! Kamu menerima +{self::REWARD_COINS} Koin dan +{self::REWARD_XP} XP atas partisipasimu di Quest Komunitas!",
        ];
    }
}
