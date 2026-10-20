<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Task;
use App\Models\SubTask;
use App\Models\FocusSession;
use App\Services\PrestigeService;
use App\Services\RpgStatsService;
use App\Services\TaskXpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class PrestigeSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_exponential_xp_curve_calculation()
    {
        $this->assertEquals(100, PrestigeService::xpRequiredForLevel(1));
        $this->assertEquals(283, PrestigeService::xpRequiredForLevel(2));
        $this->assertEquals(8944, PrestigeService::xpRequiredForLevel(20));
        $this->assertEquals(35355, PrestigeService::xpRequiredForLevel(50));
    }

    public function test_level_up_progression_within_tier()
    {
        $user = User::factory()->create([
            'prestige_tier' => 1,
            'prestige_level' => 1,
            'prestige_xp' => 0,
        ]);

        // Add 50 XP (less than 100 needed for Lv.1)
        $res1 = PrestigeService::addXp($user, 50);
        $this->assertFalse($res1['leveled_up']);
        $this->assertEquals(1, $user->prestige_level);
        $this->assertEquals(50, $user->prestige_xp);

        // Add 60 XP (total 110 XP -> 100 spent for Lv.1, 10 leftover for Lv.2)
        $res2 = PrestigeService::addXp($user, 60);
        $this->assertTrue($res2['leveled_up']);
        $this->assertFalse($res2['ascended']);
        $this->assertEquals(2, $user->prestige_level);
        $this->assertEquals(10, $user->prestige_xp);
        $this->assertEquals(1, $user->prestige_tier);
    }

    public function test_automatic_ascension_to_next_tier_when_reaching_max_level()
    {
        $user = User::factory()->create([
            'prestige_tier' => 1,
            'prestige_level' => 50,
            'prestige_xp' => 0,
        ]);

        // XP needed for Lv.50 is 35,355
        $req = PrestigeService::xpRequiredForLevel(50);
        $res = PrestigeService::addXp($user, $req);

        $this->assertTrue($res['ascended']);
        $this->assertEquals(2, $user->prestige_tier); // Promoted to Practitioner
        $this->assertEquals(1, $user->prestige_level); // Reset to level 1
        $this->assertEquals('⚔️ Practitioner Lv.1', $user->prestige_title);
    }

    public function test_tier_5_ascended_caps_at_level_50()
    {
        $user = User::factory()->create([
            'prestige_tier' => 5, // Max tier (Ascended)
            'prestige_level' => 50,
            'prestige_xp' => 0,
        ]);

        $req = PrestigeService::xpRequiredForLevel(50);
        $res = PrestigeService::addXp($user, $req + 500);

        $this->assertFalse($res['ascended']);
        $this->assertEquals(5, $user->prestige_tier);
        $this->assertEquals(50, $user->prestige_level);
    }

    public function test_progressive_overload_quality_multiplier()
    {
        // 1. Habit building phase user (level <= 10) -> multiplier is always 1.0
        $noviceUser = User::factory()->create([
            'prestige_tier' => 1,
            'prestige_level' => 5,
        ]);

        $plainTask = Task::create([
            'user_id' => $noviceUser->id,
            'title' => 'Plain Task 1',
            'date' => today()->toDateString(),
        ]);

        $multNovice = PrestigeService::getQualityMultiplier($noviceUser, $plainTask);
        $factorNovice = PrestigeService::getPlainTaskFactor($noviceUser, $plainTask);
        $this->assertEquals(1.0, $multNovice);
        $this->assertEquals(1.0, $factorNovice);

        // 2. High tier user (Tier 2 Level 10 = effective level 60)
        $masterUser = User::factory()->create([
            'prestige_tier' => 2,
            'prestige_level' => 10,
        ]);

        // Quality task with >= 2 subtasks
        $qualityTask = Task::create([
            'user_id' => $masterUser->id,
            'title' => 'Complex Architecture Refactoring',
            'date' => today()->toDateString(),
            'estimated_minutes' => 60,
        ]);

        SubTask::create(['task_id' => $qualityTask->id, 'title' => 'Sub 1', 'is_completed' => false]);
        SubTask::create(['task_id' => $qualityTask->id, 'title' => 'Sub 2', 'is_completed' => false]);

        $qualityMult = PrestigeService::getQualityMultiplier($masterUser, $qualityTask);
        $this->assertGreaterThan(1.0, $qualityMult); // Quality task gets bonus!

        // Plain task gets reduced factor
        $plainHighTierTask = Task::create([
            'user_id' => $masterUser->id,
            'title' => 'Just a one-liner note',
            'date' => today()->toDateString(),
        ]);

        $plainFactor = PrestigeService::getPlainTaskFactor($masterUser, $plainHighTierTask);
        $this->assertLessThan(1.0, $plainFactor);
    }

    public function test_rpg_stats_calculation_from_historical_data()
    {
        $user = User::factory()->create([
            'current_streak' => 15,
        ]);

        // Focus sessions (120 minutes = 2 hours)
        FocusSession::create([
            'user_id' => $user->id,
            'minutes' => 120,
            'mode' => 'work',
        ]);

        // Completed task on time
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'On Time Task',
            'date' => today()->toDateString(),
            'is_completed' => true,
            'category' => 'Engineering',
            'priority' => 'high',
            'estimated_minutes' => 45,
            'updated_at' => now(),
        ]);

        SubTask::create(['task_id' => $task->id, 'title' => 'Subtask 1', 'is_completed' => true]);

        $stats = RpgStatsService::calculateAllStats($user);

        $this->assertGreaterThan(0, $stats['focus']);
        $this->assertGreaterThan(0, $stats['consistency']);
        $this->assertGreaterThan(0, $stats['execution']);
        $this->assertGreaterThan(0, $stats['organization']);

        // Stats persisted to user
        $user->refresh();
        $this->assertEquals($stats['focus'], $user->stat_focus);
        $this->assertEquals($stats['consistency'], $user->stat_consistency);
        $this->assertEquals($stats['execution'], $user->stat_execution);
        $this->assertEquals($stats['organization'], $user->stat_organization);
    }

    public function test_task_completion_triggers_prestige_xp()
    {
        $user = User::factory()->create([
            'prestige_tier' => 1,
            'prestige_level' => 1,
            'prestige_xp' => 0,
            'pending_xp' => 0,
            'daily_xp_earned' => 0,
        ]);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Writing comprehensive unit tests',
            'date' => today()->toDateString(),
            'created_at' => now()->subMinutes(30),
        ]);

        $result = TaskXpService::evaluateTaskCompletion($task, $user);

        $this->assertGreaterThan(0, $result['xp']);
        $this->assertArrayHasKey('prestige', $result);

        $user->refresh();
        // EXP enters Pending XP pool awaiting midnight H+1 settlement
        $this->assertEquals($result['xp'], $user->pending_xp);
        $this->assertEquals($result['xp'], $user->daily_xp_earned);
        $this->assertEquals(1, $user->total_tasks_completed);
    }

    public function test_daily_settlement_synchronizes_prestige_and_lifetime_xp()
    {
        $user = User::factory()->create([
            'prestige_tier' => 1,
            'prestige_level' => 2,
            'prestige_xp' => 50,
            'pending_xp' => 150,
            'xp' => 200,
            'lifetime_xp' => 200,
            'last_settlement_date' => Carbon::yesterday()->toDateString(),
        ]);

        $settlement = $user->processDailySettlement();

        $this->assertTrue($settlement['settled']);
        $this->assertEquals(150, $settlement['xp']);
        $this->assertArrayHasKey('prestige_title', $settlement);
        $this->assertArrayHasKey('prestige_tier', $settlement);

        $user->refresh();
        $this->assertEquals(0, $user->pending_xp);
        $this->assertEquals(350, $user->xp);
        $this->assertEquals(350, $user->lifetime_xp);
        // 50 + 150 = 200 prestige XP (enough to level up or progress)
        $this->assertGreaterThan(50, $user->prestige_xp + ($user->prestige_level > 2 ? 283 : 0));
    }

    public function test_task_done_and_undone_does_not_exploit_xp()
    {
        $user = User::factory()->create([
            'prestige_tier' => 1,
            'prestige_level' => 1,
            'prestige_xp' => 0,
            'pending_xp' => 0,
            'daily_xp_earned' => 0,
        ]);

        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Mengerjakan Feature ATOMA',
            'date' => today()->toDateString(),
        ]);
        $task->created_at = now()->subMinutes(30);
        $task->save();

        // 1. First Done
        $res1 = TaskXpService::evaluateTaskCompletion($task, $user);
        $this->assertEquals(25, $res1['xp']);
        $user->refresh();
        $this->assertEquals(25, $user->pending_xp);
        $this->assertEquals(25, $user->daily_xp_earned);
        $this->assertEquals(0, $user->prestige_xp); // Unaltered before midnight!

        // 2. First Undone
        TaskXpService::revertTaskCompletion($task, $user);
        $user->refresh();
        $this->assertEquals(0, $user->pending_xp);
        $this->assertEquals(0, $user->daily_xp_earned);
        $this->assertEquals(0, $user->prestige_xp);
        $this->assertEquals(0, $task->fresh()->earned_xp);

        // 3. Second Done (Cannot accumulate duplicate or leaked XP)
        $res2 = TaskXpService::evaluateTaskCompletion($task, $user);
        $this->assertEquals(25, $res2['xp']);
        $user->refresh();
        $this->assertEquals(25, $user->pending_xp);
        $this->assertEquals(25, $user->daily_xp_earned);
        $this->assertEquals(0, $user->prestige_xp);

        // 4. Second Undone
        TaskXpService::revertTaskCompletion($task, $user);
        $user->refresh();
        $this->assertEquals(0, $user->pending_xp);
        $this->assertEquals(0, $user->daily_xp_earned);
        $this->assertEquals(0, $user->prestige_xp);
    }

    public function test_prestige_page_renders_successfully()
    {
        $user = User::factory()->create([
            'prestige_tier' => 1,
            'prestige_level' => 3,
            'prestige_xp' => 50,
            'pending_xp' => 20,
        ]);

        $response = $this->actingAs($user)->get(route('prestige'));
        $response->assertStatus(200);
        $response->assertSee('Sistem Level Up');
        $response->assertSee('Novice');
        $response->assertSee('+20');
        $response->assertSee('Kalkulator & Rumus Kebutuhan EXP', false);
    }
}
