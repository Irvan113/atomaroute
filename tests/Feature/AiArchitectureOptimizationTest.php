<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Skill;
use App\Models\AiUsageLog;
use App\Models\DailySummary;
use App\Models\AiInsight;
use App\Services\AiLoggerService;
use App\Services\AiSkillPlannerService;
use App\Services\AtomaInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AiArchitectureOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_model_pool_prioritizes_high_rpd_models(): void
    {
        $pool = AiLoggerService::getModelPool();

        $this->assertIsArray($pool);
        $this->assertGreaterThanOrEqual(2, count($pool));

        // Priority 1: 500 RPD & 15 RPM models must come first
        $this->assertEquals('gemini-3.5-flash-lite', $pool[0]);
        $this->assertEquals('gemini-3.1-flash-lite', $pool[1]);

        $this->assertEquals('gemini-3.5-flash-lite', AiLoggerService::getPrimaryModel());
    }

    public function test_ai_logger_service_records_token_telemetry(): void
    {
        $user = User::factory()->create();

        $log = AiLoggerService::log(
            userId: $user->id,
            feature: 'task_decomposer',
            promptText: 'Belajar Kubernetes Pods',
            responseText: '{"title":"Belajar Kubernetes"}',
            model: 'gemini-3.5-flash-lite',
            promptTokens: 45,
            completionTokens: 25,
            executionTimeMs: 350,
            status: 'success'
        );

        $this->assertDatabaseHas('ai_usage_logs', [
            'id' => $log->id,
            'user_id' => $user->id,
            'feature' => 'task_decomposer',
            'model' => 'gemini-3.5-flash-lite',
            'prompt_tokens' => 45,
            'completion_tokens' => 25,
            'total_tokens' => 70,
            'status' => 'success',
        ]);
    }

    public function test_ai_skill_planner_full_roadmap_generates_all_days(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Belajar Golang Microservices',
            'prompt' => 'Belajar backend Golang dari nol',
            'target_days' => 14,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
        ]);

        $planner = new AiSkillPlannerService();
        $roadmap = $planner->generateFallbackRoadmap($skill);

        $this->assertCount(14, $roadmap);
        $this->assertEquals(1, $roadmap[0]['day_number']);
        $this->assertEquals(14, $roadmap[13]['day_number']);
        $this->assertNotEmpty($roadmap[0]['title']);
        $this->assertNotEmpty($roadmap[0]['description']);
    }

    public function test_evaluate_daily_progress_is_100_percent_deterministic(): void
    {
        $planner = new AiSkillPlannerService();

        // 1. Fatigue feedback
        $fatigueFeedback = $planner->evaluateDailyProgress(
            'Belajar Piano',
            'Hari 3: Tangga Nada C',
            'Latihan 20 menit',
            'Hari ini lumayan capek dan berat materinya'
        );
        $this->assertNotEmpty($fatigueFeedback);
        $this->assertTrue(
            str_contains($fatigueFeedback, 'lelah') ||
            str_contains($fatigueFeedback, 'menantang') ||
            str_contains($fatigueFeedback, 'berat')
        );

        // 2. Momentum / Success feedback
        $successFeedback = $planner->evaluateDailyProgress(
            'Belajar Piano',
            'Hari 4: Akor Mayor',
            'Latihan 25 menit',
            'Latihan sangat lancar dan mudah dipahami, berhasil main 1 lagu!'
        );
        $this->assertNotEmpty($successFeedback);
        $this->assertTrue(
            str_contains($successFeedback, 'Luar biasa') ||
            str_contains($successFeedback, 'Ritme') ||
            str_contains($successFeedback, 'Pencapaian')
        );
    }

    public function test_atoma_insight_daily_summary_is_deterministic(): void
    {
        $user = User::factory()->create();
        $user->tasks()->create([
            'title' => 'Tugas 1 Hari Ini',
            'date' => now()->toDateString(),
            'is_completed' => true,
            'category' => 'Study',
            'priority' => 'normal',
        ]);

        $service = new AtomaInsightService();
        $result = $service->generateDailySummary($user);

        $this->assertFalse($result['is_fallback']);
        $summary = DailySummary::where('user_id', $user->id)->first();
        $this->assertNotNull($summary);
        $this->assertEquals(now()->toDateString(), \Carbon\Carbon::parse($summary->summary_date)->toDateString());
        $this->assertStringContainsString('1 tugas hari ini', $summary->content);
    }

    public function test_atoma_insight_monthly_report_generates_archetype_and_synthesis(): void
    {
        $user = User::factory()->create();

        // Create 30-day activity
        for ($i = 0; $i < 5; $i++) {
            $user->tasks()->create([
                'title' => "Tugas Bulan Ini {$i}",
                'date' => now()->subDays($i * 3)->toDateString(),
                'is_completed' => true,
                'category' => 'Work',
                'priority' => 'normal',
            ]);
        }

        $service = new AtomaInsightService();
        $result = $service->generateMonthlyReport($user);

        $insight = $result['insight'];
        $this->assertInstanceOf(AiInsight::class, $insight);
        $this->assertNotNull($insight->archetype);
        $this->assertNotEmpty($insight->observasi);
        $this->assertNotEmpty($insight->tantangan);
        $this->assertNotEmpty($insight->afirmasi);

        $this->assertDatabaseHas('ai_insights', [
            'id' => $insight->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_insights_page_renders_monthly_capstone_card(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('pages.insights')
            ->assertSee('Monthly Capstone Retrospective')
            ->assertSee('30-Day Retrospective');
    }
}
