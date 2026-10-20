<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\SkillTarget;
use App\Models\User;
use App\Services\AiChatReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiChatMilestoneReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_chat_reference_service_detects_various_ai_providers(): void
    {
        // ChatGPT
        $chatGptUrl = 'https://chatgpt.com/share/670a1234-abcd-ef01-2345-6789abcdef01';
        $this->assertTrue(AiChatReferenceService::isAiChatUrl($chatGptUrl));
        $this->assertFalse(AiChatReferenceService::isYouTubeUrl($chatGptUrl));
        $chatGptInfo = AiChatReferenceService::parseAiChat($chatGptUrl);
        $this->assertEquals('chatgpt', $chatGptInfo['provider']);
        $this->assertEquals('ChatGPT', $chatGptInfo['name']);

        // Gemini
        $geminiUrl = 'https://gemini.google.com/share/e47a98bc1234';
        $this->assertTrue(AiChatReferenceService::isAiChatUrl($geminiUrl));
        $this->assertFalse(AiChatReferenceService::isYouTubeUrl($geminiUrl));
        $geminiInfo = AiChatReferenceService::parseAiChat($geminiUrl);
        $this->assertEquals('gemini', $geminiInfo['provider']);
        $this->assertEquals('Gemini', $geminiInfo['name']);

        // Claude
        $claudeUrl = 'https://claude.ai/share/882190-sample-share';
        $this->assertTrue(AiChatReferenceService::isAiChatUrl($claudeUrl));
        $this->assertFalse(AiChatReferenceService::isYouTubeUrl($claudeUrl));
        $claudeInfo = AiChatReferenceService::parseAiChat($claudeUrl);
        $this->assertEquals('claude', $claudeInfo['provider']);
        $this->assertEquals('Claude', $claudeInfo['name']);

        // Perplexity
        $perplexityUrl = 'https://www.perplexity.ai/search/what-is-atoma-app-abc';
        $this->assertTrue(AiChatReferenceService::isAiChatUrl($perplexityUrl));
        $perplexityInfo = AiChatReferenceService::parseAiChat($perplexityUrl);
        $this->assertEquals('perplexity', $perplexityInfo['provider']);
        $this->assertEquals('Perplexity', $perplexityInfo['name']);

        // DeepSeek
        $deepseekUrl = 'https://chat.deepseek.com/a/chat-123';
        $this->assertTrue(AiChatReferenceService::isAiChatUrl($deepseekUrl));
        $deepseekInfo = AiChatReferenceService::parseAiChat($deepseekUrl);
        $this->assertEquals('deepseek', $deepseekInfo['provider']);
        $this->assertEquals('DeepSeek', $deepseekInfo['name']);

        // YouTube
        $youtubeUrl = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $this->assertTrue(AiChatReferenceService::isYouTubeUrl($youtubeUrl));
        $this->assertFalse(AiChatReferenceService::isAiChatUrl($youtubeUrl));
    }

    public function test_skill_and_target_models_provide_effective_urls_with_backward_compatibility(): void
    {
        $user = User::factory()->create();

        // 1. Both YouTube and AI Chat defined
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Mastering AI Engineering',
            'target_days' => 14,
            'reference_url' => 'https://youtube.com/watch?v=12345',
            'ai_chat_url' => 'https://gemini.google.com/share/abcde',
        ]);

        $this->assertEquals('https://youtube.com/watch?v=12345', $skill->effective_youtube_url);
        $this->assertEquals('https://gemini.google.com/share/abcde', $skill->effective_ai_chat_url);

        // 2. Legacy fallback: AI share link placed in reference_url
        $legacyTarget = SkillTarget::create([
            'skill_id' => $skill->id,
            'day_number' => 1,
            'title' => 'Intro to Prompts',
            'reference_url' => 'https://chatgpt.com/share/legacy-share-id',
            'ai_chat_url' => null,
        ]);

        $this->assertNull($legacyTarget->effective_youtube_url);
        $this->assertEquals('https://chatgpt.com/share/legacy-share-id', $legacyTarget->effective_ai_chat_url);
    }

    public function test_user_can_create_and_edit_milestone_target_with_ai_chat_reference(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Machine Learning Foundations',
            'target_days' => 14,
            'mode' => 'systematic',
        ]);

        $this->actingAs($user);

        // Test Add Target with both YouTube and AI Chat references
        Livewire::test('skills.show', ['skill' => $skill])
            ->call('openAddModal', 2)
            ->set('newDayNumber', 2)
            ->set('newTitle', 'Hari 2: Matematika Linear Algebra')
            ->set('newDescription', 'Belajari dot product dan transpose.')
            ->set('newReferenceUrl', 'https://youtube.com/watch?v=algebra')
            ->set('newAiChatUrl', 'https://claude.ai/share/math-notes')
            ->call('createTarget')
            ->assertHasNoErrors()
            ->assertSet('showAddModal', false);

        $target = $skill->targets()->where('day_number', 2)->first();
        $this->assertNotNull($target);
        $this->assertEquals('Hari 2: Matematika Linear Algebra', $target->title);
        $this->assertEquals('https://youtube.com/watch?v=algebra', $target->reference_url);
        $this->assertEquals('https://claude.ai/share/math-notes', $target->ai_chat_url);

        // Test Edit Target with AI Chat reference
        Livewire::test('skills.show', ['skill' => $skill])
            ->call('openEditModal', $target->id)
            ->assertSet('editReferenceUrl', 'https://youtube.com/watch?v=algebra')
            ->assertSet('editAiChatUrl', 'https://claude.ai/share/math-notes')
            ->set('editAiChatUrl', 'https://chatgpt.com/share/new-math-notes')
            ->call('updateTarget')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $target->refresh();
        $this->assertEquals('https://chatgpt.com/share/new-math-notes', $target->ai_chat_url);
    }

    public function test_milestone_show_renders_ai_chat_badges(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Prompt Engineering',
            'target_days' => 7,
            'mode' => 'systematic',
        ]);

        $skill->targets()->create([
            'day_number' => 1,
            'title' => 'Hari 1: Zero Shot Prompting',
            'description' => 'Eksperimen dengan model LLM',
            'reference_url' => 'https://youtube.com/watch?v=llm101',
            'ai_chat_url' => 'https://gemini.google.com/share/prompt-gems',
            'status' => 'pending',
        ]);

        $this->actingAs($user);

        $response = $this->get("/skills/{$skill->id}");
        $response->assertStatus(200);
        $response->assertSee('Video YouTube');
        $response->assertSee('Gemini Chat');
        $response->assertSee('https://gemini.google.com/share/prompt-gems');
    }

    public function test_manual_skill_creation_in_index_saves_ai_chat_reference(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('skills.index')
            ->call('openCreateModal')
            ->set('newSkillTitle', 'Deep Learning Mastery')
            ->set('newSkillDays', 21)
            ->set('referenceUrl', 'https://youtube.com/watch?v=dl101')
            ->set('aiChatUrl', 'https://chatgpt.com/share/deep-learning-syllabus')
            ->call('createManualSkill')
            ->assertHasNoErrors();

        $skill = Skill::where('title', 'Deep Learning Mastery')->first();
        $this->assertNotNull($skill);
        $this->assertEquals('https://youtube.com/watch?v=dl101', $skill->reference_url);
        $this->assertEquals('https://chatgpt.com/share/deep-learning-syllabus', $skill->ai_chat_url);

        // Verify day 1 target also inherits AI reference
        $day1 = $skill->targets()->where('day_number', 1)->first();
        $this->assertNotNull($day1);
        $this->assertEquals('https://chatgpt.com/share/deep-learning-syllabus', $day1->ai_chat_url);
    }

    public function test_ninety_day_challenge_roadmap_generation_is_instant_and_synthesizes_weekly_routine_and_phases(): void
    {
        $user = User::factory()->create([
            'subscription_tier' => 'pro',
        ]);
        $this->actingAs($user);

        $aiChatUrl = 'https://share.gemini.google/M4OZyNbL5QQf';
        $startDate = '2026-10-19'; // A Monday (ISO 1)

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => '90 DAYS CHALLANGE',
            'prompt' => '90-Day Full Transformation Challenge',
            'target_days' => 90,
            'start_date' => $startDate,
            'mode' => 'systematic',
            'training_days' => ['2', '3', '4', '5', '6', '7'], // Rest on Monday (1)
            'setup_status' => 'active',
            'current_day' => 1,
            'ai_chat_url' => $aiChatUrl,
            'interview_context' => [
                'summary' => [
                    'training_schedule' => 'Senin rest, Selasa-Minggu latihan',
                    'weekly_routine' => [
                        ['day_name' => 'Senin', 'iso' => 1, 'type' => 'rest', 'label' => 'Rest / Kuliah full'],
                        ['day_name' => 'Selasa', 'iso' => 2, 'type' => 'workout', 'label' => 'Sore Workout A (Knee Armor)'],
                        ['day_name' => 'Rabu', 'iso' => 3, 'type' => 'workout', 'label' => 'Pagi Lari Base Sesi 1 + Kuliah'],
                        ['day_name' => 'Kamis', 'iso' => 4, 'type' => 'workout', 'label' => 'Pagi Lari Form Sesi 2 + Kuliah'],
                        ['day_name' => 'Jumat', 'iso' => 5, 'type' => 'workout', 'label' => 'Sore Workout B (Core & Upper)'],
                        ['day_name' => 'Sabtu', 'iso' => 6, 'type' => 'workout', 'label' => 'Pagi Sesi Aerobik Panjang / Lari / Renang'],
                        ['day_name' => 'Minggu', 'iso' => 7, 'type' => 'active_recovery', 'label' => 'Active Recovery (sepeda/jalan santai)'],
                    ],
                    'phases' => [
                        ['phase' => 1, 'name' => 'Fondasi & Adaptasi', 'days' => 'Hari 1-22', 'focus' => 'Penyesuaian ritme & teknik dasar mobilitas'],
                        ['phase' => 2, 'name' => 'Pembentukan Teknik & Konsistensi', 'days' => 'Hari 23-45', 'focus' => 'Peningkatan stabilitas form & volume latihan'],
                        ['phase' => 3, 'name' => 'Latihan Terapan & Intensitas', 'days' => 'Hari 46-68', 'focus' => 'Penguatan kapasitas fisik & variasi beban'],
                        ['phase' => 4, 'name' => 'Uji Penguasaan & Kebiasaan Permanen', 'days' => 'Hari 69-90', 'focus' => 'Evaluasi pencapaian akhir, PR & sertifikasi'],
                    ],
                ]
            ]
        ]);

        $service = new \App\Services\AiSkillPlannerService();
        $startTime = microtime(true);
        $roadmap = $service->generateFullRoadmap($skill);
        $durationMs = (microtime(true) - $startTime) * 1000;

        // Must be instantaneous (< 100ms) - completely eliminates Nginx 60s timeout
        $this->assertLessThan(100, $durationMs);
        $this->assertCount(90, $roadmap);

        // Day 1 (Monday): Should be Rest / Kuliah full
        $this->assertEquals(1, $roadmap[0]['day_number']);
        $this->assertTrue($roadmap[0]['is_rest_day']);
        $this->assertStringContainsString('Rest / Kuliah full', $roadmap[0]['title']);
        $this->assertEquals($aiChatUrl, $roadmap[0]['ai_chat_url']);

        // Day 2 (Tuesday): Should be Workout A (Knee Armor)
        $this->assertEquals(2, $roadmap[1]['day_number']);
        $this->assertFalse($roadmap[1]['is_rest_day']);
        $this->assertStringContainsString('Sore Workout A (Knee Armor)', $roadmap[1]['title']);
        $this->assertNotEmpty($roadmap[1]['sub_tasks']);

        // Day 90: Capstone Day
        $this->assertEquals(90, $roadmap[89]['day_number']);
        $this->assertStringContainsString('Uji Capstone & Penguasaan', $roadmap[89]['title']);

        // Test Livewire finalizeSkill execution creates all 90 targets and subtasks smoothly
        Livewire::test('skills.index')
            ->set('newSkillTitle', '90 DAYS CHALLANGE 2')
            ->set('newSkillDays', 90)
            ->set('startDate', $startDate)
            ->set('trainingDays', ['2', '3', '4', '5', '6', '7'])
            ->set('aiChatUrl', $aiChatUrl)
            ->set('confirmationData', $skill->interview_context['summary'])
            ->set('finalPrompt', '90 Days transformation challenge')
            ->call('confirmSetup')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $createdSkill = Skill::where('title', '90 DAYS CHALLANGE 2')->first();
        $this->assertNotNull($createdSkill);
        $this->assertEquals(90, $createdSkill->targets()->count());
        $this->assertGreaterThanOrEqual(180, \App\Models\SubTask::whereIn('skill_target_id', $createdSkill->targets()->pluck('id'))->count());
    }

    public function test_ai_chat_reference_service_extracts_chat_metadata_and_topic(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://share.gemini.google/test-share' => \Illuminate\Support\Facades\Http::response(
                '<!DOCTYPE html><html><head><title>‎Gemini - Knee Armor Routine</title><meta property="og:title" content="‎Gemini - Knee Armor Routine"><meta property="og:description" content="Sesi latihan penguatan lutut dan mobilitas"></head><body></body></html>',
                200
            ),
            'https://chatgpt.com/share/test-share' => \Illuminate\Support\Facades\Http::response(
                '<!DOCTYPE html><html><head><title>ChatGPT - 90 Days Running Syllabus</title><meta property="og:title" content="ChatGPT - 90 Days Running Syllabus"></head><body></body></html>',
                200
            ),
        ]);

        // Test Gemini metadata fetch
        $geminiMeta = AiChatReferenceService::fetchChatMetadata('https://share.gemini.google/test-share');
        $this->assertNotNull($geminiMeta);
        $this->assertEquals('Gemini', $geminiMeta['provider']);
        $this->assertEquals('Knee Armor Routine', $geminiMeta['topic']);

        // Test ChatGPT metadata fetch
        $chatGptMeta = AiChatReferenceService::fetchChatMetadata('https://chatgpt.com/share/test-share');
        $this->assertNotNull($chatGptMeta);
        $this->assertEquals('ChatGPT', $chatGptMeta['provider']);
        $this->assertEquals('90 Days Running Syllabus', $chatGptMeta['topic']);
    }

    public function test_ai_coach_bulk_task_generator_populates_dynamic_tasks_in_milestone(): void
    {
        $user = User::factory()->create([
            'subscription_tier' => 'free',
        ]);
        $this->actingAs($user);

        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => '90 DAYS CHALLANGE',
            'target_days' => 90,
            'current_day' => 5,
            'mode' => 'systematic',
        ]);

        $service = new \App\Services\AiSkillPlannerService();
        $tasks = $service->generateBulkTasksForSkill($skill, 'Knee Armor', 5);

        $this->assertIsArray($tasks);
        $this->assertGreaterThanOrEqual(3, count($tasks));
        $this->assertNotEmpty($tasks[0]);
        $allText = implode(' ', $tasks);
        $this->assertTrue(
            str_contains(strtolower($allText), 'knee') ||
            str_contains(strtolower($allText), 'lutut') ||
            str_contains(strtolower($allText), 'armor') ||
            strlen($allText) > 20
        );

        // Test Free user triggers open-pro-modal event
        Livewire::test('skills.show', ['skill' => $skill])
            ->call('openTaskModal', null, null)
            ->set('taskModalTab', 'bulk')
            ->set('bulkTaskAiFocus', 'Knee Armor & Mobilitas')
            ->call('generateAiBulkTasks')
            ->assertDispatched('open-pro-modal');

        // Upgrade user to Pro and verify bulk tasks generation
        $user->update(['subscription_tier' => 'pro']);

        // Verify bulkTasksInput was populated with tasks
        $testComponent = Livewire::test('skills.show', ['skill' => $skill])
            ->call('openTaskModal', null, null)
            ->set('taskModalTab', 'bulk')
            ->set('bulkTaskAiFocus', 'Knee Armor')
            ->call('generateAiBulkTasks')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $bulkInput = $testComponent->get('bulkTasksInput');
        $this->assertNotEmpty($bulkInput);
        $this->assertGreaterThanOrEqual(2, count(explode("\n", trim($bulkInput))));

        // Save bulk tasks
        $testComponent
            ->set('bulkTaskDate', now()->toDateString())
            ->set('bulkTaskDateOption', 'same_day')
            ->set('bulkTaskPriority', 'medium')
            ->call('saveBulkMilestoneTasks')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertGreaterThanOrEqual(3, $user->tasks()->where('skill_id', $skill->id)->count());
    }
}
