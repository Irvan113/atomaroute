<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\StreakHistory;
use App\Services\StreakEvaluatorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreakEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_streak_resets_to_zero_when_day_is_missed_and_no_coupons()
    {
        $user = User::factory()->create([
            'current_streak' => 5,
            'streak_coupons' => 0,
            'coins' => 1000,
        ]);

        // Last completed task was 2 days ago (27 July)
        $twoDaysAgo = Carbon::today()->subDays(2);
        StreakHistory::create([
            'user_id' => $user->id,
            'date' => $twoDaysAgo->toDateString(),
            'status' => 'completed',
        ]);

        // Evaluate streak
        StreakEvaluatorService::evaluate($user);

        // Current streak should be reset to 0
        $this->assertEquals(0, $user->fresh()->current_streak);
    }

    public function test_streak_is_frozen_when_coupons_available()
    {
        $user = User::factory()->create([
            'current_streak' => 5,
            'streak_coupons' => 2,
        ]);

        // Last completed task was 2 days ago
        $twoDaysAgo = Carbon::today()->subDays(2);
        StreakHistory::create([
            'user_id' => $user->id,
            'date' => $twoDaysAgo->toDateString(),
            'status' => 'completed',
        ]);

        // Evaluate streak
        StreakEvaluatorService::evaluate($user);

        // Streak should be preserved and 1 coupon used
        $this->assertEquals(5, $user->fresh()->current_streak);
        $this->assertEquals(1, $user->fresh()->streak_coupons);

        // Yesterday should be marked as 'frozen'
        $yesterday = Carbon::today()->subDays(1)->toDateString();
        $history = StreakHistory::where('user_id', $user->id)->whereDate('date', $yesterday)->first();
        $this->assertNotNull($history);
        $this->assertEquals('frozen', $history->status);
    }

    public function test_streak_chain_counts_frozen_days_as_plus_one()
    {
        $user = User::factory()->create([
            'current_streak' => 0,
            'streak_coupons' => 2,
        ]);

        $today = Carbon::today();

        // Chain of 5 days: Day -4 (completed), Day -3 (frozen), Day -2 (frozen), Day -1 (completed), Day 0 (completed)
        StreakHistory::create(['user_id' => $user->id, 'date' => $today->copy()->subDays(4)->toDateString(), 'status' => 'completed']);
        StreakHistory::create(['user_id' => $user->id, 'date' => $today->copy()->subDays(3)->toDateString(), 'status' => 'frozen']);
        StreakHistory::create(['user_id' => $user->id, 'date' => $today->copy()->subDays(2)->toDateString(), 'status' => 'frozen']);
        StreakHistory::create(['user_id' => $user->id, 'date' => $today->copy()->subDays(1)->toDateString(), 'status' => 'completed']);
        StreakHistory::create(['user_id' => $user->id, 'date' => $today->toDateString(), 'status' => 'completed']);

        $streak = StreakEvaluatorService::calculateStreak($user);

        // Every frozen day counts +1, so total streak is 5
        $this->assertEquals(5, $streak);

        StreakEvaluatorService::recalculateStreak($user);
        $this->assertEquals(5, $user->fresh()->current_streak);
        $this->assertEquals(5, $user->fresh()->longest_streak);
    }

    public function test_undone_task_reverts_streak_by_one_without_double_decrement_or_deleting_past_frozen()
    {
        $user = User::factory()->create([
            'current_streak' => 10,
            'longest_streak' => 10,
            'streak_coupons' => 1,
        ]);

        $today = Carbon::today();

        // 10-day chain ending yesterday (including a frozen day yesterday)
        for ($i = 10; $i >= 2; $i--) {
            StreakHistory::create([
                'user_id' => $user->id,
                'date' => $today->copy()->subDays($i)->toDateString(),
                'status' => 'completed',
            ]);
        }
        // Yesterday is frozen
        $yesterdayHistory = StreakHistory::create([
            'user_id' => $user->id,
            'date' => $today->copy()->subDays(1)->toDateString(),
            'status' => 'frozen',
        ]);

        // Prior to today's task, streak is 10
        StreakEvaluatorService::recalculateStreak($user);
        $this->assertEquals(10, $user->fresh()->current_streak);

        // User completes task today -> streak becomes 11
        $task = $user->tasks()->create([
            'title' => 'Tugas Hari Ini',
            'date' => $today->toDateString(),
            'is_completed' => true,
        ]);
        StreakHistory::create([
            'user_id' => $user->id,
            'date' => $today->toDateString(),
            'status' => 'completed',
        ]);
        StreakEvaluatorService::recalculateStreak($user);
        $this->assertEquals(11, $user->fresh()->current_streak);

        // User unchecks/undones task today -> streak should revert to 10 (NEVER 9!)
        $task->update(['is_completed' => false]);
        StreakHistory::where('user_id', $user->id)->whereDate('date', $today->toDateString())->where('status', 'completed')->delete();
        StreakEvaluatorService::recalculateStreak($user);

        // Must be exactly 10 (reduced by 1, not 2)
        $this->assertEquals(10, $user->fresh()->current_streak);
        // Coupons must remain untouched
        $this->assertEquals(1, $user->fresh()->streak_coupons);
        // Yesterday's frozen record must NOT be deleted
        $this->assertDatabaseHas('streak_histories', [
            'id' => $yesterdayHistory->id,
            'status' => 'frozen',
        ]);
    }

    public function test_secret_protection_activates_for_diligent_user_when_no_coupons_and_low_coins()
    {
        $user = User::factory()->create([
            'current_streak' => 10,
            'streak_coupons' => 0,
            'coins' => 100, // < 500 coins
            'last_secret_protection_at' => null,
        ]);

        $today = Carbon::today();
        // Day -2 completed, Day -1 missed, Today evaluating
        StreakHistory::create([
            'user_id' => $user->id,
            'date' => $today->copy()->subDays(2)->toDateString(),
            'status' => 'completed',
        ]);

        StreakEvaluatorService::evaluate($user);

        // Secret protection should have activated
        $this->assertNotNull($user->fresh()->last_secret_protection_at);
        $this->assertEquals($today->toDateString(), $user->fresh()->last_secret_protection_at->toDateString());
        
        // Day -1 should now be frozen
        $yesterday = $today->copy()->subDays(1)->toDateString();
        $history = StreakHistory::where('user_id', $user->id)->whereDate('date', $yesterday)->first();
        $this->assertNotNull($history);
        $this->assertEquals('frozen', $history->status);

        // Streak should NOT be reset to 0
        $this->assertGreaterThan(0, $user->fresh()->current_streak);
    }

    public function test_secret_protection_does_not_activate_immediately_after_coupon_frozen_day()
    {
        $user = User::factory()->create([
            'current_streak' => 5,
            'streak_coupons' => 0,
            'coins' => 50,
            'last_secret_protection_at' => null,
        ]);

        $today = Carbon::today();
        // Day -2 was frozen (e.g. from previous coupon usage)
        StreakHistory::create([
            'user_id' => $user->id,
            'date' => $today->copy()->subDays(2)->toDateString(),
            'status' => 'frozen',
        ]);

        StreakEvaluatorService::evaluate($user);

        // Secret protection must NOT activate consecutively after a coupon/frozen day
        $this->assertNull($user->fresh()->last_secret_protection_at);
        $this->assertEquals(0, $user->fresh()->current_streak);
    }

    public function test_secret_protection_respects_30_day_cooldown()
    {
        $today = Carbon::today();
        $user = User::factory()->create([
            'current_streak' => 7,
            'streak_coupons' => 0,
            'coins' => 0,
            'last_secret_protection_at' => $today->copy()->subDays(10)->toDateString(), // Used 10 days ago
        ]);

        StreakHistory::create([
            'user_id' => $user->id,
            'date' => $today->copy()->subDays(2)->toDateString(),
            'status' => 'completed',
        ]);

        StreakEvaluatorService::evaluate($user);

        // Cooldown has not expired (< 30 days), so streak resets to 0
        $this->assertEquals(0, $user->fresh()->current_streak);
    }
}

