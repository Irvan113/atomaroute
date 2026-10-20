<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Skill;
use App\Services\AiSkillPlannerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MilestoneMultiTaskAndAdaptiveCoachTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_skill_planner_generates_correct_calendar_days_when_starting_on_tuesday(): void
    {
        $planner = new AiSkillPlannerService();

        // 2026-10-13 is Tuesday (dayOfWeekIso = 2)
        $tuesdayDate = '2026-10-13';
        $carbonTuesday = Carbon::parse($tuesdayDate);
        $this->assertEquals(2, $carbonTuesday->dayOfWeekIso);

        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Belajar Desain UI/UX',
            'target_days' => 7,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => $tuesdayDate,
            'training_days' => [1, 2, 3, 4, 5], // Mon(1)-Fri(5) active; Sat(6) & Sun(7) rest days
        ]);

        $roadmap = $planner->generateFallbackRoadmap($skill);

        // Day 1: Tuesday (Iso 2) -> Active training day
        $this->assertFalse(str_contains($roadmap[0]['title'], 'Rest & Recovery'));

        // Day 2: Wednesday (Iso 3) -> Active
        $this->assertFalse(str_contains($roadmap[1]['title'], 'Rest & Recovery'));

        // Day 3: Thursday (Iso 4) -> Active
        $this->assertFalse(str_contains($roadmap[2]['title'], 'Rest & Recovery'));

        // Day 4: Friday (Iso 5) -> Active
        $this->assertFalse(str_contains($roadmap[3]['title'], 'Rest & Recovery'));

        // Day 5: Saturday (Iso 6) -> Rest day!
        $this->assertTrue(str_contains($roadmap[4]['title'], 'Rest & Recovery'));

        // Day 6: Sunday (Iso 7) -> Rest day!
        $this->assertTrue(str_contains($roadmap[5]['title'], 'Rest & Recovery'));

        // Day 7: Monday (Iso 1) -> Active
        $this->assertFalse(str_contains($roadmap[6]['title'], 'Rest & Recovery'));
    }

    public function test_milestone_bulk_task_creation_sequential_scheduling(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Mastering AI Engineering',
            'target_days' => 30,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => '2026-10-13',
        ]);

        $this->actingAs($user);

        $bulkInput = "1. Setup Environment & Python Virtualenv\n- Review Prompt Engineering Principles\n• Implement Vector DB Embeddings";

        Livewire::test('skills.show', ['skill' => $skill])
            ->set('showTaskModal', true)
            ->set('taskModalTab', 'bulk')
            ->set('bulkTasksInput', $bulkInput)
            ->set('bulkTaskDate', '2026-10-13')
            ->set('bulkTaskDateOption', 'sequential')
            ->set('bulkTaskPriority', 'high')
            ->set('bulkTaskEstimatedMinutes', 45)
            ->call('saveBulkMilestoneTasks')
            ->assertDispatched('toast');

        // Only query bulk tasks (category Milestone and no target id)
        $tasks = $user->tasks()->where('skill_id', $skill->id)->where('category', 'Milestone')->orderBy('date')->get();

        $this->assertCount(3, $tasks);
        
        // Assert prefix numbering/bullets stripped
        $this->assertEquals('Setup Environment & Python Virtualenv', $tasks[0]->title);
        $this->assertEquals('2026-10-13', $tasks[0]->date->format('Y-m-d'));
        $this->assertEquals('high', $tasks[0]->priority);
        $this->assertEquals(45, $tasks[0]->estimated_minutes);

        $this->assertEquals('Review Prompt Engineering Principles', $tasks[1]->title);
        $this->assertEquals('2026-10-14', $tasks[1]->date->format('Y-m-d'));

        $this->assertEquals('Implement Vector DB Embeddings', $tasks[2]->title);
        $this->assertEquals('2026-10-15', $tasks[2]->date->format('Y-m-d'));
    }

    public function test_milestone_bulk_task_creation_same_day_scheduling(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Sprint Design System',
            'target_days' => 14,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'zen',
            'start_date' => '2026-10-13',
        ]);

        $this->actingAs($user);

        $bulkInput = "Iconography Guidelines\nColor Palette Variables\nTypography Scale";

        Livewire::test('skills.show', ['skill' => $skill])
            ->set('showTaskModal', true)
            ->set('taskModalTab', 'bulk')
            ->set('bulkTasksInput', $bulkInput)
            ->set('bulkTaskDate', '2026-10-20')
            ->set('bulkTaskDateOption', 'same_day')
            ->set('bulkTaskPriority', 'medium')
            ->call('saveBulkMilestoneTasks');

        $tasks = $user->tasks()->where('skill_id', $skill->id)->where('category', 'Milestone')->get();

        $this->assertCount(3, $tasks);
        foreach ($tasks as $task) {
            $this->assertEquals('2026-10-20', $task->date->format('Y-m-d'));
            $this->assertEquals('zen', $task->mode);
            $this->assertEquals('medium', $task->priority);
        }
    }

    public function test_skills_index_syncs_ai_coach_dynamic_plan_parameters_and_start_date(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test('skills.index')
            ->call('startSetup')
            ->set('newSkillTitle', 'Belajar Golang')
            ->set('newSkillDays', 30)
            ->set('startDate', '2026-10-13')
            ->set('trainingDays', [1, 2, 3, 4, 5]);

        // Simulate AI coach dynamically expanding plan duration to 90 days after consultation
        $component->set('newSkillDays', 90)
            ->set('startDate', '2026-10-20')
            ->set('trainingDays', [1, 2, 3, 4, 5, 6])
            ->set('confirmationData', [
                'target_role' => 'Golang Backend Engineer',
                'difficulty_level' => 'Intermediate',
                'weekly_commitment' => '6 hari/minggu',
                'weekly_routine' => [
                    ['day' => 'Senin', 'type' => 'focus', 'desc' => 'Latihan Syntax'],
                    ['day' => 'Selasa', 'type' => 'focus', 'desc' => 'Concurrency & Goroutine'],
                    ['day' => 'Rabu', 'type' => 'focus', 'desc' => 'Channels & Mutex'],
                    ['day' => 'Kamis', 'type' => 'focus', 'desc' => 'HTTP & Gin Framework'],
                    ['day' => 'Jumat', 'type' => 'focus', 'desc' => 'Database SQL/GORM'],
                    ['day' => 'Sabtu', 'type' => 'review', 'desc' => 'Mini Project API'],
                    ['day' => 'Minggu', 'type' => 'rest', 'desc' => 'Mindful Rest']
                ],
                'phases' => [
                    ['name' => 'Dasar & Concurrency', 'days' => 'Hari 1-22', 'focus' => 'Syntax and Goroutine'],
                    ['name' => 'Backend API Development', 'days' => 'Hari 23-45', 'focus' => 'REST API'],
                    ['name' => 'Microservices Architecture', 'days' => 'Hari 46-68', 'focus' => 'gRPC & Kafka'],
                    ['name' => 'Production Capstone', 'days' => 'Hari 69-90', 'focus' => 'Deployment']
                ]
            ])
            ->call('confirmSetup');

        $skill = Skill::where('user_id', $user->id)->first();
        $this->assertNotNull($skill);
        $this->assertEquals(90, $skill->target_days);
        $this->assertEquals('2026-10-20', Carbon::parse($skill->start_date)->format('Y-m-d'));
        $this->assertEquals('active', $skill->status);
    }

    public function test_quick_add_subtask_adds_subtask_to_target(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Belajar Docker & K8s',
            'target_days' => 5,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => '2026-10-15',
        ]);

        $target = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Install Docker Desktop & CLI Basics',
            'description' => 'Pelajari instalasi dan perintah docker run',
            'status' => 'pending',
            'target_date' => '2026-10-15',
        ]);

        $this->actingAs($user);

        Livewire::test('skills.show', ['skill' => $skill])
            ->call('quickAddSubTask', $target->id, 'Buka terminal dan cek docker --version')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('sub_tasks', [
            'skill_target_id' => $target->id,
            'title' => 'Buka terminal dan cek docker --version',
            'is_completed' => false,
        ]);
    }

    public function test_extend_milestone_expands_days_and_creates_targets(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Mastering Next.js',
            'target_days' => 3,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => '2026-10-15',
        ]);

        // Day 1 to 3 targets
        for ($i = 1; $i <= 3; $i++) {
            $skill->targets()->create([
                'day_number' => $i,
                'title' => "Day {$i} Next.js",
                'status' => 'pending',
                'target_date' => Carbon::parse('2026-10-15')->addDays($i - 1),
            ]);
        }

        $this->actingAs($user);

        Livewire::test('skills.show', ['skill' => $skill])
            ->set('showExtendModal', true)
            ->set('extendAdditionalDays', 4)
            ->call('extendMilestone')
            ->assertDispatched('toast');

        $skill->refresh();
        $this->assertEquals(7, $skill->target_days);
        $this->assertCount(7, $skill->targets);

        // Day 7 target exists and has subtasks
        $day7 = $skill->targets()->where('day_number', 7)->first();
        $this->assertNotNull($day7);
        $this->assertGreaterThan(0, $day7->subTasks()->count());
    }

    public function test_coach_chat_modal_and_send_message(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Belajar Machine Learning',
            'target_days' => 10,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => '2026-10-15',
        ]);

        $this->actingAs($user);

        Livewire::test('skills.show', ['skill' => $skill])
            ->call('openCoachChat')
            ->assertSet('showCoachChatModal', true)
            ->set('coachUserMessage', 'Bagaimana tips konsistensi belajar?')
            ->call('sendCoachMessage')
            ->assertSet('coachUserMessage', '')
            ->call('closeCoachChat')
            ->assertSet('showCoachChatModal', false);
    }
}
