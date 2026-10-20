<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SkillsIndexModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_skills_index_page_renders_and_detail_modal_week_filter_works(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Belajar Video Editing Pro',
            'target_days' => 30,
            'current_day' => 11,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => '2026-07-29 17:00:00',
        ]);

        // Create target for day 11
        $skill->targets()->create([
            'day_number' => 11,
            'title' => 'Hari 11: Edit Cut Transition',
            'description' => 'Latihan memotong transisi',
            'status' => 'completed'
        ]);

        $this->actingAs($user);

        Volt::test('skills.index')
            ->call('openDetailModal', $skill->id)
            ->assertSet('showDetailModal', true)
            ->assertSet('modalSelectedWeek', 2) // Day 11 is in Week 2
            ->set('modalSelectedWeek', 1)
            ->assertSet('modalSelectedWeek', 1);
    }

    public function test_create_skill_modal_opens_and_creates_skill_manually(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('skills.index')
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->set('newSkillTitle', 'Belajar Piano Klasik')
            ->set('newSkillDays', 30)
            ->call('createManualSkill')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('skills', [
            'user_id' => $user->id,
            'title' => 'Belajar Piano Klasik',
            'target_days' => 30,
        ]);
    }

    public function test_confirmation_modal_sneak_peek_preview_and_card_adjustment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $testVolt = Volt::test('skills.index')
            ->set('showCreateModal', true)
            ->set('newSkillTitle', 'Belajar Gitar dari Nol')
            ->set('newSkillDays', 30)
            ->set('startDate', now()->toDateString())
            ->set('trainingDays', ['1', '2', '3', '4', '5', '6', '7'])
            ->set('isConfirming', true)
            ->call('syncPreviewRoadmap', app(\App\Services\AiSkillPlannerService::class));

        // Open timeline sneak peek
        $testVolt->call('toggleTimelinePreview', app(\App\Services\AiSkillPlannerService::class))
            ->assertSet('showTimelinePreview', true)
            ->assertSee('Intip &amp; Sesuaikan Kartu Target', false);

        $preview = $testVolt->get('previewRoadmap');
        $this->assertCount(30, $preview);

        // Edit Day 1
        $testVolt->call('editPreviewCard', 0)
            ->assertSet('previewEditingIndex', 0)
            ->set('previewEditTitle', 'Hari 1: Master Intro Gitar Akustik')
            ->set('previewNewSubtaskInput', 'Latihan fingering 10 menit')
            ->call('addPreviewSubtask')
            ->call('savePreviewCard')
            ->assertSet('previewEditingIndex', null);

        $updatedPreview = $testVolt->get('previewRoadmap');
        $this->assertEquals('Hari 1: Master Intro Gitar Akustik', $updatedPreview[0]['title']);
        $this->assertTrue($updatedPreview[0]['is_customized']);

        // Confirm setup and check database
        $testVolt->call('confirmSetup', app(\App\Services\AiSkillPlannerService::class));

        $createdSkill = Skill::where('title', 'Belajar Gitar dari Nol')->first();
        $this->assertNotNull($createdSkill);
        
        $day1Target = $createdSkill->targets()->where('day_number', 1)->first();
        $this->assertNotNull($day1Target);
        $this->assertEquals('Hari 1: Master Intro Gitar Akustik', $day1Target->title);
        $this->assertTrue($day1Target->subTasks()->where('title', 'Latihan fingering 10 menit')->exists());
    }
}
