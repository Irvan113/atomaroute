<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class OverdueDisciplineAndInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_task_with_passed_deadline_is_recognized_as_overdue_and_gives_zero_exp(): void
    {
        $user = User::factory()->create([
            'current_streak' => 2,
            'daily_xp_earned' => 0,
            'last_xp_date' => Carbon::today()->toDateString(),
        ]);
        $this->actingAs($user);

        // Task for today with deadline in the past
        $today = Carbon::today()->toDateString();
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Polish Web Layout',
            'date' => $today,
            'time' => '07:00',
            'deadline' => '08:00',
            'priority' => 'high',
            'mode' => 'zen',
            'is_completed' => false,
        ]);

        // Travel to 10:00 (after deadline 08:00)
        Carbon::setTestNow(Carbon::parse($today . ' 10:00:00'));

        $this->assertTrue($task->isOverdue());

        // Complete via Tasks Volt component
        $test = Volt::test('tasks')
            ->set('selectedDate', $today)
            ->call('toggleComplete', $task->id);

        $freshTask = $task->fresh();
        $this->assertTrue($freshTask->is_completed);
        $this->assertTrue($freshTask->is_completed_overdue);
        $this->assertEquals(0, $freshTask->earned_xp);

        // User daily XP earned should remain 0
        $this->assertEquals(0, $user->fresh()->daily_xp_earned);

        Carbon::setTestNow();
    }

    public function test_overdue_task_completion_does_not_increase_streak(): void
    {
        $user = User::factory()->create([
            'current_streak' => 3,
            'longest_streak' => 5,
        ]);
        $this->actingAs($user);

        $yesterday = Carbon::yesterday()->toDateString();
        $overdueTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas Kemarin yang Belum Selesai',
            'date' => $yesterday,
            'priority' => 'medium',
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        Volt::test('tasks')
            ->set('selectedDate', Carbon::today()->toDateString())
            ->call('toggleComplete', $overdueTask->id);

        $freshUser = $user->fresh();
        // Streak MUST NOT increment to 4
        $this->assertEquals(3, $freshUser->current_streak);
    }

    public function test_overdue_task_edit_is_locked_and_cannot_be_opened(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $yesterday = Carbon::yesterday()->toDateString();
        $overdueTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas Overdue Terkunci',
            'date' => $yesterday,
            'deadline' => '14:00',
            'priority' => 'high',
            'mode' => 'zen',
            'is_completed' => false,
        ]);

        $test = Volt::test('tasks')
            ->set('selectedDate', Carbon::today()->toDateString())
            ->call('editTask', $overdueTask->id);

        // Modal must not open
        $test->assertSet('showModal', false);
        $test->assertDispatched('toast', function ($event, $params) {
            return str_contains($params['message'], 'terkunci dan tidak dapat diedit jamnya')
                && $params['type'] === 'warning';
        });
    }

    public function test_overdue_task_can_be_deleted_and_is_flagged_as_deleted_overdue(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $yesterday = Carbon::yesterday()->toDateString();
        $overdueTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas Overdue yang Dihapus',
            'date' => $yesterday,
            'priority' => 'low',
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        Volt::test('tasks')
            ->set('selectedDate', Carbon::today()->toDateString())
            ->call('deleteTask', $overdueTask->id);

        // Task is soft-deleted
        $this->assertSoftDeleted('tasks', ['id' => $overdueTask->id]);

        // is_deleted_overdue is true
        $trashedTask = Task::onlyTrashed()->find($overdueTask->id);
        $this->assertNotNull($trashedTask);
        $this->assertTrue((bool) $trashedTask->is_deleted_overdue);
    }

    public function test_insights_tracks_and_displays_deleted_overdue_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $yesterday = Carbon::yesterday()->toDateString();

        // Create an overdue task and delete it
        $deletedOverdueTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Proyek Rahasia Overdue Dihapus',
            'date' => $yesterday,
            'deadline' => '17:00',
            'priority' => 'high',
            'mode' => 'zen',
            'is_completed' => false,
            'is_deleted_overdue' => true,
        ]);
        $deletedOverdueTask->delete();

        $test = Volt::test('pages.insights');

        $test->assertSet('deletedOverdueCount', 1);
        $test->assertSee('Tugas Overdue yang Dihapus');
        $test->assertSee('Proyek Rahasia Overdue Dihapus');
        $test->assertSee('1 Overdue Dihapus');
    }

    public function test_insights_mark_done_on_overdue_task_gives_zero_exp_and_no_streak(): void
    {
        $user = User::factory()->create([
            'current_streak' => 1,
            'daily_xp_earned' => 0,
        ]);
        $this->actingAs($user);

        $yesterday = Carbon::yesterday()->toDateString();
        $overdueTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Task Overdue di Selesaikan dari Insights',
            'date' => $yesterday,
            'priority' => 'high',
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        Volt::test('pages.insights')
            ->call('markDone', $overdueTask->id);

        $freshTask = $overdueTask->fresh();
        $this->assertTrue($freshTask->is_completed);
        $this->assertTrue($freshTask->is_completed_overdue);
        $this->assertEquals(0, $freshTask->earned_xp);
        $this->assertEquals(1, $user->fresh()->current_streak);
    }

    public function test_insights_delete_task_on_overdue_task_marks_is_deleted_overdue(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $yesterday = Carbon::yesterday()->toDateString();
        $overdueTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Task Overdue Dihapus dari Insights',
            'date' => $yesterday,
            'priority' => 'medium',
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        Volt::test('pages.insights')
            ->call('deleteTask', $overdueTask->id);

        $trashedTask = Task::onlyTrashed()->find($overdueTask->id);
        $this->assertNotNull($trashedTask);
        $this->assertTrue((bool) $trashedTask->is_deleted_overdue);
    }
}
