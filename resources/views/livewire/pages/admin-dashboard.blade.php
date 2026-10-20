<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\SystemLog;
use App\Models\AiUsageLog;
use App\Models\UserFeedback;
use App\Models\Task;
use App\Models\FocusSession;
use App\Services\SystemLogService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    // Spotify API Modal State
    public bool $showSpotifyModal = false;
    public string $spotifyClientId = '';
    public string $spotifyClientSecret = '';

    public function mount()
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Auto-grant admin status to User ID 1 for testing & management ease
        if (!$user->is_admin && $user->id === 1) {
            $user->is_admin = true;
            $user->save();
        }

        if (!$user->is_admin) {
            abort(403, 'Akses Ditolak: Halaman ini khusus untuk Administrator.');
        }

        $this->loadSpotifyCredentials();
    }

    public function loadSpotifyCredentials(): void
    {
        $this->spotifyClientId = (string) config('services.spotify.client_id', '');
        $this->spotifyClientSecret = (string) config('services.spotify.client_secret', '');
    }

    public function openSpotifyModal(): void
    {
        $this->loadSpotifyCredentials();
        $this->showSpotifyModal = true;
    }

    public function closeSpotifyModal(): void
    {
        $this->showSpotifyModal = false;
    }

    public function saveSpotifyCredentials(): void
    {
        $this->validate([
            'spotifyClientId' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'spotifyClientSecret' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
        ], [
            'spotifyClientId.regex' => 'Format Client ID tidak valid (hanya karakter alfanumerik, underscore, dan strip).',
            'spotifyClientSecret.regex' => 'Format Client Secret tidak valid.',
        ]);

        $clientId = trim($this->spotifyClientId);
        $clientSecret = trim($this->spotifyClientSecret);

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

        SystemLogService::security(
            'admin.spotify_config_update',
            'Administrator memperbarui konfigurasi Spotify API Client ID & Secret',
            ['admin_id' => auth()->id()]
        );

        $this->showSpotifyModal = false;
        $this->dispatch('toast', message: '🎵 Kredensial Spotify API berhasil disimpan!', type: 'success');
    }

    public function clearAppCache(): void
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');

            SystemLogService::log(
                SystemLogService::LEVEL_INFO,
                SystemLogService::CAT_SYSTEM,
                'system.clear_cache',
                'Administrator membersihkan cache aplikasi (Cache, Views, Routes)',
                ['admin_id' => auth()->id()]
            );

            $this->dispatch('toast', message: '✨ Cache aplikasi, views, dan routes berhasil dibersihkan!', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Gagal membersihkan cache: ' . $e->getMessage(), type: 'error');
        }
    }

    public function generateTestLog(): void
    {
        $log = SystemLogService::info(
            'Uji coba live telemetry dari Admin Command Center',
            [
                'triggered_by' => auth()->user()->name,
                'time' => now()->toIso8601String(),
                'ip' => request()->ip(),
            ],
            'admin.test_pulse'
        );

        $this->dispatch('toast', message: '🧪 Berhasil memicu log uji coba (ID: #' . ($log ? $log->id : 'OK') . ')!', type: 'info');
    }

    public function with(): array
    {
        // Safe Metrics Calculation
        $totalUsers = User::count();
        $newUsersThisWeek = User::where('created_at', '>=', now()->subDays(7))->count();
        $activeUsersToday = User::where('updated_at', '>=', now()->startOfDay())->count();

        $totalLogs = SystemLog::count();
        $errorLogs24h = SystemLog::whereIn('level', ['ERROR', 'CRITICAL'])
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
        $securityAudits = SystemLog::where('category', 'security')->count();

        $totalAiRequests = AiUsageLog::count();
        $avgAiLatency = (int) round(AiUsageLog::avg('execution_time_ms') ?: 0);
        $totalAiTokens = (int) (AiUsageLog::sum('total_tokens') ?: 0);
        $aiSuccessCount = AiUsageLog::where('status', 'success')->count();
        $aiSuccessRate = $totalAiRequests > 0 ? round(($aiSuccessCount / $totalAiRequests) * 100, 1) : 100;

        $totalFeedbacks = UserFeedback::count();
        $pendingFeedbacks = UserFeedback::where('status', 'pending')->count();
        $resolvedFeedbacks = UserFeedback::where('status', 'resolved')->count();

        $tasksCompletedToday = Task::where('is_completed', true)->whereDate('completed_at', today())->count();
        $focusMinutesToday = (int) FocusSession::whereDate('created_at', today())->sum('minutes');

        $isSpotifyConfigured = !empty(config('services.spotify.client_id')) && !empty(config('services.spotify.client_secret'));

        // Recent Pulse Streams
        $recentLogs = SystemLog::with('user')->latest()->limit(5)->get();
        $recentFeedbacks = UserFeedback::with('user')->latest()->limit(4)->get();
        $recentAiRequests = AiUsageLog::with('user')->latest()->limit(4)->get();

        return [
            'kpis' => [
                'totalUsers' => $totalUsers,
                'newUsersThisWeek' => $newUsersThisWeek,
                'activeUsersToday' => $activeUsersToday,
                'totalLogs' => $totalLogs,
                'errorLogs24h' => $errorLogs24h,
                'securityAudits' => $securityAudits,
                'totalAiRequests' => $totalAiRequests,
                'avgAiLatency' => $avgAiLatency,
                'totalAiTokens' => $totalAiTokens,
                'aiSuccessRate' => $aiSuccessRate,
                'totalFeedbacks' => $totalFeedbacks,
                'pendingFeedbacks' => $pendingFeedbacks,
                'resolvedFeedbacks' => $resolvedFeedbacks,
                'tasksCompletedToday' => $tasksCompletedToday,
                'focusMinutesToday' => $focusMinutesToday,
                'isSpotifyConfigured' => $isSpotifyConfigured,
            ],
            'recentLogs' => $recentLogs,
            'recentFeedbacks' => $recentFeedbacks,
            'recentAiRequests' => $recentAiRequests,
        ];
    }
}; ?>

