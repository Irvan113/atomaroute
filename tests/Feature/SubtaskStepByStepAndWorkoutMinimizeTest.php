<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SubtaskStepByStepAndWorkoutMinimizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_view_renders_subtask_sequential_focus_toggle_and_step_elements(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today()->toDateString();

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Mengerjakan Modul Pembelajaran',
            'description' => 'Mengerjakan latihan dan modul',
            'date' => $today,
            'mode' => 'sistematis',
        ]);

        $task->subTasks()->createMany([
            ['title' => 'Bab 1: Pengantar', 'is_completed' => false],
            ['title' => 'Bab 2: Setup Awal', 'is_completed' => false],
            ['title' => 'Bab 3: Implementasi', 'is_completed' => false],
        ]);

        $this->actingAs($user);

        Volt::test('tasks')
            ->assertSee('Bab 1: Pengantar')
            ->assertSee('1-per-1')
            ->assertSee('Selesai & Lanjut', false);
    }

    public function test_tasks_view_contains_workout_modal_minimize_button_and_floating_dock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Volt::test('tasks')
            ->set('showWorkoutModal', true)
            ->set('activeWorkoutTitle', 'Sesi Dada & Trisep');

        $component->assertSee('isMinimized')
            ->assertSee('Kecilkan (Minimize)')
            ->assertSee('Buka Layar Penuh')
            ->assertSee('Sesi Dada & Trisep');
    }

    public function test_profile_view_renders_subtask_mode_preference(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/profile');
        $response->assertStatus(200);
        $response->assertSee('Tampilan Subtugas Default');
        $response->assertSee('Fokus Beruntun (1-per-1)');
        $response->assertSee('atoma_task_subtask_mode');
    }
}
