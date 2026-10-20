<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\LeagueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

class LeagueSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_starts_at_bronze_3_and_tracks_promotion_progress()
    {
        $user = User::factory()->create([
            'name' => 'Novice Player',
            'weekly_xp' => 150,
        ]);

        $progress = LeagueService::getPromotionProgress($user);

        $this->assertEquals('Bronze III', $progress['current_league']['name']);
        $this->assertEquals('Bronze II', $progress['next_league']['name']);
        $this->assertEquals(200, $progress['target_xp']);
        $this->assertEquals(150, $progress['weekly_xp']);
        $this->assertEquals(50, $progress['gap_xp']);
        $this->assertFalse($progress['is_qualified']);
    }

    public function test_weekly_settlement_promotes_qualified_user_max_one_rank()
    {
        $user = User::factory()->create([
            'name' => 'Hardworking Player',
            'league' => 'bronze_3',
            'weekly_xp' => 450, // More than enough for Bronze 3 (200), but max 1 rank up per week
        ]);

        $results = LeagueService::settleWeeklyLeagues();

        $user->refresh();
        $this->assertEquals('bronze_2', $user->league);
        $this->assertEquals('bronze_3', $user->prev_league);
        $this->assertEquals(0, $user->weekly_xp); // Reset for new week
        $this->assertFalse($user->league_season_claimed); // Ready for celebration popup
        $this->assertEquals(1, $results['promoted']);
    }

    public function test_inactive_user_above_bronze_3_is_demoted_one_rank()
    {
        $user = User::factory()->create([
            'name' => 'Inactive Player',
            'league' => 'silver_3',
            'weekly_xp' => 0, // 0 XP entire week
        ]);

        $results = LeagueService::settleWeeklyLeagues();

        $user->refresh();
        $this->assertEquals('bronze_1', $user->league);
        $this->assertEquals(1, $results['demoted']);
    }

    public function test_user_below_fifty_percent_target_xp_is_demoted_and_maintained_when_above()
    {
        // Silver 3 target is 700 XP. 50% is 350 XP.
        $demotedUser = User::factory()->create([
            'name' => 'Low Effort Player',
            'league' => 'silver_3',
            'weekly_xp' => 300, // < 350 XP -> demote to bronze_1
        ]);

        $maintainedUser = User::factory()->create([
            'name' => 'Safe Player',
            'league' => 'silver_3',
            'weekly_xp' => 500, // >= 350 XP and < 700 XP -> maintain silver_3
        ]);

        $results = LeagueService::settleWeeklyLeagues();

        $demotedUser->refresh();
        $maintainedUser->refresh();

        $this->assertEquals('bronze_1', $demotedUser->league);
        $this->assertEquals('silver_3', $maintainedUser->league);
        $this->assertEquals(0, $demotedUser->weekly_xp);
        $this->assertEquals(0, $maintainedUser->weekly_xp);
    }

    public function test_artisan_settle_weekly_command_executes_successfully()
    {
        User::factory()->create([
            'league' => 'bronze_2',
            'weekly_xp' => 400,
        ]);

        $this->artisan('league:settle-weekly')
            ->expectsOutputToContain('Weekly League Settlement')
            ->expectsOutputToContain('Settlement Completed Successfully!')
            ->assertExitCode(0);
    }

    public function test_user_can_claim_season_promotion_rewards()
    {
        $user = User::factory()->create([
            'league' => 'silver_3',
            'coins' => 10,
            'league_season_claimed' => false,
        ]);

        $this->actingAs($user);

        Volt::test('social')
            ->call('claimSeasonPromotionReward')
            ->assertDispatched('toast');

        $user->refresh();
        $this->assertTrue((bool)$user->league_season_claimed);
        $this->assertGreaterThan(10, $user->coins);
    }
}