<div class="py-4 sm:py-6 space-y-6 max-w-7xl mx-auto min-w-0">

    <!-- Unified Admin Navigation Bar -->
    <x-admin-navbar active="dashboard" />

    <!-- Hero Command Center Banner -->
    <div class="rounded-3xl bg-gradient-to-r from-indigo-900 via-indigo-700 to-purple-800 text-white p-6 sm:p-8 shadow-xl relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 w-56 h-56 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-1/4 -top-12 w-48 h-48 bg-purple-500/20 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-white/20 text-white border border-white/30 backdrop-blur-xs">
                        👑 Administrator Central Command
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-200 border border-emerald-400/30">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        Semua Node Berjalan Normal
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-white flex items-center gap-3">
                    <span>Selamat Datang di Admin Dashboard</span>
                </h1>
                <p class="text-xs sm:text-sm text-indigo-100/90 max-w-2xl leading-relaxed">
                    Satu pusat kendali untuk memantau kesehatan sistem, audit keamanan, telemetri kecerdasan buatan Gemini AI, kotak masuk saran pengguna, serta konfigurasi integrasi platform.
                </p>
            </div>

            <!-- Quick Maintenance Toolbar in Hero -->
            <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5 shrink-0">
                <button wire:click="clearAppCache" 
                        wire:loading.attr="disabled"
                        class="px-4 py-2.5 rounded-2xl bg-white/15 hover:bg-white/25 active:scale-95 text-white text-xs font-bold transition-all border border-white/20 shadow-sm flex items-center gap-2 cursor-pointer select-none">
                    <span wire:loading.remove wire:target="clearAppCache">🧹</span>
                    <span wire:loading wire:target="clearAppCache" class="animate-spin">⏳</span>
                    <span>Bersihkan Cache</span>
                </button>

                <button wire:click="generateTestLog" 
                        wire:loading.attr="disabled"
                        class="px-4 py-2.5 rounded-2xl bg-indigo-600/60 hover:bg-indigo-600 active:scale-95 text-white text-xs font-bold transition-all border border-indigo-400/40 shadow-sm flex items-center gap-2 cursor-pointer select-none">
                    <span wire:loading.remove wire:target="generateTestLog">🧪</span>
                    <span wire:loading wire:target="generateTestLog" class="animate-spin">⏳</span>
                    <span>Test Log Pulse</span>
                </button>
            </div>
        </div>
    </div>

    <!-- High-Level KPI Summary Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- Card 1: Users -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-xs hover:border-indigo-300 dark:hover:border-indigo-700 transition-all group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Pengguna</span>
                <span class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm">
                    👥
                </span>
            </div>
            <div class="mt-2.5 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">{{ number_format($kpis['totalUsers']) }}</span>
                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">+{{ $kpis['newUsersThisWeek'] }} minggu ini</span>
            </div>
            <div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400 font-medium flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>{{ $kpis['activeUsersToday'] }} aktif beraktivitas hari ini</span>
            </div>
        </div>

        <!-- Card 2: System Logs & Errors -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-xs hover:border-indigo-300 dark:hover:border-indigo-700 transition-all group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Log & Audit</span>
                <span class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-sm">
                    📋
                </span>
            </div>
            <div class="mt-2.5 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">{{ number_format($kpis['totalLogs']) }}</span>
                <span class="text-xs font-bold {{ $kpis['errorLogs24h'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    {{ $kpis['errorLogs24h'] }} error (24j)
                </span>
            </div>
            <div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400 font-medium flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                <span>{{ $kpis['securityAudits'] }} jejak audit keamanan tercatat</span>
            </div>
        </div>

        <!-- Card 3: AI Engine -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-xs hover:border-indigo-300 dark:hover:border-indigo-700 transition-all group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">AI Intelligence</span>
                <span class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-sm">
                    🤖
                </span>
            </div>
            <div class="mt-2.5 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">{{ number_format($kpis['totalAiRequests']) }}</span>
                <span class="text-xs font-bold text-purple-600 dark:text-purple-400">{{ $kpis['avgAiLatency'] }} ms avg</span>
            </div>
            <div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400 font-medium flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                <span>{{ number_format($kpis['totalAiTokens']) }} tokens • {{ $kpis['aiSuccessRate'] }}% sukses</span>
            </div>
        </div>

        <!-- Card 4: Feedback Inbox -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-xs hover:border-indigo-300 dark:hover:border-indigo-700 transition-all group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Kotak Masuk User</span>
                <span class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm">
                    📬
                </span>
            </div>
            <div class="mt-2.5 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">{{ number_format($kpis['totalFeedbacks']) }}</span>
                <span class="text-xs font-bold {{ $kpis['pendingFeedbacks'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500' }}">
                    {{ $kpis['pendingFeedbacks'] }} menunggu
                </span>
            </div>
            <div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400 font-medium flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>{{ $kpis['resolvedFeedbacks'] }} surat telah ditindaklanjuti</span>
            </div>
        </div>
    </div>

    <!-- Main Navigation Hub (Clickable Command Cards) -->
    <div>
        <div class="flex items-center justify-between mb-3 px-1">
            <h2 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                <span>Pusat Modul & Fitur Administrasi</span>
            </h2>
            <span class="text-xs text-gray-500 dark:text-gray-400">Pilih modul untuk eksplorasi detail</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Hub 1: System Logs -->
            <a href="{{ route('admin.system-logs') }}" 
               wire:navigate
               class="p-5 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/90 dark:border-gray-700/90 hover:border-indigo-500 dark:hover:border-indigo-500 shadow-sm hover:shadow-md transition-all group flex flex-col justify-between cursor-pointer">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl shadow-2xs group-hover:scale-110 transition-transform">
                            📋
                        </div>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300">
                            Log Viewer
                        </span>
                    </div>
                    <h3 class="text-base font-black text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                        System Logs & Audit
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 leading-relaxed">
                        Observabilitas menyeluruh: lacak bug crash, analisa waktu respon lambat, jejak audit keamanan, dan log kepatuhan.
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between text-xs font-bold text-indigo-600 dark:text-indigo-400">
                    <span>Buka Log Viewer</span>
                    <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- Hub 2: AI Telemetry -->
            <a href="{{ route('admin.ai-usage') }}" 
               wire:navigate
               class="p-5 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/90 dark:border-gray-700/90 hover:border-purple-500 dark:hover:border-purple-500 shadow-sm hover:shadow-md transition-all group flex flex-col justify-between cursor-pointer">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-11 h-11 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl shadow-2xs group-hover:scale-110 transition-transform">
                            🛡️
                        </div>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300">
                            Observability
                        </span>
                    </div>
                    <h3 class="text-base font-black text-gray-900 dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">
                        AI Telemetry & Token
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 leading-relaxed">
                        Inspeksi prompt pengguna & respon Gemini AI, pantau konsumsi token, estimasi biaya rupiah, dan statistik model.
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between text-xs font-bold text-purple-600 dark:text-purple-400">
                    <span>Buka Telemetry</span>
                    <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- Hub 3: User Feedbacks -->
            <a href="{{ route('admin.feedbacks') }}" 
               wire:navigate
               class="p-5 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/90 dark:border-gray-700/90 hover:border-amber-500 dark:hover:border-amber-500 shadow-sm hover:shadow-md transition-all group flex flex-col justify-between cursor-pointer">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-11 h-11 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl shadow-2xs group-hover:scale-110 transition-transform">
                            📬
                        </div>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300">
                            Mailbox
                        </span>
                    </div>
                    <h3 class="text-base font-black text-gray-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                        Kotak Surat User
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 leading-relaxed">
                        Tampung aspirasi, ide fitur, kritik, dan laporan bug dari pengguna ATOMA, serta kirim balasan langsung dari dev.
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between text-xs font-bold text-amber-600 dark:text-amber-400">
                    <span>Buka Kotak Surat</span>
                    <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- Hub 4: Spotify Integration -->
            <div wire:click="openSpotifyModal" 
                 class="p-5 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/90 dark:border-gray-700/90 hover:border-emerald-500 dark:hover:border-emerald-500 shadow-sm hover:shadow-md transition-all group flex flex-col justify-between cursor-pointer">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shadow-2xs group-hover:scale-110 transition-transform">
                            🎵
                        </div>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $kpis['isSpotifyConfigured'] ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400' }}">
                            {{ $kpis['isSpotifyConfigured'] ? 'Terkonfigurasi' : 'Belum Diatur' }}
                        </span>
                    </div>
                    <h3 class="text-base font-black text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                        Integrasi Spotify API
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 leading-relaxed">
                        Pengaturan kredensial OAuth Client ID & Secret untuk pemutar musik ambient Spotify pada sesi Pomodoro fokus.
                    </p>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between text-xs font-bold text-emerald-600 dark:text-emerald-400">
                    <span>Atur Kredensial API</span>
                    <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Pulse Stream & Diagnostics (2-Column Grid) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: System Logs Live Pulse (2 cols) -->
        <div class="lg:col-span-2 space-y-4">
            <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-sm">
                            ⚡
                        </span>
                        <div>
                            <h3 class="text-sm sm:text-base font-black text-gray-900 dark:text-white">
                                Live Pulse: Log Sistem Terkini
                            </h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                5 aktivitas sistem dan audit log terbaru yang masuk ke database
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('admin.system-logs') }}" wire:navigate class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                        Lihat Semua &rarr;
                    </a>
                </div>

                @if($recentLogs->isEmpty())
                    <div class="py-8 text-center text-xs text-gray-400">
                        Belum ada rekaman log sistem di database.
                    </div>
                @else
                    <div class="space-y-2.5">
                        @foreach($recentLogs as $log)
                            @php
                                $levelColors = [
                                    'DEBUG' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                    'INFO' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
                                    'WARN' => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
                                    'ERROR' => 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
                                    'CRITICAL' => 'bg-red-600 text-white animate-pulse',
                                ];
                                $pillClass = $levelColors[$log->level] ?? 'bg-gray-100 text-gray-700';
                            @endphp
                            <div class="p-3 rounded-2xl bg-gray-50/70 dark:bg-gray-750/50 border border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="px-2 py-0.5 rounded-md font-mono text-[10px] font-black {{ $pillClass }} shrink-0">
                                        {{ $log->level }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-bold text-gray-900 dark:text-white truncate">
                                            {{ $log->message }}
                                        </p>
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                                            {{ $log->action }} &bull; {{ $log->user ? $log->user->name : 'Sistem' }} &bull; {{ $log->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                                @if($log->execution_time_ms)
                                    <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-gray-200/60 dark:bg-gray-700 text-gray-600 dark:text-gray-300 shrink-0 self-start sm:self-auto">
                                        {{ $log->execution_time_ms }}ms
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Recent Feedback & AI Pulse Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Feedback Mini Pulse -->
                <div class="p-5 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                            <span>📬</span> Surat Pengguna Terbaru
                        </h4>
                        <a href="{{ route('admin.feedbacks') }}" wire:navigate class="text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:underline">
                            Kelola &rarr;
                        </a>
                    </div>
                    @if($recentFeedbacks->isEmpty())
                        <p class="text-xs text-gray-400 py-4 text-center">Belum ada surat masuk.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($recentFeedbacks as $fb)
                                <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-750/40 border border-gray-100 dark:border-gray-700/60 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-gray-900 dark:text-gray-100 truncate">{{ $fb->subject }}</span>
                                        <span class="text-[9px] font-bold px-1.5 py-0.2 rounded {{ $fb->status === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' }}">
                                            {{ $fb->status }}
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                        Oleh {{ $fb->user ? $fb->user->name : 'Anonim' }} &bull; {{ $fb->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- AI Mini Pulse -->
                <div class="p-5 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                            <span>🤖</span> Telemetri AI Terbaru
                        </h4>
                        <a href="{{ route('admin.ai-usage') }}" wire:navigate class="text-[11px] font-bold text-purple-600 dark:text-purple-400 hover:underline">
                            Detail &rarr;
                        </a>
                    </div>
                    @if($recentAiRequests->isEmpty())
                        <p class="text-xs text-gray-400 py-4 text-center">Belum ada aktivitas AI tercatat.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($recentAiRequests as $ai)
                                <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-750/40 border border-gray-100 dark:border-gray-700/60 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-mono text-[10px] text-purple-600 dark:text-purple-400 font-bold">{{ $ai->model }}</span>
                                        <span class="font-mono text-[10px] text-gray-500">{{ $ai->execution_time_ms }}ms</span>
                                    </div>
                                    <p class="text-[10px] text-gray-600 dark:text-gray-300 mt-0.5 truncate font-medium">
                                        {{ $ai->feature }} &bull; {{ $ai->total_tokens }} tokens
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right: System Diagnostics & Health Status (1 col) -->
        <div class="space-y-4">
            <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm">
                        ⚙️
                    </span>
                    <div>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white">
                            Diagnostik Sistem ATOMA
                        </h3>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400">
                            Status runtime stack dan environment
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-700/60 text-xs">
                    <div class="py-2.5 flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Framework</span>
                        <span class="font-bold text-gray-900 dark:text-white">Laravel {{ app()->version() }}</span>
                    </div>
                    <div class="py-2.5 flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">PHP Runtime</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ PHP_VERSION }}</span>
                    </div>
                    <div class="py-2.5 flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Database Driver</span>
                        <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase">
                            {{ config('database.default') }}
                        </span>
                    </div>
                    <div class="py-2.5 flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Cache Driver</span>
                        <span class="font-mono text-xs font-bold text-gray-900 dark:text-white uppercase">
                            {{ config('cache.default') }}
                        </span>
                    </div>
                    <div class="py-2.5 flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Environment</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            {{ config('app.env') }}
                        </span>
                    </div>
                    <div class="py-2.5 flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Status Spotify API</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $kpis['isSpotifyConfigured'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' }}">
                            {{ $kpis['isSpotifyConfigured'] ? 'Aktif' : 'Non-Aktif' }}
                        </span>
                    </div>
                </div>

                <!-- Productivity Pulse Widget -->
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700/60">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 block mb-2">
                        Aktivitas Produktivitas Hari Ini
                    </span>
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-750/40 border border-gray-100 dark:border-gray-700/60">
                            <span class="text-base font-black text-indigo-600 dark:text-indigo-400">{{ $kpis['tasksCompletedToday'] }}</span>
                            <span class="text-[10px] text-gray-500 dark:text-gray-400 block">Tugas Selesai</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-750/40 border border-gray-100 dark:border-gray-700/60">
                            <span class="text-base font-black text-rose-600 dark:text-rose-400">{{ $kpis['focusMinutesToday'] }}m</span>
                            <span class="text-[10px] text-gray-500 dark:text-gray-400 block">Waktu Fokus</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Spotify Credentials Modal -->
    @if($showSpotifyModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 shadow-2xl max-w-lg w-full p-6 sm:p-8 space-y-5 animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shadow-xs">
                            🎵
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-gray-900 dark:text-white">
                                Konfigurasi Spotify API
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Kredensial OAuth Developer Dashboard
                            </p>
                        </div>
                    </div>
                    <button wire:click="closeSpotifyModal" class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex items-center justify-center cursor-pointer">
                        ✕
                    </button>
                </div>

                <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                    Masukkan Client ID dan Client Secret dari <a href="https://developer.spotify.com/dashboard" target="_blank" class="text-emerald-600 dark:text-emerald-400 font-bold underline">Spotify Developer Dashboard</a> untuk mengaktifkan pemutar musik Pomodoro.
                </p>

                <form wire:submit.prevent="saveSpotifyCredentials" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                            Spotify Client ID
                        </label>
                        <input type="text" 
                               wire:model="spotifyClientId"
                               placeholder="e.g. 4c89283f6..."
                               class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs font-mono text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
                        @error('spotifyClientId')
                            <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                            Spotify Client Secret
                        </label>
                        <input type="password" 
                               wire:model="spotifyClientSecret"
                               placeholder="••••••••••••••••••••••••••••••••"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs font-mono text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
                        @error('spotifyClientSecret')
                            <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" 
                                wire:click="closeSpotifyModal" 
                                class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" 
                                class="px-5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm cursor-pointer">
                            Simpan Kredensial
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
