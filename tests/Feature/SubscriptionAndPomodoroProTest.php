<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;

class SubscriptionAndPomodoroProTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_subscription_status_methods(): void
    {
        $freeUser = User::factory()->create([
            'subscription_tier' => 'free',
        ]);

        $this->assertTrue($freeUser->isFree());
        $this->assertFalse($freeUser->isPro());
        $this->assertEquals(2, $freeUser->maxPomodoroFolders());

        $proUser = User::factory()->create([
            'subscription_tier' => 'pro',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->assertTrue($proUser->isPro());
        $this->assertFalse($proUser->isFree());
        $this->assertEquals(999, $proUser->maxPomodoroFolders());

        // Expired Pro user falls back to Free
        $expiredPro = User::factory()->create([
            'subscription_tier' => 'pro',
            'subscription_expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($expiredPro->isPro());
        $this->assertTrue($expiredPro->isFree());
    }

    public function test_free_user_pomodoro_playlists_capped_at_two_folders(): void
    {
        $freeUser = User::factory()->create([
            'subscription_tier' => 'free',
        ]);
        $this->actingAs($freeUser);

        $threeFolders = [
            ['id' => 'f1', 'name' => 'Deep Focus', 'tracks' => []],
            ['id' => 'f2', 'name' => 'Chill', 'tracks' => []],
            ['id' => 'f3', 'name' => 'Coding Beat', 'tracks' => []],
        ];

        $response = $this->postJson('/api/pomodoro/playlists', [
            'folders' => $threeFolders
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_pro' => false,
            'max_folders' => 2,
        ]);

        // Assert only 2 folders were saved for Free user
        $this->assertCount(2, $response->json('folders'));
    }

    public function test_pro_user_can_save_unlimited_pomodoro_folders(): void
    {
        $proUser = User::factory()->create([
            'subscription_tier' => 'pro',
            'subscription_expires_at' => now()->addMonth(),
        ]);
        $this->actingAs($proUser);

        $fourFolders = [
            ['id' => 'f1', 'name' => 'Deep Focus', 'tracks' => []],
            ['id' => 'f2', 'name' => 'Chill', 'tracks' => []],
            ['id' => 'f3', 'name' => 'Lo-Fi Night', 'tracks' => []],
            ['id' => 'f4', 'name' => 'Binaural Alpha', 'tracks' => []],
        ];

        $response = $this->postJson('/api/pomodoro/playlists', [
            'folders' => $fourFolders
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_pro' => true,
            'max_folders' => 999,
        ]);

        $this->assertCount(4, $response->json('folders'));
    }

    public function test_free_user_milestone_limit_blocks_second_active_milestone(): void
    {
        $freeUser = User::factory()->create([
            'subscription_tier' => 'free',
        ]);
        $this->actingAs($freeUser);

        // First milestone is allowed
        $this->assertTrue($freeUser->canCreateMilestone());

        Skill::create([
            'user_id' => $freeUser->id,
            'title' => 'First Challenge',
            'target_days' => 30,
            'status' => 'active',
        ]);

        // Second milestone should be blocked for Free user
        $this->assertFalse($freeUser->fresh()->canCreateMilestone());

        // Attempting to create via Livewire triggers upgrade modal
        Livewire::test('skills.index')
            ->call('openCreateModal')
            ->assertDispatched('open-pro-modal');

        // Pro user can create unlimited milestones
        $freeUser->update(['subscription_tier' => 'pro']);
        $this->assertTrue($freeUser->fresh()->canCreateMilestone());
    }

    public function test_smart_ai_plan_importer_receives_syllabus_text(): void
    {
        $user = User::factory()->create([
            'subscription_tier' => 'pro',
        ]);
        $this->actingAs($user);

        $syllabusText = "Minggu 1: Knee Mobility & Foam Rolling\nMinggu 2: Wall sits & Tibialis raises\nMinggu 3: Isometric holds & Single leg balance";

        Livewire::test('skills.index')
            ->call('openCreateModal')
            ->set('newSkillTitle', 'Knee Armor 30 Days')
            ->set('newSkillDays', 30)
            ->set('aiSyllabusText', $syllabusText)
            ->call('startSetup')
            ->assertHasNoErrors()
            ->assertSet('isInterviewing', true);
    }

    public function test_subscription_toggle_demo_route(): void
    {
        $user = User::factory()->create([
            'subscription_tier' => 'free',
        ]);
        $this->actingAs($user);

        // Upgrade
        $this->post('/api/subscription/toggle-demo', ['action' => 'upgrade']);
        $this->assertTrue($user->fresh()->isPro());

        // Downgrade
        $this->post('/api/subscription/toggle-demo', ['action' => 'downgrade']);
        $this->assertTrue($user->fresh()->isFree());
    }

    public function test_expired_pro_user_is_immediately_blocked_from_generating_ai_bulk_tasks(): void
    {
        $expiredPro = User::factory()->create([
            'subscription_tier' => 'pro',
            'subscription_expires_at' => now()->subMinute(),
        ]);
        $this->actingAs($expiredPro);

        $skill = Skill::create([
            'user_id' => $expiredPro->id,
            'title' => 'Test Milestone',
            'target_days' => 7,
            'status' => 'active',
        ]);

        Livewire::test('skills.show', ['skill' => $skill])
            ->call('generateAiBulkTasks')
            ->assertDispatched('open-pro-modal');
    }

    public function test_confirm_setup_blocks_second_milestone_for_expired_or_free_user(): void
    {
        $expiredUser = User::factory()->create([
            'subscription_tier' => 'pro',
            'subscription_expires_at' => now()->subHour(),
        ]);
        $this->actingAs($expiredUser);

        // User already has 1 active skill
        Skill::create([
            'user_id' => $expiredUser->id,
            'title' => 'Running Milestone',
            'target_days' => 30,
            'status' => 'active',
        ]);

        // Trying to confirm setup of second skill should trigger open-pro-modal
        Livewire::test('skills.index')
            ->set('newSkillTitle', 'Second Milestone')
            ->set('newSkillDays', 30)
            ->call('confirmSetup')
            ->assertDispatched('open-pro-modal');

        $this->assertNull(Skill::where('title', 'Second Milestone')->first());
    }
}
