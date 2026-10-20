<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\LeaderboardSeasonHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

class LeaderboardHistoryPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_periods_starts_from_may_2026_to_current()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Volt::test('leaderboard')
            ->call('setTimeRange', 'monthly');

        $periods = $component->get('availablePeriods');

        // Check that periods include 2026-08 down to 2026-05
        $keys = array_column($periods, 'key');
        $this->assertContains('2026-08', $keys);
        $this->assertContains('2026-07', $keys);
        $this->assertContains('2026-06', $keys);
        $this->assertContains('2026-05', $keys);
    }

    public function test_user_can_select_historical_month_and_view_season_records()
    {
        $user1 = User::factory()->create(['name' => 'Recent Player', 'monthly_xp' => 1000]);
        $user2 = User::factory()->create(['name' => 'July Champion', 'monthly_xp' => 50]);

        // Insert historical record for July 2026 (2026-07)
        LeaderboardSeasonHistory::create([
            'user_id' => $user2->id,
            'period_type' => 'monthly',
            'period_key' => '2026-07',
            'xp' => 4200,
            'rank' => 1,
            'league' => 'master',
        ]);

        $this->actingAs($user1);

        $component = Volt::test('leaderboard')
            ->call('setTimeRange', 'monthly')
            ->set('selectedPeriodKey', '2026-07');

        $players = $component->get('leaderboardPlayers');

        $this->assertEquals($user2->id, $players->first()->id);
        $this->assertEquals(4200, $players->first()->period_xp);
    }
}

