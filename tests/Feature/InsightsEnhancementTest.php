<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use App\Models\Journal;
use App\Models\FocusSession;
use App\Models\AiInsight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class InsightsEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_insights_page_renders_comprehensive_analytics(): void
    {
        $user = User::factory()->create([
            'name' => 'Atoma Master',
            'current_streak' => 5,
            'xp' => 450,
            'level' => 5,
            'coins' => 45,
            'weekly_xp' => 350,
        ]);

        // Create tasks with various categories
        Task::create([
            'user_id' => $user->id,
            'title' => 'Kerja Proyek Backend',
            'category' => 'Kerja',
            'is_completed' => true,
            'date' => Carbon::today()->toDateString(),
            'actual_minutes' => 50,
            'pomodoro_sessions' => 2,
        ]);

        Task::create([
            'user_id' => $user->id,
            'title' => 'Belajar Vue & Livewire',
            'category' => 'Belajar',
            'is_completed' => true,
            'date' => Carbon::today()->toDateString(),
            'actual_minutes' => 25,
            'pomodoro_sessions' => 1,
        ]);

        Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas Tertunda Kemarin',
            'category' => 'Pribadi',
            'is_completed' => false,
            'date' => Carbon::yesterday()->toDateString(),
        ]);

        // Create a Journal
        Journal::create([
            'user_id' => $user->id,
            'title' => 'Hari Menyenangkan',
            'content' => 'Fokus belajar dan sangat produktif hari ini.',
            'mood' => 'happy',
        ]);

        // Create Focus Session
        FocusSession::create([
            'user_id' => $user->id,
            'minutes' => 25,
            'mode' => 'work',
            'ambient_sound' => 'lofi',
        ]);

        $this->actingAs($user);

        Volt::test('pages.insights')
            ->assertSee('ATOMA Insights')
            ->assertSee('Completion Rate')
            ->assertSee('Deep Work Focus')
            ->assertSee('Daily Streak')
            ->assertSee('Overdue Tasks')
            ->assertSee('Life Balance Wheel')
            ->assertSee('Peak Productivity Radar')
            ->assertSee('Weekly Persona Archetype')
            ->assertSee('League Status')
            ->assertDontSee('Mood vs Productivity')
            ->assertDontSee('AI Daily Summary')
            ->assertSee('ATOMA Says')
            ->assertSee('Overdue Action Center')
            ->assertSee('Tugas Tertunda Kemarin');
    }

    public function test_filter_period_switch_and_overdue_actions(): void
    {
        $user = User::factory()->create();
        $yesterday = Carbon::yesterday()->toDateString();
        $overdueTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas Telat Standalone',
            'is_completed' => false,
            'date' => $yesterday,
        ]);

        $this->actingAs($user);

        $component = Volt::test('pages.insights')
            ->call('setFilterPeriod', 'month')
            ->assertSet('filterPeriod', 'month')
            ->assertSee('Beri Alasan')
            ->assertSee('Abaikan')
            ->assertDontSee('Besok')
            ->assertDontSee('✓ Selesaikan')
            ->call('openReasonModal', $overdueTask->id)
            ->assertSet('showReasonModal', true)
            ->assertSet('selectedOverdueTaskId', $overdueTask->id)
            ->assertSet('selectedOverdueTaskIsMilestone', false)
            ->set('chosenReason', 'Kelelahan / Burnout')
            ->set('customReasonNote', 'Kurang tidur semalam')
            ->call('saveReason')
            ->assertSet('showReasonModal', false);

        $overdueTask->refresh();
        $this->assertEquals('Kelelahan / Burnout', $overdueTask->skip_reason);
        $this->assertEquals('Kurang tidur semalam', $overdueTask->skip_reason_note);
        // Date must remain yesterday (no reschedule to tomorrow)
        $this->assertEquals($yesterday, $overdueTask->date->toDateString());

        // Test dismiss overdue task in insights
        $component->call('dismissOverdueTask', $overdueTask->id);
        $this->assertTrue($overdueTask->fresh()->is_overdue_dismissed);
        $component->assertSee('Tugas Overdue yang Diabaikan');

        // Test restore dismissed overdue task
        $component->call('restoreDismissedOverdue', $overdueTask->id);
        $this->assertFalse($overdueTask->fresh()->is_overdue_dismissed);
    }

    public function test_milestone_overdue_task_has_no_reschedule_option(): void
    {
        $user = User::factory()->create();
        $skill = \App\Models\Skill::create([
            'user_id' => $user->id,
            'title' => 'Fullstack Mastery',
            'target_days' => 30,
            'current_day' => 1,
        ]);
        $target = \App\Models\SkillTarget::create([
            'skill_id' => $skill->id,
            'day_number' => 5,
            'title' => 'Day 5: React Props',
            'status' => 'pending',
        ]);
        $yesterday = Carbon::yesterday()->toDateString();
        $milestoneTask = Task::create([
            'user_id' => $user->id,
            'skill_id' => $skill->id,
            'skill_target_id' => $target->id,
            'title' => 'Day 5: React Props',
            'is_completed' => false,
            'date' => $yesterday,
        ]);

        $this->actingAs($user);

        // Milestone task should show "🎯 Milestone (Hari 5/30)" and "Beri Alasan", but NO "Besok" button
        Volt::test('pages.insights')
            ->assertSee('Day 5: React Props')
            ->assertSee('Milestone')
            ->assertSee('(Hari 5/30)')
            ->assertSee('Beri Alasan')
            ->assertDontSee('wire:click="rescheduleTask(' . $milestoneTask->id . ')"', false)
            ->call('openReasonModal', $milestoneTask->id)
            ->assertSet('showReasonModal', true)
            ->assertSet('selectedOverdueTaskIsMilestone', true)
            ->assertSet('selectedOverdueTaskDayNumber', 5)
            ->assertSet('autoRescheduleTomorrow', false)
            ->assertSee('Target Milestone')
            ->assertSee('Hari 5/30')
            ->set('chosenReason', 'Terlalu Rumit / Bingung')
            ->set('customReasonNote', 'Perlu baca dokumentasi tambahan')
            ->call('saveReason')
            ->assertSet('showReasonModal', false);

        $milestoneTask->refresh();
        $this->assertEquals('Terlalu Rumit / Bingung', $milestoneTask->skip_reason);
        // Date should remain yesterday (milestone sequence preserved)
        $this->assertEquals($yesterday, $milestoneTask->date->toDateString());
    }

    public function test_pomodoro_completed_event_records_focus_session(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Coding Task',
            'is_completed' => false,
            'date' => Carbon::today()->toDateString(),
        ]);

        $this->actingAs($user);

        Volt::test('pomodoro-reward')
            ->dispatch('pomodoro-completed', taskId: $task->id, minutes: 30, ambientSound: 'rain');

        $this->assertDatabaseHas('focus_sessions', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'minutes' => 30,
            'ambient_sound' => 'rain',
        ]);

        $task->refresh();
        $this->assertEquals(30, $task->actual_minutes);
        $this->assertEquals(1, $task->pomodoro_sessions);
    }

    public function test_weekly_ai_insight_persists_across_days_without_regeneration(): void
    {
        $user = User::factory()->create();

        // Simulate an insight generated 2 days ago (with past period_start)
        $pastStart = Carbon::today()->subDays(8)->toDateString();
        $pastEnd = Carbon::today()->subDays(2)->toDateString();

        $insight = AiInsight::create([
            'user_id' => $user->id,
            'period_start' => $pastStart,
            'period_end' => $pastEnd,
            'archetype' => 'Weekly Sprint',
            'observasi' => 'Observasi produktivitas konsisten minggu ini.',
            'tantangan' => 'Pertahankan ritme kerja.',
            'afirmasi' => 'Tetap konsisten dan capai tujuanmu!',
            'saran_konkret' => 'Fokus pada 1 tugas prioritas.',
            'rekomendasi_jadwal' => 'Pagi hari 09:00 WIB.',
            'skor_fokus' => 85,
            'generated_at' => Carbon::today()->subDays(2),
        ]);

        $this->actingAs($user);

        // Even though periodStart today would be subDays(6), the page must load and display the existing insight
        $comp = Volt::test('pages.insights');
        $loadedInsight = $comp->get('weeklyInsight');

        $this->assertNotNull($loadedInsight);
        $this->assertEquals($insight->id, $loadedInsight->id);
        $this->assertEquals('Weekly Sprint', $loadedInsight->archetype);
        $this->assertTrue($comp->get('canRefreshWeekly')); // Since it was generated 2 days ago, refresh is allowed if desired
    }

    public function test_weekly_and_monthly_insight_generation_and_rendering(): void
    {
        $user = User::factory()->create();

        // Populate 7 days of tasks
        for ($i = 0; $i < 6; $i++) {
            Task::create([
                'user_id' => $user->id,
                'title' => "Tugas Selesai Hari {$i}",
                'category' => 'Kerja',
                'is_completed' => true,
                'date' => Carbon::today()->subDays($i)->toDateString(),
                'actual_minutes' => 50,
                'pomodoro_sessions' => 2,
            ]);
        }

        // Add 1 overdue task with reason
        Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas Overdue Hari Ini',
            'category' => 'Pribadi',
            'is_completed' => false,
            'date' => Carbon::yesterday()->toDateString(),
            'skip_reason' => 'Kelelahan / Burnout',
        ]);

        // Add Pomodoro focus sessions
        FocusSession::create([
            'user_id' => $user->id,
            'minutes' => 150,
            'mode' => 'work',
        ]);

        // Add Journal
        Journal::create([
            'user_id' => $user->id,
            'title' => 'Refleksi Mingguan',
            'content' => 'Fokus berjalan sangat baik.',
            'mood' => 'happy',
        ]);

        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => json_encode([
                                            'archetype' => 'The Deep-Sprint Master',
                                            'observasi' => 'Kecepatan Eksekusi: 6 dari 7 tugas tuntas dengan performa prima.',
                                            'tantangan' => '3 Rencana Aksi Taktis: 1. Rule of 3 2. Time-blocking 3. Micro-stepping.',
                                            'afirmasi' => 'Mental Model: Momentum dibangun konsisten hari demi hari.',
                                        ]),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => json_encode([
                                            'archetype' => 'The Deep-Focus Architect',
                                            'observasi' => 'Volume & Efisiensi: Analisis mendalam 30 hari penuh pencapaian.',
                                            'tantangan' => 'Restrukturisasi Sistemik: Eliminasi bottleneck waktu kerja.',
                                            'afirmasi' => 'Blueprint 30 Hari: Transformasi produktivitas berkelanjutan.',
                                        ]),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $service = app(\App\Services\AtomaInsightService::class);

        // 1. Weekly Insight Generation
        $weekly = $service->generateWeeklyInsight($user);
        $this->assertNotNull($weekly['insight']);
        $this->assertNotEmpty($weekly['insight']->archetype);
        $this->assertStringContainsString('Kecepatan Eksekusi', $weekly['insight']->observasi);
        $this->assertStringContainsString('3 Rencana Aksi Taktis', $weekly['insight']->tantangan);
        $this->assertStringContainsString('Mental Model', $weekly['insight']->afirmasi);

        // 2. Monthly Report Generation
        $monthly = $service->generateMonthlyReport($user);
        $this->assertNotNull($monthly['insight']);
        $this->assertNotEmpty($monthly['insight']->archetype);
        $this->assertStringContainsString('Volume & Efisiensi', $monthly['insight']->observasi);
        $this->assertStringContainsString('Restrukturisasi Sistemik', $monthly['insight']->tantangan);
        $this->assertStringContainsString('Blueprint 30 Hari', $monthly['insight']->afirmasi);

        // 3. Render Insights Page
        $this->actingAs($user);
        Volt::test('pages.insights')
            ->assertSee($weekly['insight']->archetype)
            ->assertSee($monthly['insight']->archetype)
            ->assertSee('ATOMA Says')
            ->assertSee('Observasi Mingguan')
            ->assertSee('Tantangan & Solusi')
            ->assertSee('Monthly Capstone Retrospective')
            ->assertSee('Observasi Makro 30 Hari')
            ->assertDontSee('Mood vs Productivity')
            ->assertDontSee('AI Daily Summary');
    }

    public function test_insights_fitness_hub_tab_and_workout_logging_and_progress(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. Initial render shows overview tab, can switch to fitness tab
        $component = Volt::test('pages.insights')
            ->assertSee('ATOMA Insights')
            ->call('setInsightsTab', 'fitness')
            ->assertSet('activeTab', 'fitness')
            ->assertSee('Catat Olahraga')
            ->assertSee('Seminggu')
            ->assertSee('Sebulan')
            ->assertSee('Setahun');

        // 2. Open workout modal and log cardio (Lari 5km, 30 min on today)
        $component->call('openWorkoutModal')
            ->assertSet('showWorkoutModal', true)
            ->call('setQuickExercise', 'Lari', 'cardio', 5.0)
            ->set('workoutDate', Carbon::today()->toDateString())
            ->set('workoutDurationMinutes', 30)
            ->call('saveWorkoutLog')
            ->assertSet('showWorkoutModal', false)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('workout_sessions', [
            'user_id' => $user->id,
            'title' => 'Lari',
        ]);
        $this->assertDatabaseHas('workout_exercises', [
            'exercise_name' => 'Lari',
            'weight_unit' => 'km',
        ]);

        // 3. Log bodyweight workouts in Week 1 (day 3) and Week 4 (day 25)
        $w1Date = Carbon::today()->startOfMonth()->addDays(2)->toDateString();
        $w4Date = Carbon::today()->startOfMonth()->addDays(24)->toDateString();

        $component->call('openWorkoutModal')
            ->call('setQuickExercise', 'Push Up', 'bodyweight', 50)
            ->set('workoutDate', $w1Date)
            ->set('workoutSets', 2)
            ->set('workoutReps', 25) // 50 reps total
            ->call('saveWorkoutLog');

        $component->call('openWorkoutModal')
            ->call('setQuickExercise', 'Push Up', 'bodyweight', 100)
            ->set('workoutDate', $w4Date)
            ->set('workoutSets', 4)
            ->set('workoutReps', 25) // 100 reps total
            ->call('saveWorkoutLog');

        // 4. Test weekly schedule & monthly progress display
        $component->call('setFitnessPeriod', 'week')
            ->assertSet('fitnessPeriod', 'week')
            ->assertSee('Jadwal & Hari Latihan Pekan Ini');

        $component->call('setFitnessPeriod', 'month')
            ->assertSet('fitnessPeriod', 'month')
            ->assertSee('Volume Latihan 4 Pekan')
            ->assertSee('Analisis Progres Latihan per Gerakan')
            ->assertSee('Push Up')
            ->assertSee('Progress Naik +100%');

        // 5. Test yearly period
        $component->call('setFitnessPeriod', 'year')
            ->assertSet('fitnessPeriod', 'year')
            ->assertSee('Personal Records (PR)');
    }
}

