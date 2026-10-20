<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SubscriptionTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_subscriptions_page(): void
    {
        // First user gets ID 1, second user gets ID 2
        User::factory()->create();
        $regularUser = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($regularUser);

        $response = $this->get('/admin/subscriptions');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_subscriptions_page(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/admin/subscriptions');
        $response->assertStatus(200);
        $response->assertSee('Manajemen Langganan');
    }

    public function test_admin_quick_grant_monthly_pro_to_user(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $targetUser = User::factory()->create([
            'subscription_tier' => 'free',
        ]);

        $this->actingAs($admin);

        Livewire::test('pages.admin-subscriptions')
            ->call('quickGrant', $targetUser->id, 'monthly')
            ->assertDispatched('toast');

        $targetUser->refresh();
        $this->assertTrue($targetUser->isPro());
        $this->assertNotNull($targetUser->subscription_expires_at);
        $this->assertTrue($targetUser->subscription_expires_at->isFuture());

        // Assert transaction was recorded
        $this->assertDatabaseHas('subscription_transactions', [
            'user_id' => $targetUser->id,
            'admin_id' => $admin->id,
            'plan_type' => 'monthly',
            'status' => 'active',
        ]);
    }

    public function test_admin_custom_grant_and_revoke_pro(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $targetUser = User::factory()->create([
            'subscription_tier' => 'free',
        ]);

        $this->actingAs($admin);

        // Custom grant
        Livewire::test('pages.admin-subscriptions')
            ->call('openCustomModal', $targetUser->id)
            ->set('modalPlanType', 'yearly')
            ->set('modalPaymentMethod', 'manual_transfer')
            ->set('modalAmount', 199000)
            ->set('modalNotes', 'Transfer via BCA #TRX12345')
            ->call('saveCustomGrant')
            ->assertDispatched('toast');

        $targetUser->refresh();
        $this->assertTrue($targetUser->isPro());

        $this->assertDatabaseHas('subscription_transactions', [
            'user_id' => $targetUser->id,
            'amount' => 199000,
            'notes' => 'Transfer via BCA #TRX12345',
        ]);

        // Revoke PRO
        Livewire::test('pages.admin-subscriptions')
            ->call('revokeUserPro', $targetUser->id)
            ->assertDispatched('toast');

        $targetUser->refresh();
        $this->assertFalse($targetUser->isPro());
        $this->assertEquals('free', $targetUser->subscription_tier);
    }

    public function test_user_can_view_subscription_history_and_digital_receipt_on_profile(): void
    {
        $user = User::factory()->create([
            'subscription_tier' => 'pro',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        // Create transaction history
        $tx = SubscriptionTransaction::create([
            'user_id' => $user->id,
            'reference_no' => 'ATM-SUB-TEST-001',
            'plan_type' => 'monthly',
            'plan_name' => 'ATOMA PRO Bulanan (30 Hari)',
            'amount' => 29000,
            'payment_method' => 'manual_transfer',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
            'notes' => 'Aktivasi manual',
        ]);

        $this->actingAs($user);

        $response = $this->get('/profile');
        $response->assertStatus(200);
        $response->assertSee('ATM-SUB-TEST-001');
        $response->assertSee('ATOMA PRO Bulanan');
        $response->assertSee('Rp 29.000');
    }

    public function test_expiring_soon_user_sees_reminder_banner_on_app(): void
    {
        // First user is ID 1
        User::factory()->create();

        // Expiring in 2 days
        $expiringUser = User::factory()->create([
            'is_admin' => false,
            'subscription_tier' => 'pro',
            'subscription_expires_at' => now()->addDays(2),
        ]);

        $this->actingAs($expiringUser);
        $response = $this->get('/tasks');
        $response->assertStatus(200);
        $response->assertSee('Pengingat Masa Aktif ATOMA PRO');
        $response->assertSee('Perpanjang via Admin');

        // User with 25 days remaining does NOT see the reminder
        $activeUser = User::factory()->create([
            'is_admin' => false,
            'subscription_tier' => 'pro',
            'subscription_expires_at' => now()->addDays(25),
        ]);

        $this->actingAs($activeUser);
        $response2 = $this->get('/tasks');
        $response2->assertStatus(200);
        $response2->assertDontSee('Pengingat Masa Aktif ATOMA PRO');
    }
}
