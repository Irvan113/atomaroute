@props([
    'active' => 'dashboard'
])

@php
    $pendingFeedbackCount = 0;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('user_feedbacks')) {
            $pendingFeedbackCount = \App\Models\UserFeedback::where('status', 'pending')->count();
        }
    } catch (\Throwable $e) {}

    $recentErrorsCount = 0;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('system_logs')) {
            $recentErrorsCount = \App\Models\SystemLog::whereIn('level', ['ERROR', 'CRITICAL'])
                ->where('created_at', '>=', now()->subHours(24))
                ->count();
        }
    } catch (\Throwable $e) {}

    $expiringSoonCount = 0;
    try {
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'subscription_expires_at')) {
            $expiringSoonCount = \App\Models\User::where('subscription_tier', 'pro')
                ->where('subscription_expires_at', '>', now())
                ->where('subscription_expires_at', '<=', now()->addDays(3))
                ->count();
        }
    } catch (\Throwable $e) {}

    $tabs = [
        [
            'id' => 'dashboard',
            'name' => 'Dashboard',
            'icon' => '📊',
            'route' => route('admin.dashboard'),
            'badge' => null,
            'badgeColor' => null,
        ],
        [
            'id' => 'subscriptions',
            'name' => 'Langganan PRO',
            'icon' => '👑',
            'route' => route('admin.subscriptions'),
            'badge' => $expiringSoonCount > 0 ? $expiringSoonCount : null,
            'badgeColor' => 'bg-amber-500 text-white',
        ],
        [
            'id' => 'logs',
            'name' => 'System Logs',
            'icon' => '📋',
            'route' => route('admin.system-logs'),
            'badge' => $recentErrorsCount > 0 ? $recentErrorsCount : null,
            'badgeColor' => 'bg-rose-500 text-white',
        ],
        [
            'id' => 'ai',
            'name' => 'AI Telemetry',
            'icon' => '🛡️',
            'route' => route('admin.ai-usage'),
            'badge' => null,
            'badgeColor' => null,
        ],
        [
            'id' => 'feedbacks',
            'name' => 'Kotak Masuk',
            'icon' => '📬',
            'route' => route('admin.feedbacks'),
            'badge' => $pendingFeedbackCount > 0 ? $pendingFeedbackCount : null,
            'badgeColor' => 'bg-amber-500 text-white',
        ],
    ];
@endphp

<div class="w-full bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-2xl sm:rounded-3xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-2 sm:p-2.5 mb-6 select-none transition-all">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Brand & System Status Indicator -->
        <div class="flex items-center justify-between sm:justify-start gap-3 px-2 sm:px-3 py-1">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-700 to-purple-600 text-white flex items-center justify-center font-black text-base shadow-sm ring-2 ring-indigo-500/20">
                    👑
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-black tracking-tight text-gray-900 dark:text-white uppercase">
                            Admin Command Center
                        </span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-black bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                            v2.4
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Sistem Aktif & Terpantau</span>
                    </div>
                </div>
            </div>

            <!-- Mobile Exit Button -->
            <a href="{{ route('tasks') }}" wire:navigate class="md:hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-700/70 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold transition-colors">
                <span>Keluar</span>
                <span class="text-sm">&rarr;</span>
            </a>
        </div>

        <!-- Navigation Tabs (Horizontally scrollable on mobile) -->
        <div class="flex items-center gap-1 sm:gap-1.5 overflow-x-auto pb-1 md:pb-0 px-1 scrollbar-none">
            @foreach($tabs as $tab)
                @php
                    $isActive = $active === $tab['id'];
                @endphp
                <a href="{{ $tab['route'] }}" 
                   wire:navigate
                   class="shrink-0 inline-flex items-center gap-2 px-3 sm:px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all {{ $isActive ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20 ring-1 ring-indigo-500' : 'text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50/60 dark:hover:bg-indigo-950/30' }}">
                    <span class="text-sm leading-none">{{ $tab['icon'] }}</span>
                    <span>{{ $tab['name'] }}</span>

                    @if($tab['badge'])
                        <span class="px-1.5 py-0.2 text-[10px] font-black rounded-full {{ $tab['badgeColor'] }} animate-pulse">
                            {{ $tab['badge'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>

        <!-- Desktop Exit to Main App Button -->
        <div class="hidden md:flex items-center gap-2 px-2 shrink-0">
            <a href="{{ route('tasks') }}" 
               wire:navigate 
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-gray-100/90 dark:bg-gray-700/70 hover:bg-gray-200/90 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 hover:text-gray-900 dark:hover:text-white text-xs font-bold transition-all border border-gray-200/60 dark:border-gray-600/60 shadow-2xs group cursor-pointer"
               title="Kembali ke Aplikasi ATOMA">
                <svg class="w-3.5 h-3.5 text-gray-400 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Aplikasi Utama</span>
            </a>
        </div>
    </div>
</div>
