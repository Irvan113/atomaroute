<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\FocusSession;
use App\Models\Friendship;
use App\Models\User;
use App\Services\CommunityQuestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PwaSrsAndCommunityFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pwa_offline_page_and_manifest_exist_and_valid()
    {
        $offlinePath = public_path('offline.html');
        $this->assertFileExists($offlinePath);
        $offlineContent = file_get_contents($offlinePath);
        $this->assertStringContainsString('Mode Offline', $offlineContent);
        $this->assertStringContainsString('window.addEventListener(\'online\'', $offlineContent);

        $manifestPath = public_path('manifest.json');
        $this->assertFileExists($manifestPath);
        $manifestJson = json_decode(file_get_contents($manifestPath), true);
        $this->assertNotNull($manifestJson);
        $this->assertEquals('ATOMA - Adaptive Focus & Skill Roadmap', $manifestJson['name']);
        $this->assertNotEmpty($manifestJson['icons']);

        $swPath = public_path('sw.js');
        $this->assertFileExists($swPath);
        $swContent = file_get_contents($swPath);
        $this->assertStringContainsString('atoma-pwa-v3', $swContent);
        $this->assertStringContainsString('/offline.html', $swContent);
    }

    public function test_composite_indexes_exist_on_tasks_and_flashcards()
    {
        $this->assertTrue(Schema::hasTable('tasks'));
        $this->assertTrue(Schema::hasTable('flashcards'));

        // Verify tasks and flashcards tables are queryable with composite index patterns
        $user = User::factory()->create();
        $user->tasks()->create([
            'title' => 'Test Task Index',
            'date' => Carbon::today()->toDateString(),
            'mode' => 'zen',
            'is_completed' => false,
        ]);

        $task = $user->tasks()
            ->whereDate('date', Carbon::today()->toDateString())
            ->where('is_completed', false)
            ->first();

        $this->assertNotNull($task);

        // Verify index exists on tasks table
        $indexes = collect(Schema::getIndexes('tasks'))->pluck('name')->toArray();
        $this->assertContains('tasks_user_date_completed_idx', $indexes);
        $this->assertContains('tasks_user_mode_idx', $indexes);

        $card = Flashcard::create([
            'user_id' => $user->id,
            'front' => 'Front Q',
            'back' => 'Back A',
            'deck' => 'Koding',
            'next_review_at' => Carbon::now()->subMinute(),
        ]);

        $dueCard = Flashcard::where('user_id', $user->id)
            ->where('deck', 'Koding')
            ->dueForReview()
            ->first();

        $this->assertNotNull($dueCard);
    }

    public function test_srs_flashcard_integrated_into_today_tasks_and_quick_review()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create 2 due cards and 1 future card
        Flashcard::create([
            'user_id' => $user->id,
            'front' => 'What is SM-2?',
            'back' => 'Spaced Repetition Algorithm',
            'deck' => 'Belajar',
            'repetitions' => 0,
            'next_review_at' => Carbon::now()->subHours(2),
        ]);

        $card2 = Flashcard::create([
            'user_id' => $user->id,
            'front' => 'What is PWA?',
            'back' => 'Progressive Web App',
            'deck' => 'Web',
            'repetitions' => 1,
            'next_review_at' => Carbon::now()->subHours(1),
        ]);

        Flashcard::create([
            'user_id' => $user->id,
            'front' => 'Future card',
            'back' => 'Not due today',
            'deck' => 'Belajar',
            'repetitions' => 2,
            'next_review_at' => Carbon::now()->addDays(3),
        ]);

        $component = Volt::test('tasks')
            ->set('selectedDate', Carbon::today()->toDateString())
            ->assertSee('2 Kartu Flashcard Perlu Diulang (SRS)')
            ->assertSee('Belajar')
            ->assertSee('Web');

        // Execute quick review on card2
        $component->call('recordQuickFlashcardReview', $card2->id, 'good');

        $card2->refresh();
        $this->assertEquals(2, $card2->repetitions);
        $this->assertTrue(Carbon::parse($card2->next_review_at)->isFuture());
    }

    public function test_focus_heartbeat_api_and_silent_study_room()
    {
        $userA = User::factory()->create(['name' => 'User Alice']);
        $userB = User::factory()->create(['name' => 'User Bob']);

        Friendship::create([
            'user_id' => $userA->id,
            'friend_id' => $userB->id,
            'status' => 'accepted',
        ]);

        // User B sends focus heartbeat
        $this->actingAs($userB);
        $response = $this->postJson('/api/focus/heartbeat', [
            'mode' => 'work',
            'task_title' => 'Belajar Matematika',
            'minutes_remaining' => 20,
        ]);
        $response->assertOk();
        $this->assertTrue($response->json('success'));

        // User A views social page and verifies friend User Bob
        $this->actingAs($userA);
        $social = Volt::test('social')
            ->assertDontSee('Ruang Belajar Hening')
            ->assertDontSee('Quest Komunitas Pekan Ini')
            ->assertSee('User Bob');

        $this->assertNotEmpty($social->get('activeFocusFriends'));

        // User A cheers User B
        $social->call('cheerFocusFriend', $userB->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('friend_nudges', [
            'sender_id' => $userA->id,
            'receiver_id' => $userB->id,
            'nudge_type' => 'fire',
        ]);

        // User B stops focus
        $this->actingAs($userB);
        $stopRes = $this->postJson('/api/focus/stop');
        $stopRes->assertOk();

        // User A views social page again, User B is no longer focusing
        $this->actingAs($userA);
        $social = Volt::test('social');
        $this->assertEmpty($social->get('activeFocusFriends'));
    }

    public function test_weekly_community_quest_service_and_claim()
    {
        $user = User::factory()->create(['coins' => 10, 'xp' => 100]);
        $this->actingAs($user);

        // Community quest initially empty
        $quest = CommunityQuestService::getWeeklyQuest($user);
        $this->assertEquals('Pekan Sinergi Fokus', $quest['title']);
        $this->assertFalse($quest['is_completed']);
        $this->assertFalse($quest['user_contributed']);

        // User contributes 25 minutes session
        FocusSession::create([
            'user_id' => $user->id,
            'minutes' => 25,
            'mode' => 'work',
            'created_at' => Carbon::now(),
        ]);

        $questAfterSession = CommunityQuestService::getWeeklyQuest($user);
        $this->assertTrue($questAfterSession['user_contributed']);
        $this->assertEquals(25, $questAfterSession['user_minutes']);

        // Simulate community hitting target (3000 minutes)
        FocusSession::create([
            'user_id' => User::factory()->create()->id,
            'minutes' => 3000,
            'mode' => 'work',
            'created_at' => Carbon::now(),
        ]);

        $completedQuest = CommunityQuestService::getWeeklyQuest($user);
        $this->assertTrue($completedQuest['is_completed']);
        $this->assertTrue($completedQuest['can_claim']);

        // Claim reward via API
        $claimRes = $this->postJson('/api/community-quest/claim');
        $claimRes->assertOk();
        $this->assertTrue($claimRes->json('success'));

        $user->refresh();
        $this->assertEquals(60, $user->coins); // 10 + 50
        $this->assertEquals(200, $user->xp);   // 100 + 100

        // Duplicate claim is rejected
        $claimAgain = $this->postJson('/api/community-quest/claim');
        $claimAgain->assertStatus(422);
    }
}
