<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserFeedback;
use Livewire\Volt\Volt;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_feedback_letter_to_developer()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('feedback-modal')
            ->set('type', 'feature')
            ->set('subject', 'Ide Mode Focus Pomodoro dengan Musik Lofi')
            ->set('message', 'Halo Developer, saya suka sekali ATOMA. Tolong tambahkan pilihan musik lofi otomatis.')
            ->set('mood', 'love')
            ->set('includeDeviceInfo', true)
            ->set('clientDeviceInfo', ['platform' => 'macOS', 'url' => '/dashboard'])
            ->call('sendFeedback')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('sentSuccess', true);

        $this->assertDatabaseHas('user_feedbacks', [
            'user_id' => $user->id,
            'type' => 'feature',
            'subject' => 'Ide Mode Focus Pomodoro dengan Musik Lofi',
            'mood' => 'love',
            'status' => 'pending',
        ]);
    }

    public function test_feedback_requires_valid_subject_and_message()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('feedback-modal')
            ->set('subject', 'ab') // < 3 chars
            ->set('message', 'pendek') // < 10 chars
            ->call('sendFeedback')
            ->assertHasErrors(['subject', 'message']);
    }

    public function test_user_can_only_see_their_own_feedbacks()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        UserFeedback::create([
            'user_id' => $user1->id,
            'type' => 'critique',
            'subject' => 'Pesan dari User 1',
            'message' => 'Ini kritik rahasia dari user 1 untuk developer.',
            'status' => 'pending',
        ]);

        UserFeedback::create([
            'user_id' => $user2->id,
            'type' => 'bug',
            'subject' => 'Pesan dari User 2',
            'message' => 'Ini bug laporan dari user 2 untuk developer.',
            'status' => 'pending',
        ]);

        $this->actingAs($user1);

        $test = Volt::test('feedback-modal');
        $feedbacks = $test->get('myFeedbacks');

        $this->assertCount(1, $feedbacks);
        $this->assertEquals('Pesan dari User 1', $feedbacks->first()->subject);
    }

    public function test_admin_can_access_feedbacks_page_update_status_and_reply()
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $regularUser = User::factory()->create();

        $feedback = UserFeedback::create([
            'user_id' => $regularUser->id,
            'type' => 'bug',
            'subject' => 'Tombol kalender tidak bisa diklik di mobile',
            'message' => 'Saat membuka via ponsel Chrome, tombol kalender terhalang sedikit.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin);

        // 1. Visit page
        $response = $this->get(route('admin.feedbacks'));
        $response->assertOk();

        // 2. Test Livewire admin actions
        Volt::test('pages.admin-feedbacks')
            ->call('updateStatus', $feedback->id, 'in_progress')
            ->call('openReply', $feedback->id)
            ->set('replyMessage', 'Terima kasih laporannya! Kami sudah memperbaiki styling tombol kalender.')
            ->call('saveReply')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $feedback->refresh();
        $this->assertEquals('in_progress', $feedback->status);
        $this->assertEquals('Terima kasih laporannya! Kami sudah memperbaiki styling tombol kalender.', $feedback->dev_reply);
        $this->assertNotNull($feedback->dev_replied_at);
    }

    public function test_non_admin_is_forbidden_from_admin_feedbacks_page()
    {
        $user = User::factory()->create([
            'id' => 999, // not ID 1
            'is_admin' => false,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('admin.feedbacks'));
        $response->assertForbidden();
    }

    public function test_user_can_switch_to_donate_tab()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('feedback-modal')
            ->call('openModal', 'donate')
            ->assertSet('activeTab', 'donate')
            ->assertSet('showModal', true)
            ->assertSee('Saweria')
            ->assertDontSee('Trakteer')
            ->assertDontSee('About Dev');
    }

    public function test_user_can_prepare_donation_letter()
    {
        $user = User::factory()->create(['name' => 'Waguri']);
        $this->actingAs($user);

        $test = Volt::test('feedback-modal')
            ->call('prepareDonationLetter')
            ->assertSet('activeTab', 'compose')
            ->assertSet('type', 'donation')
            ->assertSet('mood', 'love');

        $this->assertStringContainsString('Waguri', $test->get('subject'));
        $this->assertNotEmpty($test->get('message'));
    }
}
