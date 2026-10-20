<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class SpotifyService
{
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected string $redirectUri;

    public function __construct()
    {
        $this->clientId = config('services.spotify.client_id') ?? '';
        $this->clientSecret = config('services.spotify.client_secret') ?? '';
        $this->redirectUri = config('services.spotify.redirect') ?? 'https://atoma.test/spotify/callback';
    }

    public function getAuthUrl(): string
    {
        $scopes = [
            'user-read-currently-playing',
            'user-read-playback-state',
            'user-modify-playback-state',
            'streaming',
            'user-read-private',
            'user-read-email'
        ];

        $query = http_build_query([
            'client_id' => $this->clientId ?? '',
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $scopes),
            'show_dialog' => 'true'
        ]);

        return 'https://accounts.spotify.com/authorize?' . $query;
    }

    public function handleCallback(string $code): bool
    {
        try {
            $response = Http::asForm()->withHeaders([
                'Authorization' => 'Basic ' . base64_encode(($this->clientId ?? '') . ':' . ($this->clientSecret ?? '')),
            ])->post('https://accounts.spotify.com/api/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Session::put('spotify_access_token', $data['access_token']);
                if (isset($data['refresh_token'])) {
                    Session::put('spotify_refresh_token', $data['refresh_token']);
                }
                Session::put('spotify_token_expires_at', time() + ($data['expires_in'] ?? 3600));
                return true;
            }
        } catch (\Exception $e) {}

        return false;
    }

    public function refreshAccessToken(): bool
    {
        $refreshToken = Session::get('spotify_refresh_token');
        if (!$refreshToken || empty($this->clientId) || empty($this->clientSecret)) {
            return false;
        }

        try {
            $response = Http::asForm()->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            ])->post('https://accounts.spotify.com/api/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Session::put('spotify_access_token', $data['access_token']);
                if (isset($data['refresh_token'])) {
                    Session::put('spotify_refresh_token', $data['refresh_token']);
                }
                Session::put('spotify_token_expires_at', time() + ($data['expires_in'] ?? 3600));
                return true;
            }
        } catch (\Exception $e) {}

        return false;
    }

    public function getAccessToken(): ?string
    {
        $expiresAt = Session::get('spotify_token_expires_at', 0);
        if (time() >= $expiresAt - 60) {
            $this->refreshAccessToken();
        }
        return Session::get('spotify_access_token');
    }

    public function getClientCredentialsToken(): ?string
    {
        if (Session::has('spotify_client_credentials_token') && Session::get('spotify_client_credentials_expires_at', 0) > time()) {
            return Session::get('spotify_client_credentials_token');
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            return null;
        }

        try {
            $response = Http::asForm()->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            ])->post('https://accounts.spotify.com/api/token', [
                'grant_type' => 'client_credentials'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Session::put('spotify_client_credentials_token', $data['access_token']);
                Session::put('spotify_client_credentials_expires_at', time() + ($data['expires_in'] ?? 3600) - 60);
                return $data['access_token'];
            }
        } catch (\Exception $e) {}

        return null;
    }

    public function getCurrentlyPlaying(): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['is_connected' => false];
        }

        try {
            // 1. Try currently-playing endpoint first
            $response = Http::withToken($token)->get('https://api.spotify.com/v1/me/player/currently-playing');

            if ($response->status() === 401 && $this->refreshAccessToken()) {
                $token = Session::get('spotify_access_token');
                $response = Http::withToken($token)->get('https://api.spotify.com/v1/me/player/currently-playing');
            }

            if ($response->successful() && $response->status() === 200) {
                $data = $response->json();
                if (!empty($data['item'])) {
                    return [
                        'is_connected' => true,
                        'is_playing' => $data['is_playing'] ?? false,
                        'item' => [
                            'title' => $data['item']['name'] ?? 'Unknown Track',
                            'artist' => implode(', ', array_column($data['item']['artists'] ?? [], 'name')),
                            'album_art' => $data['item']['album']['images'][0]['url'] ?? null
                        ]
                    ];
                }
            }

            // 2. Fallback to full player endpoint if 204 or empty item
            $playerResp = Http::withToken($token)->get('https://api.spotify.com/v1/me/player');
            if ($playerResp->successful() && $playerResp->status() === 200) {
                $data = $playerResp->json();
                if (!empty($data['item'])) {
                    return [
                        'is_connected' => true,
                        'is_playing' => $data['is_playing'] ?? false,
                        'item' => [
                            'title' => $data['item']['name'] ?? 'Unknown Track',
                            'artist' => implode(', ', array_column($data['item']['artists'] ?? [], 'name')),
                            'album_art' => $data['item']['album']['images'][0]['url'] ?? null
                        ]
                    ];
                }
            }

        } catch (\Exception $e) {}

        return ['is_connected' => true, 'is_playing' => false, 'item' => null];
    }

    public function controlPlayback(string $action, array $params = []): bool
    {
        $token = $this->getAccessToken();
        if (!$token) return false;

        $endpoint = match ($action) {
            'play' => 'https://api.spotify.com/v1/me/player/play',
            'pause' => 'https://api.spotify.com/v1/me/player/pause',
            'next' => 'https://api.spotify.com/v1/me/player/next',
            'previous' => 'https://api.spotify.com/v1/me/player/previous',
            default => null
        };

        if (!$endpoint) return false;

        try {
            $http = Http::withToken($token);
            $response = match ($action) {
                'play', 'pause' => $http->put($endpoint, $params),
                'next', 'previous' => $http->post($endpoint, $params),
            };

            if ($response->status() === 401 && $this->refreshAccessToken()) {
                $token = Session::get('spotify_access_token');
                $http = Http::withToken($token);
                $response = match ($action) {
                    'play', 'pause' => $http->put($endpoint, $params),
                    'next', 'previous' => $http->post($endpoint, $params),
                };
            }

            return $response->successful() || $response->status() === 204;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function disconnect(): void
    {
        Session::forget(['spotify_access_token', 'spotify_refresh_token', 'spotify_token_expires_at', 'spotify_client_credentials_token', 'spotify_client_credentials_expires_at']);
    }

    public function searchTracks(string $query): array
    {
        $token = $this->getAccessToken() ?? $this->getClientCredentialsToken();

        if ($token) {
            try {
                $response = Http::withToken($token)->get('https://api.spotify.com/v1/search', [
                    'q' => $query,
                    'type' => 'track',
                    'limit' => 5
                ]);

                if ($response->successful()) {
                    $items = $response->json()['tracks']['items'] ?? [];
                    $results = [];
                    foreach ($items as $item) {
                        $results[] = [
                            'id' => $item['id'],
                            'uri' => $item['uri'],
                            'title' => $item['name'] ?? 'Track',
                            'artist' => implode(', ', array_column($item['artists'] ?? [], 'name')),
                            'album_art' => $item['album']['images'][0]['url'] ?? null,
                            'embed_url' => "https://open.spotify.com/embed/track/{$item['id']}?utm_source=generator"
                        ];
                    }
                    if (!empty($results)) {
                        return $results;
                    }
                }
            } catch (\Exception $e) {}
        }

        return [];
    }
}
