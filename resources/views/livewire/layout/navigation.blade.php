<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    
    public $user;
    
    public function mount()
    {
        $this->user = auth()->user();
    }
    
    public function hasUnfinishedTasksToday()
    {
        if (!$this->user) return false;
        
        return $this->user->tasks()
            ->whereDate('date', \Carbon\Carbon::today())
            ->where('is_completed', false)
            ->exists();
    }
    
    #[On('profile-updated')]
    public function refreshProfile()
    {
        $this->user->refresh();
    }
    
    #[On('sync-timezone')]
    public function syncTimezone($tz)
    {
        if ($this->user && ($this->user->timezone === null || $this->user->timezone !== $tz)) {
            $this->user->update(['timezone' => $tz]);
            session()->put('auto_timezone', $tz);
            date_default_timezone_set($tz);
            \Illuminate\Support\Facades\Config::set('app.timezone', $tz);
        }
    }
    
    #[On('logout-action')]
    public function logout(Logout $logout)
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<nav class="h-full flex flex-col md:py-4">
    <!-- Desktop Brand, Notification, & Close Sidebar Button -->
    <div class="hidden md:flex items-center justify-between px-5 mb-4 w-full">
        <a href="{{ route('dashboard') }}" wire:navigate.hover class="flex items-center space-x-2.5 group shrink-0">
            <img src="/images/atoma_icon.png" alt="ATOMA Icon" class="h-8 w-auto object-contain drop-shadow-sm group-hover:scale-105 transition-transform">
            <span class="text-xl font-black tracking-wider bg-gradient-to-r from-atoma-blue to-atoma-cyan bg-clip-text text-transparent uppercase">
                ATOMA
            </span>
        </a>
        <div class="flex items-center space-x-1.5 shrink-0">
            <!-- Surat ke Dev (Matching Bell size & style) -->
            <button @click="$dispatch('open-feedback-modal')" 
                    class="w-8 h-8 rounded-xl bg-gray-100/90 hover:bg-indigo-50/80 dark:bg-gray-700/60 dark:hover:bg-indigo-950/40 border border-gray-200/80 dark:border-gray-600/60 flex items-center justify-center text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 transition-all shadow-sm cursor-pointer select-none active:scale-95 shrink-0" 
                    title="{{ __('Surat & Masukan ke Developer') }}">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
            </button>
            <livewire:notification-bell align="left" />
            <!-- Close Sidebar Button -->
            <button @click="$dispatch('toggle-sidebar')" 
                    class="w-8 h-8 rounded-xl bg-gray-100/90 hover:bg-indigo-50/80 dark:bg-gray-700/60 dark:hover:bg-indigo-950/40 border border-gray-200/80 dark:border-gray-600/60 flex items-center justify-center text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 transition-all shadow-sm cursor-pointer select-none active:scale-95 shrink-0" 
                    title="{{ __('Tutup / Sembunyikan Sidebar') }}">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Desktop Header (Profile & Streak - 2 Cards Layout) -->
    <div class="hidden md:flex flex-col items-center px-5 mb-5 select-none">
        @php
            $navActiveFrame = $user->active_frame ?? null;
            $navFrameClass = 'border-4 border-atoma-aqua/40 dark:border-atoma-blue/40 shadow-sm hover:border-atoma-cyan';
            if ($navActiveFrame === 'frame_neon') {
                $navFrameClass = 'border-4 border-cyan-400 ring-2 ring-cyan-400/50 shadow-[0_0_15px_rgba(6,182,212,0.8)]';
            } elseif ($navActiveFrame === 'frame_fire') {
                $navFrameClass = 'border-4 border-orange-500 ring-2 ring-amber-500/50 shadow-[0_0_15px_rgba(249,115,22,0.8)] animate-pulse';
            } elseif ($navActiveFrame === 'frame_gold') {
                $navFrameClass = 'border-4 border-amber-400 ring-2 ring-yellow-300/80 shadow-[0_0_18px_rgba(245,158,11,0.8)]';
            }
        @endphp
        <div class="relative mb-2.5">
            @if($navActiveFrame === 'frame_gold')
                <div class="absolute -top-5 left-1/2 -translate-x-1/2 w-12 z-20 pointer-events-none drop-shadow-md">
                    <img src="{{ asset('images/frames/crown_gold.svg') }}" alt="Crown" class="w-full h-auto object-contain">
                </div>
            @endif
            <a href="{{ route('profile') }}" wire:navigate.hover class="h-20 w-20 rounded-full {{ $navFrameClass }} flex items-center justify-center text-atoma-blue text-2xl font-bold bg-white overflow-hidden transition-colors cursor-pointer group block">
                <img src="{{ $user->avatar_url }}" alt="Avatar" class="w-full h-full object-cover group-hover:scale-105 transition-transform">
            </a>
            @if($user->equipped_badge_details)
                <div class="absolute bottom-0 right-0 w-6 h-6 rounded-full bg-white dark:bg-gray-900 p-1 shadow-sm border border-gray-200 dark:border-gray-700 flex items-center justify-center z-10">
                    <img src="{{ asset($user->equipped_badge_details['svg_path']) }}" class="w-full h-full object-contain">
                </div>
            @endif
        </div>
        <div class="text-base font-black text-gray-900 dark:text-gray-100 text-center tracking-tight" x-data="{ name: '{{ $user->name }}' }" x-text="name"></div>
        
        <!-- 2 Cards Below Profile: Day Streak & Level Progress -->
        <div class="mt-3.5 grid grid-cols-2 gap-2.5 w-full">
            <button type="button" 
                    @click="$dispatch('open-streak-modal')" 
                    class="bg-orange-50 hover:bg-orange-100/80 dark:bg-gray-700/50 dark:hover:bg-gray-600/50 rounded-2xl p-3 text-center border border-orange-100 dark:border-gray-600 transition-all cursor-pointer group relative overflow-hidden flex flex-col items-center justify-center shadow-2xs hover:scale-[1.02] active:scale-95"
                    title="{{ __('Lihat Riwayat Streak (:count hari)', ['count' => $user->current_streak]) }}">
                @if($user->current_streak > 0)
                    <div class="absolute inset-0 bg-orange-400/10 blur-xl group-hover:bg-orange-400/20 transition-colors pointer-events-none"></div>
                @endif
                <div class="text-xl font-black text-orange-500 flex items-center justify-center relative z-10 gap-1">
                    <svg class="w-5 h-5 {{ $user->current_streak > 0 ? 'animate-pulse drop-shadow-xs' : '' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"></path></svg>
                    <span>{{ $user->current_streak }}</span>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 font-bold mt-1 relative z-10">{{ __('Day Streak') }}</div>
            </button>
            @php
                $prestigeTier = $user->prestige_tier_details;
                $levelProg = $user->level_progress;
            @endphp
            <a href="{{ route('prestige') }}" wire:navigate.hover 
               class="bg-atoma-ice/80 hover:bg-atoma-ice dark:bg-gray-700/50 dark:hover:bg-gray-700/80 rounded-2xl p-2.5 text-center border border-atoma-aqua/40 dark:border-gray-600 hover:border-atoma-blue/60 dark:hover:border-atoma-cyan/60 transition-all cursor-pointer group relative overflow-hidden shadow-2xs block select-none hover:scale-[1.02] active:scale-95" 
               title="{{ number_format($levelProg['current_xp']) }} / {{ number_format($levelProg['required_xp']) }} XP • {{ __('Klik untuk melihat Sistem Level & Prestige') }}">
                <div class="absolute inset-y-0 left-0 bg-gradient-to-r {{ $prestigeTier['gradient'] }} opacity-25 group-hover:opacity-35 transition-all duration-1000" style="width: {{ $levelProg['percentage'] }}%"></div>
                <div class="relative z-10 flex flex-col items-center justify-center">
                    <div class="text-atoma-blue dark:text-atoma-cyan font-bold text-base flex items-center justify-center gap-1 group-hover:scale-105 transition-transform">
                        <span>{{ $prestigeTier['icon'] }}</span>
                        <span>Lv.{{ $user->prestige_level ?? 1 }}</span>
                    </div>
                    <div class="text-[10px] text-gray-500 dark:text-gray-400 font-semibold truncate mt-0.5">
                        {{ $prestigeTier['name'] }} &bull; {{ $levelProg['percentage'] }}%
                    </div>
                    <div x-data="{
                        remaining: '',
                        calc() {
                            const now = new Date();
                            const midnight = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 0, 0, 0);
                            const diff = Math.max(0, Math.floor((midnight - now) / 1000));
                            const h = Math.floor(diff / 3600);
                            const m = Math.floor((diff % 3600) / 60);
                            this.remaining = h > 0 ? `${h}j ${m}m` : `${m}m`;
                        },
                        init() {
                            this.calc();
                            setInterval(() => this.calc(), 60000);
                        }
                    }" class="mt-1.5 pt-1 border-t border-atoma-aqua/20 dark:border-gray-600/40 flex items-center justify-between text-[9px] font-medium text-gray-500 dark:text-gray-400 px-0.5 w-full">
                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">+{{ (int)($user->pending_xp ?? 0) }} XP</span>
                        <span class="text-gray-400 dark:text-gray-500 flex items-center gap-0.5">
                            <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span x-text="remaining">~</span>
                        </span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Weekly League Badge & Rank Roadmap Trigger -->
        @php
            $navLeague = \App\Services\LeagueService::getLeagueDetails($user->league ?? 'bronze_3');
        @endphp
        <button type="button" 
                @click="$dispatch('open-rank-modal')" 
                class="mt-2.5 w-full bg-gradient-to-r from-atoma-ice/60 to-indigo-50/50 dark:from-gray-700/40 dark:to-indigo-950/30 hover:from-atoma-ice hover:to-indigo-100/60 dark:hover:from-gray-700/70 dark:hover:to-indigo-900/40 rounded-2xl px-3 py-1.5 border border-atoma-aqua/40 dark:border-gray-600 flex items-center justify-between transition-all cursor-pointer group shadow-2xs select-none"
                title="{{ __('Bocoran Semua Rank & Liga ATOMA') }}">
            <div class="flex items-center gap-2">
                <x-tier-badge league="{{ $navLeague['key'] }}" size="sm" class="group-hover:scale-110 transition-transform" />
                <div class="text-left">
                    <div class="text-[9px] font-black uppercase text-atoma-blue dark:text-indigo-300 tracking-wider leading-none">{{ __('Liga Mingguan') }}</div>
                    <div class="text-xs font-black text-gray-900 dark:text-white flex items-center gap-1 mt-0.5">
                        <span>{{ $navLeague['name'] }}</span>
                        <span>{{ $navLeague['icon'] }}</span>
                    </div>
                </div>
            </div>
            <span class="text-[10px] font-extrabold text-atoma-blue dark:text-atoma-cyan group-hover:translate-x-0.5 transition-transform flex items-center gap-0.5">
                <span>Rank</span> &rarr;
            </span>
        </button>
    </div>

    <!-- Navigation Links -->
    <div class="flex-1 px-1.5 sm:px-2 md:px-5 flex md:flex-col justify-around md:justify-start space-x-0.5 sm:space-x-1 md:space-x-0 md:space-y-1 h-16 md:h-auto items-center md:items-stretch overflow-y-hidden md:overflow-y-auto">
        <!-- Main Navigation Section Header -->
        <div class="hidden md:block px-3 pt-1 pb-1 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 select-none">
            {{ __('Menu Utama') }}
        </div>

        <!-- Dashboard / Tasks -->
        <a href="{{ route('dashboard') }}" wire:navigate.hover class="flex flex-col md:flex-row items-center p-1.5 sm:p-2 md:px-3 md:py-2.5 rounded-xl {{ request()->routeIs('dashboard') ? 'bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan font-bold border border-atoma-aqua/50 dark:border-atoma-cyan/30 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/50 hover:text-atoma-blue dark:hover:text-atoma-cyan' }} transition-colors group flex-1 md:flex-none justify-center md:justify-start relative select-none">
            <div class="relative md:mr-3 shrink-0">
                <svg class="w-5 h-5 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
            </div>
            <span class="text-[10px] sm:text-xs md:text-sm mt-0.5 sm:mt-1 md:mt-0 font-medium truncate">{{ __('Tasks') }}</span>
            @if($this->hasUnfinishedTasksToday())
                <span class="absolute top-1 right-3 md:top-auto md:right-3 flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500 border-2 border-white dark:border-gray-800"></span>
                </span>
            @endif
        </a>

        <!-- Note (renamed from Journal) -->
        <a href="{{ route('journal') }}" wire:navigate.hover class="flex flex-col md:flex-row items-center p-1.5 sm:p-2 md:px-3 md:py-2.5 rounded-xl {{ request()->routeIs('journal') ? 'bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan font-bold border border-atoma-aqua/50 dark:border-atoma-cyan/30 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/50 hover:text-atoma-blue dark:hover:text-atoma-cyan' }} transition-colors group flex-1 md:flex-none justify-center md:justify-start select-none">
            <svg class="w-5 h-5 md:mr-3 shrink-0 group-hover:scale-105 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <span class="text-[10px] sm:text-xs md:text-sm mt-0.5 sm:mt-1 md:mt-0 font-medium truncate">{{ __('Note') }}</span>
        </a>

        <!-- Mobile Center Dynamic Add Button -->
        <div x-data="{
            currentPath: window.location.pathname,
            journalTab: window.__atomaJournalTab || 'journal',
            init() {
                this.currentPath = window.location.pathname;
                this.syncJournalTab();
                window.addEventListener('livewire:navigated', () => {
                    this.currentPath = window.location.pathname;
                    this.syncJournalTab();
                });
                window.addEventListener('popstate', () => {
                    this.currentPath = window.location.pathname;
                    this.syncJournalTab();
                });
                window.addEventListener('journal-tab-changed', (e) => {
                    this.journalTab = e.detail?.tab || e.detail || 'journal';
                });
            },
            syncJournalTab() {
                if (window.__atomaJournalTab) {
                    this.journalTab = window.__atomaJournalTab;
                }
            },
            get action() {
                if (this.currentPath.includes('/journal') || this.currentPath.includes('/note')) {
                    if (this.journalTab === 'finance') {
                        return {
                            event: 'open-add-finance-modal',
                            title: '{{ __('Catat Keuangan') }}',
                            icon: 'finance'
                        };
                    }
                    return {
                        event: 'open-add-journal-modal',
                        title: '{{ __('Tulis Catatan Baru') }}',
                        icon: 'note'
                    };
                }
                if (this.currentPath.includes('/social')) {
                    return {
                        event: 'open-create-feed-modal',
                        title: '{{ __('Buat Diskusi / Topik') }}',
                        icon: 'social'
                    };
                }
                if (this.currentPath.includes('/flashcard')) {
                    return {
                        event: 'open-create-flashcard-modal',
                        title: '{{ __('Tambah Flashcard') }}',
                        icon: 'flashcard'
                    };
                }
                return {
                    event: 'open-add-task-modal',
                    title: '{{ __('Tambah Tugas Baru') }}',
                    icon: 'task'
                };
            },
            triggerAction() {
                $dispatch(this.action.event);
            }
        }" x-show="!$store.taskConfig || $store.taskConfig.mobileFab !== 'floating'" class="md:hidden flex items-center justify-center -mt-5 shrink-0 px-1">
            <button @click="triggerAction()" 
                    class="w-11 h-11 rounded-full active:scale-95 text-white shadow-lg flex items-center justify-center font-black border-2 border-white dark:border-gray-800 cursor-pointer hover:scale-105 transition-all"
                    :class="action.icon === 'finance' ? 'bg-emerald-600 hover:bg-emerald-500 shadow-emerald-600/40' : 'atoma-gradient shadow-atoma-blue/40'"
                    :title="action.title"
                    :aria-label="action.title">
                <template x-if="action.icon === 'task' || action.icon === 'finance'">
                    <span class="text-xl font-black leading-none">+</span>
                </template>
                <template x-if="action.icon === 'note'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </template>
                <template x-if="action.icon === 'social'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </template>
                <template x-if="action.icon === 'flashcard'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </template>
            </button>
        </div>

        <!-- Leaderboard / Rank -->
        <a href="{{ route('leaderboard') }}" wire:navigate.hover class="flex flex-col md:flex-row items-center p-1.5 sm:p-2 md:px-3 md:py-2.5 rounded-xl {{ request()->routeIs('leaderboard') ? 'bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan font-bold border border-atoma-aqua/50 dark:border-atoma-cyan/30 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/50 hover:text-atoma-blue dark:hover:text-atoma-cyan' }} transition-colors group flex-1 md:flex-none justify-center md:justify-start select-none">
            <svg class="w-5 h-5 md:mr-3 shrink-0 group-hover:scale-105 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.504-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.003 0H9.497m5.003 0A5.25 5.25 0 0017.25 9V4.5H6.75V9a5.25 5.25 0 002.747 4.625M17.25 4.5h2.25a2.25 2.25 0 012.25 2.25v.75a3.75 3.75 0 01-3.75 3.75h-.75M6.75 4.5H4.5A2.25 2.25 0 002.25 6.75v.75a3.75 3.75 0 003.75 3.75h.75"></path>
            </svg>
            <span class="text-[10px] sm:text-xs md:text-sm mt-0.5 sm:mt-1 md:mt-0 font-medium truncate">{{ __('Rank') }}</span>
        </a>

        <!-- Social / Sosial -->
        <a href="{{ route('social') }}" wire:navigate.hover class="flex flex-col md:flex-row items-center p-1.5 sm:p-2 md:px-3 md:py-2.5 rounded-xl {{ request()->routeIs('social') ? 'bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan font-bold border border-atoma-aqua/50 dark:border-atoma-cyan/30 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/50 hover:text-atoma-blue dark:hover:text-atoma-cyan' }} transition-colors group flex-1 md:flex-none justify-center md:justify-start relative select-none">
            <svg class="w-5 h-5 md:mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            <span class="text-[10px] sm:text-xs md:text-sm mt-0.5 sm:mt-1 md:mt-0 font-medium truncate">{{ __('Sosial') }}</span>
        </a>

        <!-- Store / Toko Hadiah (Desktop Sidebar) -->
        <a href="{{ route('store') }}" wire:navigate.hover class="hidden md:flex items-center px-3 py-2.5 rounded-xl {{ request()->routeIs('store') ? 'bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan font-bold border border-atoma-aqua/50 dark:border-atoma-cyan/30 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/50 hover:text-atoma-blue dark:hover:text-atoma-cyan' }} transition-colors group font-medium text-xs sm:text-sm select-none">
            <svg class="w-5 h-5 mr-3 shrink-0 group-hover:scale-105 transition-transform text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
            </svg>
            <span class="flex-1">{{ __('Store') }}</span>
            <span class="inline-flex items-center gap-1 text-[10px] font-black text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 px-2 py-0.5 rounded-full border border-amber-200/60 dark:border-amber-800/60">
                <span>🪙</span>
                <span>{{ number_format($user->coins ?? 0) }}</span>
            </span>
        </a>

        <!-- Desktop Tools & Guidance Group -->
        <div class="hidden md:block pt-4 pb-1 px-3 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 select-none">
            {{ __('Fokus & Bantuan') }}
        </div>

        <!-- Pomodoro Timer Toggle -->
        <button @click="$dispatch('toggle-pomodoro')" class="hidden md:flex items-center px-3 py-2 rounded-xl text-gray-600 dark:text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50/70 dark:hover:bg-rose-950/30 transition-colors cursor-pointer group font-medium text-xs sm:text-sm w-full select-none">
            <svg class="w-5 h-5 mr-3 shrink-0 group-hover:scale-110 transition-transform text-rose-500/80 group-hover:text-rose-600 dark:group-hover:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 2h6m-3 0v2"></path>
            </svg>
            <span class="flex-1 text-left">{{ __('Pomodoro Timer') }}</span>
            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">{{ __('Fokus') }}</span>
        </button>

        <!-- Guide / Panduan Fitur -->
        <a href="{{ route('guide') }}" wire:navigate.hover class="hidden md:flex items-center px-3 py-2 rounded-xl {{ request()->routeIs('guide') ? 'bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan font-bold border border-atoma-aqua/50 dark:border-atoma-cyan/30 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50/70 dark:hover:bg-indigo-950/30' }} transition-colors group font-medium text-xs sm:text-sm select-none">
            <svg class="w-5 h-5 mr-3 shrink-0 group-hover:scale-110 transition-transform text-indigo-500/80 group-hover:text-indigo-600 dark:group-hover:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <span>{{ __('Panduan Fitur') }}</span>
        </a>

        @if($user && ($user->is_admin || $user->id === 1))
            <!-- Admin Command Center Link -->
            <a href="{{ route('admin.dashboard') }}" wire:navigate.hover class="hidden md:flex items-center px-3 py-2 rounded-xl {{ request()->routeIs('admin.*') ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 font-bold border border-indigo-200 dark:border-indigo-800 shadow-2xs' : 'text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50/70 dark:hover:bg-indigo-950/30' }} transition-colors group font-medium text-xs sm:text-sm select-none">
                <span class="w-5 text-center mr-3 shrink-0 text-sm group-hover:scale-110 transition-transform">👑</span>
                <span class="flex-1">{{ __('Admin Center') }}</span>
                <span class="text-[9px] font-black uppercase px-1.5 py-0.2 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">ADMIN</span>
            </a>
        @endif
    </div>

    <!-- Desktop Footer: Settings, Theme, Terms, & Logout (Clean, Compact, & Uncluttered) -->
    <div class="hidden md:flex flex-col px-5 pt-3 pb-2 mt-auto border-t border-gray-100 dark:border-gray-700/60 select-none">
        <div class="flex items-center justify-between">
            <!-- Settings Link -->
            <a href="{{ route('profile') }}" wire:navigate.hover class="flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 font-semibold text-xs sm:text-sm transition-colors py-1">
                <x-icon.gear class="w-4 h-4 text-gray-400" />
                <span>{{ __('Settings') }}</span>
            </a>

            <!-- Quick Action Icons: Theme Toggle & Logout -->
            <div class="flex items-center gap-1">
                <!-- Theme Toggle -->
                <button x-data="{ isDark: document.documentElement.classList.contains('dark') }"
                        @dark-mode-toggled.window="isDark = document.documentElement.classList.contains('dark')"
                        @click="window.toggleDarkMode()" 
                        class="w-7 h-7 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 transition-colors cursor-pointer select-none"
                        title="{{ __('Ganti Mode Gelap / Terang') }}">
                    <svg x-show="!isDark" class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                    <svg x-show="isDark" x-cloak class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </button>

                <!-- Logout -->
                <button wire:click="logout" 
                        class="w-7 h-7 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/40 flex items-center justify-center text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors cursor-pointer select-none"
                        title="{{ __('Keluar Akun') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </button>
            </div>
        </div>

        <!-- Subtle Terms & Privacy Link at Bottom -->
        <div class="mt-2 pt-2 border-t border-gray-100/70 dark:border-gray-750 flex items-center justify-between text-[11px] text-gray-400 dark:text-gray-500">
            <a href="{{ route('terms') }}" wire:navigate.hover class="hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                {{ __('Syarat & Privasi') }}
            </a>
            <span class="text-[10px] text-gray-300 dark:text-gray-600 font-medium">ATOMA</span>
        </div>
    </div>
</nav>
