<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MultiBadgeAndProfileShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_badge_titles_do_not_contain_emojis(): void
    {
        $catalog = AchievementService::$catalog;

        // Regex detecting common emojis
        $emojiPattern = '/[\x{1F300}-\x{1F9FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u';

        foreach ($catalog as $code => $badge) {
            $this->assertEquals(
                0,
                preg_match($emojiPattern, $badge['title']),
                "Badge [{$code}] title '{$badge['title']}' should not contain emojis."
            );
        }
    }

    public function test_tiered_streak_and_task_badges_have_distinct_svg_paths(): void
    {
        $catalog = AchievementService::$catalog;

        $streakBadges = ['streak_7', 'streak_14', 'streak_30', 'streak_100'];
        $streakSvgs = [];
        foreach ($streakBadges as $code) {
            $this->assertArrayHasKey($code, $catalog, "Streak badge {$code} must exist in catalog");
            $this->assertNotEmpty($catalog[$code]['svg_path']);
            $streakSvgs[] = $catalog[$code]['svg_path'];
        }
        $this->assertCount(4, array_unique($streakSvgs), "All 4 streak tiers must have distinct SVG files");

        $taskBadges = ['first_task', 'tasks_50', 'tasks_100'];
        $taskSvgs = [];
        foreach ($taskBadges as $code) {
            $this->assertArrayHasKey($code, $catalog, "Task badge {$code} must exist in catalog");
            $this->assertNotEmpty($catalog[$code]['svg_path']);
            $taskSvgs[] = $catalog[$code]['svg_path'];
        }
        $this->assertCount(3, array_unique($taskSvgs), "All 3 task tiers must have distinct SVG files");
    }

    public function test_multi_badge_equip_allows_up_to_three_badges(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Unlock 4 badges for user
        $badgesToUnlock = ['first_task', 'streak_7', 'streak_14', 'streak_30'];
        foreach ($badgesToUnlock as $code) {
            DB::table('user_achievements')->insert([
                'user_id' => $user->id,
                'achievement_code' => $code,
                'unlocked_at' => now(),
            ]);
        }

        // Equip 1st badge
        $res1 = $this->postJson('/api/user-achievements/equip', ['code' => 'first_task']);
        $res1->assertOk();
        $this->assertEquals(['first_task'], $res1->json('equipped_badges'));
        $this->assertEquals('first_task', $res1->json('equipped_badge'));

        // Equip 2nd badge
        $res2 = $this->postJson('/api/user-achievements/equip', ['code' => 'streak_7']);
        $res2->assertOk();
        $this->assertEquals(['first_task', 'streak_7'], $res2->json('equipped_badges'));

        // Equip 3rd badge
        $res3 = $this->postJson('/api/user-achievements/equip', ['code' => 'streak_14']);
        $res3->assertOk();
        $this->assertEquals(['first_task', 'streak_7', 'streak_14'], $res3->json('equipped_badges'));

        // Equip 4th badge -> Auto-replaces first badge so user is never blocked (3/3 max showcase)
        $res4 = $this->postJson('/api/user-achievements/equip', ['code' => 'streak_30']);
        $res4->assertOk();
        $this->assertEquals(['streak_7', 'streak_14', 'streak_30'], $res4->json('equipped_badges'));
    }

    public function test_multi_badge_unequip_frees_slot(): void
    {
        $user = User::factory()->create([
            'equipped_badges' => ['first_task', 'streak_7', 'streak_14'],
        ]);
        $this->actingAs($user);

        // Unequip 'streak_7'
        $res = $this->postJson('/api/user-achievements/equip', [
            'code' => 'streak_7',
            'action' => 'unequip',
        ]);

        $res->assertOk();
        $this->assertEquals(['first_task', 'streak_14'], $res->json('equipped_badges'));

        // Now equipping 'streak_30' should succeed (since we have 2 badges)
        DB::table('user_achievements')->insert([
            'user_id' => $user->id,
            'achievement_code' => 'streak_30',
            'unlocked_at' => now(),
        ]);

        $res2 = $this->postJson('/api/user-achievements/equip', ['code' => 'streak_30']);
        $res2->assertOk();
        $this->assertEquals(['first_task', 'streak_14', 'streak_30'], $res2->json('equipped_badges'));
    }

    public function test_public_profile_page_renders_successfully(): void
    {
        $targetUser = User::factory()->create([
            'name' => 'Gading ATOMA Champion',
            'bio' => 'Ready to dominate the leaderboard!',
            'equipped_badges' => ['streak_30', 'tasks_50'],
            'xp' => 15000,
            'current_streak' => 42,
            'longest_streak' => 50,
            'league' => 'gold',
            'prestige_tier' => 2,
            'stat_focus' => 85,
            'stat_consistency' => 90,
            'stat_execution' => 75,
            'stat_organization' => 80,
        ]);

        $viewer = User::factory()->create();
        $this->actingAs($viewer);

        $response = $this->get('/u/' . $targetUser->id);

        $response->assertOk();
        $response->assertSee('Gading ATOMA Champion');
        $response->assertSee('Ready to dominate the leaderboard!');
        $response->assertSee('Rak Pamer Lencana Unggulan');
        $response->assertSee('Tantang Senggol Bacok');
    }
}
