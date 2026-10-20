<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Skill;
use App\Models\SkillTarget;
use App\Models\SubTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SubTaskMilestoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_milestone_target_can_have_subtasks_and_toggle_completion(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Belajar Vue 3',
            'target_days' => 30,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
        ]);

        $target = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Hari 1: Setup Vite & Vue',
            'description' => 'Install npm packages',
            'status' => 'pending',
        ]);

        $subTask = $target->subTasks()->create([
            'title' => 'Run npx create-vue',
            'is_completed' => false,
        ]);

        $this->actingAs($user);

        Volt::test('skills.show', ['skill' => $skill])
            ->call('toggleTargetSubTask', $target->id, $subTask->id)
            ->assertHasNoErrors();

        $this->assertTrue($subTask->fresh()->is_completed);
    }
}
