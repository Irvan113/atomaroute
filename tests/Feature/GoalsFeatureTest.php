<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoalsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_maximum_three_goals()
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test('profile.goals-manager');

        // Create 1st goal
        $component->set('title', 'Lari 4x Seminggu')
            ->set('target_value', 4)
            ->set('unit', 'sesi')
            ->set('is_public', true)
            ->call('saveGoal')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('goals', 1);

        // Create 2nd goal
        $component->set('title', 'Belajar Setiap Hari')
            ->set('target_value', 7)
            ->set('unit', 'hari')
            ->set('is_public', true)
            ->call('saveGoal')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('goals', 2);

        // Create 3rd goal
        $component->set('title', 'Dapat Nilai A')
            ->set('target_value', 1)
            ->set('unit', 'matkul')
            ->set('is_public', false)
            ->call('saveGoal')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('goals', 3);

        // Attempt to create 4th goal -> Should be rejected
        $component->set('title', 'Goal Keempat')
            ->set('target_value', 10)
            ->set('unit', 'kali')
            ->call('saveGoal')
            ->assertHasErrors(['goalLimit']);

        $this->assertDatabaseCount('goals', 3);
    }

    public function test_completing_task_linked_to_goal_increments_goal_progress()
    {
        $user = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $user->id,
            'title' => 'Konsisten Baca Buku',
            'target_value' => 4,
            'current_value' => 0,
            'unit' => 'bab',
            'is_public' => true,
            'status' => 'active',
        ]);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Membaca Bab 1',
            'date' => Carbon::today()->toDateString(),
            'is_completed' => false,
            'goal_id' => $goal->id,
            'mode' => 'simple',
        ]);

        Livewire::actingAs($user)
            ->test('tasks')
            ->call('toggleComplete', $task->id);

        $this->assertTrue($task->fresh()->is_completed);
        $this->assertEquals(1, $goal->fresh()->current_value);

        // Uncomplete task -> Goal progress decrements
        Livewire::actingAs($user)
            ->test('tasks')
            ->call('toggleComplete', $task->id);

        $this->assertFalse($task->fresh()->is_completed);
        $this->assertEquals(0, $goal->fresh()->current_value);
    }

    public function test_goal_completes_when_target_reached()
    {
        $user = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $user->id,
            'title' => 'Dapat Nilai A',
            'target_value' => 1,
            'current_value' => 0,
            'unit' => 'matkul',
            'is_public' => true,
            'status' => 'active',
        ]);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Ujian Akhir Semester',
            'date' => Carbon::today()->toDateString(),
            'is_completed' => false,
            'goal_id' => $goal->id,
            'mode' => 'simple',
        ]);

        Livewire::actingAs($user)
            ->test('tasks')
            ->call('toggleComplete', $task->id);

        $this->assertEquals(1, $goal->fresh()->current_value);
        $this->assertEquals('completed', $goal->fresh()->status);
    }

    public function test_public_goals_are_visible_on_public_profile_while_private_are_hidden()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $publicGoal = Goal::create([
            'user_id' => $user->id,
            'title' => 'Public Goal: Lari Rutin',
            'target_value' => 10,
            'current_value' => 3,
            'unit' => 'km',
            'is_public' => true,
            'status' => 'active',
        ]);

        $privateGoal = Goal::create([
            'user_id' => $user->id,
            'title' => 'Secret Private Goal: Nilai A',
            'target_value' => 1,
            'current_value' => 0,
            'unit' => 'matkul',
            'is_public' => false,
            'status' => 'active',
        ]);

        Livewire::test('pages.public-profile', ['user' => $user])
            ->assertSee('Public Goal: Lari Rutin')
            ->assertDontSee('Secret Private Goal: Nilai A');
    }

    public function test_user_can_toggle_goal_visibility()
    {
        $user = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $user->id,
            'title' => 'Belajar Coding',
            'target_value' => 30,
            'current_value' => 5,
            'unit' => 'hari',
            'is_public' => false,
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test('profile.goals-manager')
            ->call('toggleVisibility', $goal->id);

        $this->assertTrue((bool)$goal->fresh()->is_public);

        Livewire::actingAs($user)
            ->test('profile.goals-manager')
            ->call('toggleVisibility', $goal->id);

        $this->assertFalse((bool)$goal->fresh()->is_public);
    }

    public function test_user_can_dynamically_link_and_unlink_tasks_from_goal_manager()
    {
        $user = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $user->id,
            'title' => 'Belajar Coding',
            'target_value' => 5,
            'current_value' => 0,
            'unit' => 'proyek',
            'is_public' => true,
            'status' => 'active',
        ]);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Bikin Website Portofolio',
            'date' => Carbon::today()->toDateString(),
            'is_completed' => false,
            'goal_id' => null,
            'mode' => 'simple',
        ]);

        // Open modal & toggle link
        Livewire::actingAs($user)
            ->test('profile.goals-manager')
            ->call('openManageTasksModal', $goal->id)
            ->assertSet('managingGoalTasksId', $goal->id)
            ->call('toggleTaskLinkToGoal', $task->id);

        $this->assertEquals($goal->id, $task->fresh()->goal_id);

        // Toggle again to unlink
        Livewire::actingAs($user)
            ->test('profile.goals-manager')
            ->call('openManageTasksModal', $goal->id)
            ->call('toggleTaskLinkToGoal', $task->id);

        $this->assertNull($task->fresh()->goal_id);
    }

    public function test_user_can_sync_goal_progress_from_completed_linked_tasks()
    {
        $user = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $user->id,
            'title' => 'Konsisten Olahraga',
            'target_value' => 4,
            'current_value' => 0,
            'unit' => 'kali',
            'is_public' => true,
            'status' => 'active',
        ]);

        // Create 2 completed tasks and 1 pending task linked to this goal
        Task::create([
            'user_id' => $user->id,
            'title' => 'Lari Pagi',
            'date' => Carbon::today()->toDateString(),
            'is_completed' => true,
            'goal_id' => $goal->id,
            'mode' => 'simple',
        ]);

        Task::create([
            'user_id' => $user->id,
            'title' => 'Berenang',
            'date' => Carbon::today()->toDateString(),
            'is_completed' => true,
            'goal_id' => $goal->id,
            'mode' => 'simple',
        ]);

        Task::create([
            'user_id' => $user->id,
            'title' => 'Gym Malam',
            'date' => Carbon::today()->toDateString(),
            'is_completed' => false,
            'goal_id' => $goal->id,
            'mode' => 'simple',
        ]);

        // Call syncGoalProgressWithTasks
        Livewire::actingAs($user)
            ->test('profile.goals-manager')
            ->call('syncGoalProgressWithTasks', $goal->id);

        $this->assertEquals(2, $goal->fresh()->current_value);
    }
}
