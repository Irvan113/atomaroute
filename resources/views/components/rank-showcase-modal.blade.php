@php
    $user = auth()->user();
    $allLeagues = \App\Services\LeagueService::getAllLeaguesWithProgress($user);
    $currentLeagueKey = $user->league ?? 'bronze_3';
    $currentLeague = \App\Services\LeagueService::getLeagueDetails($currentLeagueKey);
    $promo = $user ? \App\Services\LeagueService::getPromotionProgress($user) : null;
    
    // Group leagues by tier
    $leaguesByTier = [];
    foreach ($allLeagues as $key => $league) {
        $tier = $league['tier'];
        if (!isset($leaguesByTier[$tier])) {
            $leaguesByTier[$tier] = [];
        }
        $leaguesByTier[$tier][] = $league;
    }

    $prestigeTiers = [
        1 => [
            'name' => 'Novice',
            'icon' => '🌱',
            'svg' => 'images/prestige/tier_1_novice.svg',
            'range' => 'Level 1 – 50',
            'est_time' => '⏱️ Est. Mulai Hari 1 (Lv. 1–50)',
            'desc' => 'Pemula yang baru memulai petualangan produktivitas di ATOMA. Capai Level 50 untuk mengalami Ascension pertama!',
            'color' => 'text-emerald-500 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800'
        ],
        2 => [
            'name' => 'Practitioner',
            'icon' => '⚔️',
            'svg' => 'images/prestige/tier_2_practitioner.svg',
            'range' => 'Level 1 – 50',
            'est_time' => '⏱️ Est. 1–2 Bulan Rajin (Lv. 1–50)',
            'desc' => 'Mulai membangun kebiasaan harian yang solid dan konsisten menuju puncak Level 50.',
            'color' => 'text-sky-500 bg-sky-50 dark:bg-sky-950/40 border-sky-200 dark:border-sky-800'
        ],
        3 => [
            'name' => 'Master',
            'icon' => '🔥',
            'svg' => 'images/prestige/tier_3_master.svg',
            'range' => 'Level 1 – 50',
            'est_time' => '⏱️ Est. 4–6 Bulan Rajin (Lv. 1–50)',
            'desc' => 'Fokus tingkat tinggi dengan penyelesaian tugas tanpa distraksi dan ritme Pomodoro mantap.',
            'color' => 'text-amber-500 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800'
        ],
        4 => [
            'name' => 'Grandmaster',
            'icon' => '💎',
            'svg' => 'images/prestige/tier_4_grandmaster.svg',
            'range' => 'Level 1 – 50',
            'est_time' => '⏱️ Est. 9–12 Bulan Rajin (Lv. 1–50)',
            'desc' => 'Elit produktivitas yang mampu mempertahankan streak puluhan hari dan disiplin waktu tinggi.',
            'color' => 'text-cyan-500 bg-cyan-50 dark:bg-cyan-950/40 border-cyan-200 dark:border-cyan-800'
        ],
        5 => [
            'name' => 'Ascended',
            'icon' => '👑',
            'svg' => 'images/prestige/tier_5_ascended.svg',
            'range' => 'Level 1 – 50',
            'est_time' => '⏱️ Est. 1.5–2 Tahun Rajin (Lv. 1–50)',
            'desc' => 'Puncak kehormatan tertinggi di ATOMA dengan disiplin baja legendaris tak tergoyahkan.',
            'color' => 'text-purple-500 bg-purple-50 dark:bg-purple-950/40 border-purple-200 dark:border-purple-800 shadow-[0_0_12px_rgba(168,85,247,0.3)]'
        ],
    ];
@endphp

