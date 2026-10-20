<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TaskEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_edit_and_delete_task(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Initial Task Title',
            'description' => 'Initial description',
            'date' => now()->toDateString(),
            'mode' => 'zen',
        ]);

        $this->actingAs($user);

        // Test Edit Task
        Volt::test('tasks')
            ->call('editTask', $task->id)
            ->assertSet('editingTaskId', $task->id)
            ->assertSet('title', 'Initial Task Title')
            ->assertSet('showAddTaskModal', true)
            ->set('title', 'Updated Task Title')
            ->call('saveTask')
            ->assertSet('showAddTaskModal', false);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated Task Title',
        ]);

        // Test Delete Task
        Volt::test('tasks')
            ->call('deleteTask', $task->id);

        $this->assertSoftDeleted('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_user_can_add_toggle_and_delete_inline_subtasks(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Web Polish Project',
            'date' => now()->toDateString(),
            'mode' => 'sistematis',
        ]);

        $this->actingAs($user);

        // 1. Add Inline Subtask
        Volt::test('tasks')
            ->call('addInlineSubTask', $task->id, 'Perbaiki action bar task card')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sub_tasks', [
            'task_id' => $task->id,
            'title' => 'Perbaiki action bar task card',
            'is_completed' => false,
        ]);

        $subTask = $task->subTasks()->first();

        // 2. Toggle Subtask
        Volt::test('tasks')
            ->call('toggleSubTask', $task->id, $subTask->id)
            ->assertHasNoErrors();

        $this->assertTrue($subTask->fresh()->is_completed);

        // 3. Delete Subtask
        Volt::test('tasks')
            ->call('deleteSubTask', $task->id, $subTask->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('sub_tasks', [
            'id' => $subTask->id,
        ]);
    }

    public function test_task_mode_switching_and_rendering(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Design Zen Task',
            'date' => now()->toDateString(),
            'mode' => 'zen',
        ]);

        $this->actingAs($user);

        // Switch to sistematis
        Volt::test('tasks')
            ->call('switchTaskMode', $task->id)
            ->assertHasNoErrors();

        $this->assertEquals('sistematis', $task->fresh()->mode);

        // Switch back to zen
        Volt::test('tasks')
            ->call('switchTaskMode', $task->id)
            ->assertHasNoErrors();

        $this->assertEquals('zen', $task->fresh()->mode);
    }

    public function test_completing_task_auto_awards_pending_subtasks_xp(): void
    {
        $user = User::factory()->create(['pending_xp' => 0, 'daily_xp_earned' => 0]);
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Feature Implementation Sprint',
            'date' => now()->toDateString(),
            'priority' => 'high',
            'is_completed' => false,
        ]);

        $st1 = $task->subTasks()->create(['title' => 'Subtask 1', 'is_completed' => false]);
        $st2 = $task->subTasks()->create(['title' => 'Subtask 2', 'is_completed' => false]);

        $this->actingAs($user);

        // Completing parent task should also complete subtasks and award subtask XP
        Volt::test('tasks')
            ->call('toggleComplete', $task->id);

        $this->assertTrue($st1->fresh()->is_completed);
        $this->assertTrue($st2->fresh()->is_completed);
        $this->assertEquals(5, $st1->fresh()->earned_xp);
        $this->assertEquals(5, $st2->fresh()->earned_xp);
    }

    public function test_deleting_task_with_completed_subtasks_reverts_subtask_xp(): void
    {
        $user = User::factory()->create(['pending_xp' => 0, 'daily_xp_earned' => 0]);
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Feature Implementation Sprint 2',
            'date' => now()->toDateString(),
            'is_completed' => false,
        ]);

        $st = $task->subTasks()->create(['title' => 'Subtask to delete', 'is_completed' => false]);

        $this->actingAs($user);

        // Complete subtask
        Volt::test('tasks')
            ->call('toggleSubTask', $task->id, $st->id);

        $this->assertEquals(5, $st->fresh()->earned_xp);
        $this->assertEquals(5, $user->fresh()->pending_xp);

        // Now delete the entire parent task
        Volt::test('tasks')
            ->call('deleteTask', $task->id);

        $this->assertEquals(0, $user->fresh()->pending_xp);
    }
}

