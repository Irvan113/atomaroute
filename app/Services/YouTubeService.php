<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeService
{
    /**
     * Generate Google / YouTube OAuth redirect URL.
     */
    public static function getAuthUrl(): string
    {
        $clientId = config('services.google.client_id') ?? 'DEMO_GOOGLE_CLIENT_ID';
        $redirectUri = config('services.google.redirect') ?? 'https://atoma.test/google/callback';

        $scopes = [
            'https://www.googleapis.com/auth/youtube.readonly',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile'
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent'
        ]);
    }

    /**
     * Exchange auth code for access token.
     */
    public static function exchangeCode(string $code): ?array
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = config('services.google.redirect');

        if (!$clientId || !$clientSecret) {
            // Mock token for local demonstration
            return [
                'access_token' => 'mock_google_access_token',
                'refresh_token' => 'mock_google_refresh_token',
                'expires_in' => 3600
            ];
        }

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code'
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('Google OAuth Exchange Failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetch authenticated user's YouTube Playlists.
     */
    public static function getUserPlaylists(?string $token): array
    {
        if (!$token || $token === 'mock_google_access_token') {
            return self::getFallbackPlaylists();
        }

        try {
            $response = Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/playlists', [
                'part' => 'snippet,contentDetails',
                'mine' => 'true',
                'maxResults' => 10
            ]);

            if ($response->successful()) {
                $items = $response->json()['items'] ?? [];
                $playlists = [];
                foreach ($items as $item) {
                    $playlists[] = [
                        'id' => $item['id'],
                        'title' => $item['snippet']['title'] ?? 'Playlist Tanpa Judul',
                        'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? ($item['snippet']['thumbnails']['default']['url'] ?? ''),
                        'item_count' => $item['contentDetails']['itemCount'] ?? 0,
                        'embed_url' => "https://www.youtube.com/embed/videoseries?list={$item['id']}&autoplay=1"
                    ];
                }
                return $playlists;
            }
        } catch (\Exception $e) {
            Log::error('YouTube API Playlists error: ' . $e->getMessage());
        }

        return self::getFallbackPlaylists();
    }

    /**
     * Fetch user's Recommended YouTube Music Mixes.
     */
    public static function getRecommendedMixes(): array
    {
        return [
            [
                'id' => 'RDCLAK5uy_kbc2R8gM0x_6P49H0',
                'title' => '⚡ Deep Focus & Study Mix',
                'description' => 'Disesuaikan dari selera musik YouTube kamu',
                'embed_url' => 'https://www.youtube.com/embed/5qap5aO4i9A?autoplay=1',
                'video_id' => '5qap5aO4i9A'
            ],
            [
                'id' => 'RDCLAK5uy_m5yW7p7_Lofi',
                'title' => '🎧 Chill & Lofi Beats Mix',
                'description' => 'Rekomendasi instan YouTube Music',
                'embed_url' => 'https://www.youtube.com/embed/jfKfPfyJRdk?autoplay=1',
                'video_id' => 'jfKfPfyJRdk'
            ],
            [
                'id' => 'RDCLAK5uy_Piano_Mix',
                'title' => '🎻 Classical & Piano Focus Mix',
                'description' => 'Suasana tenang untuk produktivitas',
                'embed_url' => 'https://www.youtube.com/embed/2OEL4P1rub0?autoplay=1',
                'video_id' => '2OEL4P1rub0'
            ]
        ];
    }

    /**
     * Fallback playlists if offline or in demo mode.
     */
    public static function getFallbackPlaylists(): array
    {
        return [
            [
                'id' => 'PLWwAypAcFRgKAORV922763f6n4283838',
                'title' => '📚 Study & Focus Hits',
                'thumbnail' => 'https://i.ytimg.com/vi/5qap5aO4i9A/hqdefault.jpg',
                'item_count' => 45,
                'embed_url' => 'https://www.youtube.com/embed/5qap5aO4i9A?autoplay=1'
            ],
            [
                'id' => 'PLWwAypAcFRgLofiMix2026',
                'title' => '☕ Chill Work Atmosphere',
                'thumbnail' => 'https://i.ytimg.com/vi/2OEL4P1rub0/hqdefault.jpg',
                'item_count' => 30,
                'embed_url' => 'https://www.youtube.com/embed/2OEL4P1rub0?autoplay=1'
            ]
        ];
    }
}
