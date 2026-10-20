<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Task;
use App\Models\Journal;
use App\Models\Flashcard;
use Livewire\Volt\Volt;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MobileWidgetAndOverdueTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_quick_task_creates_today_task_in_zen_mode(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/widget/quick-task', [
            'title' => 'Tugas Cepat Mobile Widget',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Tugas Cepat Mobile Widget',
            'mode' => 'zen',
            'is_completed' => false,
        ]);
    }

    public function test_widget_toggle_task_flips_completion_and_evaluates_xp(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Selesaikan PR Matematika',
            'date' => Carbon::today()->toDateString(),
            'mode' => 'zen',
            'is_completed' => false,
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        $response = $this->postJson('/api/widget/toggle-task', [
            'task_id' => $task->id,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'is_completed' => true]);
        $this->assertTrue($task->fresh()->is_completed);
    }

    public function test_widget_quick_note_creates_journal_entry(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/widget/quick-note', [
            'content' => 'Catatan penting saat membaca bab 2 ekonomi.',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('journals', [
            'user_id' => $user->id,
            'content' => "Catatan penting saat membaca bab 2 ekonomi.",
        ]);
    }

    public function test_overdue_tasks_appear_in_today_view_and_can_be_dismissed_or_reason_recorded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $yesterday = Carbon::yesterday()->toDateString();
        $oldDate = Carbon::today()->subDays(3)->toDateString();

        $overdueTask1 = Task::create([
            'user_id' => $user->id,
            'title' => 'Task Kemarin yang Belum Selesai',
            'date' => $yesterday,
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        $overdueTask2 = Task::create([
            'user_id' => $user->id,
            'title' => 'Task Lama Tertunda Lainnya',
            'date' => $oldDate,
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        $today = Carbon::today()->toDateString();

        $test = Volt::test('tasks')
            ->set('selectedDate', $today);

        $overdueList = $test->get('overdueTasks');
        $this->assertCount(2, $overdueList);

        // Give reason for task 1 (date must remain yesterday)
        $test->call('openOverdueReasonModal', $overdueTask1->id)
            ->assertSet('showOverdueReasonModal', true)
            ->set('chosenOverdueReason', 'Kelelahan / Burnout')
            ->set('customOverdueReasonNote', 'Perlu istirahat sejenak')
            ->call('saveOverdueReason')
            ->assertSet('showOverdueReasonModal', false);

        $this->assertEquals('Kelelahan / Burnout', $overdueTask1->fresh()->skip_reason);
        $this->assertEquals('Perlu istirahat sejenak', $overdueTask1->fresh()->skip_reason_note);
        $this->assertEquals($yesterday, $overdueTask1->fresh()->date->toDateString());

        // Dismiss task 1 (removes from overdue notification tray, date preserved)
        $test->call('dismissOverdueTask', $overdueTask1->id);
        $this->assertTrue($overdueTask1->fresh()->is_overdue_dismissed);
        $this->assertEquals($yesterday, $overdueTask1->fresh()->date->toDateString());

        // Move task 2 to backlog
        $test->call('moveOverdueToBacklog', $overdueTask2->id);
        $this->assertNull($overdueTask2->fresh()->date);

        // After actions, overdueTasks count should be 0
        $this->assertCount(0, $test->get('overdueTasks'));
    }

    public function test_dismiss_all_overdue_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $yesterday = Carbon::yesterday()->toDateString();
        $twoDaysAgo = Carbon::today()->subDays(2)->toDateString();

        $task1 = Task::create([
            'user_id' => $user->id,
            'title' => 'Task Terlambat 1',
            'date' => $yesterday,
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        $task2 = Task::create([
            'user_id' => $user->id,
            'title' => 'Task Terlambat 2',
            'date' => $twoDaysAgo,
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        $today = Carbon::today()->toDateString();

        $test = Volt::test('tasks')
            ->set('selectedDate', $today);

        $this->assertCount(2, $test->get('overdueTasks'));

        $test->call('dismissAllOverdue');

        $this->assertCount(0, $test->get('overdueTasks'));
        $this->assertTrue($task1->fresh()->is_overdue_dismissed);
        $this->assertTrue($task2->fresh()->is_overdue_dismissed);
        // Dates should remain untouched
        $this->assertEquals($yesterday, $task1->fresh()->date->toDateString());
        $this->assertEquals($twoDaysAgo, $task2->fresh()->date->toDateString());
    }

    public function test_overdue_completed_task_receives_zero_exp_and_no_streak(): void
    {
        $user = User::factory()->create([
            'current_streak' => 0,
            'daily_xp_earned' => 0,
            'last_xp_date' => Carbon::today()->toDateString(),
        ]);
        $this->actingAs($user);

        $yesterday = Carbon::yesterday()->toDateString();

        $overdueTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Mengerjakan Laporan Praktikum Fisika',
            'date' => $yesterday,
            'priority' => 'medium', // Base XP would normally be 30
            'created_at' => Carbon::now()->subDays(2),
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        // Complete via Volt component
        $test = Volt::test('tasks')
            ->set('selectedDate', Carbon::today()->toDateString())
            ->call('toggleComplete', $overdueTask->id);

        $freshUser = $user->fresh();

        // Under strict overdue discipline: 0 XP!
        $this->assertEquals(0, $freshUser->daily_xp_earned);
        $this->assertEquals(0, $overdueTask->fresh()->earned_xp);

        // Streak for yesterday (past day) MUST NOT be credited!
        $this->assertFalse(\App\Models\StreakHistory::where('user_id', $user->id)->whereDate('date', $yesterday)->exists());

        // Streak for yesterday is NOT resurrected
        $this->assertEquals(0, $freshUser->current_streak);
    }

    public function test_overdue_task_completed_sets_is_completed_overdue_and_renders_special_overdue_checklist_styling(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $yesterday = Carbon::yesterday()->toDateString();
        $overdueTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas Coding Laravel Overdue',
            'date' => $yesterday,
            'priority' => 'high',
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        $test = Volt::test('tasks')
            ->set('selectedDate', Carbon::today()->toDateString());

        // Initial state: in overdueTasks as pending
        $this->assertFalse($overdueTask->fresh()->is_completed);
        $this->assertFalse($overdueTask->fresh()->is_completed_overdue);

        // Complete the overdue task
        $test->call('toggleComplete', $overdueTask->id);

        $freshTask = $overdueTask->fresh();
        $this->assertTrue($freshTask->is_completed);
        $this->assertTrue($freshTask->is_completed_overdue);

        // Component should render special overdue checklist styling on its scheduled date
        $test->call('selectDate', $yesterday);
        $test->assertSee('Overdue Checklist (0 EXP)');
        $test->assertSee('from-amber-500 to-orange-500');

        // On today's date, all overdue tasks are completed so the banner is hidden
        $test->call('selectDate', Carbon::today()->toDateString());
        $test->assertDontSee('Semua Tugas Tertunda Selesai');

        // Uncomplete (toggle back)
        $test->call('toggleComplete', $overdueTask->id);
        $freshTaskAfterUncheck = $overdueTask->fresh();
        $this->assertFalse($freshTaskAfterUncheck->is_completed);
        $this->assertFalse($freshTaskAfterUncheck->is_completed_overdue);
    }

    public function test_completing_overdue_recurring_task_generates_clean_normal_task_for_today(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $yesterday = Carbon::yesterday()->toDateString();
        $today = Carbon::today()->toDateString();

        $overdueRecurringTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Daily Overdue Recurring Task',
            'date' => $yesterday,
            'priority' => 'high',
            'mode' => 'sistematis',
            'is_recurring' => true,
            'recurrence_pattern' => 'daily',
            'is_completed' => false,
            'skip_reason' => 'Lainnya',
            'skip_reason_note' => 'Old note',
        ]);

        $st1 = $overdueRecurringTask->subTasks()->create([
            'title' => 'Subtask 1',
            'is_completed' => false,
        ]);
        $st2 = $overdueRecurringTask->subTasks()->create([
            'title' => 'Subtask 2',
            'is_completed' => false,
        ]);

        $test = Volt::test('tasks')
            ->set('selectedDate', $today);

        // Complete the overdue recurring task
        $test->call('toggleComplete', $overdueRecurringTask->id);

        $freshOverdue = $overdueRecurringTask->fresh();
        $this->assertTrue($freshOverdue->is_completed);
        $this->assertTrue($freshOverdue->is_completed_overdue);

        // A new task for today should have been generated
        $todayTask = Task::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->where('title', 'Daily Overdue Recurring Task')
            ->first();

        $this->assertNotNull($todayTask, 'A new recurring task should have been created for today.');
        $this->assertNotEquals($overdueRecurringTask->id, $todayTask->id);
        $this->assertFalse($todayTask->is_completed);
        $this->assertFalse($todayTask->is_completed_overdue);
        $this->assertFalse($todayTask->is_deleted_overdue);
        $this->assertFalse($todayTask->is_overdue_dismissed);
        $this->assertNull($todayTask->skip_reason);
        $this->assertNull($todayTask->skip_reason_note);
        $this->assertEquals(0, $todayTask->earned_xp);
        $this->assertNull($todayTask->xp_settled_at);
        $this->assertFalse($todayTask->isOverdue(), 'Today newly generated recurring task must NOT be considered overdue.');

        // Subtasks of the new task must be clean and uncompleted
        $this->assertCount(2, $todayTask->subTasks);
        foreach ($todayTask->subTasks as $newSt) {
            $this->assertFalse($newSt->is_completed);
            $this->assertEquals(0, $newSt->earned_xp);
            $this->assertNull($newSt->xp_settled_at);
        }
    }

    public function test_unchecking_task_unchecks_all_subtasks_and_reverts_subtask_xp(): void
    {
        $user = User::factory()->create(['pending_xp' => 0, 'daily_xp_earned' => 0]);
        $this->actingAs($user);

        $today = Carbon::today()->toDateString();
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Task With Subtasks Uncheck Test',
            'date' => $today,
            'priority' => 'high',
            'mode' => 'sistematis',
            'is_completed' => false,
        ]);

        $st1 = $task->subTasks()->create(['title' => 'Step 1', 'is_completed' => false]);
        $st2 = $task->subTasks()->create(['title' => 'Step 2', 'is_completed' => false]);

        $test = Volt::test('tasks')->set('selectedDate', $today);

        // 1. Complete task -> subtasks are completed and awarded XP
        $test->call('toggleComplete', $task->id);

        $this->assertTrue($task->fresh()->is_completed);
        $this->assertTrue($st1->fresh()->is_completed);
        $this->assertTrue($st2->fresh()->is_completed);
        $this->assertEquals(5, $st1->fresh()->earned_xp);
        $this->assertEquals(5, $st2->fresh()->earned_xp);

        // 2. Uncheck task -> subtasks must be unchecked and XP reverted
        $test->call('toggleComplete', $task->id);

        $this->assertFalse($task->fresh()->is_completed);
        $this->assertFalse($st1->fresh()->is_completed);
        $this->assertFalse($st2->fresh()->is_completed);
        $this->assertEquals(0, $st1->fresh()->earned_xp);
        $this->assertEquals(0, $st2->fresh()->earned_xp);
    }
}
