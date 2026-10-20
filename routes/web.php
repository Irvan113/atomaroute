<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');
Route::view('guide', 'guide')->name('guide');
Route::view('terms', 'terms')->name('terms');

Route::get('lang/{locale}', function ($locale) {
    if (in_array($locale, ['id', 'en'])) {
        session(['locale' => $locale]);
        if (auth()->check()) {
            auth()->user()->update(['locale' => $locale]);
        }
    }
    return redirect()->back();
})->name('lang.switch');

// Google Authentication (SSO Sign In / Sign Up)
Route::middleware(['guest'])->group(function () {
    Route::get('/auth/google', [\App\Http\Controllers\GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [\App\Http\Controllers\GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

Route::view('dashboard', 'dashboard')->middleware(['auth', 'verified'])->name('dashboard');
Route::view('social', 'social')->middleware(['auth', 'verified'])->name('social');
Route::view('leaderboard', 'leaderboard')->middleware(['auth', 'verified'])->name('leaderboard');
Volt::route('social/chat/{friend_id}', 'pages.chat')->middleware(['auth', 'verified'])->name('chat');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('/tasks', 'tasks')->name('tasks');
    Volt::route('/journal', 'journal.index')->name('journal');
    Volt::route('/flashcards', 'flashcard.index')->name('flashcards');
    Volt::route('/insights', 'pages.insights')->name('insights');
    Volt::route('/skills', 'skills.index')->name('skills.index');
    Volt::route('/skills/{skill}', 'skills.show')->name('skills.show');
});

Volt::route('store', 'pages.store')->middleware(['auth', 'verified'])->name('store');
Volt::route('prestige', 'pages.prestige')->middleware(['auth', 'verified'])->name('prestige');
Volt::route('admin', 'pages.admin-dashboard')->middleware(['auth', 'verified'])->name('admin.dashboard');
Volt::route('admin/dashboard', 'pages.admin-dashboard')->middleware(['auth', 'verified']);
Volt::route('admin/ai-usage', 'pages.admin-ai-usage')->middleware(['auth', 'verified'])->name('admin.ai-usage');
Volt::route('admin/feedbacks', 'pages.admin-feedbacks')->middleware(['auth', 'verified'])->name('admin.feedbacks');
Volt::route('admin/system-logs', 'pages.admin-system-logs')->middleware(['auth', 'verified'])->name('admin.system-logs');
Volt::route('admin/subscriptions', 'pages.admin-subscriptions')->middleware(['auth', 'verified'])->name('admin.subscriptions');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Volt::route('u/{user}', 'pages.public-profile')
    ->middleware(['auth'])
    ->name('user.profile');

Route::middleware(['auth'])->get('/api/journal/preview/{id}', function ($id) {
    $journal = auth()->user()->journals()
        ->select('id', 'title', 'content', 'tags', 'created_at', 'updated_at')
        ->find($id);

    if (!$journal) {
        return response()->json([
            'id' => $id,
            'title' => 'Catatan Tidak Ditemukan',
            'snippet' => 'Catatan ini tidak ditemukan atau telah dihapus.',
            'content_html' => '<p class="text-gray-400 italic">Catatan ini tidak ditemukan atau telah dihapus.</p>',
            'tags' => [],
            'word_count' => 0,
            'date' => '',
        ], 404);
    }

    $rawTags = $journal->tags;
    if (is_string($rawTags)) $rawTags = json_decode($rawTags, true) ?? [];
    if (!is_array($rawTags)) $rawTags = [];
    $rawContent = $journal->content ?: '';
    $cleanText = trim(strip_tags($rawContent));
    $wordCount = $cleanText ? count(preg_split('/\s+/', $cleanText)) : 0;

    return response()->json([
        'id' => $journal->id,
        'title' => $journal->title ?: 'Catatan Tanpa Judul',
        'snippet' => \Illuminate\Support\Str::limit($cleanText, 120),
        'content_html' => $rawContent ?: '<p class="text-gray-400 italic">Catatan ini belum memiliki isi konten.</p>',
        'tags' => array_values(array_filter($rawTags, fn($t) => is_string($t))),
        'word_count' => $wordCount,
        'date' => $journal->updated_at ? $journal->updated_at->format('d M Y, H:i') : $journal->created_at->format('d M Y, H:i'),
    ]);
})->name('api.journal.preview');

Route::middleware(['auth', 'throttle:60,1'])->get('/api/user-achievements', function () {
    $user = auth()->user();
    $service = new \App\Services\AchievementService();
    $service->checkAndUnlock($user);

    $achievements = $service->getProgress($user);
    $totalCount = count($achievements);
    $unlockedCount = collect($achievements)->where('unlocked', true)->count();
    $totalXp = collect($achievements)->where('unlocked', true)->sum('bonus_xp');

    $equippedBadges = $user->equipped_badges;
    if (!is_array($equippedBadges)) {
        $equippedBadges = !empty($user->equipped_badge) ? [$user->equipped_badge] : [];
    }

    return response()->json([
        'achievements' => $achievements,
        'stats' => [
            'total' => $totalCount,
            'unlocked' => $unlockedCount,
            'percentage' => $totalCount > 0 ? (int) round(($unlockedCount / $totalCount) * 100) : 0,
            'total_xp' => $totalXp,
            'equipped_badge' => $user->equipped_badge,
            'equipped_badges' => $equippedBadges,
            'equipped_details' => $user->equipped_badge_details,
            'equipped_badges_details' => $user->equipped_badges_details,
            'max_slots' => 3,
        ],
    ]);
});

Route::middleware(['auth', 'throttle:30,1'])->post('/api/user-achievements/equip', function (\Illuminate\Http\Request $request) {
    $user = auth()->user();
    $code = $request->input('code');
    $action = $request->input('action'); // 'equip', 'unequip', or 'toggle'

    $equippedBadges = $user->equipped_badges;
    if (!is_array($equippedBadges)) {
        $equippedBadges = !empty($user->equipped_badge) ? [$user->equipped_badge] : [];
    }

    // Clear all if empty code provided
    if (empty($code)) {
        $user->equipped_badge = null;
        $user->equipped_badges = [];
        $user->save();
        return response()->json([
            'success' => true,
            'equipped_badge' => null,
            'equipped_badges' => [],
            'details' => null,
            'equipped_badges_details' => [],
            'message' => __('Semua lencana berhasil dilepas.'),
        ]);
    }

    $isCurrentlyEquipped = in_array($code, $equippedBadges);

    if ($action === 'unequip' || ($isCurrentlyEquipped && $action !== 'equip')) {
        // Unequip
        $equippedBadges = array_values(array_filter($equippedBadges, fn($c) => $c !== $code));
        $msg = __('Lencana berhasil dilepas dari profil.');
    } else {
        // Verify user has unlocked this badge (or it's league / prestige badge)
        $hasBadge = \Illuminate\Support\Facades\DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->where('achievement_code', $code)
            ->exists();

        if (!$hasBadge && !str_starts_with($code, 'league_') && !str_starts_with($code, 'prestige_')) {
            return response()->json(['success' => false, 'message' => __('Kamu belum membuka lencana ini.')], 403);
        }

        // Equip
        if (!$isCurrentlyEquipped) {
            if (count($equippedBadges) >= 3) {
                // Auto-replace the oldest/first badge slot so user is never blocked
                array_shift($equippedBadges);
                $equippedBadges[] = $code;
                $msg = __('Lencana berhasil dipasang ke rak pamer (menggantikan slot pertama)!');
            } else {
                $equippedBadges[] = $code;
                $msg = __('Lencana berhasil dipasang di rak pamer profil!');
            }
        }
    }

    $user->equipped_badges = $equippedBadges;
    $user->equipped_badge = !empty($equippedBadges) ? $equippedBadges[0] : null;
    $user->save();

    return response()->json([
        'success' => true,
        'equipped_badge' => $user->equipped_badge,
        'equipped_badges' => $user->equipped_badges,
        'details' => $user->equipped_badge_details,
        'equipped_badges_details' => $user->equipped_badges_details,
        'message' => $msg,
    ]);
});


Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::get('/spotify/login', [\App\Http\Controllers\SpotifyAuthController::class, 'redirect'])->name('spotify.login');
    Route::get('/spotify/callback', [\App\Http\Controllers\SpotifyAuthController::class, 'callback'])->name('spotify.callback');
    Route::post('/spotify/disconnect', [\App\Http\Controllers\SpotifyAuthController::class, 'disconnect'])->name('spotify.disconnect');
    Route::get('/api/spotify/status', [\App\Http\Controllers\SpotifyAuthController::class, 'status'])->name('spotify.status');
    Route::post('/api/spotify/control', [\App\Http\Controllers\SpotifyAuthController::class, 'control'])->name('spotify.control');
    Route::get('/api/spotify/search', [\App\Http\Controllers\SpotifyAuthController::class, 'search'])->name('spotify.search');

    // Mobile Widget Productivity Hub Endpoints
    Route::post('/api/widget/quick-task', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);
        $user = auth()->user();
        $userTz = $user->timezone ?? config('app.timezone', 'Asia/Jakarta');
        
        $task = $user->tasks()->create([
            'title' => trim($request->title),
            'date' => \Carbon\Carbon::today($userTz)->toDateString(),
            'mode' => 'zen',
            'is_completed' => false,
            'priority' => 'medium',
        ]);

        return response()->json([
            'success' => true,
            'task' => [
                'id' => (string)$task->id,
                'title' => $task->title,
                'is_completed' => false,
                'estimated_minutes' => 25,
                'completed_sessions' => 0,
            ],
            'message' => __('Tugas berhasil ditambahkan ke Hari Ini!'),
        ]);
    });

    Route::post('/api/widget/toggle-task', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'task_id' => 'required',
        ]);
        $task = auth()->user()->tasks()->with('subTasks')->findOrFail($request->task_id);
        $task->is_completed = !$task->is_completed;
        $task->save();

        $evaluation = null;
        if ($task->is_completed) {
            $evaluation = \App\Services\TaskXpService::evaluateTaskCompletion($task, auth()->user());
        }

        return response()->json([
            'success' => true,
            'is_completed' => (bool)$task->is_completed,
            'evaluation' => $evaluation,
            'message' => $task->is_completed ? __('Tugas selesai!') : __('Tugas dibuka kembali.'),
        ]);
    });

    Route::post('/api/widget/quick-note', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'content' => 'required|string',
            'title' => 'nullable|string|max:255',
        ]);
        $user = auth()->user();
        $userTz = $user->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $title = $request->title ?: 'Catatan Cepat ' . \Carbon\Carbon::now($userTz)->format('d M H:i');

        $journal = $user->journals()->create([
            'title' => $title,
            'content' => nl2br(e($request->content)),
            'date' => \Carbon\Carbon::today($userTz)->toDateString(),
            'mood' => 'neutral',
        ]);

        return response()->json([
            'success' => true,
            'journal_id' => $journal->id,
            'message' => __('Catatan cepat berhasil disimpan ke Jurnal!'),
        ]);
    });

    Route::post('/api/focus/heartbeat', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        $mode = $request->input('mode', 'work');
        $taskTitle = $request->input('task_title') ?: 'Sesi Fokus';
        $minutesRemaining = (int) $request->input('minutes_remaining', 25);

        $focusData = [
            'user_id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar_path ? asset('storage/' . $user->avatar_path) : null,
            'mode' => $mode,
            'task_title' => $taskTitle,
            'minutes_remaining' => $minutesRemaining,
            'updated_at' => now()->toISOString(),
        ];

        \Illuminate\Support\Facades\Cache::put("user_focus_{$user->id}", $focusData, now()->addSeconds(90));

        return response()->json(['success' => true, 'focus' => $focusData]);
    });

    Route::post('/api/focus/stop', function () {
        $user = auth()->user();
        \Illuminate\Support\Facades\Cache::forget("user_focus_{$user->id}");
        return response()->json(['success' => true]);
    });

    Route::post('/api/community-quest/claim', function () {
        $result = \App\Services\CommunityQuestService::claimReward(auth()->user());
        return response()->json($result, $result['success'] ? 200 : 422);
    });

    Route::get('/api/command-palette/search', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        $q = trim($request->input('q', ''));

        $tasks = $user->tasks()
            ->when($q, fn($query) => $query->where('title', 'like', "%{$q}%"))
            ->orderBy('is_completed')
            ->orderBy('date', 'desc')
            ->limit(8)
            ->get(['id', 'title', 'is_completed', 'date', 'priority', 'category'])
            ->map(fn($t) => [
                'type' => 'task',
                'id' => $t->id,
                'title' => $t->title,
                'subtitle' => ($t->is_completed ? '✓ Selesai' : 'Pending') . ($t->date ? ' · ' . \Carbon\Carbon::parse($t->date)->format('d M') : ''),
                'badge' => $t->category ?: $t->priority,
                'is_completed' => (bool)$t->is_completed,
                'action' => 'task',
            ]);

        $flashcards = $user->flashcards()
            ->when($q, fn($query) => $query->where('front', 'like', "%{$q}%")->orWhere('deck', 'like', "%{$q}%"))
            ->limit(5)
            ->get(['id', 'front', 'deck'])
            ->map(fn($f) => [
                'type' => 'flashcard',
                'id' => $f->id,
                'title' => $f->front,
                'subtitle' => 'Deck: ' . $f->deck,
                'badge' => 'SRS Flashcard',
                'url' => '/flashcards',
                'action' => 'navigate',
            ]);

        $journals = $user->journals()
            ->when($q, fn($query) => $query->where('title', 'like', "%{$q}%"))
            ->limit(5)
            ->get(['id', 'title', 'date'])
            ->map(fn($j) => [
                'type' => 'journal',
                'id' => $j->id,
                'title' => $j->title,
                'subtitle' => 'Jurnal · ' . \Carbon\Carbon::parse($j->date)->format('d M Y'),
                'badge' => 'Catatan',
                'url' => '/journal',
                'action' => 'navigate',
            ]);

        return response()->json([
            'tasks' => $tasks,
            'flashcards' => $flashcards,
            'journals' => $journals,
        ]);
    });
    
    Route::post('/api/spotify/save-credentials', function (\Illuminate\Http\Request $request) {
        if (!auth()->user()->is_admin) {
            return response()->json(['success' => false, 'message' => 'Hanya Administrator yang dapat mengubah kredensial Spotify.'], 403);
        }

        $clientId = trim($request->input('client_id', ''));
        $clientSecret = trim($request->input('client_secret', ''));

        if (empty($clientId) || empty($clientSecret)) {
            return response()->json(['success' => false, 'message' => 'Client ID & Secret tidak boleh kosong.'], 422);
        }

        // Strict alphanumeric validation to prevent .env injection
        if (!preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $clientId) || !preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $clientSecret)) {
            return response()->json(['success' => false, 'message' => 'Format Client ID atau Secret tidak valid.'], 422);
        }

        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            
            if (str_contains($envContent, 'SPOTIFY_CLIENT_ID=')) {
                $envContent = preg_replace('/SPOTIFY_CLIENT_ID=.*/', 'SPOTIFY_CLIENT_ID=' . $clientId, $envContent);
            } else {
                $envContent .= "\nSPOTIFY_CLIENT_ID=" . $clientId;
            }

            if (str_contains($envContent, 'SPOTIFY_CLIENT_SECRET=')) {
                $envContent = preg_replace('/SPOTIFY_CLIENT_SECRET=.*/', 'SPOTIFY_CLIENT_SECRET=' . $clientSecret, $envContent);
            } else {
                $envContent .= "\nSPOTIFY_CLIENT_SECRET=" . $clientSecret;
            }

            file_put_contents($envPath, $envContent);
        }

        config(['services.spotify.client_id' => $clientId]);
        config(['services.spotify.client_secret' => $clientSecret]);

        return response()->json(['success' => true, 'redirect_url' => route('spotify.login')]);
    });

    Route::get('/google/login', [\App\Http\Controllers\GoogleAuthController::class, 'redirect'])->name('google.login');
    Route::get('/google/callback', [\App\Http\Controllers\GoogleAuthController::class, 'callback'])->name('google.callback');
    Route::post('/google/disconnect', [\App\Http\Controllers\GoogleAuthController::class, 'disconnect'])->name('google.disconnect');
    Route::get('/api/youtube/playlists', [\App\Http\Controllers\GoogleAuthController::class, 'playlists'])->name('youtube.playlists');
    
    Route::get('/api/youtube/search', function (\Illuminate\Http\Request $request) {
        $query = trim($request->input('q', ''));
        if (empty($query)) {
            return response()->json([
                'video_id' => '5qap5aO4i9A',
                'title' => 'Lofi Beats - Deep Focus',
                'author' => 'Lofi Music',
                'thumbnail' => 'https://i.ytimg.com/vi/5qap5aO4i9A/hqdefault.jpg',
                'results' => []
            ]);
        }

        // Direct YouTube / YT Music URL or 11-char ID parsing
        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/', $query, $matches);
        if (!empty($matches[1])) {
            $vid = $matches[1];
            return response()->json([
                'video_id' => $vid,
                'title' => 'YouTube Track',
                'author' => 'YouTube Channel',
                'thumbnail' => "https://i.ytimg.com/vi/{$vid}/hqdefault.jpg",
                'results' => []
            ]);
        }

        if (strlen($query) === 11 && !str_contains($query, ' ')) {
            return response()->json([
                'video_id' => $query,
                'title' => 'YouTube Track',
                'author' => 'YouTube Channel',
                'thumbnail' => "https://i.ytimg.com/vi/{$query}/hqdefault.jpg",
                'results' => []
            ]);
        }

        $cacheKey = 'yt_search_' . md5(strtolower($query));
        $cachedData = \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($query) {
            // Direct Scraping of YouTube Search Results Page for 100% Real Video IDs
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(5)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept-Language' => 'en-US,en;q=0.9',
                    ])
                    ->get("https://www.youtube.com/results?search_query=" . urlencode($query));

                if ($response->successful()) {
                    $html = $response->body();
                    preg_match_all('/"videoId":"([^"]{11})"/', $html, $videoMatches);
                    preg_match_all('/\/watch\?v=([a-zA-Z0-9_-]{11})/', $html, $watchMatches);

                    $allMatches = array_merge($videoMatches[1] ?? [], $watchMatches[1] ?? []);

                    if (!empty($allMatches)) {
                        $uniqueIds = array_values(array_unique($allMatches));
                        
                        // Extract titles if possible
                        preg_match_all('/"title":\{"runs":\[\{"text":"([^"]+)"\}/', $html, $titleMatches);
                        preg_match_all('/"ownerText":\{"runs":\[\{"text":"([^"]+)"\}/', $html, $ownerMatches);

                        $results = [];
                        for ($i = 0; $i < min(6, count($uniqueIds)); $i++) {
                            $vId = $uniqueIds[$i];
                            $t = $titleMatches[1][$i] ?? ($query . ' (Track ' . ($i + 1) . ')');
                            $a = $ownerMatches[1][$i] ?? 'YouTube Audio';
                            $results[] = [
                                'video_id' => $vId,
                                'title' => html_entity_decode($t, ENT_QUOTES, 'UTF-8'),
                                'author' => html_entity_decode($a, ENT_QUOTES, 'UTF-8'),
                                'thumbnail' => "https://i.ytimg.com/vi/{$vId}/hqdefault.jpg"
                            ];
                        }

                        if (!empty($results)) {
                            return [
                                'video_id' => $results[0]['video_id'],
                                'title' => $results[0]['title'],
                                'author' => $results[0]['author'],
                                'thumbnail' => $results[0]['thumbnail'],
                                'results' => $results
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {}

            // Fallback popular real working YouTube focus videos
            $fallbackTracks = [
                ['video_id' => '5qap5aO4i9A', 'title' => 'Lofi Beats - Deep Focus', 'author' => 'Lofi Girl'],
                ['video_id' => '2OEL4P1rub0', 'title' => 'Peaceful Piano Focus', 'author' => 'Relaxation Music'],
                ['video_id' => 'jfKfPfyJRdk', 'title' => 'Lofi Hip Hop Radio - Beats to Relax/Study to', 'author' => 'Lofi Girl'],
                ['video_id' => '4xDzrJKXOOY', 'title' => 'Rain & Ambient Beats', 'author' => 'Ambient Sound'],
            ];

            return [
                'video_id' => $fallbackTracks[0]['video_id'],
                'title' => $query,
                'author' => 'YouTube Audio',
                'thumbnail' => "https://i.ytimg.com/vi/{$fallbackTracks[0]['video_id']}/hqdefault.jpg",
                'results' => $fallbackTracks
            ];
        });

        return response()->json($cachedData);
    });

    // Pomodoro Playlist Sync (Cross-Server, Zero Server Bandwidth)
    Route::get('/api/pomodoro/playlists', function () {
        $user = auth()->user();
        $playlist = \App\Models\PomodoroPlaylist::firstOrCreate(
            ['user_id' => $user->id],
            ['folders' => null]
        );
        return response()->json([
            'folders' => $playlist->folders ?: []
        ]);
    });

    Route::post('/api/pomodoro/playlists', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        $folders = $request->input('folders', []);

        if ($user->isFree() && is_array($folders) && count($folders) > $user->maxPomodoroFolders()) {
            $folders = array_slice($folders, 0, $user->maxPomodoroFolders());
        }
        
        $playlist = \App\Models\PomodoroPlaylist::updateOrCreate(
            ['user_id' => $user->id],
            ['folders' => $folders]
        );

        return response()->json([
            'success' => true,
            'is_pro' => $user->isPro(),
            'max_folders' => $user->maxPomodoroFolders(),
            'folders' => $playlist->folders
        ]);
    });

    // Pomodoro Today's Sessions Log
    Route::get('/api/pomodoro/today-sessions', function () {
        $user = auth()->user();
        $userTz = $user->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $today = \Carbon\Carbon::today($userTz);

        $sessions = \App\Models\FocusSession::with('task')
            ->where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($s) use ($userTz) {
                return [
                    'id' => $s->id,
                    'time' => $s->created_at->timezone($userTz)->format('H:i'),
                    'minutes' => $s->minutes,
                    'mode' => $s->mode,
                    'ambient_sound' => $s->ambient_sound,
                    'xp_earned' => (int) $s->xp_earned,
                    'coins_earned' => (int) $s->coins_earned,
                    'task_title' => $s->task ? $s->task->title : null,
                ];
            });

        $totalMinutes = $sessions->sum('minutes');
        $totalXp = $sessions->sum('xp_earned');
        $totalCoins = $sessions->sum('coins_earned');

        return response()->json([
            'sessions' => $sessions,
            'summary' => [
                'total_sessions' => $sessions->count(),
                'total_minutes' => $totalMinutes,
                'total_xp' => $totalXp,
                'total_coins' => $totalCoins,
            ]
        ]);
    });

    // Subscription Demo / Activation Route
    Route::post('/api/subscription/toggle-demo', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        $action = $request->input('action', 'upgrade');

        if ($action === 'upgrade') {
            $user->grantPro('yearly', now()->addYear(), 'Simulasi Demo Aktivasi PRO', null, 199000, 'promo');
            return back();
        } else {
            $user->revokePro('Simulasi Demo Downgrade');
            return back();
        }
    });
});

