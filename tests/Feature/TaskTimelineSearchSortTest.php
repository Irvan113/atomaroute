<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TaskTimelineSearchSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_search_tasks_by_title_and_description(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today()->toDateString();

        $task1 = Task::create([
            'user_id' => $user->id,
            'title' => 'Wireframing and Brainstorming',
            'description' => 'Mendesain layout UI timeline',
            'date' => $today,
            'mode' => 'sistematis',
        ]);

        $task2 = Task::create([
            'user_id' => $user->id,
            'title' => 'Database Migration',
            'description' => 'Setup database schema',
            'date' => $today,
            'mode' => 'sistematis',
        ]);

        $this->actingAs($user);

        // Search for 'Wireframing'
        Volt::test('tasks')
            ->set('search', 'Wireframing')
            ->assertSee('Wireframing and Brainstorming')
            ->assertDontSee('Database Migration');

        // Search for 'schema' from description
        Volt::test('tasks')
            ->set('search', 'schema')
            ->assertSee('Database Migration')
            ->assertDontSee('Wireframing and Brainstorming');
    }

    public function test_user_can_filter_tasks_by_priority_and_category(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today()->toDateString();

        $highTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Critical Production Bug',
            'priority' => 'high',
            'category' => 'DevOps',
            'date' => $today,
            'mode' => 'sistematis',
        ]);

        $normalTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Read Clean Architecture',
            'priority' => 'normal',
            'category' => 'Belajar',
            'date' => $today,
            'mode' => 'sistematis',
        ]);

        $this->actingAs($user);

        // Filter high priority only
        Volt::test('tasks')
            ->set('filterPriority', 'high')
            ->assertSee('Critical Production Bug')
            ->assertDontSee('Read Clean Architecture');

        // Filter by category 'Belajar'
        Volt::test('tasks')
            ->set('filterPriority', '')
            ->set('filterCategory', 'Belajar')
            ->assertSee('Read Clean Architecture')
            ->assertDontSee('Critical Production Bug');
    }

    public function test_user_can_sort_tasks_by_priority_and_time(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today()->toDateString();

        $earlyTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Morning Standup',
            'start_time' => '08:00',
            'deadline' => '08:30',
            'priority' => 'normal',
            'date' => $today,
            'mode' => 'sistematis',
        ]);

        $lateHighTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Evening Critical Deploy',
            'start_time' => '20:00',
            'deadline' => '21:00',
            'priority' => 'high',
            'date' => $today,
            'mode' => 'sistematis',
        ]);

        $this->actingAs($user);

        // Test sort by time_asc: Morning Standup should come first
        $component = Volt::test('tasks')
            ->set('sortBy', 'time_asc');
        $tasks = $component->get('tasks');
        $this->assertEquals('Morning Standup', $tasks->first()->title);

        // Test sort by priority_desc: Evening Critical Deploy should come first
        $component = Volt::test('tasks')
            ->set('sortBy', 'priority_desc');
        $tasks = $component->get('tasks');
        $this->assertEquals('Evening Critical Deploy', $tasks->first()->title);
    }

    public function test_user_can_toggle_view_layout_and_reset_filters(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('tasks')
            ->assertSet('viewLayout', 'timeline')
            ->call('setViewLayout', 'card')
            ->assertSet('viewLayout', 'card')
            ->set('search', 'some query')
            ->set('filterPriority', 'high')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('filterPriority', '')
            ->assertSet('sortBy', 'default');
    }
}
