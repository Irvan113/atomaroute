<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Friendship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

class LeaderboardGamificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gamified_leaderboard_renders_podium_and_filters()
    {
        $user1 = User::factory()->create(['name' => 'Atoma Master', 'monthly_xp' => 1250, 'xp' => 1250, 'current_streak' => 5]);
        $user2 = User::factory()->create(['name' => 'Luna Stark', 'monthly_xp' => 1190, 'xp' => 1190, 'current_streak' => 4]);
        $user3 = User::factory()->create(['name' => 'Jax Rivers', 'monthly_xp' => 1155, 'xp' => 1155, 'current_streak' => 3]);
        $user4 = User::factory()->create(['name' => 'Maya C.', 'monthly_xp' => 985, 'xp' => 985, 'current_streak' => 4]);

        $this->actingAs($user1);

        $component = Volt::test('leaderboard');

        // Check default monthly range
        $component->assertSee('Atoma Master')
            ->assertSee('Luna Stark')
            ->assertSee('Jax Rivers')
            ->assertSee('Maya C.')
            ->assertSee('1,250 XP')
            ->assertSee('1,190 XP')
            ->assertSee('1,155 XP')
            ->assertSee('985 XP');

        // Switch to weekly
        $component->call('setTimeRange', 'weekly')
            ->assertSet('timeRange', 'weekly');

        // Switch scope to friends
        $component->call('setLeaderboardScope', 'friends')
            ->assertSet('leaderboardScope', 'friends');
    }

    public function test_user_standing_sticky_card_calculates_gap_xp()
    {
        $user1 = User::factory()->create(['name' => 'Leader', 'monthly_xp' => 1000, 'xp' => 1000]);
        $user2 = User::factory()->create(['name' => 'Challenger', 'monthly_xp' => 950, 'xp' => 950]);

        $this->actingAs($user2);

        $component = Volt::test('leaderboard');
        $standing = $component->get('userStanding');

        $this->assertEquals(2, $standing['rank']);
        $this->assertEquals(950, $standing['xp']);
        $this->assertEquals(51, $standing['gap_xp']); // 1000 - 950 + 1 = 51 XP to overtake #1
    }

    public function test_challenge_friend_action()
    {
        $user1 = User::factory()->create(['name' => 'Hero']);
        $user2 = User::factory()->create(['name' => 'Rival']);

        Friendship::create(['user_id' => $user1->id, 'friend_id' => $user2->id, 'status' => 'accepted']);

        $this->actingAs($user1);

        Volt::test('leaderboard')
            ->call('setLeaderboardScope', 'friends')
            ->call('challengeFriend', $user2->id)
            ->assertDispatched('toast');
    }

    public function test_get_all_leagues_with_progress_returns_all_12_tiers()
    {
        $user = User::factory()->create(['league' => 'bronze_2', 'weekly_xp' => 100]);

        $leagues = \App\Services\LeagueService::getAllLeaguesWithProgress($user);

        $this->assertCount(12, $leagues);
        $this->assertTrue($leagues['bronze_3']['is_unlocked']);
        $this->assertTrue($leagues['bronze_2']['is_current']);
        $this->assertTrue($leagues['bronze_1']['is_next']);
        $this->assertFalse($leagues['silver_3']['is_unlocked']);
    }

    public function test_user_is_qualified_for_monday_settlement_when_weekly_target_met()
    {
        // Bronze III requires 200 weekly XP
        $user = User::factory()->create([
            'league' => 'bronze_3',
            'weekly_xp' => 250, // Exceeds target 200
            'coins' => 10,
        ]);

        $this->actingAs($user);

        $comp = Volt::test('leaderboard');
        $comp->assertSee('Terkualifikasi Promosi');
        $comp->assertDontSee('Promosikan Sekarang!');

        $progress = \App\Services\LeagueService::getPromotionProgress($user);
        $this->assertTrue($progress['is_qualified']);
        $this->assertFalse($progress['can_promote_now']);
    }
}
