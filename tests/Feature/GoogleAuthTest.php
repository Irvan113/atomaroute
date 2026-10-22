<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_google_sign_in_button(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee(route('auth.google'));
        $response->assertSee('Masuk dengan Google');
    }

    public function test_register_page_renders_google_sign_up_button(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee(route('auth.google'));
        $response->assertSee('Daftar dengan Google');
    }

    public function test_redirect_to_google_generates_valid_oauth_url_and_state(): void
    {
        Config::set('services.google.client_id', 'test_client_id.apps.googleusercontent.com');

        $response = $this->get('/auth/google');

        $response->assertStatus(302);
        $targetUrl = $response->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $targetUrl);
        $this->assertStringContainsString('client_id=test_client_id.apps.googleusercontent.com', $targetUrl);
        $this->assertStringContainsString('scope=openid+email+profile', $targetUrl);

        $sessionState = session('oauth_state');
        $this->assertNotEmpty($sessionState);
        $this->assertStringContainsString('state=' . $sessionState, $targetUrl);
    }

    public function test_redirect_to_google_auto_corrects_localhost_redirect_on_live_domain(): void
    {
        Config::set('services.google.client_id', 'test_client_id.apps.googleusercontent.com');
        Config::set('services.google.redirect', 'http://localhost:8000/auth/google/callback');

        // Simulate incoming request to live domain https://atomaroute.com
        $response = $this->withHeaders(['Host' => 'atomaroute.com', 'X-Forwarded-Proto' => 'https'])
            ->get('https://atomaroute.com/auth/google');

        $response->assertStatus(302);
        $targetUrl = $response->headers->get('Location');

        // Must NOT use localhost! Must use live domain!
        $this->assertStringNotContainsString('localhost', $targetUrl);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fatomaroute.com%2Fauth%2Fgoogle%2Fcallback', $targetUrl);
    }

    public function test_callback_fails_when_state_does_not_match_csrf(): void
    {
        $response = $this->withSession(['oauth_state' => 'expected_secure_state_123'])
            ->get('/auth/google/callback?state=wrong_state&code=test_code');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_callback_handles_user_cancellation(): void
    {
        $response = $this->get('/auth/google/callback?error=access_denied');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_callback_registers_new_user_and_logs_in(): void
    {
        Config::set('services.google.client_id', 'test_client_id');
        Config::set('services.google.client_secret', 'test_secret');

        $state = 'valid_test_oauth_state';

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock_access_token_xyz',
                'expires_in' => 3600,
            ], 200),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google_unique_1001',
                'name' => 'Aditiya Google',
                'email' => 'aditiya.google@example.com',
                'picture' => 'https://lh3.googleusercontent.com/a/avatar123.jpg',
                'email_verified' => true,
            ], 200),
        ]);

        $response = $this->withSession(['oauth_state' => $state])
            ->get('/auth/google/callback?state=' . $state . '&code=mock_google_code');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'aditiya.google@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('google_unique_1001', $user->google_id);
        $this->assertEquals('Aditiya Google', $user->name);
        $this->assertEquals('https://lh3.googleusercontent.com/a/avatar123.jpg', $user->avatar_path);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_callback_links_existing_user_with_same_email(): void
    {
        Config::set('services.google.client_id', 'test_client_id');
        Config::set('services.google.client_secret', 'test_secret');

        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Existing Manual User',
            'google_id' => null,
            'avatar_path' => null,
        ]);

        $state = 'valid_test_state_existing';

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock_access_token_existing',
            ], 200),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google_sub_2002',
                'name' => 'Existing Manual User',
                'email' => 'existing@example.com',
                'picture' => 'https://lh3.googleusercontent.com/a/avatar_existing.jpg',
                'email_verified' => true,
            ], 200),
        ]);

        $response = $this->withSession(['oauth_state' => $state])
            ->get('/auth/google/callback?state=' . $state . '&code=mock_code_existing');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($existingUser);

        $existingUser->refresh();
        $this->assertEquals('google_sub_2002', $existingUser->google_id);
        $this->assertEquals('https://lh3.googleusercontent.com/a/avatar_existing.jpg', $existingUser->avatar_path);
    }

    public function test_callback_logs_in_existing_user_with_same_google_id(): void
    {
        Config::set('services.google.client_id', 'test_client_id');
        Config::set('services.google.client_secret', 'test_secret');

        $googleUser = User::factory()->create([
            'email' => 'returning@example.com',
            'name' => 'Returning Google User',
            'google_id' => 'google_sub_already_saved_3003',
        ]);

        $state = 'valid_test_state_returning';

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock_access_token_returning',
            ], 200),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google_sub_already_saved_3003',
                'name' => 'Returning Google User',
                'email' => 'returning@example.com',
                'picture' => 'https://lh3.googleusercontent.com/a/avatar_returning.jpg',
                'email_verified' => true,
            ], 200),
        ]);

        $response = $this->withSession(['oauth_state' => $state])
            ->get('/auth/google/callback?state=' . $state . '&code=mock_code_returning');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($googleUser);
    }

    public function test_mobile_google_auth_returns_handoff_view_and_deep_link(): void
    {
        Config::set('services.google.client_id', 'test_client_id');
        Config::set('services.google.client_secret', 'test_secret');

        $state = 'valid_mobile_oauth_state:mobile_app';

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock_mobile_access_token',
            ], 200),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google_sub_mobile_4004',
                'name' => 'Mobile User',
                'email' => 'mobile.user@example.com',
                'picture' => 'https://lh3.googleusercontent.com/a/avatar_mobile.jpg',
                'email_verified' => true,
            ], 200),
        ]);

        $response = $this->withSession(['oauth_state' => $state, 'is_mobile_app' => true])
            ->get('/auth/google/callback?state=' . urlencode($state) . '&code=mock_code_mobile');

        $response->assertStatus(200);
        $response->assertSee('atomaroute://auth-callback?token=');
        $response->assertSee('Lanjutkan ke Aplikasi ATOMA');
        $this->assertAuthenticated();
    }

    public function test_mobile_auth_token_login_authenticates_user_successfully(): void
    {
        $user = User::factory()->create();
        $token = 'test_valid_mobile_token_12345';
        \Illuminate\Support\Facades\Cache::put("mobile_auth_{$token}", $user->id, now()->addMinutes(5));

        $response = $this->get('/mobile-auth-token-login?token=' . $token);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has("mobile_auth_{$token}"));
    }

    public function test_mobile_auth_token_login_rejects_expired_or_invalid_token(): void
    {
        $response = $this->get('/mobile-auth-token-login?token=invalid_token');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_apk_stateless_google_auth_callback_without_session(): void
    {
        Config::set('services.google.client_id', 'test_client_id');
        Config::set('services.google.client_secret', 'test_secret');

        $state = 'atoma_apk_stateless_token_999';
        \Illuminate\Support\Facades\Cache::put("oauth_apk_state_{$state}", true, now()->addMinutes(15));

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock_mobile_access_token',
            ], 200),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google_sub_mobile_stateless',
                'name' => 'Stateless Mobile User',
                'email' => 'stateless.mobile@example.com',
                'picture' => 'https://lh3.googleusercontent.com/a/avatar_mobile.jpg',
                'email_verified' => true,
            ], 200),
        ]);

        // Request callback with NO session cookies (simulating external Chrome callback)
        $response = $this->get('/auth/google/callback?state=' . urlencode($state) . '&code=mock_code_mobile');

        $response->assertStatus(200);
        $response->assertSee('atomaroute://auth-callback?token=');
        $response->assertSee('intent://auth-callback?token=');
        $response->assertSee('Lanjutkan ke Aplikasi ATOMA');
        $this->assertAuthenticated();
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has("oauth_apk_state_{$state}"));
    }
}
