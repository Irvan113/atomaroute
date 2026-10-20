<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\SystemLog;
use App\Models\User;
use App\Models\UserFeedback;
use App\Services\SystemLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create(['id' => 888, 'is_admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(403);

        $response2 = $this->actingAs($user)->get('/admin/dashboard');
        $response2->assertStatus(403);
    }

    public function test_admin_can_access_admin_dashboard_and_render_modules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Create some sample data for KPIs
        SystemLogService::info('Database connection established', [], 'db.connect');
        UserFeedback::create([
            'user_id' => $admin->id,
            'type' => 'suggestion',
            'subject' => 'Tambahkan fitur Pomodoro custom sound',
            'message' => 'Tolong sediakan opsi upload lagu kustom.',
            'status' => 'pending',
        ]);
        AiUsageLog::create([
            'user_id' => $admin->id,
            'feature' => 'task_decomposer',
            'model' => 'gemini-3.5-flash-lite',
            'prompt_tokens' => 120,
            'completion_tokens' => 80,
            'total_tokens' => 200,
            'execution_time_ms' => 450,
            'status' => 'success',
            'prompt_text' => 'Bagi tugas belajar',
            'response_text' => '{"subtasks":["Tugas 1"]}',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('Admin Command Center');
        $response->assertSee('Selamat Datang di Admin Dashboard');
        $response->assertSee('System Logs & Audit', false);
        $response->assertSee('AI Telemetry & Token', false);
        $response->assertSee('Kotak Surat User');
        $response->assertSee('Integrasi Spotify API');

        // Test Livewire Volt Component directly
        Volt::actingAs($admin)->test('pages.admin-dashboard')
            ->assertSee('Tambahkan fitur Pomodoro custom sound')
            ->assertSee('Database connection established')
            ->assertSee('gemini-3.5-flash-lite');
    }

    public function test_user_id_1_automatically_granted_admin(): void
    {
        $user1 = User::factory()->create(['id' => 1, 'is_admin' => false]);

        $response = $this->actingAs($user1)->get(route('admin.dashboard'));
        $response->assertOk();

        $user1->refresh();
        $this->assertTrue((bool) $user1->is_admin);
    }

    public function test_admin_can_clear_cache_action(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Volt::actingAs($admin)->test('pages.admin-dashboard')
            ->call('clearAppCache')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('system_logs', [
            'category' => 'system',
            'action' => 'system.clear_cache',
        ]);
    }

    public function test_admin_can_generate_test_log(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Volt::actingAs($admin)->test('pages.admin-dashboard')
            ->call('generateTestLog')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('system_logs', [
            'category' => 'system',
            'action' => 'admin.test_pulse',
            'level' => 'INFO',
        ]);
    }

    public function test_admin_can_manage_spotify_credentials(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Volt::actingAs($admin)->test('pages.admin-dashboard')
            ->call('openSpotifyModal')
            ->assertSet('showSpotifyModal', true)
            ->set('spotifyClientId', 'test_client_id_1234567890')
            ->set('spotifyClientSecret', 'test_client_secret_0987654321')
            ->call('saveSpotifyCredentials')
            ->assertSet('showSpotifyModal', false)
            ->assertDispatched('toast');

        $this->assertEquals('test_client_id_1234567890', config('services.spotify.client_id'));
        $this->assertEquals('test_client_secret_0987654321', config('services.spotify.client_secret'));

        $this->assertDatabaseHas('system_logs', [
            'category' => 'security',
            'action' => 'admin.spotify_config_update',
        ]);
    }

    public function test_spotify_credentials_validation_fails_on_invalid_input(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Volt::actingAs($admin)->test('pages.admin-dashboard')
            ->set('spotifyClientId', 'short')
            ->set('spotifyClientSecret', 'short')
            ->call('saveSpotifyCredentials')
            ->assertHasErrors(['spotifyClientId', 'spotifyClientSecret']);
    }
}