<div x-data="{ 
        show: false, 
        activeTab: 'leagues',
        close() { this.show = false; }
     }" 
     @open-rank-modal.window="show = true"
     @keydown.escape.window="close()"
     x-show="show" 
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto" 
     style="display: none;">
    
    <!-- Backdrop -->
    <div x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="close()"
         class="fixed inset-0 bg-gray-900/70 backdrop-blur-md"></div>

    <!-- Modal Dialog -->
    <div class="min-h-full flex items-center justify-center p-3 sm:p-4 text-center">
        <div x-show="show" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4"
             class="relative w-full max-w-3xl bg-white dark:bg-gray-850 rounded-3xl shadow-2xl border border-gray-150 dark:border-gray-700/80 overflow-hidden text-left flex flex-col max-h-[90vh]">
            
            <!-- Modal Header -->
            <div class="p-5 sm:p-6 bg-gradient-to-r from-atoma-blue/10 via-atoma-cyan/5 to-transparent dark:from-indigo-950/50 dark:via-gray-800 dark:to-gray-850 border-b border-gray-100 dark:border-gray-700/80 flex items-start justify-between gap-4 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-atoma-blue to-atoma-cyan p-0.5 shadow-md flex items-center justify-center shrink-0">
                        <div class="w-full h-full bg-white dark:bg-gray-900 rounded-[14px] flex items-center justify-center">
                            <span class="text-2xl">🏆</span>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                            <span>{{ __('Bocoran Semua Rank & Liga ATOMA') }}</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-0.5">
                            {{ __('Panduan lengkap 12 divisi liga mingguan dan sistem prestise RPG') }}
                        </p>
                    </div>
                </div>

                <button type="button" 
                        @click="close()" 
                        class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center justify-center transition-all cursor-pointer shrink-0">
                    ✕
                </button>
            </div>

            <!-- Tabs Switcher -->
            <div class="px-5 sm:px-6 pt-4 shrink-0 bg-white dark:bg-gray-850">
                <div class="flex items-center gap-2 p-1 bg-gray-100 dark:bg-gray-800 rounded-2xl border border-gray-200/50 dark:border-gray-700/50">
                    <button type="button" 
                            @click="activeTab = 'leagues'" 
                            :class="activeTab === 'leagues' ? 'bg-atoma-blue dark:bg-indigo-600 text-white shadow-xs font-black' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold'"
                            class="flex-1 py-2 px-3 rounded-xl text-xs transition-all cursor-pointer flex items-center justify-center gap-1.5">
                        <span>🏅</span>
                        <span>{{ __('12 Divisi Liga Mingguan') }}</span>
                    </button>
                    <button type="button" 
                            @click="activeTab = 'prestige'" 
                            :class="activeTab === 'prestige' ? 'bg-atoma-blue dark:bg-indigo-600 text-white shadow-xs font-black' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold'"
                            class="flex-1 py-2 px-3 rounded-xl text-xs transition-all cursor-pointer flex items-center justify-center gap-1.5">
                        <span>🌱</span>
                        <span>{{ __('Sistem Prestise Akun') }}</span>
                    </button>
                </div>
            </div>

            <!-- Scrollable Content -->
            <div class="p-5 sm:p-6 overflow-y-auto flex-1 space-y-6">
                
                <!-- TAB 1: 12 LIGA MINGGUAN -->
                <div x-show="activeTab === 'leagues'" class="space-y-6">
                    <!-- Current User Status Card -->
                    @if($promo)
                        <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-atoma-ice via-indigo-50/50 to-purple-50/40 dark:from-indigo-950/40 dark:via-gray-800 dark:to-purple-950/30 border border-atoma-aqua/50 dark:border-indigo-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5">
                                <x-tier-badge league="{{ $currentLeagueKey }}" size="lg" />
                                <div>
                                    <div class="text-[11px] font-black uppercase text-atoma-blue dark:text-indigo-300 tracking-wider">
                                        {{ __('Posisimu Saat Ini') }}
                                    </div>
                                    <div class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-1.5">
                                        <span>{{ $currentLeague['name'] }}</span>
                                        <span>{{ $currentLeague['icon'] }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                        {{ __('XP Mingguan Terkumpul:') }} <span class="font-bold text-gray-800 dark:text-gray-200">{{ $promo['weekly_xp'] }} XP</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="sm:text-right">
                                <div class="text-[11px] text-gray-500 dark:text-gray-400 font-medium">{{ __('Status Promosi:') }}</div>
                                <div class="text-xs font-black {{ $promo['is_qualified'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ $promo['progress_text'] }}
                                </div>
                                <div class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">
                                    {{ __('Settlement mingguan otomatis tiap Senin 12:00 WIB') }}
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- How it works banner -->
                    <div class="p-3.5 sm:p-4 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-150 dark:border-gray-700/70 text-xs text-gray-600 dark:text-gray-300 space-y-1.5 leading-relaxed">
                        <div class="font-black text-gray-900 dark:text-white flex items-center gap-1.5">
                            <span>💡</span>
                            <span>{{ __('Aturan Promosi & Degradasi Liga:') }}</span>
                        </div>
                        <ul class="list-disc list-inside space-y-1 pl-1 text-[11px] sm:text-xs">
                            <li>{{ __('Kumpulkan XP mingguan dari tugas harian, Pomodoro, dan latihan flashcard.') }}</li>
                            <li>{{ __('Promosi Liga diproses 1x per minggu pada settlement hari Senin pukul 12:00 WIB.') }}</li>
                            <li>{{ __('Pemain yang mencapai ≥100% target XP mingguan akan dipromosikan 1 tingkat ke divisi berikutnya!') }}</li>
                            <li>{{ __('Degradasi: Jika XP mingguan di bawah 50% target, pemain akan turun 1 divisi (kecuali Bronze III). Perolehan 50%–99% aman bertahan di divisi saat ini.') }}</li>
                            <li>{{ __('Setelah settlement, XP mingguan direset ke 0 untuk memulai kompetisi baru secara adil.') }}</li>
                            <li>{{ __('Setiap promosi divisi baru memberikan reward koin yang dapat dibelanjakan di Toko ATOMA!') }}</li>
                        </ul>
                    </div>

                    <!-- Leagues By Tier Grid -->
                    <div class="space-y-4">
                        @foreach($leaguesByTier as $tierName => $leagues)
                            <div class="border border-gray-150 dark:border-gray-700/80 rounded-2xl p-4 bg-white dark:bg-gray-800/60 shadow-xs">
                                <div class="flex items-center justify-between mb-3 border-b border-gray-100 dark:border-gray-700/50 pb-2">
                                    <div class="flex items-center gap-2">
                                        <x-tier-badge league="{{ $leagues[0]['key'] }}" size="sm" />
                                        <h4 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-wider">
                                            Tier {{ $tierName }}
                                        </h4>
                                    </div>
                                    <span class="text-[11px] font-bold text-gray-400">
                                        {{ count($leagues) }} {{ __('Divisi') }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    @foreach($leagues as $l)
                                        @php
                                            $isCurrent = $l['is_current'];
                                            $isUnlocked = $l['is_unlocked'];
                                        @endphp
                                        <div class="relative rounded-xl p-3 border transition-all flex flex-col justify-between {{ $isCurrent ? 'border-atoma-blue dark:border-atoma-cyan ring-2 ring-atoma-blue/30 dark:ring-atoma-cyan/30 bg-atoma-ice/60 dark:bg-atoma-blue/15 shadow-sm' : ($isUnlocked ? 'border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/40' : 'border-dashed border-gray-200 dark:border-gray-700/60 bg-gray-50/20 dark:bg-gray-800/20 opacity-75') }}">
                                            
                                            <!-- Top badge row -->
                                            <div class="flex items-start justify-between gap-2 mb-2">
                                                <div class="flex items-center gap-2">
                                                    <x-tier-badge league="{{ $l['key'] }}" size="sm" />
                                                    <div>
                                                        <div class="font-extrabold text-xs sm:text-sm text-gray-900 dark:text-white flex items-center gap-1">
                                                            <span>{{ $l['name'] }}</span>
                                                            <span>{{ $l['icon'] }}</span>
                                                        </div>
                                                        <div class="text-[10px] text-gray-400 font-medium">
                                                            Min {{ number_format($l['min_weekly_xp']) }} XP/minggu
                                                        </div>
                                                    </div>
                                                </div>

                                                @if($isCurrent)
                                                    <span class="px-2 py-0.5 bg-atoma-blue dark:bg-indigo-600 text-white rounded-full text-[9px] font-black tracking-wider uppercase shadow-xs shrink-0">
                                                        AKTIF
                                                    </span>
                                                @elseif($isUnlocked)
                                                    <span class="text-emerald-500 font-bold text-[10px] shrink-0" title="Telah dicapai">
                                                        ✓
                                                    </span>
                                                @else
                                                    <span class="text-gray-400 font-bold text-[10px] shrink-0" title="Terkunci">
                                                        🔒
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Bottom reward row -->
                                            <div class="mt-2 pt-2 border-t border-gray-200/60 dark:border-gray-700/60 flex items-center justify-between text-[11px]">
                                                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __('Reward:') }}</span>
                                                <span class="font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                                    <span>+{{ $l['reward_coins'] }}</span>
                                                    <span>🪙</span>
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- TAB 2: SISTEM PRESTISE AKUN -->
                <div x-show="activeTab === 'prestige'" class="space-y-4">
                    <div class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/80 dark:border-emerald-800/60 text-xs text-emerald-900 dark:text-emerald-200 space-y-1.5">
                        <div class="font-black flex items-center gap-1.5">
                            <span>🌱</span>
                            <span>{{ __('Apa itu Sistem Prestise RPG?') }}</span>
                        </div>
                        <p class="text-[11px] sm:text-xs text-emerald-800 dark:text-emerald-300 leading-relaxed">
                            {{ __('Sistem Prestise memiliki 5 Tier di mana setiap Tier terdiri dari Level 1 sampai 50. Begitu mencapai Level 50 maksimal, akunmu akan mengalami Ascension (Naik ke Tier berikutnya)! Total XP & Level bersifat permanen, membuka gelar prestise, frame avatar eksklusif, dan lencana kehormatan.') }}
                        </p>
                    </div>

                    <div class="space-y-3">
                        @foreach($prestigeTiers as $tierNum => $pt)
                            <div class="p-4 rounded-2xl border transition-all flex items-start gap-4 {{ $pt['color'] }}">
                                <div class="w-12 h-12 rounded-2xl bg-white/90 dark:bg-gray-800/90 shadow-xs border border-inherit flex items-center justify-center p-1.5 shrink-0">
                                    @if(!empty($pt['svg']) && file_exists(public_path($pt['svg'])))
                                        <img src="{{ asset($pt['svg']) }}" alt="{{ $pt['name'] }}" class="w-full h-full object-contain">
                                    @else
                                        <span class="text-2xl">{{ $pt['icon'] }}</span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2 flex-wrap sm:flex-nowrap">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-black text-sm sm:text-base text-gray-900 dark:text-white">
                                                Tier {{ $tierNum }}: {{ $pt['name'] }}
                                            </h4>
                                            <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500">
                                                (50 Level)
                                            </span>
                                        </div>
                                        <span class="text-[10px] sm:text-[11px] font-black px-2.5 py-0.5 rounded-full bg-white/80 dark:bg-gray-800/80 border border-inherit shrink-0 shadow-2xs">
                                            {{ $pt['est_time'] }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 leading-relaxed">
                                        {{ $pt['desc'] }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-2 text-center">
                        <a href="{{ route('prestige') }}" wire:navigate class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-atoma-blue hover:bg-blue-600 text-white font-bold text-xs shadow-md transition-all active:scale-95 cursor-pointer">
                            <span>🌱</span>
                            <span>{{ __('Buka Halaman Prestise Lengkap') }} &rarr;</span>
                        </a>
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="p-4 sm:p-5 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-150 dark:border-gray-700/80 flex items-center justify-end shrink-0">
                <button type="button" 
                        @click="close()" 
                        class="px-5 py-2 rounded-xl bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-bold text-xs transition-colors cursor-pointer">
                    {{ __('Tutup') }}
                </button>
            </div>
        </div>
    </div>
</div>
