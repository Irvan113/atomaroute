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

    public function test_parse_task_list_hierarchical_structure_from_user_prompt(): void
    {
        $rawText = "Task list :
-1 Routine pagi : 
sub task : - Bangun jam 4 
- Gosok gigi .. 
-2 Jam 7-10 : 
sub task : - kuliah
- belajar coding 
- makan pagi 
- 3. Jam 12 makan siang 
-4 . jam 15 lanjut kuliah 
- 5 . routine malam 
sub task : - gosok gigi 
- cuci muka";

        $parsed = AiSkillPlannerService::parseTaskList($rawText);

        $this->assertCount(5, $parsed);
        
        // Task 1: Routine pagi
        $this->assertEquals('Routine pagi', $parsed[0]['title']);
        $this->assertCount(2, $parsed[0]['sub_tasks']);
        $this->assertEquals('Bangun jam 4', $parsed[0]['sub_tasks'][0]);
        $this->assertEquals('Gosok gigi', $parsed[0]['sub_tasks'][1]);

        // Task 2: Jam 7-10
        $this->assertEquals('Jam 7-10', $parsed[1]['title']);
        $this->assertCount(3, $parsed[1]['sub_tasks']);
        $this->assertEquals('kuliah', $parsed[1]['sub_tasks'][0]);
        $this->assertEquals('belajar coding', $parsed[1]['sub_tasks'][1]);
        $this->assertEquals('makan pagi', $parsed[1]['sub_tasks'][2]);

        // Task 3: Jam 12 makan siang (no subtasks)
        $this->assertEquals('Jam 12 makan siang', $parsed[2]['title']);
        $this->assertCount(0, $parsed[2]['sub_tasks']);

        // Task 4: jam 15 lanjut kuliah
        $this->assertEquals('jam 15 lanjut kuliah', $parsed[3]['title']);
        $this->assertCount(0, $parsed[3]['sub_tasks']);

        // Task 5: routine malam
        $this->assertEquals('routine malam', $parsed[4]['title']);
        $this->assertCount(2, $parsed[4]['sub_tasks']);
        $this->assertEquals('gosok gigi', $parsed[4]['sub_tasks'][0]);
        $this->assertEquals('cuci muka', $parsed[4]['sub_tasks'][1]);
    }

    public function test_create_bulk_tasks_for_target_creates_tasks_and_child_subtasks(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Master Rutinitas Sukses',
            'target_days' => 7,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => '2026-10-26',
        ]);

        $rawText = "Task list :