require __DIR__.'/auth.php';

// Temporary Database Cleaner Route (No cPanel Terminal Required)
Route::match(['get', 'post'], '/admin/db-maintenance', function (\Illuminate\Http\Request $request) {
    $validSecret = 'atoma2026';
    $secret = $request->query('secret') ?? $request->input('secret');

    if ($secret !== $validSecret && !(auth()->check() && auth()->user()->is_admin)) {
        return response('<h3 style="font-family: sans-serif; color: #dc2626; text-align: center; margin-top: 50px;">Akses Ditolak: Token Rahasia Salah atau Anda Belum Login sebagai Admin.</h3><p style="text-align: center; font-family: sans-serif; color: #666;">Akses halaman ini dengan menambahkan <code>?secret=atoma2026</code> di akhir URL Anda.</p>', 403);
    }

    $message = null;
    $messageType = 'success';

    if ($request->isMethod('post')) {
        $action = $request->input('action');

        if ($action === 'fix_storage') {
            try {
                $publicStorage = public_path('storage');
                $appStorage = storage_path('app/public');
                
                if (file_exists($publicStorage) || is_link($publicStorage)) {
                    if (is_link($publicStorage)) {
                        @unlink($publicStorage);
                    } else {
                        @rename($publicStorage, public_path('storage_backup_' . time()));
                    }
                }

                if (@symlink($appStorage, $publicStorage)) {
                    $message = '✅ SUKSES! Shortcut Storage Link berhasil dihubungkan. Foto profil dan file upload sekarang dapat diakses secara publik!';
                    $messageType = 'success';
                } else {
                    \Illuminate\Support\Facades\Artisan::call('storage:link');
                    $message = '✅ Artisan storage:link dijalankan: ' . trim(\Illuminate\Support\Facades\Artisan::output());
                    $messageType = 'success';
                }
            } catch (\Throwable $e) {
                $message = 'Gagal membuat storage link: ' . $e->getMessage();
                $messageType = 'error';
            }
        } elseif ($action === 'clear_cache') {
            try {
                \Illuminate\Support\Facades\Artisan::call('optimize:clear');
                $message = '✅ Cache aplikasi, konfigurasi, route, dan view berhasil dibersihkan total!';
                $messageType = 'success';
            } catch (\Throwable $e) {
                $message = 'Gagal membersihkan cache: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $keepIds = array_filter(array_map('intval', (array) $request->input('keep_ids', [])));

            if (empty($keepIds)) {
                $message = 'Gagal: Anda harus memilih setidaknya 1 user untuk dipertahankan!';
                $messageType = 'error';
            } else {
            // 1. Backup SQLite
            $dbPath = config('database.connections.sqlite.database');
            $backupFilename = 'database_backup_clean_' . date('Ymd_His') . '.sqlite';
            if ($dbPath && file_exists($dbPath)) {
                @copy($dbPath, dirname($dbPath) . '/' . $backupFilename);
            }

            // 2. Identify dummy users
            $dummyUsers = \App\Models\User::whereNotIn('id', $keepIds)->get();
            $dummyIds = $dummyUsers->pluck('id')->toArray();
            $deletedCount = count($dummyIds);

            if (!empty($dummyIds)) {
                $tables = [
                    'tasks', 'sub_tasks', 'task_templates', 'template_shares', 'shared_task_invitations',
                    'goals', 'skills', 'skill_targets', 'journals', 'journal_links', 'flashcards',
                    'finance_transactions', 'focus_sessions', 'pomodoro_playlists', 'workout_sessions',
                    'workout_exercises', 'workout_sets', 'friendships', 'friend_nudges', 'messages',
                    'posts', 'comments', 'likes', 'notifications', 'user_achievements', 'streak_histories',
                    'pending_xp_logs', 'daily_summaries', 'ai_insights', 'ai_usage_logs', 'mcp_tokens',
                    'subscription_transactions', 'user_feedbacks',
                ];

                foreach ($tables as $t) {
                    if (\Illuminate\Support\Facades\Schema::hasTable($t) && \Illuminate\Support\Facades\Schema::hasColumn($t, 'user_id')) {
                        \Illuminate\Support\Facades\DB::table($t)->whereIn('user_id', $dummyIds)->delete();
                    }
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('friendships')) {
                    \Illuminate\Support\Facades\DB::table('friendships')
                        ->whereIn('user_id', $dummyIds)
                        ->orWhereIn('friend_id', $dummyIds)
                        ->delete();
                }

                \App\Models\User::whereIn('id', $dummyIds)->delete();
            }

            // 3. Clean dummy logs
            $deletedLogs = \App\Models\SystemLog::where('ip_address', '127.0.0.1')
                ->orWhere('url', 'like', '%localhost%')
                ->orWhere('url', 'like', '%atoma.test%')
                ->delete();

            // 4. VACUUM
            try {
                \Illuminate\Support\Facades\DB::statement('VACUUM;');
            } catch (\Throwable) {}

            // 5. Clear Cache
            try {
                \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            } catch (\Throwable) {}

            $message = "Pembersihan berhasil! {$deletedCount} user dummy dan {$deletedLogs} log testing telah dihapus. File cadangan disimpan sebagai '{$backupFilename}'. Cache aplikasi telah diperbarui!";
        }
    }
}

    $users = \App\Models\User::withCount(['tasks', 'journals', 'focusSessions'])->orderBy('id')->get();
    $logsCount = \App\Models\SystemLog::where('ip_address', '127.0.0.1')
        ->orWhere('url', 'like', '%localhost%')
        ->orWhere('url', 'like', '%atoma.test%')
        ->count();

    return view('admin.db-maintenance', [
        'users' => $users,
        'logsCount' => $logsCount,
        'secret' => $secret,
        'message' => $message,
        'messageType' => $messageType,
    ]);
});

// Direct Application Downloads (Windows .EXE & Android .APK)
Route::get('/download/exe', function () {
    $path = public_path('downloads/ATOMA-Setup.exe');
    if (file_exists($path)) {
        return response()->download($path, 'ATOMA-Setup.exe', [
            'Content-Type' => 'application/vnd.microsoft.portable-executable',
        ]);
    }
    return redirect('https://github.com/Irvan113/atomaroute/actions');
})->name('download.exe');

Route::get('/download/exe-portable', function () {
    $path = public_path('downloads/ATOMA-Portable.exe');
    if (file_exists($path)) {
        return response()->download($path, 'ATOMA-Portable.exe', [
            'Content-Type' => 'application/vnd.microsoft.portable-executable',
        ]);
    }
    return redirect('https://github.com/Irvan113/atomaroute/actions');
})->name('download.exe-portable');

Route::get('/download/apk', function () {
    $path = public_path('downloads/ATOMA.apk');
    if (file_exists($path)) {
        return response()->download($path, 'ATOMA.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
    return redirect('https://github.com/Irvan113/atomaroute/actions');
})->name('download.apk');
