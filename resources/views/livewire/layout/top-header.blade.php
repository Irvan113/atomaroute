<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public $user;
    
    public function mount()
    {
        $this->user = auth()->user();
        if ($this->user) {
            $userTz = $this->user->timezone ?? config('app.timezone', 'Asia/Jakarta');
            $today = \Carbon\Carbon::today($userTz)->toDateString();
            $lastSettlement = $this->user->last_settlement_date 
                ? ($this->user->last_settlement_date instanceof \Carbon\Carbon 
                    ? $this->user->last_settlement_date->toDateString() 
                    : \Carbon\Carbon::parse($this->user->last_settlement_date)->toDateString())
                : null;

            if ($lastSettlement !== $today && (int) $this->user->pending_xp > 0) {
                $settlement = $this->user->processDailySettlement();
                if (!empty($settlement['settled'])) {
                    $this->dispatch('profile-updated');
                    $this->dispatch('toast', message: "✨ Settlement Harian Selesai: +{$settlement['xp']} XP & +{$settlement['coins']} Koin!", type: 'success');
                }
            }

            if (session('secret_protection_activated')) {
                $this->dispatch('toast', message: "🛡️ Secret Protection Aktif! Dedikasi belajarmu menyelamatkan streak 1x bulan ini.", type: 'info');
            }
        }
    }
    
    #[On('profile-updated')]
    #[On('streak-updated')]
    public function refreshProfile()
    {
        $this->user->refresh();
    }
}; ?>

