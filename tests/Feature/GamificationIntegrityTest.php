<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Task;
use App\Models\SubTask;
use App\Models\Journal;
use App\Models\Flashcard;
use App\Models\FocusSession;
use App\Services\TaskXpService;
use App\Services\GamificationService;
use App\Services\PrestigeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Livewire\Volt\Volt;

class GamificationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_uncheck_and_recheck_after_settlement_does_not_award_duplicate_xp()
    {
        $user = User::factory()->create([
            'pending_xp' => 0,
            'xp' => 100,
            'daily_xp_earned' => 0,
        ]);
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Tugas Penting Hari Ini',
            'is_completed' => false,
            'date' => today()->toDateString(),
        ]);
        $task->created_at = now()->subMinutes(30);
        $task->save();

        // 1. Complete task on Day 1
        $res1 = TaskXpService::evaluateTaskCompletion($task, $user);
        $this->assertEquals(25, $res1['xp']);
        $task->is_completed = true;
        $task->save();
        $user->refresh();
        $this->assertEquals(25, $user->pending_xp);

        // 2. Midnight settlement runs
        $settlement = $user->processDailySettlement();
        $this->assertTrue($settlement['settled']);
        $user->refresh();
        $task->refresh();
        $this->assertEquals(0, $user->pending_xp);
        $this->assertNotNull($task->xp_settled_at);

        // 3. User unchecks task on Day 2
        TaskXpService::revertTaskCompletion($task, $user);
        $task->is_completed = false;
        $task->save();
        $user->refresh();
        $task->refresh();
        // XP and level remain intact, task earned_xp remains preserved
        $this->assertEquals(0, $user->pending_xp);
        $this->assertEquals(25, $task->earned_xp);

        // 4. User attempts to re-check task on Day 2
        $res2 = TaskXpService::evaluateTaskCompletion($task, $user);
        $this->assertEquals(0, $res2['xp']);
        $this->assertEquals('already_settled', $res2['reason']);
        $user->refresh();
        $this->assertEquals(0, $user->pending_xp); // No duplicate XP!
    }

    public function test_deleting_completed_task_reverts_pending_xp_and_retains_duplicate_detection()
    {
        $user = User::factory()->create([
            'pending_xp' => 0,
            'daily_xp_earned' => 0,
        ]);
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Belajar Vue dan Laravel',
            'is_completed' => false,
            'date' => today()->toDateString(),
        ]);
        $task->created_at = now()->subMinutes(30);
        $task->save();

        // Complete task
        $res = TaskXpService::evaluateTaskCompletion($task, $user);
        $this->assertEquals(25, $res['xp']);
        $task->is_completed = true;
        $task->save();
        $user->refresh();
        $this->assertEquals(25, $user->pending_xp);

        // Delete task via tasks component logic
        Volt::test('tasks')
            ->call('deleteTask', $task->id);

        $user->refresh();
        $this->assertEquals(0, $user->pending_xp); // Pending XP reverted!
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);

        // Creating duplicate task on same day is blocked from earning XP
        $duplicateTask = Task::create([
            'user_id' => $user->id,
            'title' => 'Belajar Vue dan Laravel',
            'date' => today()->toDateString(),
        ]);
        $duplicateTask->created_at = now()->subMinutes(30);
        $duplicateTask->save();

        $resDup = TaskXpService::evaluateTaskCompletion($duplicateTask, $user);
        $this->assertEquals(0, $resDup['xp']);
        $this->assertEquals('duplicate_spam', $resDup['reason']);
    }

    public function test_flashcard_daily_cap_and_pending_xp()
    {
        $user = User::factory()->create(['pending_xp' => 0]);
        $this->actingAs($user);

        // 10 cards * 5 XP = 50 XP (the daily cap)
        $awarded1 = GamificationService::awardFlashcardXp($user, 10);
        $this->assertEquals(50, $awarded1);
        $user->refresh();
        $this->assertEquals(50, $user->pending_xp);

        // 11th card exceeds cap -> 0 XP
        $awarded2 = GamificationService::awardFlashcardXp($user, 1);
        $this->assertEquals(0, $awarded2);
        $user->refresh();
        $this->assertEquals(50, $user->pending_xp);
    }

    public function test_ascension_overflow_xp_is_preserved()
    {
        $user = User::factory()->create([
            'prestige_tier' => 1,
            'prestige_level' => 50,
            'prestige_xp' => 0,
        ]);

        // XP needed for Lv.50 is 35,355
        // If user receives required + 150 XP, they should ascend to Tier 2 and have leftover XP (not 0)
        $req = PrestigeService::xpRequiredForLevel(50);
        $res = PrestigeService::addXp($user, $req + 150);

        $this->assertTrue($res['ascended']);
        $this->assertEquals(2, $user->prestige_tier); // Ascended to Tier 2 (Practitioner)
        // Tier 2 Level 1 requires 100 XP, so 150 overflow XP levels user to Level 2 with 50 XP remaining!
        $this->assertEquals(2, $user->prestige_level);
        $this->assertEquals(50, $user->prestige_xp);
    }

    public function test_journal_reflection_awards_pending_xp()
    {
        $user = User::factory()->create(['pending_xp' => 0]);
        $this->actingAs($user);

        $journal = Journal::create([
            'user_id' => $user->id,
            'title' => 'Refleksi Harian Produktif',
            'content' => 'Hari ini saya belajar banyak tentang arsitektur perangkat lunak, optimasi database, dan desain sistem gamifikasi yang aman.',
        ]);

        $awarded = GamificationService::awardJournalXp($user, $journal);
        $this->assertEquals(25, $awarded);
        $user->refresh();
        $this->assertEquals(25, $user->pending_xp);

        // Second journal on same day does not exceed cap
        $journal2 = Journal::create([
            'user_id' => $user->id,
            'title' => 'Refleksi Kedua',
            'content' => 'Menulis catatan kedua untuk hari ini yang cukup panjang juga untuk menguji batas harian XP jurnal.',
        ]);

        $awarded2 = GamificationService::awardJournalXp($user, $journal2);
        $this->assertEquals(0, $awarded2);
        $user->refresh();
        $this->assertEquals(25, $user->pending_xp);
    }

    public function test_subtask_completion_awards_subtask_xp()
    {
        $user = User::factory()->create(['pending_xp' => 0]);
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Project Rancang Bangun',
            'date' => today()->toDateString(),
        ]);

        $subTask = SubTask::create([
            'task_id' => $task->id,
            'title' => 'Analisis Kebutuhan',
            'is_completed' => true,
        ]);

        $awarded = GamificationService::awardSubTaskXp($user, $subTask);
        $this->assertEquals(5, $awarded);
        $user->refresh();
        $subTask->refresh();
        $this->assertEquals(5, $user->pending_xp);
        $this->assertEquals(5, $subTask->earned_xp);
    }

    public function test_subtask_anti_abuse_done_and_undone_and_settlement()
    {
        $user = User::factory()->create(['pending_xp' => 0]);
        $this->actingAs($user);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Anti Abuse Subtask Testing',
            'date' => today()->toDateString(),
        ]);

        $subTask = SubTask::create([
            'task_id' => $task->id,
            'title' => 'Subtask Anti Spam',
            'is_completed' => false,
        ]);

        // 1. First Done -> Awards 5 XP
        $awarded1 = GamificationService::awardSubTaskXp($user, $subTask);
        $this->assertEquals(5, $awarded1);
        $subTask->refresh();
        $user->refresh();
        $this->assertEquals(5, $subTask->earned_xp);
        $this->assertEquals(5, $user->pending_xp);

        // 2. Spamming Done without unchecking -> 0 XP
        $awardedSpam = GamificationService::awardSubTaskXp($user, $subTask);
        $this->assertEquals(0, $awardedSpam);

        // 3. Undone -> Reverts XP
        GamificationService::revertSubTaskXp($user, $subTask);
        $subTask->refresh();
        $user->refresh();
        $this->assertEquals(0, $subTask->earned_xp);
        $this->assertEquals(0, $user->pending_xp);

        // 4. Re-checking legitimately gives back 5 XP (cannot exceed daily 25 cap)
        $subTask->update(['is_completed' => true]);
        $awarded2 = GamificationService::awardSubTaskXp($user, $subTask);
        $this->assertEquals(5, $awarded2);
        $user->refresh();
        $this->assertEquals(5, $user->pending_xp);

        // 5. Midnight settlement settles subtask
        $user->processDailySettlement();
        $subTask->refresh();
        $this->assertNotNull($subTask->xp_settled_at);

        // 6. After settlement, uncheck/recheck cannot exploit or re-award XP
        GamificationService::revertSubTaskXp($user, $subTask);
        $this->assertEquals(5, $subTask->earned_xp); // Protected!
        $awardedPostSettle = GamificationService::awardSubTaskXp($user, $subTask);
        $this->assertEquals(0, $awardedPostSettle); // Protected!
    }

    public function test_dynamic_daily_randomized_hidden_mission_lifecycle()
    {
        $user = User::factory()->create([
            'pending_xp' => 0,
            'last_xp_date' => Carbon::today('Asia/Jakarta')->toDateString(),
        ]);
        $this->actingAs($user);

        // 1. Get today's secret combination
        $mission = GamificationService::getDailyHiddenMission($user);
        $this->assertArrayHasKey('id', $mission);
        $this->assertArrayHasKey('name', $mission);
        $this->assertCount(2, $mission['criteria']);
        $this->assertFalse($mission['is_unlocked']);

        // 2. Fulfill only first criterion
        $crit1 = $mission['criteria'][0];
        $this->fulfillCriterion($user, $crit1);

        // Check should still be null (not fully satisfied)
        $resPartial = GamificationService::checkDailyHiddenMission($user);
        $this->assertNull($resPartial);

        // 3. Fulfill second criterion
        $crit2 = $mission['criteria'][1];
        $this->fulfillCriterion($user, $crit2);

        // Now both criteria are met -> Unlocks +100 EXP!
        $resFull = GamificationService::checkDailyHiddenMission($user);
        $this->assertNotNull($resFull);
        $this->assertTrue($resFull['unlocked']);
        $this->assertEquals(100, $resFull['bonus_xp']);

        $user->refresh();
        $this->assertGreaterThanOrEqual(100, $user->pending_xp);

        // 4. Repeated check on same day is blocked
        $resDup = GamificationService::checkDailyHiddenMission($user);
        $this->assertNull($resDup);
    }

    public function test_daily_exp_breakdown_progress_bars()
    {
        $user = User::factory()->create([
            'pending_xp' => 0,
            'daily_xp_earned' => 100,
            'last_xp_date' => Carbon::today('Asia/Jakarta')->toDateString(),
        ]);
        $this->actingAs($user);

        $breakdown = GamificationService::getDailyExpBreakdown($user);

        $this->assertArrayHasKey('task', $breakdown);
        $this->assertEquals(100, $breakdown['task']['current']);
        $this->assertEquals(250, $breakdown['task']['cap']);
        $this->assertEquals(40, $breakdown['task']['percentage']);

        $this->assertArrayHasKey('pomodoro', $breakdown);
        $this->assertEquals(150, $breakdown['pomodoro']['cap']);

        $this->assertArrayHasKey('flashcard', $breakdown);
        $this->assertEquals(50, $breakdown['flashcard']['cap']);

        $this->assertArrayHasKey('journal', $breakdown);
        $this->assertEquals(25, $breakdown['journal']['cap']);

        $this->assertArrayHasKey('subtask', $breakdown);
        $this->assertEquals(25, $breakdown['subtask']['cap']);

        $this->assertArrayHasKey('total_routine', $breakdown);
        $this->assertEquals(500, $breakdown['total_routine']['cap']);

        $this->assertArrayHasKey('hidden_mission', $breakdown);
    }

    private function fulfillCriterion(User $user, string $criterion): void
    {
        $today = GamificationService::getUserToday($user);

        switch ($criterion) {
            case 'task':
                $user->daily_xp_earned = 250;
                $user->last_xp_date = $today;
                $user->save();
                break;

            case 'pomodoro':
                FocusSession::create([
                    'user_id' => $user->id,
                    'minutes' => 60,
                    'mode' => 'work',
                    'xp_earned' => 150,
                    'coins_earned' => 1,
                    'created_at' => Carbon::now('Asia/Jakarta'),
                ]);
                break;

            case 'flashcard':
                GamificationService::awardFlashcardXp($user, 10);
                break;

            case 'journal':
                $journal = Journal::create([
                    'user_id' => $user->id,
                    'title' => 'Secret Reflection Journal',
                    'content' => 'Refleksi mendalam untuk membuka misi rahasia harian yang sangat menyenangkan.',
                    'created_at' => Carbon::now('Asia/Jakarta'),
                ]);
                GamificationService::awardJournalXp($user, $journal);
                break;

            case 'subtask':
                $task = Task::create([
                    'user_id' => $user->id,
                    'title' => 'Task for Subtask Fulfillment',
                    'date' => $today,
                ]);
                for ($i = 1; $i <= 5; $i++) {
                    $st = SubTask::create([
                        'task_id' => $task->id,
                        'title' => "Subtask {$i}",
                    ]);
                    GamificationService::awardSubTaskXp($user, $st);
                }
                break;
        }
    }
}
