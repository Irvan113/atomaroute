<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceTelemetryAndCleanLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_includes_performance_and_database_metrics(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/tasks');
        $response->assertOk();

        // Verify performance telemetry headers
        $this->assertTrue($response->headers->has('X-Execution-Time'));
        $this->assertTrue($response->headers->has('X-Query-Count'));
        $this->assertTrue($response->headers->has('X-Memory-Peak'));
        $this->assertTrue($response->headers->has('Server-Timing'));
        $this->assertTrue($response->headers->has('Access-Control-Expose-Headers'));
    }

    public function test_api_journal_preview_endpoint_returns_json_instantly(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $note = Journal::create([
            'user_id' => $user->id,
            'title' => 'Catatan Proyek Rahasia',
            'content' => '<p>Target rilis aplikasi ATOMA pada akhir bulan ini dengan performa tinggi.</p>',
            'tags' => ['Atoma', 'Rilis'],
            'mood' => '🚀',
        ]);

        $response = $this->getJson("/api/journal/preview/{$note->id}");
        $response->assertOk();
        $response->assertJson([
            'id' => $note->id,
            'title' => 'Catatan Proyek Rahasia',
            'tags' => ['Atoma', 'Rilis'],
        ]);
        $this->assertGreaterThan(0, $response->json('word_count'));

        // Non-existent note returns 404 with friendly fallback
        $missing = $this->getJson('/api/journal/preview/999999');
        $missing->assertNotFound();
        $missing->assertJsonFragment(['title' => 'Catatan Tidak Ditemukan']);
    }

    public function test_performance_monitor_hud_is_rendered_for_admins_only(): void
    {
        $regularUser = User::factory()->create(['id' => 99, 'is_admin' => false]);
        $this->actingAs($regularUser);

        $response = $this->get('/journal');
        $response->assertOk();

        // 1. Regular user must NOT see the performance monitor HUD
        $response->assertDontSee('atomaPerformanceMonitor()', false);
        $response->assertDontSee('Atoma Performance & Server Telemetry', false);
        $response->assertSee('journal-note-link text-blue-600', false);
        $response->assertSee('fast-scroll-viewport', false);

        // 2. Admin user must see the performance monitor HUD
        $adminUser = User::factory()->create(['is_admin' => true]);
        $this->actingAs($adminUser);

        $adminResponse = $this->get('/journal');
        $adminResponse->assertOk();
        $adminResponse->assertSee('atomaPerformanceMonitor()', false);
        $adminResponse->assertSee('Atoma Performance & Server Telemetry', false);
        $adminResponse->assertSee('Beban Server', false);
        $adminResponse->assertSee('Livewire.hook', false);
        // FPS indicator must NOT be rendered (cleaned up per request)
        $adminResponse->assertDontSee("fps + ' FPS'", false);
        $adminResponse->assertDontSee('Kelancaran Layar', false);
    }

    public function test_store_button_only_in_left_navbar_and_not_in_topbar_or_settings(): void
    {
        $user = User::factory()->create(['coins' => 250]);
        $this->actingAs($user);

        // 1. Visit Tasks page: Left navbar has Store link, Top header does NOT have duplicate Store button
        $tasksResponse = $this->get('/tasks');
        $tasksResponse->assertOk();
        // Left navbar has Store
        $tasksResponse->assertSee('Store / Toko Hadiah (Desktop Sidebar)', false);
        // Top header does not have the duplicate store quick button
        $tasksResponse->assertDontSee('Desktop Right Actions (Store Button with Coins & Quick Access)', false);
        $tasksResponse->assertDontSee('Toko Hadiah &amp; Kostumisasi', false);

        // 2. Visit Settings/Profile page: Store buttons are removed from settings
        $settingsResponse = $this->get('/profile');
        $settingsResponse->assertOk();
        $settingsResponse->assertDontSee('Toko Hadiah & Store', false);
        $settingsResponse->assertDontSee('Buka Toko Hadiah & Kostumisasi (Store)', false);
        // Goals removed from settings UI
        $settingsResponse->assertDontSee('Target & Goals', false);
    }

    public function test_settings_renders_subscription_tab_for_free_and_pro_users(): void
    {
        // 1. Free user
        $freeUser = User::factory()->create([
            'subscription_tier' => 'free',
            'is_admin' => false,
        ]);
        $this->actingAs($freeUser);

        $response = $this->get('/profile?tab=subscription');
        $response->assertOk();
        $response->assertSee('Paket & Langganan', false);
        $response->assertSee('FREE STARTER', false);
        $response->assertSee('Rp 199.000', false);
        $response->assertSee('Rp 29.000', false);
        $response->assertSee('Perbandingan Fitur Free vs ATOMA PRO', false);

        // 2. Pro user
        $proUser = User::factory()->create([
            'subscription_tier' => 'pro',
            'subscription_expires_at' => now()->addMonths(6),
            'subscription_status' => 'active',
            'is_admin' => false,
        ]);
        $this->actingAs($proUser);

        $proResponse = $this->get('/profile?tab=subscription');
        $proResponse->assertOk();
        $proResponse->assertSee('PRO AKTIF', false);
        $proResponse->assertSee('Semua Fitur Premium Terbuka Penuh', false);
        $proResponse->assertSee('Unlimited Milestones', false);
        $proResponse->assertSee('AI Coach Unlimited', false);
    }

    public function test_mini_note_returns_full_content_and_has_draggable_virtualized_markup(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a massive note (25,000+ characters with 50 paragraphs)
        $largeParagraphs = '';
        for ($i = 1; $i <= 50; $i++) {
            $largeParagraphs .= "<p>Paragraf ke-{$i}: Latihan maraton dan triatlon membutuhkan komitmen harian yang konsisten serta pemantauan data performa secara detail dan berkelanjutan.</p>";
        }

        $largeNote = Journal::create([
            'user_id' => $user->id,
            'title' => 'Program Latihan Triatlon Panjang',
            'content' => $largeParagraphs,
            'tags' => ['Olahraga', 'Triatlon'],
            'mood' => '🏊‍♂️',
        ]);

        // 1. API preview must return 100% full content (NO truncation or capping)
        $response = $this->getJson("/api/journal/preview/{$largeNote->id}");
        $response->assertOk();
        $returnedHtml = $response->json('content_html');
        $this->assertEquals($largeParagraphs, $returnedHtml);
        $this->assertStringContainsString('Paragraf ke-50', $returnedHtml);
        $this->assertArrayNotHasKey('is_truncated', $response->json());

        // 2. Journal page contains draggable handle, clamping, and full content rendering
        $viewResponse = $this->get('/journal');
        $viewResponse->assertOk();
        $viewResponse->assertSee('x-ref="miniNotePopup"', false);
        $viewResponse->assertSee('startDragMiniNote($event)', false);
        $viewResponse->assertSee('cursor-grab', false);
        $viewResponse->assertSee('activeNotePreview.content_html', false);
        $viewResponse->assertSee('clampMiniNotePosition', false);
    }
}
