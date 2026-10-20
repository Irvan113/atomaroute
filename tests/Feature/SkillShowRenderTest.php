<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Skill;
use App\Models\SkillTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SkillShowRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_skill_show_page_renders_successfully_without_blade_errors(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Belajar Editing Video',
            'target_days' => 30,
            'current_day' => 13,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => '2026-07-29 17:00:00',
        ]);

        $this->actingAs($user);

        $response = $this->get("/skills/{$skill->id}");
        $response->assertStatus(200);
        $response->assertSee('Belajar Editing Video');
        $response->assertSee('Roadmap Matrix');
        $response->assertSee('Timeline');
        $response->assertSee('Kartu Mingguan');
        $response->assertSee('start-pomodoro-task');
    }

    public function test_view_mode_toggle_switches_between_timeline_and_cards(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Belajar Piano Jazz',
            'target_days' => 14,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
        ]);

        $this->actingAs($user);

        Livewire::test('skills.show', ['skill' => $skill])
            ->assertSet('viewMode', 'timeline')
            ->call('setViewMode', 'cards')
            ->assertSet('viewMode', 'cards')
            ->assertSee('Week 1')
            ->call('setViewMode', 'timeline')
            ->assertSet('viewMode', 'timeline');
    }

    public function test_rest_day_opens_mindful_modal_and_confirms_successfully(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Lari Marathon 10K',
            'target_days' => 14,
            'current_day' => 7,
            'status' => 'active',
            'mode' => 'systematic',
        ]);

        $restTarget = SkillTarget::create([
            'skill_id' => $skill->id,
            'day_number' => 7,
            'title' => 'Hari 7: Rest & Recovery - Pemulihan Otot',
            'description' => 'Istirahat penuh untuk pemulihan.',
            'status' => 'pending',
        ]);

        $this->actingAs($user);

        Livewire::test('skills.show', ['skill' => $skill])
            ->call('openRestModal', $restTarget->id)
            ->assertSet('showRestModal', true)
            ->set('restNotes', 'Tidur 8 jam dan stretching.')
            ->call('confirmRestDay')
            ->assertSet('showRestModal', false);

        $this->assertEquals('completed', $restTarget->fresh()->status);
        $this->assertStringContainsString('Tidur 8 jam', $restTarget->fresh()->evaluation_feedback);
    }

    public function test_completed_skill_shows_digital_mastery_certificate_modal(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso']);
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Fullstack Mastery',
            'target_days' => 7,
            'current_day' => 7,
            'status' => 'completed',
            'mode' => 'systematic',
        ]);

        for ($i = 1; $i <= 7; $i++) {
            SkillTarget::create([
                'skill_id' => $skill->id,
                'day_number' => $i,
                'title' => "Hari {$i}: Target Fullstack",
                'status' => 'completed',
            ]);
        }

        $this->actingAs($user);

        Livewire::test('skills.show', ['skill' => $skill])
            ->assertSee('Sertifikat')
            ->set('showCertificateModal', true)
            ->assertSee('ATOMA DIGITAL MASTERY CERTIFICATE')
            ->assertSee('Budi Santoso')
            ->assertSee('Fullstack Mastery')
            ->assertSee('Terverifikasi oleh ATOMA AI Coach Engine');
    }

    public function test_ai_coach_consultation_modal_renders_auto_expanding_chatbox_ui(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Marathon Sub-40',
            'target_days' => 30,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
        ]);

        $this->actingAs($user);

        Livewire::test('skills.show', ['skill' => $skill])
            ->call('openCoachChat')
            ->assertSet('showCoachChatModal', true)
            ->assertSee('AI Coach &amp; Konsultasi Milestone', false)
            ->assertSee('min-height: 48px; max-height: 140px;', false)
            ->assertSee('pl-4 pr-14 py-3', false)
            ->assertSee('Ketik curhatan atau pertanyaan untuk coach');
    }

    public function test_ai_coach_can_propose_schedule_adjustment_and_user_can_apply_it(): void
    {
        \Carbon\Carbon::setTestNow(now()->startOfWeek());
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Triathlon Sprint & Dopamine Detox',
            'target_days' => 30,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => now()->startOfWeek()->toDateString(), // Monday
            'training_days' => ['1', '2', '3', '4', '5', '6', '7'],
        ]);

        for ($d = 1; $d <= 7; $d++) {
            $skill->targets()->create([
                'day_number' => $d,
                'title' => "Hari {$d}: Latihan Awal",
                'status' => 'pending',
                'is_ai_generated' => true,
            ]);
        }

        $this->actingAs($user);

        // User asks to adjust schedule due to heavy classes on Wednesday
        $testComponent = Livewire::test('skills.show', ['skill' => $skill])
            ->call('openCoachChat')
            ->set('coachUserMessage', 'Aku mau sesuaikan jadwal kuliah hari rabu padat jadi libur istirahat')
            ->call('sendCoachMessage');

        // Chat should have proposal adjustment
        $testComponent->assertSee('Preview Penyesuaian Jadwal &amp; Roadmap', false);
        $testComponent->assertSee('Terapkan Perubahan ke Milestone');

        // Apply adjustment
        $testComponent->call('applyCoachAdjustment', 1);

        $skillFresh = $skill->fresh();
        // Wednesday (3) should be shifted to rest in training_days
        $this->assertNotContains('3', $skillFresh->training_days);

        // Day 3 (Wednesday) target should be updated to Rest & Recovery
        $day3Target = $skillFresh->targets()->where('day_number', 3)->first();
        $this->assertNotNull($day3Target);
        $this->assertStringContainsString('Rest & Recovery', $day3Target->title);

        $testComponent->assertDispatched('toast');
        \Carbon\Carbon::setTestNow();
    }

    public function test_ai_coach_schedule_adjustment_can_be_dismissed(): void
    {
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Belajar Coding Flutter',
            'target_days' => 14,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'training_days' => ['1', '2', '3', '4', '5', '6', '7'],
        ]);

        $this->actingAs($user);

        $testComponent = Livewire::test('skills.show', ['skill' => $skill])
            ->call('openCoachChat')
            ->set('coachUserMessage', 'Tolong re-adjust jadwal')
            ->call('sendCoachMessage');

        $testComponent->call('dismissCoachAdjustment', 1);
        $this->assertNull($testComponent->get('pendingCoachAdjustment'));
    }

    public function test_ai_coach_adjustment_preview_has_setuju_revisi_intip_card_and_diff_highlights(): void
    {
        \Carbon\Carbon::setTestNow(now()->startOfWeek());
        $user = User::factory()->create();
        $skill = Skill::create([
            'user_id' => $user->id,
            'title' => 'Mastering AI Engineering',
            'target_days' => 14,
            'current_day' => 1,
            'status' => 'active',
            'mode' => 'systematic',
            'start_date' => now()->startOfWeek()->toDateString(),
            'training_days' => ['1', '2', '3', '4', '5', '6', '7'],
        ]);

        for ($d = 1; $d <= 14; $d++) {
            $skill->targets()->create([
                'day_number' => $d,
                'title' => "Hari {$d}: Latihan Dasar",
                'status' => 'pending',
                'is_ai_generated' => true,
            ]);
        }

        $this->actingAs($user);

        $testComponent = Livewire::test('skills.show', ['skill' => $skill])
            ->call('openCoachChat')
            ->set('coachUserMessage', 'Aku mau sesuaikan jadwal kuliah hari rabu padat jadi libur istirahat')
            ->call('sendCoachMessage');

        // Verify Setuju, Revisi, Intip Card, and Diff exist in preview
        $testComponent->assertSee('Preview Penyesuaian Jadwal &amp; Roadmap', false);
        $testComponent->assertSee('Setuju &amp; Terapkan Perubahan ke Milestone', false);
        $testComponent->assertSee('Minta Revisi');
        $testComponent->assertSee('Intip Card Target Milestone');
        $testComponent->assertSee('Diubah');

        // Test Intip Card Drawer
        $testComponent->call('toggleCoachTimelinePreview')
            ->assertSet('showCoachTimelinePreview', true)
            ->assertSee('Preview Seluruh Kartu Target');

        // Test Filter in Sneak Peek
        $testComponent->set('coachPreviewFilter', 'rest')
            ->assertSee('Rest');

        // Test Revision Drawer
        $testComponent->call('toggleCoachRevisionBox')
            ->assertSet('showCoachRevisionBox', true)
            ->assertSee('Minta Revisi Jadwal ke AI Coach');

        // Apply Adjustment
        $testComponent->call('applyCoachAdjustment');
        $this->assertNull($testComponent->get('pendingCoachAdjustment'));

        $freshSkill = $skill->fresh();
        $this->assertNotContains('3', $freshSkill->training_days);

        \Carbon\Carbon::setTestNow();
    }
}
