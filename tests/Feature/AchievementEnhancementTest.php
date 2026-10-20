<?php

namespace Tests\Feature;

use App\Models\FocusSession;
use App\Models\Skill;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AchievementEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_achievements_api_returns_catalog_with_progress_and_stats(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create 2 completed tasks
        Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas Pertama',
            'date' => now()->toDateString(),
            'is_completed' => true,
        ]);
        Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas Kedua',
            'date' => now()->toDateString(),
            'is_completed' => true,
        ]);

        $response = $this->getJson('/api/user-achievements');

        $response->assertOk();
        $response->assertJsonStructure([
            'achievements' => [
                '*' => [
                    'code',
                    'title',
                    'description',
                    'category',
                    'category_name',
                    'svg_path',
                    'bonus_xp',
                    'bonus_coins',
                    'target_value',
                    'current_value',
                    'progress_percent',
                    'unlocked',
                    'is_equipped',
                ]
            ],
            'stats' => [
                'total',
                'unlocked',
                'percentage',
                'total_xp',
                'equipped_badge',
            ]
        ]);

        // first_task should be unlocked
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_code' => 'first_task',
        ]);
    }

    public function test_user_can_equip_and_unequip_unlocked_badge(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Unlock first_task
        $service = new AchievementService();
        $service->unlock($user, 'first_task');

        // Equip badge
        $response = $this->postJson('/api/user-achievements/equip', [
            'code' => 'first_task',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'equipped_badge' => 'first_task',
        ]);
        $this->assertEquals('first_task', $user->fresh()->equipped_badge);
        $this->assertNotNull($user->fresh()->equipped_badge_details);

        // Unequip badge
        $unequipRes = $this->postJson('/api/user-achievements/equip', [
            'code' => '',
        ]);

        $unequipRes->assertOk();
        $this->assertNull($user->fresh()->equipped_badge);
    }

    public function test_user_cannot_equip_locked_badge(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Try to equip locked badge
        $response = $this->postJson('/api/user-achievements/equip', [
            'code' => 'tasks_100',
        ]);

        $response->assertStatus(403);
        $this->assertNull($user->fresh()->equipped_badge);
    }

    public function test_milestone_completion_rate_above_80_unlocks_milestone_badges(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create 30-day skill with status completed (calculated_progress = 100)
        Skill::create([
            'user_id' => $user->id,
            'title' => 'Web Mastery 30D',
            'target_days' => 30,
            'current_day' => 30,
            'status' => 'completed',
            'mode' => 'systematic',
        ]);

        // Create 90-day skill with status completed
        Skill::create([
            'user_id' => $user->id,
            'title' => 'DevOps Quarter 90D',
            'target_days' => 90,
            'current_day' => 90,
            'status' => 'completed',
            'mode' => 'systematic',
        ]);

        $service = new AchievementService();
        $unlocked = $service->checkAndUnlock($user);
        $unlockedCodes = array_column($unlocked, 'code');

        $this->assertContains('milestone_30d', $unlockedCodes);
        $this->assertContains('milestone_90d', $unlockedCodes);
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_code' => 'milestone_30d',
        ]);
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_code' => 'milestone_90d',
        ]);
    }

    public function test_flowtime_and_workout_achievements_unlock_properly(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 65 minute flowtime session
        FocusSession::create([
            'user_id' => $user->id,
            'mode' => 'flowtime',
            'minutes' => 65,
            'xp_earned' => 50,
        ]);

        // Finished workout session
        WorkoutSession::create([
            'user_id' => $user->id,
            'title' => 'Leg Day PR',
            'duration_seconds' => 3600,
            'total_volume_kg' => 1250.0,
            'completed_at' => now(),
        ]);

        $service = new AchievementService();
        $unlocked = $service->checkAndUnlock($user);
        $unlockedCodes = array_column($unlocked, 'code');

        $this->assertContains('flowtime_60m', $unlockedCodes);
        $this->assertContains('workout_pr', $unlockedCodes);
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_code' => 'flowtime_60m',
        ]);
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_code' => 'workout_pr',
        ]);
    }

    public function test_task_completion_dispatches_achievement_unlocked(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Task Perdana Saya',
            'date' => now()->toDateString(),
            'is_completed' => false,
        ]);

        \Livewire\Volt\Volt::test('tasks')
            ->call('toggleComplete', $task->id)
            ->assertDispatched('achievement-unlocked');

        $this->assertTrue($task->fresh()->is_completed);
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_code' => 'first_task',
        ]);
    }

    public function test_achievement_celebration_component_is_present_in_app_layout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/tasks');
        $response->assertOk();
        $response->assertSee('achievementCelebration()', false);
        $response->assertSee('@achievement-unlocked.window="celebrate($event.detail)"', false);
        $response->assertSee('Pencapaian Baru Terbuka!', false);
        $response->assertSee('Pasang di Profil Sekarang', false);
    }
}
