<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_bell_loads_notifications_and_actions()
    {
        $user = User::factory()->create();

        // Send 2 notifications to user
        $user->notify(new AppNotification([
            'icon' => '⏰',
            'message' => 'Jangan lupa tugas hari ini!',
        ]));

        $user->notify(new AppNotification([
            'icon' => '⚔️',
            'message' => 'Teman mengirimkan duel!',
        ]));

        $comp = Volt::actingAs($user)->test('notification-bell');

        $comp->assertSet('unreadCount', 2)
            ->assertSet('totalCount', 2);

        // Switch filter to unread
        $comp->call('setFilter', 'unread');
        $comp->assertSet('filter', 'unread');

        // Mark all as read
        $comp->call('markAllAsRead');
        $comp->assertSet('unreadCount', 0);
        $this->assertEquals(0, $user->unreadNotifications()->count());

        // Clear all
        $comp->call('clearAll');
        $comp->assertSet('totalCount', 0);
        $this->assertEquals(0, $user->notifications()->count());
    }

    public function test_notification_bell_supports_custom_alignment_and_close()
    {
        $user = User::factory()->create();

        $compLeft = Volt::actingAs($user)->test('notification-bell', ['align' => 'left']);
        $compLeft->assertSet('align', 'left')
            ->assertSee('sm:left-0');

        $compRight = Volt::actingAs($user)->test('notification-bell', ['align' => 'right']);
        $compRight->assertSet('align', 'right')
            ->assertSee('sm:right-0');

        // Test toggle and close
        $compRight->call('toggle');
        $compRight->assertSet('isOpen', true);
        $compRight->call('close');
        $compRight->assertSet('isOpen', false);
    }
}
