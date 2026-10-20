<?php

use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
    public User $user;
    public array $equippedBadgesDetails = [];
    public array $rpgStats = [];
    public array $allAchievements = [];
    public int $totalBadges = 0;
    public int $unlockedCount = 0;
    public int $unlockedPercentage = 0;
    public int $totalXpFromAchievements = 0;
    public int $completedTasksCount = 0;
    public string $activeCategory = 'all';

    public function mount(User $user)
    {
        $this->user = $user;
        $this->equippedBadgesDetails = $user->equipped_badges_details ?? [];
        $this->rpgStats = $user->rpg_stats;

        // Fetch user's achievements
        $service = new AchievementService();
        $this->allAchievements = $service->getProgress($user);
        $this->totalBadges = count($this->allAchievements);
        $this->unlockedCount = collect($this->allAchievements)->where('unlocked', true)->count();
        $this->unlockedPercentage = $this->totalBadges > 0 
            ? (int) round(($this->unlockedCount / $this->totalBadges) * 100) 
            : 0;
        $this->totalXpFromAchievements = collect($this->allAchievements)->where('unlocked', true)->sum('bonus_xp');
        
        $this->completedTasksCount = DB::table('tasks')
            ->where('user_id', $user->id)
            ->where('is_completed', true)
            ->count();
    }

    public function setCategory(string $category)
    {
        $this->activeCategory = $category;
    }
}; ?>

