<?php

namespace App\Http\Controllers;

use App\Services\SpotifyService;
use Illuminate\Http\Request;

class SpotifyAuthController extends Controller
{
    protected SpotifyService $spotifyService;

    public function __construct(SpotifyService $spotifyService)
    {
        $this->spotifyService = $spotifyService;
    }

    public function redirect()
    {
        $clientId = config('services.spotify.client_id');
        if (empty($clientId)) {
            return redirect()->back()->with('toast', [
                'message' => 'Masukkan SPOTIFY_CLIENT_ID & SPOTIFY_CLIENT_SECRET pada file .env untuk menghubungkan akun Spotify asli kamu.',
                'type' => 'nudge'
            ]);
        }

        return redirect()->away($this->spotifyService->getAuthUrl());
    }

    public function callback(Request $request)
    {
        $code = $request->input('code');

        if ($code && $this->spotifyService->handleCallback($code)) {
            return redirect()->route('dashboard')->with('toast', ['message' => 'Spotify berhasil terhubung!', 'type' => 'success']);
        }

        return redirect()->route('dashboard')->with('toast', ['message' => 'Gagal menghubungkan akun Spotify.', 'type' => 'error']);
    }

    public function disconnect()
    {
        $this->spotifyService->disconnect();
        return redirect()->back()->with('toast', ['message' => 'Akun Spotify berhasil diputuskan.', 'type' => 'info']);
    }

    public function status()
    {
        return response()->json($this->spotifyService->getCurrentlyPlaying());
    }

    public function control(Request $request)
    {
        $action = $request->input('action');
        $params = $request->input('params', []);

        $success = $this->spotifyService->controlPlayback($action, $params);

        return response()->json(['success' => $success]);
    }

    public function search(Request $request)
    {
        $query = $request->input('q', '');
        if (empty($query)) return response()->json(['results' => []]);

        $results = $this->spotifyService->searchTracks($query);
        return response()->json(['results' => $results]);
    }
}
