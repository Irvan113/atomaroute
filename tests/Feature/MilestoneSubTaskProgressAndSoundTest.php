<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Skill;
use App\Models\SkillTarget;
use App\Models\SubTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MilestoneSubTaskProgressAndSoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_milestone_allows_multiple_targets_in_the_same_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Mastering DevOps & Docker',
            'target_days' => 14,
            'current_day' => 1,
            'status' => 'in_progress',
            'mode' => 'sistematis',
        ]);

        // Target 1 on Day 1
        $target1 = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Dasar Docker Container',
            'status' => 'pending',
        ]);

        // Target 2 on Day 1 (Multiple targets in 1 day!)
        $target2 = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Setup Dockerfile & Port Binding',
            'status' => 'pending',
        ]);

        $this->assertEquals(2, $skill->targets()->where('day_number', 1)->count());

        // Test adding new target on Day 1 via Volt
        Volt::test('skills.show', ['skill' => $skill])
            ->set('newDayNumber', 1)
            ->set('newTitle', 'Docker Compose Multi-Container')
            ->call('createTarget')
            ->assertHasNoErrors();

        $this->assertEquals(3, $skill->fresh()->targets()->where('day_number', 1)->count());
    }

    public function test_subtasks_accurately_calculate_milestone_completion_rate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Belajar Vue 3 & Pinia',
            'target_days' => 2,
            'current_day' => 1,
            'status' => 'in_progress',
            'mode' => 'sistematis',
        ]);

        // Target 1 with 4 subtasks
        $target1 = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Vue 3 Reactivity',
            'status' => 'pending',
        ]);

        $st1 = $target1->subTasks()->create(['title' => 'Ref vs Reactive', 'is_completed' => false]);
        $st2 = $target1->subTasks()->create(['title' => 'Computed properties', 'is_completed' => false]);
        $st3 = $target1->subTasks()->create(['title' => 'Watch & WatchEffect', 'is_completed' => false]);
        $st4 = $target1->subTasks()->create(['title' => 'Template Refs', 'is_completed' => false]);

        // Target 2 with no subtasks
        $target2 = $skill->targets()->create([
            'day_number' => 2,
            'title' => 'Pinia State Store',
            'status' => 'pending',
        ]);

        // Initial progress: 0%
        $this->assertEquals(0, $skill->fresh()->calculated_progress);

        // Complete 2 of 4 subtasks in Target 1 (Target 1 is 50% done -> Skill progress: (0.5 + 0) / 2 = 25%)
        Volt::test('skills.show', ['skill' => $skill])
            ->call('toggleTargetSubTask', $target1->id, $st1->id)
            ->call('toggleTargetSubTask', $target1->id, $st2->id);

        $this->assertEquals(25, $skill->fresh()->calculated_progress);
        $this->assertEquals('pending', $target1->fresh()->status);

        // Complete remaining 2 subtasks in Target 1 -> Target 1 automatically completes!
        Volt::test('skills.show', ['skill' => $skill])
            ->call('toggleTargetSubTask', $target1->id, $st3->id)
            ->call('toggleTargetSubTask', $target1->id, $st4->id);

        $this->assertEquals('completed', $target1->fresh()->status);
        $this->assertEquals(50, $skill->fresh()->calculated_progress);
    }

    public function test_milestone_card_in_index_renders_granular_progress(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Python Data Science',
            'target_days' => 5,
            'current_day' => 1,
            'status' => 'in_progress',
        ]);

        $target = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Pandas & Numpy',
            'status' => 'pending',
        ]);

        $st = $target->subTasks()->create(['title' => 'DataFrame basics', 'is_completed' => true]);

        Volt::test('skills.index')
            ->assertSee('Python Data Science')
            ->assertSee('Subtask');
    }
}
