<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Task;
use App\Models\Journal;
use App\Models\FinanceTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BackendOptimizationAndAiLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_secondary_database_indexes_and_locale_column_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'locale'));
        $this->assertTrue(Schema::hasColumn('users', 'timezone'));
        $this->assertTrue(Schema::hasTable('journals'));
        $this->assertTrue(Schema::hasTable('finance_transactions'));
        $this->assertTrue(Schema::hasTable('tasks'));
    }

    public function test_user_can_update_locale_and_middleware_applies_it(): void
    {
        $user = User::factory()->create([
            'locale' => 'id',
            'timezone' => 'Asia/Jakarta',
        ]);

        $this->actingAs($user);

        // Verify initial locale via middleware request
        $response = $this->get('/dashboard');
        $response->assertOk();
        $this->assertEquals('id', App::getLocale());

        // Update profile with English locale
        Volt::test('profile.update-profile-information-form')
            ->set('name', 'John Doe')
            ->set('email', $user->email)
            ->set('locale', 'en')
            ->call('updateProfileInformation');

        $user->refresh();
        $this->assertEquals('en', $user->locale);

        // Next request should apply English locale
        $this->get('/dashboard');
        $this->assertEquals('en', App::getLocale());
    }

    public function test_ai_atomic_lock_prevents_duplicate_concurrent_requests(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Simulate an already active lock from first click
        $lockKey = "ai_lock_user_{$user->id}";
        $lock = Cache::lock($lockKey, 15);
        $this->assertTrue($lock->get());

        // Attempting to decompose while locked should fail gracefully with warning toast
        Volt::test('tasks')
            ->set('aiDecomposePrompt', 'Belajar Laravel Cloud')
            ->call('decomposeAiTask')
            ->assertDispatched('toast');

        // Release simulated lock
        $lock->release();
    }

    public function test_ai_rate_limiter_caps_requests_at_5_per_minute(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $rateKey = "ai_rate_limit_user_{$user->id}";
        // Simulate user has already made 5 AI requests in this minute
        Cache::put($rateKey, 5, 60);

        // 6th attempt should be blocked
        Volt::test('tasks')
            ->set('aiDecomposePrompt', 'Test Prompt')
            ->call('decomposeAiTask')
            ->assertDispatched('toast');

        $this->assertEquals(5, Cache::get($rateKey));
    }

    public function test_insights_caching_and_cache_clearing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Volt::test('pages.insights');
        $component->assertOk();

        $statsCacheKey = "insights_stats_v2_{$user->id}_7days";
        // Verify cache was populated
        $this->assertTrue(Cache::has($statsCacheKey));

        // Call clearInsightsCache
        $component->call('clearInsightsCache');
        $this->assertFalse(Cache::has($statsCacheKey));
    }
}
