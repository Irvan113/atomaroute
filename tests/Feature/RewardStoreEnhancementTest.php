<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RewardStoreEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_page_renders_successfully_without_double_xp_booster(): void
    {
        $user = User::factory()->create([
            'coins' => 1250,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('store'));
        $response->assertOk();
        $response->assertSee('Reward Store ATOMA');
        $response->assertSee('1,250');
        $response->assertSee('Semua');
        $response->assertSee('Utilitas Streak');
        $response->assertSee('Frame Avatar');
        $response->assertSee('Tema Tampilan');
        $response->assertSee('Coming Soon');
        $response->assertSee('Sound Packs');
        $response->assertSee('Inventori Saya');

        // Verify Double EXP boosters are completely removed for fair play
        $response->assertDontSee('Ramuan 2x EXP');
        $response->assertDontSee('Mega Booster 2x EXP');
    }

    public function test_themes_are_locked_with_coming_soon(): void
    {
        $user = User::factory()->create([
            'coins' => 3000,
            'unlocked_themes' => ['default'],
        ]);

        $this->actingAs($user);

        // Attempting to buy a theme should trigger a Coming Soon notification and not deduct coins
        Volt::test('pages.store')
            ->call('buyTheme', 'midnight', 1400)
            ->assertDispatched('toast', function ($event, $params) {
                return $params['type'] === 'info' && str_contains($params['message'], 'Coming Soon');
            });

        $user->refresh();
        $this->assertEquals(3000, $user->coins);
        $this->assertNotContains('midnight', $user->unlocked_themes);
    }

    public function test_buy_streak_saver_coupon_limit(): void
    {
        $user = User::factory()->create([
            'coins' => 5000,
            'streak_coupons' => 4,
        ]);

        $this->actingAs($user);

        // Buy 5th coupon
        Volt::test('pages.store')
            ->call('buyStreakCoupon')
            ->assertDispatched('toast', function ($event, $params) {
                return $params['type'] === 'success';
            });

        $user->refresh();
        $this->assertEquals(5, $user->streak_coupons);
        $this->assertEquals(4500, $user->coins);

        // Attempt to buy 6th coupon -> rejected because max capacity is 5
        Volt::test('pages.store')
            ->call('buyStreakCoupon')
            ->assertDispatched('toast', function ($event, $params) {
                return $params['type'] === 'error' && str_contains($params['message'], 'maksimal');
            });

        $user->refresh();
        $this->assertEquals(5, $user->streak_coupons);
        $this->assertEquals(4500, $user->coins);
    }

    public function test_buy_streak_revive_token(): void
    {
        $user = User::factory()->create([
            'coins' => 2500,
            'current_streak' => 0,
            'longest_streak' => 14,
        ]);

        $this->actingAs($user);

        // Buy Streak Revive Token (1200 coins)
        Volt::test('pages.store')
            ->call('buyStreakRevive', 1200)
            ->assertDispatched('toast', function ($event, $params) {
                return $params['type'] === 'success';
            });

        $user->refresh();
        $this->assertEquals(1300, $user->coins);
        // Min(7, longest_streak) = 7
        $this->assertEquals(7, $user->current_streak);

        // Attempt to buy when current_streak > 0 -> should be rejected
        Volt::test('pages.store')
            ->call('buyStreakRevive', 1200)
            ->assertDispatched('toast', function ($event, $params) {
                return $params['type'] === 'warning';
            });

        $user->refresh();
        $this->assertEquals(1300, $user->coins);
    }

    public function test_buy_and_equip_avatar_frames(): void
    {
        $user = User::factory()->create([
            'coins' => 4000,
            'unlocked_frames' => [],
            'active_frame' => null,
        ]);

        $this->actingAs($user);

        // Buy frame_gold for 3500 coins
        Volt::test('pages.store')
            ->call('buyFrame', 'frame_gold', 3500)
            ->assertDispatched('toast', function ($event, $params) {
                return $params['type'] === 'success';
            });

        $user->refresh();
        $this->assertEquals(500, $user->coins);
        $this->assertContains('frame_gold', $user->unlocked_frames);

        // Equip frame_gold
        Volt::test('pages.store')
            ->call('equipFrame', 'frame_gold')
            ->assertDispatched('toast', function ($event, $params) {
                return $params['type'] === 'success' && str_contains($params['message'], 'dipasang');
            });

        $user->refresh();
        $this->assertEquals('frame_gold', $user->active_frame);

        // Toggle equip frame_gold -> unequip
        Volt::test('pages.store')
            ->call('equipFrame', 'frame_gold')
            ->assertDispatched('toast', function ($event, $params) {
                return $params['type'] === 'success' && str_contains($params['message'], 'dilepas');
            });

        $user->refresh();
        $this->assertNull($user->active_frame);
    }

    public function test_top_header_renders_equipped_avatar_frames(): void
    {
        // 1. Test user with equipped Gold Imperial Crown Frame
        $goldUser = User::factory()->create([
            'active_frame' => 'frame_gold',
            'unlocked_frames' => ['frame_gold'],
        ]);
        $this->actingAs($goldUser);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('images/frames/crown_gold.svg');
        $response->assertSee('border-amber-400');

        // 2. Test user with Neon Glow Frame
        $neonUser = User::factory()->create([
            'active_frame' => 'frame_neon',
            'unlocked_frames' => ['frame_neon'],
        ]);
        $this->actingAs($neonUser);

        $responseNeon = $this->get(route('dashboard'));
        $responseNeon->assertOk();
        $responseNeon->assertSee('border-cyan-400');

        // 3. Test user with Fire Ring Frame
        $fireUser = User::factory()->create([
            'active_frame' => 'frame_fire',
            'unlocked_frames' => ['frame_fire'],
        ]);
        $this->actingAs($fireUser);

        $responseFire = $this->get(route('dashboard'));
        $responseFire->assertOk();
        $responseFire->assertSee('border-orange-500');
    }

    public function test_profile_and_public_profile_display_custom_crown_svg(): void
    {
        $targetUser = User::factory()->create([
            'name' => 'Kaisar Emas Atoma',
            'active_frame' => 'frame_gold',
            'unlocked_frames' => ['frame_gold'],
        ]);

        $this->actingAs($targetUser);

        // Test Profile page
        $profileRes = $this->get(route('profile'));
        $profileRes->assertOk();
        $profileRes->assertSee('images/frames/crown_gold.svg');

        // Test Public Profile page
        $publicRes = $this->get('/u/' . $targetUser->id);
        $publicRes->assertOk();
        $publicRes->assertSee('Kaisar Emas Atoma');
        $publicRes->assertSee('images/frames/crown_gold.svg');
        $publicRes->assertSee('border-amber-400');
    }

    public function test_buy_and_equip_sound_packs(): void
    {
        $user = User::factory()->create([
            'coins' => 1500,
            'unlocked_sound_packs' => ['default'],
            'active_sound_pack' => 'default',
        ]);

        $this->actingAs($user);

        // Buy zen_bell sound pack for 900 coins
        Volt::test('pages.store')
            ->call('buySoundPack', 'zen_bell', 900)
            ->assertDispatched('toast', function ($event, $params) {
                return $params['type'] === 'success';
            });

        $user->refresh();
        $this->assertEquals(600, $user->coins);
        $this->assertContains('zen_bell', $user->unlocked_sound_packs);

        // Equip zen_bell
        Volt::test('pages.store')
            ->call('equipSoundPack', 'zen_bell')
            ->assertDispatched('toast');

        $user->refresh();
        $this->assertEquals('zen_bell', $user->active_sound_pack);
    }
}
