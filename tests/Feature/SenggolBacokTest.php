<?php

namespace Tests\Feature;

use App\Models\FriendNudge;
use App\Models\Friendship;
use App\Models\Task;
use App\Models\User;
use App\Services\AchievementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SenggolBacokTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_open_and_set_nudge_types()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $friend = User::factory()->create(['name' => 'Bob', 'allow_friend_nudges' => true]);
        Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        $comp = Volt::actingAs($user)->test('social');
        $comp->call('openSenggolModal', $friend->id);

        $comp->assertSet('showSenggolModal', true);
        $comp->assertSet('targetFriendId', $friend->id);
        $comp->assertSet('selectedNudgeType', 'bacok');

        $comp->call('setNudgeType', 'roast');
        $comp->assertSet('selectedNudgeType', 'roast');

        $comp->call('selectRoastTemplate', 'Minimal buka laptop dulu lahh, masa rebahan terus? 🦥');
        $comp->assertSet('customRoastMessage', 'Minimal buka laptop dulu lahh, masa rebahan terus? 🦥');

        $comp->call('closeSenggolModal');
        $comp->assertSet('showSenggolModal', false);
    }

    public function test_user_can_send_bacok_duel_and_roast()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $friend = User::factory()->create(['name' => 'Bob', 'allow_friend_nudges' => true]);
        Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        $comp = Volt::actingAs($user)->test('social');
        $comp->call('openSenggolModal', $friend->id);
        $comp->call('setNudgeType', 'bacok');
        $comp->call('executeSenggol');

        $comp->assertDispatched('toast');
        $this->assertDatabaseHas('friend_nudges', [
            'sender_id' => $user->id,
            'receiver_id' => $friend->id,
            'nudge_type' => 'bacok',
            'status' => 'pending',
            'target_task_count' => 3,
        ]);

        // Send a shield nudge
        $comp->call('openSenggolModal', $friend->id);
        $comp->call('setNudgeType', 'shield');
        $comp->call('executeSenggol');

        $this->assertDatabaseHas('friend_nudges', [
            'sender_id' => $user->id,
            'receiver_id' => $friend->id,
            'nudge_type' => 'shield',
        ]);
    }

    public function test_cooldown_prevents_spamming_same_nudge_type()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $friend = User::factory()->create(['name' => 'Bob', 'allow_friend_nudges' => true]);
        Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        $comp = Volt::actingAs($user)->test('social');
        $comp->call('openSenggolModal', $friend->id);
        $comp->call('setNudgeType', 'fire');
        $comp->call('executeSenggol');

        // Try again immediately
        $comp->call('openSenggolModal', $friend->id);
        $comp->call('setNudgeType', 'fire');
        $comp->call('executeSenggol');

        $comp->assertDispatched('toast', function ($name, $params) {
            return str_contains($params['message'] ?? '', 'Tunggu');
        });

        $this->assertEquals(1, FriendNudge::where('sender_id', $user->id)->where('nudge_type', 'fire')->count());
    }

    public function test_incoming_nudge_modal_and_duel_response()
    {
        $sender = User::factory()->create(['name' => 'Alice']);
        $receiver = User::factory()->create(['name' => 'Bob', 'allow_friend_nudges' => true]);
        Friendship::create(['user_id' => $sender->id, 'friend_id' => $receiver->id, 'status' => 'accepted']);

        $nudge = FriendNudge::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'nudge_type' => 'bacok',
            'message' => 'Tantangan Duel 24 Jam!',
            'status' => 'pending',
            'target_task_count' => 3,
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $comp = Volt::actingAs($receiver)->test('social');
        $comp->assertSet('showIncomingNudgeModal', true);
        $comp->assertSet('activeIncomingNudgeId', $nudge->id);

        // Accept duel
        $comp->call('respondToDuel', $nudge->id, 'accept');

        $nudge->refresh();
        $this->assertEquals('accepted', $nudge->status);
        $this->assertNotNull($nudge->acknowledged_at);
    }

    public function test_task_completion_progresses_and_wins_duel()
    {
        $sender = User::factory()->create(['name' => 'Alice', 'xp' => 100]);
        $receiver = User::factory()->create(['name' => 'Bob', 'xp' => 100]);

        $nudge = FriendNudge::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'nudge_type' => 'bacok',
            'message' => 'Duel!',
            'status' => 'accepted',
            'sender_task_count' => 2,
            'receiver_task_count' => 1,
            'target_task_count' => 3,
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $task = Task::create([
            'user_id' => $sender->id,
            'title' => 'Tugas Penentu Kemenangan',
            'date' => Carbon::now()->toDateString(),
            'is_completed' => false,
            'xp_reward' => 20,
        ]);

        // Complete task via tasks component
        $tasksComp = Volt::actingAs($sender)->test('tasks');
        $tasksComp->call('toggleComplete', $task->id);

        $nudge->refresh();
        $this->assertEquals(3, $nudge->sender_task_count);
        $this->assertEquals('completed', $nudge->status);
        $this->assertEquals($sender->id, $nudge->winner_id);

        // Sender gets bonus duel win XP (+50) plus task XP (+20) in pending_xp
        $sender->refresh();
        $this->assertGreaterThanOrEqual(70, $sender->pending_xp);
    }

    public function test_kebal_bacok_achievement_unlocked_on_completion_after_nudge()
    {
        $sender = User::factory()->create(['name' => 'Alice']);
        $receiver = User::factory()->create(['name' => 'Bob', 'xp' => 50]);

        // Alice sends a task nudge to Bob
        FriendNudge::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'nudge_type' => 'task',
            'message' => 'Ayo selesaikan tugasmu!',
            'status' => 'pending',
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        $task = Task::create([
            'user_id' => $receiver->id,
            'title' => 'Tugas Anti Malas',
            'date' => Carbon::now()->toDateString(),
            'is_completed' => false,
            'xp_reward' => 15,
        ]);

        $tasksComp = Volt::actingAs($receiver)->test('tasks');
        $tasksComp->call('toggleComplete', $task->id);

        // Check if Bob unlocked kebal_bacok badge
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $receiver->id,
            'achievement_code' => 'kebal_bacok',
        ]);
    }

    public function test_head_to_head_calculation()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $friend = User::factory()->create(['name' => 'Bob']);

        // Duel 1: User won
        FriendNudge::create([
            'sender_id' => $user->id,
            'receiver_id' => $friend->id,
            'nudge_type' => 'bacok',
            'status' => 'completed',
            'winner_id' => $user->id,
        ]);

        // Duel 2: Friend won
        FriendNudge::create([
            'sender_id' => $friend->id,
            'receiver_id' => $user->id,
            'nudge_type' => 'bacok',
            'status' => 'completed',
            'winner_id' => $friend->id,
        ]);

        // General nudge
        FriendNudge::create([
            'sender_id' => $user->id,
            'receiver_id' => $friend->id,
            'nudge_type' => 'fire',
            'status' => 'pending',
        ]);

        $comp = Volt::actingAs($user)->test('social');
        $h2h = $comp->instance()->getHeadToHeadScore($friend->id);

        $this->assertEquals(3, $h2h['total_exchanges']);
        $this->assertEquals(1, $h2h['my_wins']);
        $this->assertEquals(1, $h2h['friend_wins']);
    }

    public function test_active_duel_shows_indicator_and_prevents_spam()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $friend = User::factory()->create(['name' => 'Bob', 'allow_friend_nudges' => true]);
        Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        // Create accepted active duel
        $duel = FriendNudge::create([
            'sender_id' => $user->id,
            'receiver_id' => $friend->id,
            'nudge_type' => 'bacok',
            'status' => 'accepted',
            'target_task_count' => 3,
            'sender_task_count' => 1,
            'receiver_task_count' => 2,
            'expires_at' => now()->addHours(12),
        ]);

        $comp = Volt::actingAs($user)->test('social');
        $activeDuels = $comp->instance()->activeDuels();

        $this->assertTrue($activeDuels->has($friend->id));
        $comp->assertSee('Duel Berlangsung: 1 vs 2/3');
        $comp->assertSee('Sedang Berduel');

        // Trying to execute another bacok duel should be blocked
        $comp->call('executeSenggol', $friend->id, 'bacok');
        $comp->assertDispatched('toast');

        // Number of bacok duels should still be 1
        $this->assertEquals(1, FriendNudge::where('nudge_type', 'bacok')->count());
    }

    public function test_social_feed_orders_by_latest_activity()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $friend = User::factory()->create(['name' => 'Bob']);

        // Post 1 (Older)
        $post1 = \App\Models\Post::create([
            'user_id' => $user->id,
            'title' => 'Post Pertama',
            'content' => 'Konten pertama #General',
        ]);
        $post1->timestamps = false;
        $post1->created_at = now()->subHours(5);
        $post1->updated_at = now()->subHours(5);
        $post1->save();

        // Post 2 (Newer)
        $post2 = \App\Models\Post::create([
            'user_id' => $friend->id,
            'title' => 'Post Kedua',
            'content' => 'Konten kedua #General',
        ]);
        $post2->timestamps = false;
        $post2->created_at = now()->subHours(1);
        $post2->updated_at = now()->subHours(1);
        $post2->save();

        $comp = Volt::actingAs($user)->test('social');
        $posts = $comp->instance()->posts();
        $this->assertEquals($post2->id, $posts->first()->id);

        // Bob comments on Post 1, bumping its updated_at
        $comp->call('openTopic', $post1->id);
        $comp->set('threadCommentContent', 'Komentar baru yang membangkitkan postingan lama!');
        $comp->call('sendThreadComment');

        $updatedPosts = $comp->instance()->posts();
        $this->assertEquals($post1->id, $updatedPosts->first()->id);
    }

    public function test_friend_realtime_daily_task_completion_rate_and_reminder()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $friend = User::factory()->create(['name' => 'Bob', 'allow_friend_nudges' => true]);
        Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        // Bob has 4 tasks today: 3 completed (75%)
        for ($i = 1; $i <= 3; $i++) {
            Task::create([
                'user_id' => $friend->id,
                'title' => "Tugas Selesai {$i}",
                'date' => Carbon::today()->toDateString(),
                'is_completed' => true,
            ]);
        }
        Task::create([
            'user_id' => $friend->id,
            'title' => "Tugas Belum Selesai 4",
            'date' => Carbon::today()->toDateString(),
            'is_completed' => false,
        ]);

        $comp = Volt::actingAs($user)->test('social');
        $friends = $comp->instance()->friends();
        $bob = $friends->firstWhere('id', $friend->id);

        $this->assertEquals(4, $bob->today_total_tasks);
        $this->assertEquals(3, $bob->today_completed_tasks);
        $this->assertEquals(75, $bob->today_completion_rate);

        $comp->assertSee('Tugas Hari Ini:')
            ->assertSee('3/4')
            ->assertSee('75%')
            ->assertSee('Ingatkan Tugas');

        // Alice sends reminder
        $comp->call('sendTaskReminder', $friend->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('messages', [
            'sender_id' => $user->id,
            'receiver_id' => $friend->id,
        ]);
    }
}