<header class="bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border-b border-gray-200/80 dark:border-gray-700/80 px-2.5 sm:px-4 md:px-6 py-2 flex items-center justify-between md:justify-center sticky top-0 z-10 shrink-0 shadow-sm relative">
    <!-- Desktop Left Actions (Toggle Sidebar & Command Palette Button) -->
    <div class="hidden md:flex items-center space-x-2 absolute left-4 sm:left-6">
        <button x-show="sidebarCollapsed" 
                @click="$dispatch('toggle-sidebar')" 
                class="w-8 h-8 rounded-xl bg-gray-100/90 hover:bg-indigo-50/80 dark:bg-gray-700/60 dark:hover:bg-indigo-950/40 text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400 border border-gray-200/80 dark:border-gray-600/60 transition-all cursor-pointer shadow-sm flex items-center justify-center select-none shrink-0" 
                title="{{ __('Buka Sidebar') }}">
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
        </button>

        <button @click="$dispatch('open-command-palette')" 
                class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-gray-100/90 hover:bg-indigo-50/80 dark:bg-gray-700/60 dark:hover:bg-indigo-950/40 text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 border border-gray-200/80 dark:border-gray-600/60 transition-all cursor-pointer shadow-2xs text-xs font-semibold select-none"
                title="{{ __('Buka Command Palette (⌘K / Ctrl+K)') }}">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <span class="hidden lg:inline">{{ __('Cari atau aksi...') }}</span>
            <span class="lg:hidden">{{ __('Cari') }}</span>
            <kbd class="px-1.5 py-0.2 text-[10px] font-mono font-bold bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300">⌘K</kbd>
        </button>
    </div>

    <!-- Brand / Logo (Mobile Only) -->
    <a href="{{ route('dashboard') }}" wire:navigate.hover class="flex items-center space-x-1.5 shrink-0 md:hidden group select-none">
        <img src="/images/atoma_icon.png" alt="ATOMA Icon" width="28" height="28" class="w-7 h-7 object-contain drop-shadow-xs group-hover:scale-105 transition-transform shrink-0">
        <span class="text-sm sm:text-base font-black tracking-wider text-gray-900 dark:text-white uppercase">
            ATOMA
        </span>
    </a>

    <!-- Realtime Clock & Timezone Widget (Centered on Desktop) -->
    <div x-data="{ 
        targetTz: '{{ $user->timezone ?: (session('auto_timezone') ?: 'Asia/Jakarta') }}',
        timeStr: '',
        tzStr: '',
        gmtStr: '',
        updateClock() {
            const tz = this.targetTz || 'Asia/Jakarta';
            const now = new Date();
            try {
                this.timeStr = now.toLocaleTimeString('id-ID', { 
                    timeZone: tz, 
                    hour: '2-digit', 
                    minute: '2-digit', 
                    second: '2-digit' 
                });
                const parts = new Intl.DateTimeFormat('en-US', { 
                    timeZone: tz, 
                    timeZoneName: 'shortOffset' 
                }).formatToParts(now);
                const tzPart = parts.find(p => p.type === 'timeZoneName');
                this.gmtStr = tzPart ? tzPart.value : 'GMT+7';
                this.tzStr = tz.split('/').pop().replace('_', ' ');
            } catch (err) {
                this.timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                this.gmtStr = 'GMT+7';
                this.tzStr = 'Jakarta';
            }
        }
    }" 
       x-init="updateClock(); setInterval(() => updateClock(), 1000)" 
       @profile-updated.window="$wire.refreshProfile(); $nextTick(() => { updateClock(); })"
       @sync-timezone.window="targetTz = $event.detail.tz; updateClock()"
       class="h-8 px-2 sm:px-3 bg-gray-100/90 dark:bg-gray-700/60 rounded-xl text-[10px] sm:text-xs border border-gray-200/80 dark:border-gray-600/60 shadow-sm flex items-center space-x-1 sm:space-x-1.5 font-bold select-none min-w-0">
        <span class="text-indigo-600 dark:text-indigo-400 font-black tracking-wide tabular-nums font-mono" x-text="timeStr"></span>
        <span class="text-gray-300 dark:text-gray-600 hidden sm:inline">•</span>
        <span class="font-bold text-gray-700 dark:text-gray-300 hidden sm:inline tabular-nums font-mono" x-text="gmtStr"></span>
        <span class="hidden sm:inline text-gray-400 dark:text-gray-400 font-medium text-[10px] sm:text-xs truncate max-w-[120px]" x-text="`(${tzStr})`"></span>
    </div>


    <!-- Mobile Actions (Streak, Mail, Bell, Profile - Mobile Only) -->
    <div class="flex items-center space-x-1 sm:space-x-1.5 md:space-x-2 md:hidden shrink-0">
        <!-- Gamification Streak Stats -->
        <button @click="$dispatch('open-streak-modal')" 
                class="h-8 px-1.5 sm:px-2.5 flex items-center bg-orange-500/10 hover:bg-orange-500/15 dark:bg-orange-500/20 dark:hover:bg-orange-500/25 rounded-xl border border-orange-200/80 dark:border-orange-800/60 transition-all relative overflow-hidden group shadow-sm cursor-pointer select-none shrink-0"
                title="{{ __('Lihat Riwayat Streak') }}">
            @if($user->current_streak > 0)
                <div class="absolute inset-0 bg-orange-400/20 blur-sm group-hover:bg-orange-400/30 transition-colors pointer-events-none"></div>
            @endif
            <span class="text-orange-500 font-extrabold text-xs mr-0.5 relative z-10 tabular-nums">{{ $user->current_streak }}d</span>
            <svg class="w-3.5 h-3.5 text-orange-500 relative z-10 {{ $user->current_streak > 0 ? 'animate-pulse drop-shadow-xs' : '' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"></path></svg>
        </button>
        
        <!-- Surat ke Dev (Icon button matching Bell shape) -->
        <button @click="$dispatch('open-feedback-modal')" 
                class="w-8 h-8 rounded-xl bg-gray-100/90 hover:bg-indigo-50/80 dark:bg-gray-700/60 dark:hover:bg-indigo-950/50 border border-gray-200/80 dark:border-gray-600/60 flex items-center justify-center text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 transition-all shadow-sm cursor-pointer select-none active:scale-95 shrink-0" 
                title="{{ __('Surat & Masukan ke Developer') }}">
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
        </button>

        <!-- Quick Pomodoro Floating Widget Toggle (Mobile Only) -->
        <button type="button" 
                @click="$dispatch('toggle-pomodoro-widget')" 
                class="w-8 h-8 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 border border-rose-200/80 dark:border-rose-800/60 flex items-center justify-center text-rose-500 transition-all shadow-xs cursor-pointer select-none active:scale-95 shrink-0" 
                title="{{ __('Buka Pomodoro & Musik Fokus') }}">
            <span class="text-sm">🍅</span>
        </button>

        <div class="block shrink-0">
            <livewire:notification-bell align="right" />
        </div>

        @php
            $activeFrame = $user->active_frame ?? null;
            $headerFrameClass = 'border-2 border-indigo-200 dark:border-indigo-800 group-hover:border-indigo-400';
            if ($activeFrame === 'frame_neon') {
                $headerFrameClass = 'border-2 border-cyan-400 ring-2 ring-cyan-400/50 shadow-[0_0_10px_rgba(6,182,212,0.85)]';
            } elseif ($activeFrame === 'frame_fire') {
                $headerFrameClass = 'border-2 border-orange-500 ring-2 ring-amber-500/50 shadow-[0_0_10px_rgba(249,115,22,0.85)] animate-pulse';
            } elseif ($activeFrame === 'frame_gold') {
                $headerFrameClass = 'border-2 border-amber-400 ring-2 ring-yellow-300/80 shadow-[0_0_12px_rgba(245,158,11,0.85)]';
            }
        @endphp

        <!-- Dropdown Profile (Mobile & Tablet) -->
        <div x-data="{ open: false, isDark: document.documentElement.classList.contains('dark') }" 
             @dark-mode-toggled.window="isDark = document.documentElement.classList.contains('dark')"
             class="relative shrink-0">
            <button @click="open = !open" @click.away="open = false" class="w-8 h-8 flex items-center justify-center group cursor-pointer transition-transform active:scale-95 focus:outline-none relative select-none shrink-0 aspect-square">
                @if($activeFrame === 'frame_gold')
                    <!-- Custom Golden Crown Frame atop Header Avatar -->
                    <div class="absolute -top-[28%] left-1/2 -translate-x-1/2 w-[55%] z-20 pointer-events-none drop-shadow-md">
                        <img src="{{ asset('images/frames/crown_gold.svg') }}" alt="Crown" class="w-full h-auto object-contain">
                    </div>
                @endif
                <img src="{{ $user->avatar_url }}" 
                     alt="{{ $user->name }}" 
                     width="32" 
                     height="32" 
                     fetchpriority="high"
                     class="h-8 w-8 rounded-full {{ $headerFrameClass }} object-cover bg-white shadow-sm transition-all aspect-square shrink-0">
                @if($user->equipped_badge_details)
                    <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-white dark:bg-gray-900 p-0.5 shadow-sm border border-gray-200 dark:border-gray-700 flex items-center justify-center z-20 aspect-square shrink-0">
                        <img src="{{ asset($user->equipped_badge_details['svg_path']) }}" class="w-full h-full object-contain">
                    </div>
                @endif
            </button>
            
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                 class="absolute right-0 mt-2.5 w-64 bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700/80 py-2.5 z-50 overflow-hidden backdrop-blur-xl"
                 style="display: none;">
                
                <!-- Profile Header -->
                <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700/80 mb-1.5 flex items-center justify-between">
                    <a href="{{ route('prestige') }}" wire:navigate.hover class="min-w-0 flex-1 group/prestige cursor-pointer">
                        <div class="flex items-center gap-1.5">
                            <p class="text-sm font-black text-gray-900 dark:text-gray-100 truncate group-hover/prestige:text-indigo-600 dark:group-hover/prestige:text-indigo-400">{{ $user->name }}</p>
                            @if($user->is_admin || $user->id === 1)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-black bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 tracking-wider">ADMIN</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-indigo-600 dark:text-indigo-400 font-bold tracking-wide mt-0.5 flex items-center gap-1">
                            <span>{{ $user->prestige_title }} &bull; {{ $user->level_progress['percentage'] }}%</span>
                            <span class="text-xs group-hover/prestige:translate-x-0.5 transition-transform">&rarr;</span>
                        </p>
                    </a>
                    @if(!empty($user->equipped_badges_details))
                        <div class="flex items-center -space-x-1.5 ml-2 shrink-0">
                            @foreach($user->equipped_badges_details as $b)
                                <div class="w-6 h-6 p-0.5 rounded-lg bg-gray-50 dark:bg-gray-700/60 border border-gray-200 dark:border-gray-600 shadow-2xs" title="{{ $b['title'] }}">
                                    <img src="{{ asset($b['svg_path']) }}" class="w-full h-full object-contain">
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                
                <!-- Group 1: Profil Links -->
                <div class="px-1.5 space-y-0.5">
                    <!-- ATOMA PRO Upgrade / Status Pill -->
                    <button type="button" 
                            @click="open = false; $dispatch('open-pro-modal')" 
                            class="w-full text-left px-3 py-2 rounded-xl text-xs sm:text-sm flex items-center justify-between font-black transition-all cursor-pointer mb-1 {{ auth()->user()->isPro() ? 'bg-gradient-to-r from-amber-500/15 via-yellow-500/15 to-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-400/30' : 'bg-gradient-to-r from-amber-500 to-yellow-500 text-white shadow-md shadow-amber-500/20 hover:brightness-105' }}">
                        <div class="flex items-center">
                            <span class="w-5 text-center mr-2">👑</span>
                            <span>{{ auth()->user()->isPro() ? __('ATOMA PRO Aktif') : __('Upgrade ATOMA PRO') }}</span>
                        </div>
                        <span class="text-[9px] uppercase px-1.5 py-0.5 rounded font-black {{ auth()->user()->isPro() ? 'bg-amber-400/20 text-amber-700 dark:text-amber-300' : 'bg-white/20 text-white' }}">{{ auth()->user()->isPro() ? __('PRO') : __('NEW') }}</span>
                    </button>

                    <a href="{{ route('prestige') }}" wire:navigate.hover class="px-3 py-2 rounded-xl text-xs sm:text-sm text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/30 flex items-center font-bold transition-colors">
                        <span class="w-5 text-center mr-2">🌱</span>
                        <span>{{ __('Sistem Level & Prestige') }}</span>
                    </a>

                    <a href="{{ route('user.profile', $user->id) }}" wire:navigate.hover class="px-3 py-2 rounded-xl text-xs sm:text-sm text-atoma-blue dark:text-atoma-cyan hover:bg-indigo-50/60 dark:hover:bg-indigo-950/30 flex items-center font-bold transition-colors">
                        <span class="w-5 text-center mr-2">🌐</span>
                        <span>{{ __('Profil Publik Pamer') }}</span>
                    </a>

                    <button type="button" 
                            @click="open = false; $dispatch('open-rank-modal')" 
                            class="w-full text-left px-3 py-2 rounded-xl text-xs sm:text-sm text-amber-600 dark:text-amber-400 hover:bg-amber-50/60 dark:hover:bg-amber-950/30 flex items-center font-bold transition-colors cursor-pointer">
                        <span class="w-5 text-center mr-2">🏆</span>
                        <span>{{ __('Bocoran Rank & Liga') }}</span>
                    </button>

                    <a href="{{ route('profile') }}" wire:navigate.hover class="px-3 py-2 rounded-xl text-xs sm:text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100/70 dark:hover:bg-gray-700/50 flex items-center font-medium transition-colors">
                        <x-icon.gear class="w-4 h-4 mr-3 text-gray-400" />
                        <span>{{ __('Pengaturan Akun') }}</span>
                    </a>

                    <a href="{{ route('guide') }}" wire:navigate.hover class="px-3 py-2 rounded-xl text-xs sm:text-sm text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50/70 dark:hover:bg-indigo-950/40 flex items-center font-bold transition-colors">
                        <span class="w-5 text-center mr-2 text-sm">📖</span>
                        <span>{{ __('Panduan Fitur (Guide)') }}</span>
                    </a>
                </div>

                <div class="border-t border-gray-100 dark:border-gray-700/80 my-1.5"></div>

                <!-- Group 2: Fitur Utama -->
                <div class="px-1.5 space-y-0.5">
                    <!-- Pomodoro Timer -->
                    <button @click="open = false; $dispatch('toggle-pomodoro')" 
                            class="w-full text-left px-3 py-2 rounded-xl text-xs sm:text-sm text-rose-600 dark:text-rose-400 hover:bg-rose-50/70 dark:hover:bg-rose-950/40 flex items-center justify-between cursor-pointer font-bold group transition-colors">
                        <div class="flex items-center">
                            <span class="w-5 text-center mr-2 text-sm group-hover:scale-110 transition-transform">🍅</span>
                            <span>{{ __('Pomodoro Timer') }}</span>
                        </div>
                        <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">{{ __('Fokus') }}</span>
                    </button>

                    <!-- Medali & Pencapaian -->
                    <button @click="open = false; $dispatch('open-achievements')" class="w-full text-left px-3 py-2 rounded-xl text-xs sm:text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100/70 dark:hover:bg-gray-700/50 flex items-center justify-between cursor-pointer font-medium transition-colors">
                        <div class="flex items-center">
                            <span class="w-5 text-center mr-2 text-sm">🏆</span>
                            <span>{{ __('Medali & Koleksi') }}</span>
                        </div>
                        @if(!empty($user->equipped_badges_details))
                            <span class="text-[9px] font-black text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 px-1.5 py-0.5 rounded border border-amber-300/40">⭐ {{ count($user->equipped_badges_details) }}/3</span>
                        @endif
                    </button>

                    <!-- Store -->
                    <a href="{{ route('store') }}" wire:navigate.hover class="px-3 py-2 rounded-xl text-xs sm:text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100/70 dark:hover:bg-gray-700/50 flex items-center font-medium transition-colors">
                        <span class="w-5 text-center mr-2 text-sm">🛒</span>
                        <span>{{ __('Reward Store') }}</span>
                    </a>
                </div>

                @if($user->is_admin || $user->id === 1)
                    <div class="border-t border-gray-100 dark:border-gray-700/80 my-1.5"></div>
                    <div class="px-1.5 space-y-0.5">
                        <a href="{{ route('admin.dashboard') }}" wire:navigate.hover class="px-3 py-2 rounded-xl text-xs sm:text-sm text-indigo-600 dark:text-indigo-400 font-black hover:bg-indigo-50/80 dark:hover:bg-indigo-950/50 flex items-center justify-between transition-colors">
                            <div class="flex items-center">
                                <span class="w-5 text-center mr-2 text-sm">👑</span>
                                <span>{{ __('Admin Dashboard') }}</span>
                            </div>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">PUSAT</span>
                        </a>
                        <div class="pl-7 pr-2 py-0.5 space-y-0.5">
                            <a href="{{ route('admin.system-logs') }}" wire:navigate.hover class="px-2 py-1 rounded-lg text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-gray-100/70 dark:hover:bg-gray-700/50 flex items-center transition-colors">
                                <span class="w-4 text-center mr-1.5">📋</span>
                                <span>{{ __('System Logs & Audit') }}</span>
                            </a>
                            <a href="{{ route('admin.ai-usage') }}" wire:navigate.hover class="px-2 py-1 rounded-lg text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-gray-100/70 dark:hover:bg-gray-700/50 flex items-center transition-colors">
                                <span class="w-4 text-center mr-1.5">🛡️</span>
                                <span>{{ __('AI Telemetry') }}</span>
                            </a>
                            <a href="{{ route('admin.feedbacks') }}" wire:navigate.hover class="px-2 py-1 rounded-lg text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-gray-100/70 dark:hover:bg-gray-700/50 flex items-center transition-colors">
                                <span class="w-4 text-center mr-1.5">📬</span>
                                <span>{{ __('Kotak Surat Admin') }}</span>
                            </a>
                        </div>
                    </div>
                @endif

                <div class="border-t border-gray-100 dark:border-gray-700/80 my-1.5"></div>

                <!-- Group 3: Tema & Keluar -->
                <div class="px-1.5 space-y-0.5">
                    <!-- Dark / Light Mode in Profile Menu -->
                    <button @click="window.toggleDarkMode(); isDark = document.documentElement.classList.contains('dark')" 
                            class="w-full text-left px-3 py-2 rounded-xl text-xs sm:text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100/70 dark:hover:bg-gray-700/50 flex items-center justify-between cursor-pointer font-medium transition-colors">
                        <div class="flex items-center">
                            <svg x-show="!isDark" class="w-4 h-4 mr-3 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                            <svg x-show="isDark" x-cloak class="w-4 h-4 mr-3 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            <span x-text="isDark ? '{{ __('Mode Terang') }}' : '{{ __('Mode Gelap') }}'"></span>
                        </div>
                        <span class="text-[9px] font-black px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300" x-text="isDark ? 'DARK' : 'LIGHT'"></span>
                    </button>
                    
                    <button wire:click="$dispatch('logout-action')" class="w-full text-left px-3 py-2 rounded-xl text-xs sm:text-sm text-red-600 dark:text-red-400 hover:bg-red-50/70 dark:hover:bg-red-950/40 flex items-center cursor-pointer font-medium transition-colors">
                        <svg class="w-4 h-4 mr-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        <span>{{ __('Keluar Akun') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>
