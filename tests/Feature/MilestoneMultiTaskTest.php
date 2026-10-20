<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Skill;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MilestoneMultiTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_multiple_tasks_with_dynamic_dates_to_a_milestone(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $milestone = Skill::create([
            'user_id' => $user->id,
            'title' => 'Produktif di Bulan November',
            'target_days' => 30,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
        ]);

        $testComponent = Livewire::test('skills.show', ['skill' => $milestone])
            ->set('activeTab', 'tasks')
            ->call('openTaskModal')
            ->assertSet('showTaskModal', true)
            ->set('taskTitle', 'Task 1: Rencana Awal November')
            ->set('taskDescription', 'Menyiapkan draft awal strategi')
            ->set('taskDate', '2026-11-02')
            ->set('taskPriority', 'high')
            ->set('taskEstimatedMinutes', 45)
            ->call('saveMilestoneTask')
            ->assertSet('showTaskModal', false);

        $task = Task::where('title', 'Task 1: Rencana Awal November')->first();
        $this->assertNotNull($task);
        $this->assertEquals($milestone->id, $task->skill_id);
        $this->assertEquals('2026-11-02', $task->date->format('Y-m-d'));
        $this->assertEquals('high', $task->priority);
        $this->assertEquals(45, $task->estimated_minutes);
        $this->assertFalse($task->is_completed);

        // Add 9 more tasks dynamically for different dates in November
        for ($i = 2; $i <= 10; $i++) {
            $day = str_pad($i * 2, 2, '0', STR_PAD_LEFT);
            $testComponent->call('openTaskModal')
                ->set('taskTitle', "Task {$i}: Aktivitas Tanggal {$day} November")
                ->set('taskDate', "2026-11-{$day}")
                ->set('taskPriority', $i % 2 === 0 ? 'medium' : 'normal')
                ->call('saveMilestoneTask');
        }

        $this->assertEquals(10, $milestone->tasks()->whereNull('skill_target_id')->count());
        $this->assertEquals(0, $milestone->fresh()->calculated_progress);
    }

    public function test_milestone_progress_updates_dynamically_based_on_task_completions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $milestone = Skill::create([
            'user_id' => $user->id,
            'title' => 'Produktif di Bulan November',
            'target_days' => 30,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
        ]);

        // Create 10 tasks for this milestone
        $tasks = [];
        for ($i = 1; $i <= 10; $i++) {
            $tasks[] = Task::create([
                'user_id' => $user->id,
                'skill_id' => $milestone->id,
                'title' => "Tugas ke-{$i} Target November",
                'date' => "2026-11-" . str_pad($i, 2, '0', STR_PAD_LEFT),
                'priority' => 'normal',
                'is_completed' => false,
            ]);
        }

        $component = Livewire::test('skills.show', ['skill' => $milestone])
            ->set('activeTab', 'tasks');

        // Toggle first 5 tasks to complete
        for ($i = 0; $i < 5; $i++) {
            $component->call('toggleMilestoneTask', $tasks[$i]->id);
        }

        // 5 / 10 = 50%
        $this->assertEquals(50, $milestone->fresh()->calculated_progress);

        // Toggle the remaining 5 tasks to complete
        for ($i = 5; $i < 10; $i++) {
            $component->call('toggleMilestoneTask', $tasks[$i]->id);
        }

        // 10 / 10 = 100% and milestone marked completed
        $this->assertEquals(100, $milestone->fresh()->calculated_progress);
        $this->assertEquals('completed', $milestone->fresh()->status);
    }

    public function test_task_created_or_edited_in_tasks_page_can_be_linked_to_milestone(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $milestone = Skill::create([
            'user_id' => $user->id,
            'title' => 'Milestone Produktif',
            'target_days' => 14,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'sistematis',
        ]);

        Livewire::test('tasks')
            ->set('title', 'Tugas dari Halaman Task List')
            ->set('skill_id', $milestone->id)
            ->call('saveTask');

        $task = Task::where('title', 'Tugas dari Halaman Task List')->first();
        $this->assertNotNull($task);
        $this->assertEquals($milestone->id, $task->skill_id);
    }

    public function test_user_can_edit_and_delete_milestone_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $milestone = Skill::create([
            'user_id' => $user->id,
            'title' => 'Milestone Test Edit',
            'target_days' => 7,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
        ]);

        $task = Task::create([
            'user_id' => $user->id,
            'skill_id' => $milestone->id,
            'title' => 'Judul Sebelum Edit',
            'date' => '2026-11-10',
            'priority' => 'normal',
            'is_completed' => false,
        ]);

        Livewire::test('skills.show', ['skill' => $milestone])
            ->set('activeTab', 'tasks')
            ->call('openTaskModal', $task->id)
            ->assertSet('editingTaskId', $task->id)
            ->assertSet('taskTitle', 'Judul Sebelum Edit')
            ->set('taskTitle', 'Judul Sesudah Diedit')
            ->set('taskPriority', 'high')
            ->call('saveMilestoneTask');

        $this->assertEquals('Judul Sesudah Diedit', $task->fresh()->title);
        $this->assertEquals('high', $task->fresh()->priority);

        // Delete task
        Livewire::test('skills.show', ['skill' => $milestone])
            ->call('deleteMilestoneTask', $task->id);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }
}