<div class="min-h-screen bg-gray-50/70 dark:bg-gray-900 pb-20 overflow-y-auto"
     x-data="{
        copied: false,
        copyProfileLink() {
            const url = window.location.origin + '/u/{{ $user->id }}';
            navigator.clipboard.writeText(url).then(() => {
                this.copied = true;
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: '{{ __('Link profil berhasil disalin ke clipboard!') }}', type: 'success' }
                }));
                setTimeout(() => this.copied = false, 3000);
            });
        }
     }">

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8 space-y-6">

        <!-- Top Navigation Bar & Breadcrumb -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-xs sm:text-sm font-bold text-gray-500 dark:text-gray-400">
                <a href="{{ route('leaderboard') }}" wire:navigate class="hover:text-atoma-blue dark:hover:text-atoma-cyan transition-colors flex items-center gap-1">
                    <span>←</span> Leaderboard
                </a>
                <span>/</span>
                <a href="{{ route('social') }}" wire:navigate class="hover:text-atoma-blue dark:hover:text-atoma-cyan transition-colors">
                    {{ __('Sosial') }}
                </a>
                <span>/</span>
                <span class="text-gray-900 dark:text-white font-black truncate max-w-[150px] sm:max-w-none">{{ $user->name }}</span>
            </div>

            <!-- Share Button -->
            <button @click="copyProfileLink()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-black text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-750 shadow-xs transition-all cursor-pointer">
                <span x-show="!copied">🔗 {{ __('Salin Link Profil') }}</span>
                <span x-show="copied" class="text-emerald-600 dark:text-emerald-400">✓ {{ __('Link Tersalin!') }}</span>
            </button>
        </div>

        <!-- HERO PROFILE BANNER -->
        <div class="relative rounded-3xl overflow-hidden bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-md">
            <!-- Background Artwork Header -->
            <div class="h-36 sm:h-48 bg-gradient-to-r from-blue-600 via-indigo-600 to-cyan-500 relative p-6 flex items-end">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.2),transparent_60%)]"></div>
                <div class="absolute top-4 right-4 flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-white/20 backdrop-blur-md text-white border border-white/30 shadow-xs">
                        👑 {{ $user->prestige_title }}
                    </span>
                    <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-white/20 backdrop-blur-md text-white border border-white/30 shadow-xs">
                        🏆 {{ __('Liga') }} {{ ucfirst(__($user->league ?? 'Bronze')) }}
                    </span>
                </div>
            </div>

            <!-- Profile Info Content -->
            <div class="px-6 pb-6 pt-0 relative">
                <div class="flex flex-col sm:flex-row items-start sm:items-end justify-between gap-4 -mt-16 sm:-mt-20 mb-4">
                    <!-- Avatar & Name -->
                    <div class="flex flex-col sm:flex-row items-center sm:items-end gap-4 text-center sm:text-left">
                        @php
                            $frameClass = 'border-4 border-white dark:border-gray-800 shadow-xl';
                            $frameEffect = '';
                            if ($user->active_frame === 'frame_neon') {
                                $frameClass = 'border-2 border-indigo-400 shadow-[0_0_20px_rgba(99,102,241,0.8)]';
                                $frameEffect = '<div class="absolute inset-0 rounded-full border-2 border-indigo-400 pointer-events-none z-10"></div>';
                            } elseif ($user->active_frame === 'frame_fire') {
                                $frameClass = 'border-4 border-orange-500 shadow-[0_0_20px_rgba(249,115,22,0.8)]';
                                $frameEffect = '<div class="absolute inset-0 rounded-full border-4 border-orange-500 animate-pulse pointer-events-none z-10"></div>';
                            } elseif ($user->active_frame === 'frame_gold') {
                                $frameClass = 'border-4 border-amber-400 ring-4 ring-yellow-300/80 shadow-[0_0_30px_rgba(250,204,21,0.9)]';
                                $frameEffect = '<div class="absolute inset-0 rounded-full border-4 border-amber-400 pointer-events-none z-10"></div>';
                            }
                        @endphp
                        <div class="relative shrink-0">
                            @if($user->active_frame === 'frame_gold')
                                <div class="absolute -top-7 sm:-top-8 left-1/2 -translate-x-1/2 w-16 sm:w-20 z-20 pointer-events-none drop-shadow-xl">
                                    <img src="{{ asset('images/frames/crown_gold.svg') }}" alt="Crown" class="w-full h-auto object-contain">
                                </div>
                            @endif
                            <div class="w-28 h-28 sm:w-32 sm:h-32 rounded-full p-1 bg-white dark:bg-gray-800 relative overflow-hidden {{ $frameClass }}">
                                {!! $frameEffect !!}
                                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-full h-full rounded-full object-cover bg-white relative z-0">
                            </div>
                            <!-- Level Badge Pill -->
                            <div class="absolute -bottom-1 -right-1 z-30 bg-gradient-to-r from-amber-400 to-amber-500 text-yellow-950 font-black text-xs px-2.5 py-1 rounded-full border-2 border-white dark:border-gray-800 shadow-md flex items-center gap-1">
                                <span>⭐</span>
                                <span>{{ $user->level }}</span>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center gap-2 justify-center sm:justify-start flex-wrap">
                                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">
                                    {{ $user->name }}
                                </h1>
                                @if(auth()->id() === $user->id)
                                    <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-atoma-blue text-white uppercase tracking-wider">{{ __('Kamu') }}</span>
                                @endif
                            </div>
                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium max-w-lg">
                                {{ $user->bio ?? ('Level ' . $user->level . ' ATOMA User. ' . __('Membangun kebiasaan produktif setiap hari.')) }}
                            </p>
                            <div class="flex items-center gap-3 text-xs text-gray-400 pt-1 justify-center sm:justify-start">
                                <span>📅 {{ __('Bergabung') }} {{ $user->created_at->translatedFormat('F Y') }}</span>
                                <span>•</span>
                                <span>⚡ {{ number_format($completedTasksCount) }} {{ __('Tugas Selesai') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2 w-full sm:w-auto justify-center">
                        @if(auth()->id() === $user->id)
                            <a href="{{ route('profile') }}" wire:navigate 
                               class="flex-1 sm:flex-initial px-4 py-2 rounded-xl text-xs font-black bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 transition-all cursor-pointer flex items-center justify-center gap-1.5">
                                <x-icon.gear class="w-4 h-4 inline-block shrink-0" />
                                <span>{{ __('Pengaturan Profil') }}</span>
                            </a>
                            <button @click="$dispatch('open-achievements')" 
                                    class="flex-1 sm:flex-initial px-4 py-2 rounded-xl text-xs font-black bg-atoma-blue hover:bg-atoma-blue/90 text-white shadow-md transition-all cursor-pointer">
                                🏆 {{ __('Kelola Rak Pamer') }}
                            </button>
                        @else
                            <a href="{{ route('social') }}" wire:navigate 
                               class="flex-1 sm:flex-initial px-4 py-2 rounded-xl text-xs font-black bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white shadow-md transition-all cursor-pointer flex items-center justify-center gap-1.5">
                                <span>⚔️</span>
                                <span>{{ __('Tantang Senggol Bacok') }}</span>
                            </a>
                            <a href="{{ route('chat', $user->id) }}" wire:navigate 
                               class="flex-1 sm:flex-initial px-4 py-2 rounded-xl text-xs font-black bg-atoma-blue hover:bg-atoma-blue/90 text-white shadow-md transition-all cursor-pointer flex items-center justify-center gap-1.5">
                                <span>💬</span>
                                <span>{{ __('Kirim Pesan') }}</span>
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Quick Stat Chips -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 pt-4 border-t border-gray-100 dark:border-gray-700/60">
                    <div class="p-3 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/40 text-center">
                        <div class="text-[10px] uppercase font-black tracking-wider text-indigo-600 dark:text-indigo-400">{{ __('Total XP') }}</div>
                        <div class="text-lg sm:text-xl font-black text-indigo-950 dark:text-indigo-200 font-mono">{{ number_format($user->xp) }}</div>
                    </div>
                    <div class="p-3 rounded-2xl bg-orange-50/70 dark:bg-orange-950/30 border border-orange-100 dark:border-orange-900/40 text-center">
                        <div class="text-[10px] uppercase font-black tracking-wider text-orange-600 dark:text-orange-400">{{ __('Streak Saat Ini') }}</div>
                        <div class="text-lg sm:text-xl font-black text-orange-950 dark:text-orange-200 font-mono flex items-center justify-center gap-1">
                            <span>🔥</span> {{ $user->current_streak }} {{ __('Hari') }}
                        </div>
                    </div>
                    <div class="p-3 rounded-2xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/40 text-center">
                        <div class="text-[10px] uppercase font-black tracking-wider text-emerald-600 dark:text-emerald-400">{{ __('Rekor Streak') }}</div>
                        <div class="text-lg sm:text-xl font-black text-emerald-950 dark:text-emerald-200 font-mono flex items-center justify-center gap-1">
                            <span>⚡</span> {{ $user->longest_streak }} {{ __('Hari') }}
                        </div>
                    </div>
                    <div class="p-3 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/40 text-center">
                        <div class="text-[10px] uppercase font-black tracking-wider text-amber-600 dark:text-amber-400">{{ __('Trofi Terbuka') }}</div>
                        <div class="text-lg sm:text-xl font-black text-amber-950 dark:text-amber-200 font-mono flex items-center justify-center gap-1">
                            <span>🏆</span> {{ $unlockedCount }} / {{ $totalBadges }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @php
            $publicGoals = $user->goals()->where('is_public', true)->get();
        @endphp

        @if($publicGoals->isNotEmpty())
            <!-- TARGET & GOALS SECTION (PUBLIC SHOWCASE) -->
            <div class="p-5 sm:p-6 rounded-3xl bg-gradient-to-br from-orange-500/10 via-amber-500/5 to-red-500/10 dark:from-orange-950/40 dark:via-gray-800 dark:to-red-950/30 border-2 border-orange-300/60 dark:border-orange-500/40 shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-orange-500 to-amber-500 text-white flex items-center justify-center font-black text-sm shadow-md">
                            🔥
                        </div>
                        <div>
                            <h2 class="text-base sm:text-lg font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                                <span>{{ __('Target & Goals Aktif') }}</span>
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Target komitmen yang sedang dikejar oleh :name', ['name' => $user->name]) }}
                            </p>
                        </div>
                    </div>
                    <div class="text-xs font-black text-orange-700 dark:text-orange-300 bg-orange-100 dark:bg-orange-900/60 px-3 py-1 rounded-xl border border-orange-300/50">
                        {{ $publicGoals->count() }} {{ __('Goal Dibagikan') }}
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
                    @foreach($publicGoals as $pGoal)
                        @php
                            $gPct = $pGoal->progress_percentage;
                            $gDone = $pGoal->status === 'completed' || $gPct >= 100;
                        @endphp
                        <div class="rounded-2xl p-4 transition-all relative flex flex-col justify-between min-h-[140px] bg-white dark:bg-gray-800 border {{ $gDone ? 'border-emerald-300 dark:border-emerald-700/60 shadow-md ring-2 ring-emerald-400/20' : 'border-gray-200 dark:border-gray-700 shadow-sm hover:border-orange-300' }}">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4 {{ $gDone ? 'text-emerald-500' : 'text-orange-500' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12.0002 2.5C12.0002 2.5 13.5002 6.5 15.5002 8.5C17.5002 10.5 19.5002 12.5 19.5002 15.5C19.5002 19.5 16.0002 22 12.0002 22C8.00024 22 4.50024 19.5 4.50024 15.5C4.50024 11.5 8.00024 7.5 12.0002 2.5Z" />
                                        </svg>
                                        <span class="text-[10px] font-black uppercase text-gray-400 dark:text-gray-500">GOAL</span>
                                    </div>
                                    @if($gDone)
                                        <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
                                            ✓ {{ __('Tuntas') }}
                                        </span>
                                    @endif
                                </div>

                                <h4 class="text-sm font-black text-gray-900 dark:text-white line-clamp-2 mb-1">
                                    {{ $pGoal->title }}
                                </h4>
                                @if($pGoal->description)
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 line-clamp-1 mb-2">
                                        {{ $pGoal->description }}
                                    </p>
                                @endif
                            </div>

                            <div class="pt-3 border-t border-gray-100 dark:border-gray-700/60 mt-auto">
                                <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                                    <span class="text-gray-500 dark:text-gray-400">
                                        {{ $pGoal->current_value }} / {{ $pGoal->target_value }} <span class="text-[10px] font-normal">{{ $pGoal->unit }}</span>
                                    </span>
                                    <span class="font-mono font-black {{ $gDone ? 'text-emerald-600 dark:text-emerald-400' : 'text-orange-600 dark:text-orange-400' }}">
                                        {{ $gPct }}%
                                    </span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden p-0.5">
                                    <div class="h-full rounded-full transition-all duration-700 {{ $gDone ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-amber-500 to-orange-500' }}" style="width: {{ $gPct }}%"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- RAK PAMER 3 SLOT LENCANA UTAMA (SHOWCASE RACK) -->
        <div class="p-5 sm:p-6 rounded-3xl bg-gradient-to-br from-amber-500/10 via-amber-400/5 to-cyan-500/10 dark:from-amber-950/40 dark:via-gray-800 dark:to-cyan-950/30 border-2 border-amber-300/60 dark:border-amber-500/40 shadow-lg">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center font-black text-sm shadow-md">
                        ⭐
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-gray-900 dark:text-white tracking-tight">
                            {{ __('Rak Pamer Lencana Unggulan') }}
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('3 lencana kebanggaan yang dipamerkan oleh :name', ['name' => $user->name]) }}
                        </p>
                    </div>
                </div>
                <div class="text-xs font-black text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/60 px-3 py-1 rounded-xl border border-amber-300/50">
                    {{ count($equippedBadgesDetails) }} / 3 {{ __('Slot Terisi') }}
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
                @for($slot = 1; $slot <= 3; $slot++)
                    @php
                        $badge = $equippedBadgesDetails[$slot - 1] ?? null;
                    @endphp
                    <div class="rounded-2xl p-4 transition-all relative flex flex-col justify-between min-h-[160px] {{ $badge ? 'bg-white dark:bg-gray-800 border-2 border-amber-400/70 dark:border-amber-400/60 shadow-md ring-2 ring-amber-400/20' : 'bg-white/50 dark:bg-gray-800/40 border-2 border-dashed border-gray-300 dark:border-gray-700 items-center justify-center text-center' }}">
                        @if($badge)
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-gradient-to-r from-amber-400 to-amber-500 text-yellow-950 font-mono shadow-xs">
                                        {{ __('Slot') }} #{{ $slot }}
                                    </span>
                                    <span class="text-[10px] font-bold text-gray-400">
                                        {{ __($badge['category_name'] ?? 'Lencana') }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3.5 mt-2">
                                    <div class="w-14 h-14 shrink-0 p-1 rounded-2xl bg-gray-50 dark:bg-gray-700/60 border border-gray-150 dark:border-gray-700 shadow-sm flex items-center justify-center">
                                        @if(!empty($badge['svg_path']))
                                            <img src="{{ asset($badge['svg_path']) }}" alt="{{ $badge['title'] }}" class="w-full h-full object-contain">
                                        @else
                                            <span class="text-2xl">{{ $badge['icon'] ?? '🏆' }}</span>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-sm font-black text-gray-900 dark:text-white truncate">
                                            {{ $badge['title'] }}
                                        </h3>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-snug mt-0.5 line-clamp-2">
                                            {{ $badge['description'] }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 pt-2 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between text-[10px] font-bold text-gray-400">
                                <span class="text-amber-600 dark:text-amber-400 font-black">+{{ $badge['bonus_xp'] ?? 0 }} XP</span>
                                <span>⭐ {{ __('Terpasang di Profil') }}</span>
                            </div>
                        @else
                            <div class="py-4 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                                <div class="w-12 h-12 rounded-full border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-xl mb-2">
                                    ＋
                                </div>
                                <div class="text-xs font-black text-gray-600 dark:text-gray-400">{{ __('Slot #:slot Kosong', ['slot' => $slot]) }}</div>
                                <div class="text-[10px] text-gray-400 mt-0.5">
                                    @if(auth()->id() === $user->id)
                                        <button @click="$dispatch('open-achievements')" class="text-atoma-blue dark:text-atoma-cyan underline font-bold cursor-pointer">
                                            {{ __('Pasang Lencana Sekarang') }}
                                        </button>
                                    @else
                                        {{ __('Belum ada lencana dipasang') }}
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endfor
            </div>
        </div>

        <!-- 2 COLUMNS: KASTA LIGA & MONOGRAM PRESTISE + STATISTIK RPG -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- Kasta Liga & Monogram Prestise -->
            <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-md space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm sm:text-base font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span>🛡️</span> {{ __('Liga & Monogram Prestise') }}
                    </h2>
                    <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                        {{ __('Tier Karakter') }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <!-- Liga Crest -->
                    @php
                        $leagueKey = strtolower($user->league ?? 'bronze');
                        $leagueSvg = match($leagueKey) {
                            'silver' => 'images/badges/league/league_silver.svg',
                            'gold' => 'images/badges/league/league_gold.svg',
                            'diamond' => 'images/badges/league/league_diamond.svg',
                            'master' => 'images/badges/league/league_master.svg',
                            default => 'images/badges/league/league_bronze.svg',
                        };
                    @endphp
                    <div class="p-4 rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-750 dark:to-gray-800 border border-blue-150 dark:border-gray-700 flex flex-col items-center text-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 mb-2 filter drop-shadow-md">
                            <img src="{{ asset($leagueSvg) }}" alt="{{ __('Liga') }} {{ ucfirst(__($leagueKey)) }}" class="w-full h-full object-contain">
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-wider text-blue-600 dark:text-blue-400">{{ __('Liga Mingguan') }}</div>
                        <div class="text-sm font-black text-gray-900 dark:text-white capitalize">{{ __('Liga') }} {{ ucfirst(__($leagueKey)) }}</div>
                        <div class="text-[10px] text-gray-400 mt-0.5">{{ __('Perisai Kasta Kejuaraan') }}</div>
                    </div>

                    <!-- Prestige Monogram -->
                    @php
                        $pTier = max(1, min(5, (int) ($user->prestige_tier ?? 1)));
                        $prestigeSvg = "images/badges/prestige/prestige_tier_{$pTier}.svg";
                    @endphp
                    <div class="p-4 rounded-2xl bg-gradient-to-br from-amber-50 to-yellow-50 dark:from-gray-750 dark:to-gray-800 border border-amber-150 dark:border-gray-700 flex flex-col items-center text-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 mb-2 filter drop-shadow-md">
                            <img src="{{ asset($prestigeSvg) }}" alt="Prestige Tier {{ $pTier }}" class="w-full h-full object-contain">
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">{{ __('Monogram Prestise') }}</div>
                        <div class="text-sm font-black text-gray-900 dark:text-white">{{ $user->prestige_title }}</div>
                        <div class="text-[10px] text-gray-400 mt-0.5">{{ __('Tier :tier Masteri ATOMA', ['tier' => $pTier]) }}</div>
                    </div>
                </div>
            </div>

            <!-- RPG Character Stats -->
            <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-md space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm sm:text-base font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span>📊</span> {{ __('Atribut Karakter RPG') }}
                    </h2>
                    <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400">
                        {{ __('Profil Kemampuan') }}
                    </span>
                </div>

                <div class="space-y-3">
                    <!-- Focus -->
                    <div>
                        <div class="flex justify-between text-xs font-black mb-1">
                            <span class="text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
                                <span>🎯</span> {{ __('Fokus (Focus & Flow)') }}
                            </span>
                            <span class="font-mono text-gray-900 dark:text-white">{{ $rpgStats['focus'] ?? 0 }} pts</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-rose-500 to-pink-500 rounded-full" 
                                 style="width: {{ min(100, max(10, ($rpgStats['focus'] ?? 0) * 5)) }}%"></div>
                        </div>
                    </div>

                    <!-- Consistency -->
                    <div>
                        <div class="flex justify-between text-xs font-black mb-1">
                            <span class="text-orange-600 dark:text-orange-400 flex items-center gap-1.5">
                                <span>🔥</span> {{ __('Konsistensi (Streak)') }}
                            </span>
                            <span class="font-mono text-gray-900 dark:text-white">{{ $rpgStats['consistency'] ?? 0 }} pts</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-orange-500 to-amber-500 rounded-full" 
                                 style="width: {{ min(100, max(10, ($rpgStats['consistency'] ?? 0) * 5)) }}%"></div>
                        </div>
                    </div>

                    <!-- Execution -->
                    <div>
                        <div class="flex justify-between text-xs font-black mb-1">
                            <span class="text-blue-600 dark:text-blue-400 flex items-center gap-1.5">
                                <span>⚡</span> {{ __('Eksekusi (Tugas Selesai)') }}
                            </span>
                            <span class="font-mono text-gray-900 dark:text-white">{{ $rpgStats['execution'] ?? 0 }} pts</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-blue-500 to-cyan-400 rounded-full" 
                                 style="width: {{ min(100, max(10, ($rpgStats['execution'] ?? 0) * 5)) }}%"></div>
                        </div>
                    </div>

                    <!-- Organization -->
                    <div>
                        <div class="flex justify-between text-xs font-black mb-1">
                            <span class="text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                <span>📂</span> {{ __('Organisasi (Habit & Jurnal)') }}
                            </span>
                            <span class="font-mono text-gray-900 dark:text-white">{{ $rpgStats['organization'] ?? 0 }} pts</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full" 
                                 style="width: {{ min(100, max(10, ($rpgStats['organization'] ?? 0) * 5)) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ETALASE TROFI & PENCAPAIAN LENGKAP (TROPHY CABINET) -->
        <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-md space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-atoma-blue to-cyan-400 p-0.5 shadow-sm flex items-center justify-center text-white text-lg font-black">
                        🏆
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-gray-900 dark:text-white tracking-tight">
                            {{ __('Etalase Trofi & Koleksi Medali') }}
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Total koleksi pencapaian produktivitas yang diraih oleh :name', ['name' => $user->name]) }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <div class="px-3 py-1.5 rounded-xl bg-gray-50 dark:bg-gray-750 border border-gray-200 dark:border-gray-700 text-center">
                        <div class="text-[9px] uppercase font-black text-gray-400">{{ __('Terbuka') }}</div>
                        <div class="text-xs font-black text-atoma-blue dark:text-cyan-400">
                            {{ $unlockedCount }} / {{ $totalBadges }} ({{ $unlockedPercentage }}%)
                        </div>
                    </div>
                    <div class="px-3 py-1.5 rounded-xl bg-gray-50 dark:bg-gray-750 border border-gray-200 dark:border-gray-700 text-center">
                        <div class="text-[9px] uppercase font-black text-gray-400">{{ __('XP Medali') }}</div>
                        <div class="text-xs font-black text-amber-500">
                            +{{ number_format($totalXpFromAchievements) }} XP
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories Tabs Filter -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-xs font-black">
                @php
                    $categories = [
                        'all' => '🌟 ' . __('Semua'),
                        'unlocked' => '🔓 ' . __('Terbuka'),
                        'milestone' => '🎯 ' . __('Milestone >80%'),
                        'streak' => '🔥 Streak',
                        'tasks' => '⚡ ' . __('Tugas'),
                        'focus' => '🍅 ' . __('Fokus'),
                        'social' => '🤝 ' . __('Sosial'),
                        'reflection' => '✍️ ' . __('Refleksi'),
                        'workout' => '🏋️ Workout',
                    ];
                @endphp
                @foreach($categories as $catKey => $catLabel)
                    <button wire:click="setCategory('{{ $catKey }}')"
                            class="px-3 py-1.5 rounded-xl transition-all whitespace-nowrap cursor-pointer {{ $activeCategory === $catKey ? 'bg-atoma-blue text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}">
                        {{ $catLabel }}
                    </button>
                @endforeach
            </div>

            <!-- Grid of Badges -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-2">
                @php
                    $filtered = collect($allAchievements)->filter(function ($b) use ($activeCategory) {
                        if ($activeCategory === 'all') return true;
                        if ($activeCategory === 'unlocked') return $b['unlocked'] === true;
                        return ($b['category'] ?? '') === $activeCategory;
                    });
                @endphp

                @forelse($filtered as $item)
                    <div class="p-3.5 rounded-2xl border transition-all flex flex-col justify-between relative {{ $item['unlocked'] ? ($item['is_equipped'] ? 'bg-gradient-to-br from-amber-50 via-white to-amber-100/40 dark:from-amber-950/40 dark:via-gray-800 dark:to-gray-900 border-amber-400/70 shadow-sm ring-1 ring-amber-400/40' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 shadow-xs') : 'bg-gray-50/60 dark:bg-gray-850/40 border-dashed border-gray-200 dark:border-gray-750 opacity-60' }}">
                        
                        <div class="flex items-start gap-3">
                            <div class="w-12 h-12 shrink-0 p-1 rounded-2xl bg-gray-50 dark:bg-gray-700/80 border border-gray-100 dark:border-gray-700 flex items-center justify-center {{ !$item['unlocked'] ? 'grayscale contrast-50' : '' }}">
                                @if(!empty($item['svg_path']))
                                    <img src="{{ asset($item['svg_path']) }}" alt="{{ $item['title'] }}" class="w-full h-full object-contain">
                                @else
                                    <span class="text-2xl">{{ $item['icon'] ?? '🏆' }}</span>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1">
                                    <h4 class="text-xs font-black truncate text-gray-900 dark:text-white">
                                        {{ $item['title'] }}
                                    </h4>
                                    @if($item['is_equipped'])
                                        <span class="text-[9px] font-black uppercase tracking-wider text-amber-600 dark:text-amber-300 bg-amber-100 dark:bg-amber-950/70 px-1.5 py-0.5 rounded border border-amber-300/40">
                                            {{ __('Slot') }} #{{ $item['equipped_slot'] }}
                                        </span>
                                    @elseif($item['unlocked'])
                                        <span class="text-[9px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-950/70 px-1.5 py-0.5 rounded">
                                            {{ __('Terbuka') }}
                                        </span>
                                    @else
                                        <span class="text-[9px] font-black uppercase tracking-wider text-gray-400 bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">
                                            🔒
                                        </span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 leading-snug line-clamp-2">
                                    {{ $item['description'] }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-2.5 pt-2 border-t border-gray-100 dark:border-gray-750 flex items-center justify-between text-[10px]">
                            <span class="font-bold text-atoma-blue dark:text-cyan-400">
                                +{{ $item['bonus_xp'] }} XP
                            </span>
                            @if($item['unlocked'] && !empty($item['unlocked_at']))
                                <span class="text-gray-400">
                                    {{ \Carbon\Carbon::parse($item['unlocked_at'])->format('d M Y') }}
                                </span>
                            @elseif(!$item['unlocked'])
                                <span class="text-gray-400">
                                    {{ __('Progres') }}: {{ $item['progress_percent'] }}%
                                </span>
                            @endif
                        </div>

                    </div>
                @empty
                    <div class="col-span-full py-12 text-center text-gray-400">
                        <div class="text-2xl mb-1">🔍</div>
                        <p class="text-xs">{{ __('Tidak ada lencana pada kategori ini.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
