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

class WorkoutCardioAndColorUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_card_color_classes_logic(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. High priority normal (Merah)
        $highTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Submit Annual Tax',
            'priority' => 'high',
            'date' => now()->toDateString(),
        ]);

        // 2. Workout task normal priority (Orange)
        $workoutTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Lari Pagi Santai',
            'category' => 'Olahraga',
            'priority' => 'medium',
            'date' => now()->toDateString(),
        ]);

        // 3. Finance task normal priority (Hijau)
        $financeTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Catat Pengeluaran Bulanan',
            'category' => 'Keuangan',
            'priority' => 'low',
            'date' => now()->toDateString(),
        ]);

        // 4. Workout + High priority (Merah-Orange)
        $workoutHighTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Lari Marathon 21K',
            'category' => 'Olahraga',
            'priority' => 'high',
            'date' => now()->toDateString(),
        ]);

        // 5. Finance + High priority (Hijau-Merah)
        $financeHighTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Bayar Invoice Server',
            'category' => 'Finance',
            'priority' => 'high',
            'date' => now()->toDateString(),
        ]);

        // 6. Plain / Neutral task (Putih Polos)
        $plainTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Baca Buku Bab 3',
            'category' => 'Belajar',
            'priority' => 'medium',
            'date' => now()->toDateString(),
        ]);

        $component = Volt::test('tasks');

        // Check high priority (Merah)
        $highColors = $component->instance()->getTaskCardColorClasses($highTask);
        $this->assertEquals('high', $highColors['type']);
        $this->assertStringContainsString('border-l-red-500', $highColors['border']);
        $this->assertStringContainsString('bg-red-50', $highColors['bg']);

        // Check workout (Orange)
        $workoutColors = $component->instance()->getTaskCardColorClasses($workoutTask);
        $this->assertEquals('workout', $workoutColors['type']);
        $this->assertStringContainsString('border-l-orange-500', $workoutColors['border']);
        $this->assertStringContainsString('bg-orange-50', $workoutColors['bg']);

        // Check finance (Hijau)
        $financeColors = $component->instance()->getTaskCardColorClasses($financeTask);
        $this->assertEquals('finance', $financeColors['type']);
        $this->assertStringContainsString('border-l-emerald-500', $financeColors['border']);
        $this->assertStringContainsString('bg-emerald-50', $financeColors['bg']);

        // Check workout + high (Merah-Orange)
        $workoutHighColors = $component->instance()->getTaskCardColorClasses($workoutHighTask);
        $this->assertEquals('workout_high', $workoutHighColors['type']);
        $this->assertStringContainsString('border-l-red-500', $workoutHighColors['border']);
        $this->assertStringContainsString('from-red-50', $workoutHighColors['bg']);
        $this->assertStringContainsString('via-orange-50', $workoutHighColors['bg']);

        // Check finance + high (Hijau-Merah)
        $financeHighColors = $component->instance()->getTaskCardColorClasses($financeHighTask);
        $this->assertEquals('finance_high', $financeHighColors['type']);
        $this->assertStringContainsString('border-l-red-500', $financeHighColors['border']);
        $this->assertStringContainsString('from-red-50', $financeHighColors['bg']);
        $this->assertStringContainsString('via-emerald-50', $financeHighColors['bg']);

        // Check plain task
        $plainColors = $component->instance()->getTaskCardColorClasses($plainTask);
        $this->assertEquals('plain', $plainColors['type']);
        $this->assertStringContainsString('bg-white', $plainColors['bg']);
    }

    public function test_toggle_complete_intercepts_workout_tasks_to_open_modal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $workoutTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Lari 5K Pagi',
            'category' => 'Olahraga',
            'date' => now()->toDateString(),
            'is_completed' => false,
        ]);

        $normalTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Review Laporan Mingguan',
            'category' => 'Kerja',
            'date' => now()->toDateString(),
            'is_completed' => false,
        ]);

        // Attempting to toggleComplete on workoutTask intercepts: does NOT mark complete, opens workout modal instead!
        Volt::test('tasks')
            ->call('toggleComplete', $workoutTask->id)
            ->assertSet('showWorkoutModal', true)
            ->assertSet('activeWorkoutTaskId', $workoutTask->id);

        $this->assertFalse($workoutTask->fresh()->is_completed);

        // Attempting to toggleComplete on normalTask completes normally
        Volt::test('tasks')
            ->call('toggleComplete', $normalTask->id);

        $this->assertTrue($normalTask->fresh()->is_completed);
    }

    public function test_cardio_exercise_modes_intervals_and_pace_calculation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Latihan Kardio & Lari',
            'category' => 'Olahraga',
            'date' => now()->toDateString(),
        ]);

        $component = Volt::test('tasks')
            ->call('openWorkoutModal', $task->id)
            ->call('addExerciseToSession', 'Lari Outdoor 5K', 'Kardio');

        $exercises = $component->get('workoutExercises');
        $this->assertNotEmpty($exercises);
        $this->assertEquals('cardio', $exercises[0]['exercise_type']);
        $this->assertEquals('km', $exercises[0]['unit']);
        $this->assertEquals('standard', $exercises[0]['cardio_mode']);

        // Switch to interval mode
        $component->call('setCardioMode', 0, 'interval');
        $exercises = $component->get('workoutExercises');
        $this->assertEquals('interval', $exercises[0]['cardio_mode']);

        // Add interval laps
        $component->call('addSetToExercise', 0);
        $exercises = $component->get('workoutExercises');
        $this->assertCount(2, $exercises[0]['sets']);

        // Test pace calculation: 5 km in 25:00 minutes = 05:00 /km
        $calcPace = $component->instance()->calculatePaceString(5.0, 1500);
        $this->assertEquals('05:00', $calcPace);

        // Test pace calculation: 10 km in 48:30 minutes (2910s) = 04:51 /km
        $calcPaceFast = $component->instance()->calculatePaceString(10.0, 2910);
        $this->assertEquals('04:51', $calcPaceFast);

        // Recalculate pace dynamically when setting distance and formatted duration
        $component->set('workoutExercises.0.sets.0.distance_km', 5.0)
            ->set('workoutExercises.0.sets.0.duration_formatted', '25:00')
            ->call('recalculateCardioPace', 0, 0);

        $exercises = $component->get('workoutExercises');
        $this->assertEquals('05:00', $exercises[0]['sets'][0]['pace']);
        $this->assertEquals(1500, $exercises[0]['sets'][0]['duration_seconds']);
    }

    public function test_workout_session_memory_persistence_on_undone_and_reopen(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Lari Interval Sore',
            'category' => 'Olahraga',
            'date' => now()->toDateString(),
            'is_completed' => false,
        ]);

        $task->subTasks()->create([
            'title' => 'Interval Run',
            'order' => 1,
        ]);

        // Step 1: Open modal, complete 2 laps of 1.5 km each (total 3.0 km), 15 mins (900s)
        $component = Volt::test('tasks')
            ->call('openWorkoutModal', $task->id)
            ->call('setCardioMode', 0, 'interval')
            ->set('workoutExercises.0.sets.0.distance_km', 1.5)
            ->set('workoutExercises.0.sets.0.duration_formatted', '07:30')
            ->call('recalculateCardioPace', 0, 0)
            ->call('toggleSetCompleted', 0, 0)
            ->set('workoutExercises.0.sets.1.distance_km', 1.5)
            ->set('workoutExercises.0.sets.1.duration_formatted', '07:30')
            ->call('recalculateCardioPace', 0, 1)
            ->call('toggleSetCompleted', 0, 1)
            ->set('activeWorkoutNotes', 'Sesi interval di lapangan GBK.')
            ->call('finishWorkout', 900);

        $this->assertTrue($task->fresh()->is_completed);

        $session = WorkoutSession::where('task_id', $task->id)->first();
        $this->assertNotNull($session);
        $this->assertEquals(900, $session->duration_seconds);
        $this->assertEquals(3.0, (float)$session->total_distance_km);
        $this->assertEquals('Sesi interval di lapangan GBK.', $session->notes);

        // Step 2: User undones the task
        Volt::test('tasks')->call('toggleComplete', $task->id);
        $this->assertFalse($task->fresh()->is_completed);

        // Step 3: User reopens workout modal -> MEMORY loads previous session data!
        $reopened = Volt::test('tasks')
            ->call('openWorkoutModal', $task->id);

        $reopened->assertSet('activeWorkoutDurationSeconds', 900)
            ->assertSet('activeWorkoutNotes', 'Sesi interval di lapangan GBK.');

        $loadedExercises = $reopened->get('workoutExercises');
        $this->assertNotEmpty($loadedExercises);
        $this->assertEquals('Interval Run', $loadedExercises[0]['name']);
        $this->assertEquals('cardio', $loadedExercises[0]['exercise_type']);
        $this->assertEquals(1.5, $loadedExercises[0]['sets'][0]['distance_km']);
        $this->assertEquals('07:30', $loadedExercises[0]['sets'][0]['duration_formatted']);
        $this->assertEquals('05:00', $loadedExercises[0]['sets'][0]['pace']);

        // Step 4: User updates duration to 1200s and adds note, then finishes again
        $reopened->set('activeWorkoutNotes', 'Sesi interval di lapangan GBK (Update).')
            ->call('finishWorkout', 1200);

        // Ensure session was updated in place, NOT duplicated!
        $this->assertEquals(1, WorkoutSession::where('task_id', $task->id)->count());
        $updatedSession = WorkoutSession::where('task_id', $task->id)->first();
        $this->assertEquals(1200, $updatedSession->duration_seconds);
        $this->assertEquals('Sesi interval di lapangan GBK (Update).', $updatedSession->notes);
    }

    public function test_cardio_sessions_feed_into_sports_insights(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a workout session with cardio exercise
        $session = WorkoutSession::create([
            'user_id' => $user->id,
            'title' => 'Lari 10K',
            'duration_seconds' => 3000,
            'total_volume_kg' => 0,
            'total_distance_km' => 10.0,
            'completed_at' => now(),
        ]);

        $ex = WorkoutExercise::create([
            'workout_session_id' => $session->id,
            'exercise_name' => 'Lari Outdoor 10K',
            'muscle_group' => 'Kardio',
            'weight_unit' => 'km',
            'cardio_mode' => 'standard',
            'order' => 1,
        ]);

        WorkoutSet::create([
            'workout_exercise_id' => $ex->id,
            'set_number' => 1,
            'weight_kg' => 10.0,
            'reps' => 50,
            'distance_km' => 10.0,
            'duration_seconds' => 3000,
            'pace' => '05:00',
            'is_completed' => true,
        ]);

        // Test insights component calculation
        $insights = Volt::test('pages.insights');
        $fitnessData = $insights->viewData('fitnessData') ?? $insights->instance()->fitnessAnalytics();

        $this->assertNotNull($fitnessData);
        $this->assertGreaterThanOrEqual(10.0, $fitnessData['week_distance_km']);
        $this->assertGreaterThanOrEqual(10.0, $fitnessData['pr_longest_run_km']);
        $this->assertGreaterThanOrEqual(10.0, $fitnessData['year_total_distance']);
    }

    public function test_cardio_add_interval_set_and_polished_toasts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Lari Pagi Santai',
            'category' => 'Olahraga',
            'date' => now()->toDateString(),
        ]);

        $component = Volt::test('tasks')
            ->call('openWorkoutModal', $task->id)
            ->call('addExerciseToSession', 'Lari Santai 30 Menit', 'Kardio');

        $exercises = $component->get('workoutExercises');
        $this->assertCount(1, $exercises[0]['sets']);
        // Default set is standard 5.0 km / 30:00
        $this->assertEquals(5.0, $exercises[0]['sets'][0]['distance_km']);
        $this->assertEquals('30:00', $exercises[0]['sets'][0]['duration_formatted']);

        // Now test adding an interval set via dedicated button
        $component->call('addIntervalSetToExercise', 0);
        $exercises = $component->get('workoutExercises');
        $this->assertCount(2, $exercises[0]['sets']);

        $intervalSet = $exercises[0]['sets'][1];
        $this->assertEquals(0.4, $intervalSet['distance_km']);
        $this->assertEquals('01:30', $intervalSet['duration_formatted']);
        $this->assertEquals(90, $intervalSet['duration_seconds']);
        $this->assertEquals('03:45', $intervalSet['pace']);
        $this->assertEquals('interval', $intervalSet['set_type']);

        // Test closing modal triggers polished toast
        $component->call('closeWorkoutModal')
            ->assertDispatched('toast', function ($event, $params) {
                return ($params['type'] ?? '') === 'info' && str_contains($params['message'] ?? '', 'Sesi latihan ditutup');
            });

        // Test finishing modal with 1 completed interval set triggers polished completion toast (without emoji)
        $component->call('openWorkoutModal', $task->id)
            ->call('toggleSetCompleted', 0, 0)
            ->call('finishWorkout', 600)
            ->assertDispatched('toast', function ($event, $params) {
                return ($params['type'] ?? '') === 'success' && !str_contains($params['message'] ?? '', '🎉') && str_contains($params['message'] ?? '', 'berhasil diselesaikan');
            });
    }
}

