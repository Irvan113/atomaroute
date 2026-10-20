<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AdminAiUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_ai_usage_page(): void
    {
        $user = User::factory()->create(['id' => 99, 'is_admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.ai-usage'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_and_render_ai_telemetry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        AiUsageLog::create([
            'user_id' => $admin->id,
            'feature' => 'task_decomposer',
            'model' => 'gemini-3.5-flash-lite',
            'prompt_tokens' => 150,
            'completion_tokens' => 100,
            'total_tokens' => 250,
            'execution_time_ms' => 450,
            'status' => 'success',
            'prompt_text' => 'Belajar Docker Container',
            'response_text' => '{"title":"Belajar Docker","subtasks":["Install Docker Engine","Buat Dockerfile"]}',
        ]);

        $comp = Volt::actingAs($admin)->test('pages.admin-ai-usage');
        $comp->assertSee('AI Telemetry & Intelligence Observability')
             ->assertSee('Belajar Docker Container')
             ->assertSee('gemini-3.5-flash-lite')
             ->assertSee('SUCCESS');
    }

    public function test_admin_can_inspect_ai_log_modal(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $log = AiUsageLog::create([
            'user_id' => $admin->id,
            'feature' => 'task_decomposer',
            'model' => 'gemini-3.5-flash-lite',
            'prompt_tokens' => 200,
            'completion_tokens' => 150,
            'total_tokens' => 350,
            'execution_time_ms' => 600,
            'status' => 'success',
            'prompt_text' => 'Bantu buat jadwal belajar UTBK',
            'response_text' => '{"title":"Rencana Belajar UTBK","category":"Study","priority":"high","estimated_minutes":60,"subtasks":["Matematika Dasar","Penalaran Umum"]}',
        ]);

        $comp = Volt::actingAs($admin)->test('pages.admin-ai-usage');
        $comp->call('openInspect', $log->id, 'output');
        $comp->assertSet('inspectLogId', $log->id);
        $comp->assertSee('Rencana Belajar UTBK');
        $comp->assertSee('Penalaran Umum');

        // Test tab switching
        $comp->call('setInspectTab', 'prompt');
        $comp->assertSet('inspectTab', 'prompt');
        $comp->assertSee('Bantu buat jadwal belajar UTBK');

        $comp->call('closeInspect');
        $comp->assertSet('inspectLogId', null);
    }

    public function test_admin_can_export_csv(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        AiUsageLog::create([
            'user_id' => $admin->id,
            'feature' => 'weekly_insight',
            'model' => 'gemini-3.5-flash-lite',
            'prompt_tokens' => 100,
            'completion_tokens' => 100,
            'total_tokens' => 200,
            'execution_time_ms' => 500,
            'status' => 'success',
            'prompt_text' => 'Review Mingguan',
            'response_text' => 'Weekly evaluation content',
        ]);

        $comp = Volt::actingAs($admin)->test('pages.admin-ai-usage');
        $response = $comp->call('exportCsv');

        $this->assertNotNull($response);
    }
}
