<?php

namespace App\Http\Controllers;

use App\Services\YouTubeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoogleAuthController extends Controller
{
    /**
     * Resolve effective Google OAuth redirect URI.
     * Prevents unintended redirection to localhost when deployed on a live domain.
     */
    protected function getEffectiveRedirectUri(Request $request): string
    {
        $configured = config('services.google.redirect');
        $currentHost = $request->getHost();
        $isLocalHost = in_array($currentHost, ['localhost', '127.0.0.1', '::1']);

        // If configured URI contains localhost but request is on a live domain, dynamically use current origin
        if (!empty($configured) && str_contains($configured, 'localhost') && !$isLocalHost) {
            return $request->getSchemeAndHttpHost() . '/auth/google/callback';
        }

        if (!empty($configured)) {
            return $configured;
        }

        return $request->getSchemeAndHttpHost() . '/auth/google/callback';
    }

    /**
     * Redirect guest to Google OAuth consent screen for ATOMA Sign In / Sign Up.
     */
    public function redirectToGoogle(Request $request)
    {
        $clientId = config('services.google.client_id');
        $redirectUri = $this->getEffectiveRedirectUri($request);

        if (empty($clientId)) {
            return redirect()->route('login')->with('toast', [
                'message' => __('Google Client ID belum dikonfigurasi di file .env. Masukkan GOOGLE_CLIENT_ID & GOOGLE_CLIENT_SECRET terlebih dahulu.'),
                'type' => 'error'
            ]);
        }

        $platform = $request->query('platform') ?: $request->query('source');
        $userAgent = $request->header('User-Agent', '');
        $isMobileApp = in_array($platform, ['apk', 'mobile_app', 'app'])
            || $request->header('X-Requested-With') === 'com.atoma.app'
            || str_contains($userAgent, 'Capacitor')
            || (str_contains($userAgent, 'Android') && str_contains($userAgent, 'wv'));

        $state = \Illuminate\Support\Str::random(40);
        if ($isMobileApp) {
            $state = 'atoma_apk_' . $state;
            \Illuminate\Support\Facades\Cache::put("oauth_apk_state_{$state}", true, now()->addMinutes(15));
            session(['is_mobile_app' => true]);
        }
        session(['oauth_state' => $state]);
        \Illuminate\Support\Facades\Cache::put("oauth_state_{$state}", true, now()->addMinutes(15));

        $scopes = [
            'openid',
            'email',
            'profile'
        ];

        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);

        return redirect()->away($authUrl);
    }

    /**
     * Handle Google OAuth callback for ATOMA Sign In / Sign Up.
     */
    public function handleGoogleCallback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('login')->with('toast', [
                'message' => __('Login dengan Google dibatalkan.'),
                'type' => 'nudge'
            ]);
        }

        // Validate state for CSRF protection
        $savedState = session()->pull('oauth_state');
        $receivedState = $request->query('state');
        $isMobileApp = false;

        $isValidState = false;
        if (is_string($receivedState) && !empty($receivedState)) {
            // Check general oauth_state cache (bulletproof against lost session cookies on mobile browsers / cross-site redirects)
            if (\Illuminate\Support\Facades\Cache::has("oauth_state_{$receivedState}")) {
                \Illuminate\Support\Facades\Cache::forget("oauth_state_{$receivedState}");
                $isValidState = true;
            }

            if (str_starts_with($receivedState, 'atoma_apk_')) {
                $cacheKey = "oauth_apk_state_{$receivedState}";
                if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                    \Illuminate\Support\Facades\Cache::forget($cacheKey);
                    $isMobileApp = true;
                    $isValidState = true;
                } elseif (!empty($savedState) && hash_equals($savedState, $receivedState)) {
                    $isMobileApp = true;
                    $isValidState = true;
                }
            } elseif (str_ends_with($receivedState, ':mobile_app')) {
                if (!empty($savedState) && hash_equals($savedState, $receivedState)) {
                    $isMobileApp = true;
                    $isValidState = true;
                }
            } elseif (!empty($savedState) && hash_equals($savedState, $receivedState)) {
                $isMobileApp = session()->pull('is_mobile_app', false);
                $isValidState = true;
            }
        }

        if (!$isValidState) {
            return redirect()->route('login')->with('toast', [
                'message' => __('Sesi otentikasi kedaluwarsa atau tidak valid. Silakan coba lagi.'),
                'type' => 'error'
            ]);
        }

        $code = $request->query('code');
        if (empty($code)) {
            return redirect()->route('login')->with('toast', [
                'message' => __('Kode otentikasi dari Google tidak ditemukan.'),
                'type' => 'error'
            ]);
        }

        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = $this->getEffectiveRedirectUri($request);

        try {
            // 1. Exchange code for access token
            $tokenResponse = \Illuminate\Support\Facades\Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code'
            ]);

            if (!$tokenResponse->successful()) {
                \Illuminate\Support\Facades\Log::error('Google OAuth Token Exchange Failed', [
                    'body' => $tokenResponse->body()
                ]);
                return redirect()->route('login')->with('toast', [
                    'message' => __('Gagal menukarkan token dengan Google.'),
                    'type' => 'error'
                ]);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;

            if (!$accessToken) {
                return redirect()->route('login')->with('toast', [
                    'message' => __('Token akses Google tidak valid.'),
                    'type' => 'error'
                ]);
            }

            // 2. Fetch User Profile from Google UserInfo
            $userInfoResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v3/userinfo');

            if (!$userInfoResponse->successful()) {
                return redirect()->route('login')->with('toast', [
                    'message' => __('Gagal mengambil data profil dari Google.'),
                    'type' => 'error'
                ]);
            }

            $googleUser = $userInfoResponse->json();
            $googleId = $googleUser['sub'] ?? null;
            $email = $googleUser['email'] ?? null;
            $name = $googleUser['name'] ?? null;
            $picture = $googleUser['picture'] ?? null;

            if (empty($googleId) || empty($email)) {
                return redirect()->route('login')->with('toast', [
                    'message' => __('Data akun Google tidak lengkap (email tidak tersedia).'),
                    'type' => 'error'
                ]);
            }

            // 3. Find or Create User
            // Case A: User already linked by google_id
            $user = \App\Models\User::where('google_id', $googleId)->first();

            if (!$user) {
                // Case B: User exists with the same email
                $user = \App\Models\User::where('email', $email)->first();

                if ($user) {
                    $user->google_id = $googleId;
                    if (!$user->avatar_path && $picture) {
                        $user->avatar_path = $picture;
                    }
                    if (!$user->email_verified_at) {
                        $user->email_verified_at = now();
                    }
                    $user->save();
                } else {
                    // Case C: Register new user
                    $user = \App\Models\User::create([
                        'name' => $name ?: explode('@', $email)[0],
                        'email' => $email,
                        'google_id' => $googleId,
                        'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                        'email_verified_at' => now(),
                        'avatar_path' => $picture,
                        'prestige_tier' => 1,
                        'prestige_level' => 1,
                        'league' => 'bronze_3',
                    ]);

                    event(new \Illuminate\Auth\Events\Registered($user));
                }
            } else {
                // Update avatar if not yet set
                if (!$user->avatar_path && $picture) {
                    $user->avatar_path = $picture;
                    $user->save();
                }
            }

            // 4. Log in the user
            Auth::login($user, remember: true);
            $request->session()->regenerate();

            if ($isMobileApp) {
                $token = \Illuminate\Support\Str::random(64);
                \Illuminate\Support\Facades\Cache::put("auth_exchange_{$token}", $user->id, now()->addMinutes(10));
                \Illuminate\Support\Facades\Cache::put("mobile_auth_{$token}", $user->id, now()->addMinutes(10));
                
                $intentUri = "intent://auth-callback?token={$token}#Intent;scheme=atomaroute;package=com.atoma.app;end";
                $customSchemeUri = "atomaroute://auth-callback?token={$token}";

                return response()->view('auth.mobile-handoff', [
                    'token' => $token,
                    'user' => $user,
                    'appDeepLink' => $customSchemeUri,
                    'intentUri' => $intentUri,
                ]);
            }

            $targetUrl = route('dashboard');
            if (str_contains($targetUrl, 'localhost') && !in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'])) {
                $targetUrl = $request->getSchemeAndHttpHost() . '/dashboard';
            }

            return redirect()->intended($targetUrl)->with('toast', [
                'message' => __('Berhasil masuk dengan Google!'),
                'type' => 'success'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Google OAuth Callback Exception: ' . $e->getMessage());
            return redirect()->route('login')->with('toast', [
                'message' => __('Terjadi kesalahan saat masuk dengan Google. Silakan coba lagi.'),
                'type' => 'error'
            ]);
        }
    }

    /**
     * Redirect user to Google OAuth consent screen or connect in demo mode (for YouTube Music).
     */
    public function redirect()
    {
        $clientId = config('services.google.client_id');
        if (empty($clientId)) {
            session([
                'youtube_access_token' => 'demo_google_access_token',
                'youtube_is_connected' => true
            ]);
            return redirect()->back()->with('toast', ['message' => 'Berhasil terhubung ke YouTube Music (Mode Dev/Demo)!', 'type' => 'success']);
        }

        return redirect()->away(YouTubeService::getAuthUrl());
    }

    /**
     * Handle Google OAuth callback.
     */
    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('dashboard')->with('toast', ['message' => 'Koneksi Google YouTube dibatalkan.', 'type' => 'nudge']);
        }

        $code = $request->query('code');
        if ($code) {
            $tokenData = YouTubeService::exchangeCode($code);
            if ($tokenData && isset($tokenData['access_token'])) {
                session([
                    'youtube_access_token' => $tokenData['access_token'],
                    'youtube_refresh_token' => $tokenData['refresh_token'] ?? null,
                    'youtube_is_connected' => true
                ]);

                // Store in user record if logged in
                if (Auth::check()) {
                    $user = Auth::user();
                    $user->google_id = 'google_' . rand(1000, 9999);
                    $user->save();
                }

                return redirect()->route('dashboard')->with('toast', ['message' => 'Berhasil menghubungkan akun YouTube Music!', 'type' => 'success']);
            }
        }

        return redirect()->route('dashboard')->with('toast', ['message' => 'Gagal menghubungkan akun Google YouTube.', 'type' => 'error']);
    }

    /**
     * Disconnect Google YouTube account.
     */
    public function disconnect(Request $request)
    {
        session()->forget(['youtube_access_token', 'youtube_refresh_token', 'youtube_is_connected']);
        return back()->with('toast', ['message' => 'Akun YouTube berhasil diputuskan.', 'type' => 'info']);
    }

    /**
     * Get user playlists and YT recommendations for Pomodoro widget.
     */
    public function playlists(Request $request)
    {
        $isConnected = session('youtube_is_connected', false) || session()->has('youtube_access_token');
        $token = session('youtube_access_token');

        $userPlaylists = YouTubeService::getUserPlaylists($token);
        $recommendedMixes = YouTubeService::getRecommendedMixes();

        return response()->json([
            'is_connected' => $isConnected,
            'user_playlists' => $userPlaylists,
            'recommended_mixes' => $recommendedMixes
        ]);
    }
}
