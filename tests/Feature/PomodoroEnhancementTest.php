<?php

namespace Tests\Feature;

use App\Models\FocusSession;
use App\Models\PomodoroPlaylist;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PomodoroEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_progressive_xp_tiers_and_coin_rewards()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Session 1: 25 mins * 2.5 = 63 XP, 0 Coin
        Volt::test('pomodoro-reward')
            ->call('rewardPomodoro', null, 25, null, 'work');

        $user->refresh();
        $this->assertEquals(63, $user->pending_xp);
        $this->assertEquals(0, $user->coins);
        $this->assertDatabaseHas('focus_sessions', [
            'user_id' => $user->id,
            'minutes' => 25,
            'mode' => 'work',
            'xp_earned' => 63,
            'coins_earned' => 0,
        ]);

        // Session 2: 25 mins * 2.5 = 63 XP (total 126 XP)
        Volt::test('pomodoro-reward')
            ->call('rewardPomodoro', null, 25, null, 'work');

        $user->refresh();
        $this->assertEquals(126, $user->pending_xp);
        $this->assertEquals(0, $user->coins);

        // Session 3: 25 mins -> hits remaining cap to 150 (24 XP, total 150 XP)
        Volt::test('pomodoro-reward')
            ->call('rewardPomodoro', null, 25, null, 'work');

        $user->refresh();
        $this->assertEquals(150, $user->pending_xp);
        $this->assertEquals(0, $user->coins);

        // Session 4: 150 cap reached (0 XP), awards 1 Coin (total 150 XP, 1 Coin)
        Volt::test('pomodoro-reward')
            ->call('rewardPomodoro', null, 25, null, 'work');

        $user->refresh();
        $this->assertEquals(150, $user->pending_xp);
        $this->assertEquals(1, $user->coins);
    }

    public function test_flowtime_mode_awards_proportional_blocks_and_records_mode()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 50 minutes of flowtime = 50 * 2.5 = 125 XP
        Volt::test('pomodoro-reward')
            ->call('rewardPomodoro', null, 50, 'rain', 'flowtime');

        $user->refresh();
        $this->assertEquals(125, $user->pending_xp);
        $this->assertDatabaseHas('focus_sessions', [
            'user_id' => $user->id,
            'minutes' => 50,
            'mode' => 'flowtime',
            'ambient_sound' => 'rain',
            'xp_earned' => 125,
        ]);
    }

    public function test_complete_task_directly_from_pomodoro()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'is_completed' => false,
            'title' => 'Belajar Algoritma & Struktur Data',
            'date' => Carbon::today('Asia/Jakarta')->toDateString(),
            'mode' => 'zen',
        ]);

        Volt::test('pomodoro-reward')
            ->call('completeTaskFromPomodoro', $task->id);

        $task->refresh();
        $this->assertTrue($task->is_completed);

        $user->refresh();
        $this->assertGreaterThan(0, $user->pending_xp);
    }

    public function test_playlist_sync_api_endpoints()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Initially empty
        $response = $this->getJson('/api/pomodoro/playlists');
        $response->assertStatus(200);
        $response->assertJson(['folders' => []]);

        // Save folders
        $folders = [
            [
                'id' => 'deep_work',
                'name' => 'Deep Work Vibes',
                'icon' => '⚡',
                'tracks' => [
                    ['video_id' => '5qap5aO4i9A', 'title' => 'Lofi Beats', 'author' => 'Lofi Girl']
                ]
            ]
        ];

        $postResponse = $this->postJson('/api/pomodoro/playlists', [
            'folders' => $folders
        ]);
        $postResponse->assertStatus(200);
        $postResponse->assertJson(['success' => true]);

        // Verify stored in DB
        $this->assertDatabaseHas('pomodoro_playlists', [
            'user_id' => $user->id,
        ]);

        // Verify GET returns saved folders
        $getAgain = $this->getJson('/api/pomodoro/playlists');
        $getAgain->assertStatus(200);
        $getAgain->assertJsonFragment(['name' => 'Deep Work Vibes']);
    }

    public function test_today_sessions_api_endpoint()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas Matematika Diskrit',
            'date' => Carbon::today('Asia/Jakarta')->toDateString(),
            'mode' => 'zen',
        ]);

        // Create 2 focus sessions today
        FocusSession::create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'minutes' => 25,
            'mode' => 'work',
            'xp_earned' => 25,
            'coins_earned' => 0,
            'created_at' => Carbon::now('Asia/Jakarta'),
        ]);

        FocusSession::create([
            'user_id' => $user->id,
            'task_id' => null,
            'minutes' => 45,
            'mode' => 'flowtime',
            'xp_earned' => 20,
            'coins_earned' => 0,
            'created_at' => Carbon::now('Asia/Jakarta'),
        ]);

        $response = $this->getJson('/api/pomodoro/today-sessions');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'sessions' => [
                '*' => ['id', 'time', 'minutes', 'mode', 'xp_earned', 'coins_earned', 'task_title']
            ],
            'summary' => ['total_sessions', 'total_minutes', 'total_xp', 'total_coins']
        ]);

        $data = $response->json();
        $this->assertCount(2, $data['sessions']);
        $this->assertEquals(2, $data['summary']['total_sessions']);
        $this->assertEquals(70, $data['summary']['total_minutes']);
        $this->assertEquals(45, $data['summary']['total_xp']);
    }

    public function test_toggle_subtask_from_pomodoro()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas dengan subtask',
            'date' => Carbon::today('Asia/Jakarta')->toDateString(),
            'mode' => 'zen',
        ]);

        $subtask = $task->subTasks()->create([
            'title' => 'Langkah 1: Riset pustaka',
            'is_completed' => false,
        ]);

        Volt::test('pomodoro-reward')
            ->dispatch('toggle-subtask-from-pomodoro', taskId: $task->id, subTaskId: $subtask->id)
            ->assertDispatched('subtask-toggled-global');

        $subtask->refresh();
        $this->assertTrue((bool)$subtask->is_completed);

        // Toggle again to uncomplete
        Volt::test('pomodoro-reward')
            ->dispatch('toggle-subtask-from-pomodoro', taskId: $task->id, subTaskId: $subtask->id)
            ->assertDispatched('subtask-toggled-global');

        $subtask->refresh();
        $this->assertFalse((bool)$subtask->is_completed);
    }

    public function test_pomodoro_view_renders_float_and_pip_prev_next_track_controls()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $view = $this->blade('<x-pomodoro-timer :tasks="[]" :user-streak="3" />');

        // Verify float controls contain playPrevTrack and playNextTrack
        $view->assertSee('playPrevTrack()');
        $view->assertSee('playNextTrack()');
        $view->assertSee('pipAudioPrev');
        $view->assertSee('pipAudioNext');
    }
}

