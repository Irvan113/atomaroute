<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use App\Models\WorkoutSession;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WorkoutTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_workout_button_rendered_for_fitness_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $fitnessTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Latihan Otot Dada',
            'category' => 'Olahraga',
            'date' => now()->toDateString(),
            'mode' => 'sistematis',
        ]);

        $workTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Meeting Sprint Review',
            'category' => 'Kerja',
            'date' => now()->toDateString(),
            'mode' => 'sistematis',
        ]);

        Volt::test('tasks')
            ->assertSee('Latihan Otot Dada')
            ->assertSee('Meeting Sprint Review')
            ->assertSee('openWorkoutModal(' . $fitnessTask->id . ')')
            ->assertDontSee('openWorkoutModal(' . $workTask->id . ')');
    }

    public function test_open_and_close_workout_modal_from_task_with_subtasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Calisthenics Push Day',
            'category' => 'Olahraga',
            'date' => now()->toDateString(),
            'mode' => 'zen',
        ]);

        // Add 2 custom movements as subtasks
        $task->subTasks()->createMany([
            ['title' => 'Parallel Bar Dips'],
            ['title' => 'Diamond Push Up'],
        ]);

        Volt::test('tasks')
            ->call('openWorkoutModal', $task->id)
            ->assertSet('showWorkoutModal', true)
            ->assertSet('activeWorkoutTaskId', $task->id)
            ->assertSet('activeWorkoutTitle', 'Latihan: Calisthenics Push Day')
            ->assertCount('workoutExercises', 2)
            ->assertSee('Parallel Bar Dips')
            ->assertSee('Diamond Push Up')
            ->call('closeWorkoutModal')
            ->assertSet('showWorkoutModal', false)
            ->assertSet('activeWorkoutTaskId', null)
            ->assertCount('workoutExercises', 0);
    }

    public function test_add_and_remove_exercises_and_sets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $test = Volt::test('tasks')
            ->call('openWorkoutModal')
            ->assertSet('showWorkoutModal', true);

        // Add 2nd exercise
        $test->call('addExerciseToSession', 'Incline Dumbbell Press', 'Dada');
        $this->assertCount(2, $test->get('workoutExercises'));
        $this->assertEquals('Incline Dumbbell Press', $test->get('workoutExercises')[1]['name']);

        // Add set to the 2nd exercise (initial sets count is 3)
        $this->assertCount(3, $test->get('workoutExercises')[1]['sets']);
        $test->call('addSetToExercise', 1);
        $this->assertCount(4, $test->get('workoutExercises')[1]['sets']);
        $this->assertEquals(4, $test->get('workoutExercises')[1]['sets'][3]['set_number']);

        // Remove a set from 2nd exercise
        $test->call('removeSetFromExercise', 1, 0);
        $this->assertCount(3, $test->get('workoutExercises')[1]['sets']);
        $this->assertEquals(1, $test->get('workoutExercises')[1]['sets'][0]['set_number']);

        // Remove 1st exercise
        $test->call('removeExerciseFromSession', 0);
        $this->assertCount(1, $test->get('workoutExercises'));
        $this->assertEquals('Incline Dumbbell Press', $test->get('workoutExercises')[0]['name']);
    }

    public function test_swap_exercise_in_workout_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $test = Volt::test('tasks')
            ->call('openWorkoutModal')
            ->assertSet('showWorkoutModal', true);

        // Initially defaults to Bench Press (Barbell)
        $this->assertEquals('Bench Press (Barbell)', $test->get('workoutExercises')[0]['name']);

        // Swap exercise 0 to Barbell Squat
        $test->call('swapExercise', 0, 'Barbell Squat', 'Kaki');

        $this->assertEquals('Barbell Squat', $test->get('workoutExercises')[0]['name']);
        $this->assertEquals('Kaki', $test->get('workoutExercises')[0]['muscle']);
        $test->assertDispatched('toast');
    }

    public function test_toggle_set_completion_and_detect_personal_record(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Seed historical session with Bench Press 50kg x 10 reps
        $pastSession = WorkoutSession::create([
            'user_id' => $user->id,
            'title' => 'Past Workout',
            'duration_seconds' => 1800,
            'total_volume_kg' => 1500,
            'xp_earned' => 100,
            'completed_at' => now()->subDays(2),
        ]);

        $pastExercise = WorkoutExercise::create([
            'workout_session_id' => $pastSession->id,
            'exercise_name' => 'Bench Press (Barbell)',
            'muscle_group' => 'Dada',
            'order' => 1,
        ]);

        WorkoutSet::create([
            'workout_exercise_id' => $pastExercise->id,
            'set_number' => 1,
            'weight_kg' => 50,
            'reps' => 10,
            'set_type' => 'normal',
            'is_completed' => true,
        ]);

        // Start new workout session
        $test = Volt::test('tasks')
            ->call('openWorkoutModal');

        // Check previous PR was loaded into exercise state
        $exercises = $test->get('workoutExercises');
        $this->assertEquals(50, $exercises[0]['previous_max_weight']);
        $this->assertEquals(10, $exercises[0]['previous_max_reps']);

        // Set weight to 55kg (higher than 50kg)
        $exercises[0]['sets'][0]['weight_kg'] = 55;
        $exercises[0]['sets'][0]['reps'] = 10;
        $test->set('workoutExercises', $exercises);

        // Toggle completed
        $test->call('toggleSetCompleted', 0, 0);

        $updatedExercises = $test->get('workoutExercises');
        $this->assertTrue($updatedExercises[0]['sets'][0]['is_completed']);
        $this->assertTrue($updatedExercises[0]['sets'][0]['is_pr']);
        $test->assertDispatched('toast');
    }

    public function test_finish_workout_calculates_volume_awards_xp_and_completes_task(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Leg Day Full Workout',
            'category' => 'Olahraga',
            'date' => now()->toDateString(),
            'mode' => 'zen',
            'is_completed' => false,
        ]);

        $test = Volt::test('tasks')
            ->call('openWorkoutModal', $task->id);

        // Task title contains 'Leg', so Barbell Squat is the first exercise
        $exercises = $test->get('workoutExercises');
        $this->assertEquals('Barbell Squat', $exercises[0]['name']);

        // Configure 2 completed sets:
        // Set 1: 60kg x 10 reps = 600 kg
        // Set 2: 70kg x 8 reps = 560 kg
        // Total Volume = 1160 kg
        $exercises[0]['sets'][0]['weight_kg'] = 60;
        $exercises[0]['sets'][0]['reps'] = 10;
        $exercises[0]['sets'][0]['is_completed'] = true;

        $exercises[0]['sets'][1]['weight_kg'] = 70;
        $exercises[0]['sets'][1]['reps'] = 8;
        $exercises[0]['sets'][1]['is_completed'] = true;

        $test->set('workoutExercises', $exercises);

        // Finish workout after 1200 seconds (20 mins)
        $test->call('finishWorkout', 1200)
            ->assertSet('showWorkoutModal', false)
            ->assertSet('showWorkoutSummaryModal', true);

        // Assert DB records
        $this->assertDatabaseHas('workout_sessions', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'total_volume_kg' => 1160,
            'duration_seconds' => 1200,
        ]);

        $session = WorkoutSession::where('user_id', $user->id)->first();
        $this->assertNotNull($session);
        $this->assertEquals(1160, $session->total_volume_kg);
        $this->assertGreaterThan(50, $session->xp_earned);

        // Assert exercises and sets
        $this->assertDatabaseHas('workout_exercises', [
            'workout_session_id' => $session->id,
            'exercise_name' => 'Barbell Squat',
        ]);

        // Assert linked task is marked as completed
        $this->assertTrue($task->fresh()->is_completed);

        // Summary modal data verification
        $summary = $test->get('workoutSummaryData');
        $this->assertNotNull($summary);
        $this->assertEquals('20:00', $summary['duration_formatted']);
        $this->assertEquals(2, $summary['total_sets']);
        $this->assertEquals('1.160', $summary['total_volume_kg']);
    }

    public function test_finish_workout_requires_at_least_one_completed_set(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $test = Volt::test('tasks')
            ->call('openWorkoutModal')
            ->call('finishWorkout', 60);

        $test->assertDispatched('toast');
        $this->assertEquals(0, WorkoutSession::count());
    }

    public function test_workout_split_selection_and_custom_exercise_in_add_task(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Simulate choosing category Olahraga -> Calisthenics -> Pull split
        $test = Volt::test('tasks')
            ->set('category', 'Olahraga')
            ->call('setWorkoutType', 'calisthenics')
            ->call('selectWorkoutSplit', 'calisthenics', 'Pull (Punggung/Bisep)')
            ->set('customWorkoutExerciseInput', 'Muscle Up Bersih')
            ->call('addCustomExerciseToTask')
            ->call('saveTask')
            ->assertHasNoErrors();

        $task = Task::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($task);
        $this->assertEquals('Olahraga', $task->category);
        $this->assertStringContainsString('Pull', $task->title);

        // Verify subtasks were created for the task
        $this->assertDatabaseHas('sub_tasks', [
            'task_id' => $task->id,
            'title' => 'Pull Up',
        ]);
        $this->assertDatabaseHas('sub_tasks', [
            'task_id' => $task->id,
            'title' => 'Muscle Up Bersih',
        ]);
    }

    public function test_exercise_weight_unit_support_and_persistence(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $test = Volt::test('tasks')
            ->call('openWorkoutModal');

        // Initial exercise defaults to Bench Press with kg unit
        $exercises = $test->get('workoutExercises');
        $this->assertEquals('kg', $exercises[0]['unit']);

        // Switch exercise 0 to lbs
        $test->call('setExerciseUnit', 0, 'lbs');
        $exercises = $test->get('workoutExercises');
        $this->assertEquals('lbs', $exercises[0]['unit']);

        // Switch exercise 0 to bw
        $test->call('setExerciseUnit', 0, 'bw');
        $exercises = $test->get('workoutExercises');
        $this->assertEquals('bw', $exercises[0]['unit']);

        // Add a bodyweight exercise (Pull Up) - should automatically detect 'bw' unit
        $test->call('addExerciseToSession', 'Pull Up', 'Punggung');
        $exercises = $test->get('workoutExercises');
        $this->assertCount(2, $exercises);
        $this->assertEquals('Pull Up', $exercises[1]['name']);
        $this->assertEquals('bw', $exercises[1]['unit']);

        // Complete 1 set for Pull Up with BW + 10 kg
        $exercises[1]['sets'][0]['weight_kg'] = 10;
        $exercises[1]['sets'][0]['reps'] = 12;
        $exercises[1]['sets'][0]['is_completed'] = true;
        $test->set('workoutExercises', $exercises);

        // Finish workout
        $test->call('finishWorkout', 600);

        // Verify WorkoutExercise has weight_unit persisted
        $this->assertDatabaseHas('workout_exercises', [
            'exercise_name' => 'Pull Up',
            'weight_unit' => 'bw',
        ]);
        $this->assertDatabaseHas('workout_exercises', [
            'exercise_name' => 'Bench Press (Barbell)',
            'weight_unit' => 'bw',
        ]);
    }
}
