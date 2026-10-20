<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Task;
use App\Models\PendingXpLog;
use App\Services\AchievementService;
use App\Services\TaskXpService;
use App\Services\PendingXpService;
use Livewire\Volt\Volt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class PendingXpBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_pending_xp_creates_log_and_increments_pool(): void
    {
        $user = User::factory()->create(['pending_xp' => 0]);

        $user->addPendingXp(150, 'achievement', 'Pencapaian: Master Koding', 'Bonus EXP pencapaian');

        $this->assertEquals(150, $user->fresh()->pending_xp);
        $this->assertDatabaseHas('pending_xp_logs', [
            'user_id' => $user->id,
            'amount' => 150,
            'source' => 'achievement',
            'title' => 'Pencapaian: Master Koding',
            'settled_at' => null,
        ]);
    }

    public function test_subtract_pending_xp_decrements_pool_and_removes_log(): void
    {
        $user = User::factory()->create(['pending_xp' => 0]);

        $user->addPendingXp(30, 'task', 'Tugas: Belajar Laravel', null, 999);
        $this->assertEquals(30, $user->fresh()->pending_xp);

        $user->subtractPendingXp(30, 'task', 999);
        $this->assertEquals(0, $user->fresh()->pending_xp);
        $this->assertDatabaseMissing('pending_xp_logs', [
            'user_id' => $user->id,
            'reference_id' => 999,
        ]);
    }

    public function test_achievement_unlock_records_achievement_pending_xp_log(): void
    {
        $user = User::factory()->create(['pending_xp' => 0]);

        (new AchievementService())->unlock($user, 'kebal_bacok'); // catalog has bonus_xp: 100

        $this->assertEquals(100, $user->fresh()->pending_xp);
        $this->assertDatabaseHas('pending_xp_logs', [
            'user_id' => $user->id,
            'source' => 'achievement',
            'amount' => 100,
        ]);
    }

    public function test_pending_xp_service_reconciles_legacy_untracked_xp(): void
    {
        $user = User::factory()->create([
            'pending_xp' => 1000,
        ]);

        // Unlock achievement directly into DB to simulate legacy state
        \DB::table('user_achievements')->insert([
            'user_id' => $user->id,
            'achievement_code' => 'kebal_bacok', // 100 XP
            'unlocked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $breakdown = PendingXpService::getBreakdown($user);

        $this->assertEquals(1000, $breakdown['total_pending']);
        $this->assertNotEmpty($breakdown['categories']);

        // Check that achievement category exists
        $achievementCat = collect($breakdown['categories'])->firstWhere('key', 'achievement');
        $this->assertNotNull($achievementCat);
        $this->assertEquals(100, $achievementCat['total']);

        // Check that remaining is accounted for in other/general
        $totalCategoriesSum = collect($breakdown['categories'])->sum('total');
        $this->assertEquals(1000, $totalCategoriesSum);
    }

    public function test_midnight_settlement_marks_pending_xp_logs_as_settled(): void
    {
        $user = User::factory()->create(['pending_xp' => 0]);
        $user->addPendingXp(200, 'task', 'Tugas Arsitektur');

        $this->assertEquals(1, PendingXpLog::where('user_id', $user->id)->whereNull('settled_at')->count());

        $user->processDailySettlement();

        $this->assertEquals(0, $user->fresh()->pending_xp);
        $this->assertEquals(0, PendingXpLog::where('user_id', $user->id)->whereNull('settled_at')->count());
        $this->assertEquals(1, PendingXpLog::where('user_id', $user->id)->whereNotNull('settled_at')->count());
    }

    public function test_prestige_page_opens_and_closes_pending_xp_breakdown_modal(): void
    {
        $user = User::factory()->create([
            'pending_xp' => 0,
        ]);

        $user->addPendingXp(500, 'achievement', 'Pencapaian: Grandmaster ATOMA');
        $user->addPendingXp(250, 'task', 'Tugas: Integrasi Database');
        $user->addPendingXp(283, 'pomodoro', 'Fokus Pomodoro (60m)');

        $this->actingAs($user);

        $test = Volt::test('pages.prestige');

        // Modal is closed initially
        $test->assertSet('showPendingModal', false);
        $test->assertSee('+1033');
        $test->assertSee('Pending Hari Ini');

        // Open modal
        $test->call('openPendingBreakdown');
        $test->assertSet('showPendingModal', true);
        $test->assertSee('Rincian EXP Pending Hari Ini');
        $test->assertSee('Pencapaian: Grandmaster ATOMA');
        $test->assertSee('+500 XP');
        $test->assertSee('Tugas: Integrasi Database');
        $test->assertSee('+250 XP');

        // Close modal
        $test->call('closePendingBreakdown');
        $test->assertSet('showPendingModal', false);
    }
}
