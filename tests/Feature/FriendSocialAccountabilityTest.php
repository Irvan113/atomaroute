<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class FriendSocialAccountabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_friend_subtabs_navigation()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $friend = User::factory()->create(['name' => 'Bob']);
        Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        $comp = Volt::actingAs($user)->test('social');
        $comp->assertSee('Bob');
        $comp->assertSee('Social Accountability');

        $comp->call('setFriendSubTab', 'find');
        $comp->assertSet('friendSubTab', 'find');

        $comp->call('setFriendSubTab', 'requests');
        $comp->assertSet('friendSubTab', 'requests');
    }

    public function test_user_can_save_privacy_settings()
    {
        $user = User::factory()->create([
            'share_profile_with_friends' => true,
            'share_daily_tasks' => true,
            'share_achievements' => true,
            'allow_friend_nudges' => true,
        ]);

        $comp = Volt::actingAs($user)->test('social');
        $comp->call('openPrivacyModal');
        $comp->assertSet('showPrivacyModal', true);

        $comp->set('privacyShareProfile', false);
        $comp->set('privacyShareTasks', false);
        $comp->call('savePrivacySettings');

        $user->refresh();
        $this->assertFalse($user->share_profile_with_friends);
        $this->assertFalse($user->share_daily_tasks);
    }

    public function test_friend_profile_showcase_modal_with_accountability_and_achievements()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $friend = User::factory()->create([
            'name' => 'Bob',
            'share_profile_with_friends' => true,
            'share_daily_tasks' => true,
            'share_achievements' => true,
            'current_streak' => 10,
            'xp' => 2500,
        ]);

        // Create tasks for today
        $today = now('Asia/Jakarta')->toDateString();
        Task::create([
            'user_id' => $friend->id,
            'title' => 'Belajar Laravel Livewire',
            'date' => $today,
            'is_completed' => false,
        ]);
        Task::create([
            'user_id' => $friend->id,
            'title' => 'Latihan Coding Pagi',
            'date' => $today,
            'is_completed' => true,
        ]);

        Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        $comp = Volt::actingAs($user)->test('social');
        $comp->call('openFriendProfile', $friend->id);
        $comp->assertSet('showProfileModal', true);
        $comp->assertSee('Showcase Pencapaian');
        $comp->assertSee('Belajar Laravel Livewire');
        $comp->assertSee('Target Hari Ini');
    }

    public function test_private_profile_displays_privacy_notice()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $privateFriend = User::factory()->create([
            'name' => 'Charlie',
            'share_profile_with_friends' => false,
        ]);

        Friendship::create(['user_id' => $user->id, 'friend_id' => $privateFriend->id, 'status' => 'accepted']);

        $comp = Volt::actingAs($user)->test('social');
        $comp->call('openFriendProfile', $privateFriend->id);
        $comp->assertSet('showProfileModal', true);
        $comp->assertSee('Profil Ini Disetel Privat');
    }

    public function test_user_can_send_task_reminder_to_friend()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $friend = User::factory()->create([
            'name' => 'Bob',
            'allow_friend_nudges' => true,
        ]);

        Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        $comp = Volt::actingAs($user)->test('social');
        $comp->call('sendTaskReminder', $friend->id);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $user->id,
            'receiver_id' => $friend->id,
        ]);
    }

    public function test_varied_nudges_fire_task_challenge()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $friend = User::factory()->create([
            'name' => 'Bob',
            'allow_friend_nudges' => true,
        ]);

        Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        $comp = Volt::actingAs($user)->test('social');
        
        $comp->call('sendNudge', $friend->id, 'fire');
        $comp->assertDispatched('toast');

        $comp->call('sendNudge', $friend->id, 'task');
        $comp->assertDispatched('toast');

        $comp->call('sendNudge', $friend->id, 'challenge');
        $comp->assertDispatched('toast');
    }

    public function test_friend_card_renders_cleanly_with_streak_and_level()
    {
        $user = User::factory()->create(['name' => 'Alice', 'weekly_xp' => 450]);
        $friend = User::factory()->create(['name' => 'Bob', 'weekly_xp' => 300, 'current_streak' => 7, 'level' => 5]);

        Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        $comp = Volt::actingAs($user)->test('social');
        $comp->assertSee('Bob');
        $comp->assertSee('7 Hari Streak');
        $comp->assertSee('Lvl 5');
    }

    public function test_user_can_send_friend_request()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $comp = Volt::actingAs($user)->test('social');
        $comp->call('sendRequest', $bob->id);
        $comp->assertDispatched('toast');

        $this->assertDatabaseHas('friendships', [
            'user_id' => $user->id,
            'friend_id' => $bob->id,
            'status' => 'pending',
        ]);

        $this->assertEquals(1, $bob->unreadNotifications()->count());
    }

    public function test_user_can_accept_friend_request()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $friendship = Friendship::create([
            'user_id' => $bob->id,
            'friend_id' => $user->id,
            'status' => 'pending',
        ]);

        $comp = Volt::actingAs($user)->test('social');
        $comp->call('acceptRequest', $friendship->id);
        $comp->assertDispatched('toast');

        $this->assertDatabaseHas('friendships', [
            'id' => $friendship->id,
            'status' => 'accepted',
        ]);
    }

    public function test_user_can_reject_friend_request()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $friendship = Friendship::create([
            'user_id' => $bob->id,
            'friend_id' => $user->id,
            'status' => 'pending',
        ]);

        $comp = Volt::actingAs($user)->test('social');
        $comp->call('rejectRequest', $friendship->id);
        $comp->assertDispatched('toast');

        $this->assertDatabaseMissing('friendships', [
            'id' => $friendship->id,
        ]);
    }

    public function test_user_can_cancel_sent_friend_request()
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $friendship = Friendship::create([
            'user_id' => $user->id,
            'friend_id' => $bob->id,
            'status' => 'pending',
        ]);

        $comp = Volt::actingAs($user)->test('social');
        $comp->call('cancelRequest', $friendship->id);
        $comp->assertDispatched('toast');

        $this->assertDatabaseMissing('friendships', [
            'id' => $friendship->id,
        ]);
    }
}