-1 Routine pagi : 
sub task : - Bangun jam 4 
- Gosok gigi
-2 Jam 7-10 : 
sub task : - kuliah
- belajar coding 
- 3. Jam 12 makan siang";

        $target = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Hari 1: Eksekusi Rutinitas',
            'description' => $rawText,
            'status' => 'pending',
            'pending_evaluation_data' => [
                'task_list' => AiSkillPlannerService::parseTaskList($rawText)
            ]
        ]);

        $created = AiSkillPlannerService::createBulkTasksForTarget($skill, $target, $user);

        $this->assertCount(3, $created);

        // Check first task
        $task1 = $created[0];
        $this->assertEquals('Routine pagi', $task1->title);
        $this->assertEquals($target->id, $task1->skill_target_id);
        $this->assertEquals($skill->id, $task1->skill_id);

        // Check subtasks for task 1
        $subs1 = $task1->subTasks;
        $this->assertCount(2, $subs1);
        $this->assertEquals('Bangun jam 4', $subs1[0]->title);
        $this->assertEquals($target->id, $subs1[0]->skill_target_id);
        $this->assertEquals($task1->id, $subs1[0]->task_id);

        // Check task 2
        $task2 = $created[1];
        $this->assertEquals('Jam 7-10', $task2->title);
        $this->assertCount(2, $task2->subTasks);

        // Check target's total subtasks relationship
        $this->assertEquals(4, $target->subTasks()->count());
    }

    public function test_two_way_sync_between_milestone_and_dashboard_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Fitness & Coding Bootcamp',
            'target_days' => 5,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => '2026-10-26',
        ]);

        $target = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Hari 1: Focus Day',
            'description' => 'Target hari pertama',
            'status' => 'pending',
        ]);

        // Create 2 tasks for this target
        $task1 = $user->tasks()->create([
            'skill_id' => $skill->id,
            'skill_target_id' => $target->id,
            'title' => 'Routine Pagi',
            'date' => '2026-10-26',
            'is_completed' => false,
        ]);
        $sub1 = $task1->subTasks()->create([
            'skill_target_id' => $target->id,
            'title' => 'Bangun jam 4',
            'is_completed' => false,
        ]);
        $sub2 = $task1->subTasks()->create([
            'skill_target_id' => $target->id,
            'title' => 'Gosok gigi',
            'is_completed' => false,
        ]);

        $task2 = $user->tasks()->create([
            'skill_id' => $skill->id,
            'skill_target_id' => $target->id,
            'title' => 'Kuliah & Coding',
            'date' => '2026-10-26',
            'is_completed' => false,
        ]);

        // 1. Toggling task 1 in skills.show completes task 1 and its child subtasks
        Livewire::test('skills.show', ['skill' => $skill])
            ->call('toggleTargetTask', $target->id, $task1->id);

        $task1->refresh();
        $sub1->refresh();
        $sub2->refresh();
        $this->assertTrue($task1->is_completed);
        $this->assertTrue($sub1->is_completed);
        $this->assertTrue($sub2->is_completed);
        
        // Target is still pending because task2 is not completed
        $target->refresh();
        $this->assertEquals('pending', $target->status);

        // 2. Toggling task 2 on Dashboard/Tasks (livewire:tasks) completes the entire target!
        Livewire::test('tasks')
            ->call('toggleComplete', $task2->id);

        $task2->refresh();
        $target->refresh();
        $this->assertTrue($task2->is_completed);
        $this->assertEquals('completed', $target->status);

        // 3. Unchecking a subtask from Dashboard reverts task and target to pending!
        Livewire::test('tasks')
            ->call('toggleSubTask', $task1->id, $sub1->id);

        $sub1->refresh();
        $task1->refresh();
        $target->refresh();
        $this->assertFalse($sub1->is_completed);
        $this->assertFalse($task1->is_completed);
        $this->assertEquals('pending', $target->status);
    }

    public function test_quick_add_task_and_subtask_in_skills_show(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Skill Fullstack Developer',
            'target_days' => 7,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => '2026-10-26',
        ]);

        $target = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Hari 1: Dasar Laravel',
            'status' => 'pending',
        ]);

        // Call quickAddTaskToTarget
        Livewire::test('skills.show', ['skill' => $skill])
            ->call('quickAddTaskToTarget', $target->id, 'Review Eloquent ORM')
            ->assertDispatched('toast');

        $task = $target->tasks()->first();
        $this->assertNotNull($task);
        $this->assertEquals('Review Eloquent ORM', $task->title);
        $this->assertEquals($skill->id, $task->skill_id);

        // Call quickAddSubTaskToTask
        Livewire::test('skills.show', ['skill' => $skill])
            ->call('quickAddSubTaskToTask', $target->id, $task->id, 'Pelajari HasMany relationship')
            ->assertDispatched('toast');

        $subTask = $task->subTasks()->first();
        $this->assertNotNull($subTask);
        $this->assertEquals('Pelajari HasMany relationship', $subTask->title);
        $this->assertEquals($target->id, $subTask->skill_target_id);
    }

    public function test_ai_skill_planner_day_blocking_segments_weekly_syllabus_cleanly_into_cards(): void
    {
        $planner = new AiSkillPlannerService();
        $user = User::factory()->create();

        // 2026-10-26 is Monday (dayOfWeekIso = 1)
        $startDate = '2026-10-26';
        $this->assertEquals(1, Carbon::parse($startDate)->dayOfWeekIso);

        $syllabusText = "
Rutinitas Harian:
- 04.00 - 04.30: Bangun & Minum Air
sub task : - Air hangat
- Wudhu & Sholat

Senin:
- Routine Pagi:
sub task : - Bangun jam 4
- Gosok gigi
- Jam 07.00 - 10.00: Kuliah Algoritma
- Sore 16.00: Lari 5KM
sub task : - Pemanasan dinamis
- Lari zona 2 30 menit

Selasa:
- Jam 08.00 - 11.00: Deep Work Coding Laravel
sub task : - Slicing UI mobile
- Sore 16.30: Workout Otot Kaki
sub task : - Squat 4 set
- Lunges 3 set

Rabu:
- Sore: Istirahat Total & Stretching

Kamis:
- Jam 08.00 - 10.00: Review Catatan Kuliah
- Sore 16.00: Renang 1000m

Jumat:
- Jam 09.00 - 11.00: Praktikum Lab
- Sore: Workout Upper Body Push-Pull

Sabtu:
- Pagi 06.00: Long Run Aerobik 10KM

Minggu:
- Pagi: Active Recovery Jalan Santai
";

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Master Disiplin 90 Hari',
            'target_days' => 14,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => $startDate,
            'interview_context' => [
                'syllabus_text' => $syllabusText,
            ],
        ]);

        $roadmap = $planner->generateFallbackRoadmap($skill);

        $this->assertCount(14, $roadmap);

        // Day 1: Monday (Senin)
        $day1 = $roadmap[0];
        $this->assertEquals(1, $day1['day_number']);
        $day1Tasks = $day1['task_list'];
        $day1Titles = array_column($day1Tasks, 'title');

        // Day 1 MUST contain Daily Routine + Monday tasks
        $this->assertTrue(collect($day1Titles)->contains(fn($t) => str_contains($t, 'Bangun & Minum Air')));
        $this->assertTrue(collect($day1Titles)->contains(fn($t) => str_contains($t, 'Kuliah Algoritma')));
        $this->assertTrue(collect($day1Titles)->contains(fn($t) => str_contains($t, 'Lari 5KM')));

        // Day 1 MUST NOT contain Tuesday (Deep Work Coding Laravel / Workout Otot Kaki)
        $this->assertFalse(collect($day1Titles)->contains(fn($t) => str_contains($t, 'Coding Laravel')));
        $this->assertFalse(collect($day1Titles)->contains(fn($t) => str_contains($t, 'Workout Otot Kaki')));
        // Day 1 MUST NOT contain Wednesday (Istirahat Total) or Kamis (Renang)
        $this->assertFalse(collect($day1Titles)->contains(fn($t) => str_contains($t, 'Renang 1000m')));

        // Day 2: Tuesday (Selasa)
        $day2 = $roadmap[1];
        $this->assertEquals(2, $day2['day_number']);
        $day2Tasks = $day2['task_list'];
        $day2Titles = array_column($day2Tasks, 'title');

        // Day 2 MUST contain Tuesday tasks
        $this->assertTrue(collect($day2Titles)->contains(fn($t) => str_contains($t, 'Coding Laravel')));
        $this->assertTrue(collect($day2Titles)->contains(fn($t) => str_contains($t, 'Workout Otot Kaki')));
        // Day 2 MUST NOT contain Monday tasks
        $this->assertFalse(collect($day2Titles)->contains(fn($t) => str_contains($t, 'Kuliah Algoritma')));
        $this->assertFalse(collect($day2Titles)->contains(fn($t) => str_contains($t, 'Lari 5KM')));

        // Day 3: Wednesday (Rabu) - Rest day/Stretching
        $day3 = $roadmap[2];
        $day3Tasks = $day3['task_list'];
        $day3Titles = array_column($day3Tasks, 'title');
        $this->assertTrue(collect($day3Titles)->contains(fn($t) => str_contains($t, 'Istirahat Total & Stretching')));

        // Day 8: Next week Monday (Senin) -> Cycles back to Monday schedule!
        $day8 = $roadmap[7];
        $this->assertEquals(8, $day8['day_number']);
        $day8Tasks = $day8['task_list'];
        $day8Titles = array_column($day8Tasks, 'title');
        $this->assertTrue(collect($day8Titles)->contains(fn($t) => str_contains($t, 'Kuliah Algoritma')));
        $this->assertTrue(collect($day8Titles)->contains(fn($t) => str_contains($t, 'Lari 5KM')));
        $this->assertFalse(collect($day8Titles)->contains(fn($t) => str_contains($t, 'Coding Laravel')));
    }

    public function test_create_bulk_tasks_for_target_filters_by_day_of_week_from_imported_syllabus(): void
    {
        $user = User::factory()->create();

        // 2026-10-26 is Monday
        $startDate = '2026-10-26';

        $syllabusText = "
Senin:
- Jam 07.00 - 10.00: Kuliah Algoritma
- Sore 16.00: Lari 5KM

Selasa:
- Jam 08.00 - 11.00: Deep Work Coding
- Sore 16.30: Workout Otot Kaki
";

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Bootcamp Disiplin',
            'target_days' => 7,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => $startDate,
            'interview_context' => [
                'syllabus_text' => $syllabusText,
            ],
        ]);

        // Target Day 1 (Monday)
        $targetDay1 = $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Hari 1 (Senin)',
            'status' => 'pending',
            'target_date' => '2026-10-26',
        ]);

        $createdDay1 = AiSkillPlannerService::createBulkTasksForTarget($skill, $targetDay1, $user);

        // Day 1 should only get Monday's 2 tasks!
        $this->assertCount(2, $createdDay1);
        $titlesDay1 = array_map(fn($t) => $t->title, $createdDay1);
        $this->assertTrue(collect($titlesDay1)->contains(fn($t) => str_contains($t, 'Kuliah Algoritma')));
        $this->assertTrue(collect($titlesDay1)->contains(fn($t) => str_contains($t, 'Lari 5KM')));
        $this->assertFalse(collect($titlesDay1)->contains(fn($t) => str_contains($t, 'Deep Work Coding')));

        // Target Day 2 (Tuesday)
        $targetDay2 = $skill->targets()->create([
            'day_number' => 2,
            'title' => 'Hari 2 (Selasa)',
            'status' => 'pending',
            'target_date' => '2026-10-27',
        ]);

        $createdDay2 = AiSkillPlannerService::createBulkTasksForTarget($skill, $targetDay2, $user);

        // Day 2 should only get Tuesday's 2 tasks!
        $this->assertCount(2, $createdDay2);
        $titlesDay2 = array_map(fn($t) => $t->title, $createdDay2);
        $this->assertTrue(collect($titlesDay2)->contains(fn($t) => str_contains($t, 'Deep Work Coding')));
        $this->assertTrue(collect($titlesDay2)->contains(fn($t) => str_contains($t, 'Workout Otot Kaki')));
        $this->assertFalse(collect($titlesDay2)->contains(fn($t) => str_contains($t, 'Kuliah Algoritma')));
    }
}
