<?php

namespace Tests\Feature;

use App\Models\StreakHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class StreakModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_streak_modal_can_navigate_between_months()
    {
        $user = User::factory()->create([
            'current_streak' => 7,
            'longest_streak' => 14,
            'streak_coupons' => 3,
        ]);

        $today = Carbon::today();
        $lastMonth = $today->copy()->subMonth();

        // Create a completed streak record for today and last month
        StreakHistory::create([
            'user_id' => $user->id,
            'date' => $today->toDateString(),
            'status' => 'completed',
        ]);
        StreakHistory::create([
            'user_id' => $user->id,
            'date' => $lastMonth->copy()->startOfMonth()->addDays(5)->toDateString(),
            'status' => 'completed',
        ]);

        $comp = Volt::actingAs($user)->test('streak-modal');

        // Initially on current month
        $comp->assertSet('year', $today->year)
            ->assertSet('month', $today->month)
            ->assertSet('currentStreak', 7)
            ->assertSet('longestStreak', 14)
            ->assertSet('streakCoupons', 3);

        // Click prevMonth (< button)
        $comp->call('prevMonth');
        $comp->assertSet('year', $lastMonth->year)
            ->assertSet('month', $lastMonth->month);

        // Click nextMonth (> button)
        $comp->call('nextMonth');
        $comp->assertSet('year', $today->year)
            ->assertSet('month', $today->month);

        // Click prevMonth twice then goToToday
        $comp->call('prevMonth');
        $comp->call('prevMonth');
        $this->assertNotEquals($today->month, $comp->get('month'));

        $comp->call('goToToday');
        $comp->assertSet('year', $today->year)
            ->assertSet('month', $today->month);
    }
}
