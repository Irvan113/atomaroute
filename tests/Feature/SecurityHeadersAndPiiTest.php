<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersAndPiiTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present_on_web_response()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_user_email_is_hidden_from_public_profile_serialization()
    {
        $viewer = User::factory()->create();
        $targetUser = User::factory()->create([
            'email' => 'supersecretuser@example.com',
            'name' => 'Secret User',
        ]);

        $response = $this->actingAs($viewer)->get('/u/' . $targetUser->id);

        $response->assertStatus(200);
        $response->assertSee('Secret User');
        $response->assertDontSee('supersecretuser@example.com');
    }

    public function test_spotify_credentials_endpoint_forbids_non_admin()
    {
        $regularUser = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($regularUser)->postJson('/api/spotify/save-credentials', [
            'client_id' => 'abcdef1234567890abcdef12',
            'client_secret' => 'abcdef1234567890abcdef12',
        ]);

        $response->assertStatus(403);
    }

    public function test_spotify_credentials_endpoint_rejects_malformed_input()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->postJson('/api/spotify/save-credentials', [
            'client_id' => "injected\nAPP_KEY=hacked",
            'client_secret' => 'valid_secret_1234567890',
        ]);

        $response->assertStatus(422);
    }
}
