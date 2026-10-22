<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" data-navigate-once></script>

<div x-data="{ 
    openModal: false,
    downloading: false,
    cardTheme: (document.documentElement.classList.contains('dark') ? 'dark' : 'light'),
    init() {
        this.$watch('openModal', val => {
            if (val) {
                this.cardTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            }
        });
    },
    downloadInfographic() {
        this.downloading = true;
        const target = document.getElementById('infographic-card');
        const bgColor = this.cardTheme === 'dark' ? '#0b1329' : '#ffffff';
        html2canvas(target, { 
            scale: 3, 
            useCORS: true, 
            backgroundColor: bgColor,
            logging: false 
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'ATOMA-Laporan-Mingguan-' + (new Date().toISOString().slice(0, 10)) + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            this.downloading = false;
        }).catch(() => { 
            this.downloading = false; 
        });
    }
}" @open-infographic.window="openModal = true">

    <div x-show="openModal" 
         x-transition.opacity
         style="display: none;" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/80 backdrop-blur-md">
        
        <div class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-3xl shadow-2xl p-6 sm:p-7 max-w-lg w-full border border-gray-200 dark:border-gray-800 relative transition-all">
            <!-- Close Modal Button -->
            <button @click="openModal = false" class="absolute top-5 right-5 text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors cursor-pointer p-1 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <!-- Modal Header with Theme Switcher -->
            <div class="flex items-center justify-between mb-4 pr-8">
                <div>
                    <h3 class="text-base font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <span>{{ __('Kartu Laporan Mingguan') }}</span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pratinjau sebelum menyimpan atau membagikan') }}</p>
                </div>

                <!-- Theme Toggle -->
                <div class="inline-flex p-1 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <button type="button" 
                            @click="cardTheme = 'light'" 
                            :class="cardTheme === 'light' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" 
                            class="px-2.5 py-1 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer select-none">
                        <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <span>{{ __('Terang') }}</span>
                    </button>
                    <button type="button" 
                            @click="cardTheme = 'dark'" 
                            :class="cardTheme === 'dark' ? 'bg-gray-900 text-white shadow-xs font-bold' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" 
                            class="px-2.5 py-1 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer select-none">
                        <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                        <span>{{ __('Gelap') }}</span>
                    </button>
                </div>
            </div>

            @php
                $startOfWeek = now()->startOfWeek();
                $endOfWeek = now()->endOfWeek();
                $weekTasks = auth()->user()->tasks()->whereBetween('date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()]);
                $completedTasks = (clone $weekTasks)->where('is_completed', true)->count();
                $totalTasks = (clone $weekTasks)->count();
                $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
                $focusMinutes = (clone $weekTasks)->sum('actual_minutes');
                $focusFormatted = $focusMinutes >= 60 ? floor($focusMinutes / 60) . 'j ' . ($focusMinutes % 60) . 'm' : $focusMinutes . 'm';
                $streakCount = auth()->user()->current_streak;
            @endphp

            <!-- INFOGRAPHIC CARD TARGET (Exportable to PNG) -->
            <div id="infographic-card" 
                 :class="cardTheme === 'dark' ? 'bg-slate-900 text-white border-slate-700/80 shadow-slate-950/50' : 'bg-white text-slate-800 border-slate-200/90 shadow-blue-500/5'"
                 class="p-6 sm:p-7 rounded-3xl border-2 shadow-2xl transition-colors duration-200 text-left select-none relative overflow-hidden">
                
                <!-- Ambient Glow Elements (Subtle Decorative Background) -->
                <div class="absolute -top-12 -right-12 w-44 h-44 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-12 -left-12 w-44 h-44 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <!-- 1. Brand Header with Authentic Vector SVG Logo -->
                <div class="flex items-center justify-between pb-4 mb-4 border-b relative z-10"
                     :class="cardTheme === 'dark' ? 'border-slate-800/90' : 'border-slate-100'">
                    
                    <div class="flex items-center space-x-3">
                        <!-- ATOMA Dynamic Vector SVG Mark (No raster JPEG black box) -->
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center p-1 shrink-0 shadow-xs"
                             :class="cardTheme === 'dark' ? 'bg-slate-800/90 border border-slate-700/80' : 'bg-slate-50 border border-slate-200/90'">
                            <svg class="w-full h-full" viewBox="0 0 100 100" fill="none">
                                <defs>
                                    <linearGradient id="info-left-stem" x1="0%" y1="100%" x2="70%" y2="0%">
                                        <stop offset="0%" stop-color="#00F0FF"/>
                                        <stop offset="45%" stop-color="#00A3FF"/>
                                        <stop offset="100%" stop-color="#3E64FF"/>
                                    </linearGradient>
                                    <linearGradient id="info-right-stem" x1="20%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#3E64FF"/>
                                        <stop offset="40%" stop-color="#6B46FE"/>
                                        <stop offset="80%" stop-color="#8B3DFF"/>
                                        <stop offset="100%" stop-color="#A828FF"/>
                                    </linearGradient>
                                    <linearGradient id="info-crossbar" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#00C8FF"/>
                                        <stop offset="50%" stop-color="#5551FF"/>
                                        <stop offset="100%" stop-color="#7B2CBF"/>
                                    </linearGradient>
                                </defs>
                                <path d="M 22 76 C 19 76 17 73.5 18 70.5 L 43 20 C 44.5 17 47.5 17 49 20 L 59 40 L 35 76 Z" fill="url(#info-left-stem)"/>
                                <path d="M 45 19 C 47 16 50 16 52 19 L 81 70.5 C 82.5 73.5 80.5 76 77 76 L 62 76 C 60 76 58.5 74.5 57.5 72.5 L 51 58 L 38 32 L 45 19 Z" fill="url(#info-right-stem)"/>
                                <path d="M 32 64 C 31 64 30.2 63.3 30.5 62.3 L 34.5 53.5 C 35 52.5 36 52 37 52 L 67 52 C 68.5 52 69.5 53.5 69 55 L 65.5 62.5 C 65 63.5 64 64 63 64 Z" fill="url(#info-crossbar)"/>
                            </svg>
                        </div>
                        
                        <div>
                            <div class="flex items-center gap-1.5 leading-none">
                                <span class="font-black text-lg tracking-wider uppercase font-sans"
                                      :class="cardTheme === 'dark' ? 'text-white' : 'text-slate-900'">
                                    ATOMA
                                </span>
                                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                            </div>
                            <span class="text-[10px] font-semibold tracking-tight mt-0.5 block"
                                  :class="cardTheme === 'dark' ? 'text-slate-400' : 'text-slate-500'">
                                {{ __('Minggu Ini') }} • {{ now()->startOfWeek()->isoFormat('D MMM') }} - {{ now()->endOfWeek()->isoFormat('D MMM Y') }}
                            </span>
                        </div>
                    </div>

                    <!-- Modern Badge -->
                    <div class="flex items-center gap-1.5 px-3 py-1 rounded-full border text-[10px] font-extrabold uppercase tracking-wider"
                         :class="cardTheme === 'dark' ? 'text-cyan-300 bg-cyan-950/50 border-cyan-800/60 shadow-2xs' : 'text-blue-700 bg-blue-50/90 border-blue-200/90 shadow-2xs'">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>{{ __('Laporan Mingguan') }}</span>
                    </div>
                </div>

                <!-- 2. User Profile Card -->
                <div class="flex items-center space-x-3.5 mb-4 p-3.5 rounded-2xl border relative z-10"
                     :class="cardTheme === 'dark' ? 'bg-slate-800/70 border-slate-700/70' : 'bg-slate-50/90 border-slate-200/80'">
                    
                    <!-- Radiant Avatar Ring -->
                    <div class="p-0.5 rounded-full bg-gradient-to-tr from-amber-400 via-rose-500 to-indigo-500 shrink-0 shadow-sm">
                        <img src="{{ auth()->user()->avatar_url }}" class="w-11 h-11 rounded-full object-cover bg-white dark:bg-slate-800">
                    </div>

                    <div class="flex-1 min-w-0">
                        <h4 class="font-black text-sm truncate leading-tight mb-1"
                            :class="cardTheme === 'dark' ? 'text-white' : 'text-slate-900'">
                            {{ auth()->user()->name }}
                        </h4>
                        <div class="flex items-center gap-2 flex-wrap text-xs">
                            <span class="px-2 py-0.5 rounded-lg font-black text-[10px] uppercase tracking-wider border"
                                  :class="cardTheme === 'dark' ? 'bg-slate-700/80 text-cyan-300 border-slate-600' : 'bg-white text-blue-700 border-slate-200 shadow-2xs'">
                                Level {{ auth()->user()->level }}
                            </span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg font-black text-[10px] text-amber-600 bg-amber-500/10 border border-amber-500/20 dark:text-amber-300">
                                <svg class="w-3 h-3 text-amber-500 fill-amber-500" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.316.492-.63 1.154-.925 1.933-.59 1.558-1.18 3.526-1.597 5.166-.364.08-.737.126-1.121.126-1.895 0-3.48-1.12-4.11-2.73a1 1 0 00-1.636-.264c-1.39 1.545-2.074 3.535-1.928 5.568.3 4.195 3.82 7.553 8.044 7.553 4.544 0 8.232-3.69 8.232-8.235 0-3.612-2.193-6.732-5.112-7.897z" clip-rule="evenodd"/></svg>
                                {{ $streakCount }} {{ __('Hari Streak') }}
                            </span>
                        </div>
                    </div>

                    <!-- League / Rank Badge -->
                    <div class="hidden sm:flex flex-col items-end shrink-0 pl-2">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Liga</span>
                        <span class="text-xs font-black text-indigo-500 dark:text-indigo-400">{{ auth()->user()->league_name ?? 'Bronze III' }}</span>
                    </div>
                </div>

                <!-- 3. Dynamic Performance Highlight Strip -->
                @php
                    if ($completedTasks >= 8 && $completionRate >= 75) {
                        $highlightIcon = "🎯";
                        $highlightTitle = __("Performa Pekan Ini Sangat Solid!");
                        $highlightDesc = __("Tingkat penyelesaian dan eksekusi targetmu di atas rata-rata.");
                        $highlightStyle = "bg-emerald-500/10 border-emerald-500/20 text-emerald-800 dark:text-emerald-300";
                    } elseif ($completedTasks > 0) {
                        $highlightIcon = "🚀";
                        $highlightTitle = __("Momentum Produktif Terus Tumbuh!");
                        $highlightDesc = __("Setiap tugas yang selesai membentuk kebiasaan pemenang.");
                        $highlightStyle = "bg-blue-500/10 border-blue-500/20 text-blue-800 dark:text-blue-300";
                    } else {
                        $highlightIcon = "✨";
                        $highlightTitle = __("Awal Baru Pekan Ini!");
                        $highlightDesc = __("Mulai rencanakan target pertamamu dan bangun streak emas.");
                        $highlightStyle = "bg-indigo-500/10 border-indigo-500/20 text-indigo-800 dark:text-indigo-300";
                    }
                @endphp
                <div class="p-2.5 px-3.5 rounded-2xl border flex items-center gap-2.5 mb-4 relative z-10 {{ $highlightStyle }}">
                    <span class="text-base shrink-0">{{ $highlightIcon }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="font-extrabold text-[11px] leading-tight">{{ $highlightTitle }}</div>
                        <div class="text-[10px] opacity-85 truncate mt-0.5">{{ $highlightDesc }}</div>
                    </div>
                </div>

                <!-- 4. 4-Metric Grid with Vibrant Accents & Micro Indicators -->
                <div class="grid grid-cols-2 gap-3 mb-4 relative z-10">
                    
                    <!-- Metric 1: Tasks Completed -->
                    <div class="p-3.5 rounded-2xl border transition-all"
                         :class="cardTheme === 'dark' ? 'bg-slate-800/60 border-slate-700/60' : 'bg-emerald-50/40 border-emerald-100/90'">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                                {{ __('Task Selesai') }}
                            </span>
                            <div class="w-6 h-6 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </div>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400 leading-none mb-1">
                            {{ $completedTasks }}
                        </div>
                        <div class="text-[10px] font-medium"
                             :class="cardTheme === 'dark' ? 'text-slate-400' : 'text-slate-500'">
                            {{ __('dari :total tugas terencana', ['total' => $totalTasks]) }}
                        </div>
                        <!-- Mini progress bar -->
                        <div class="w-full bg-emerald-200/50 dark:bg-emerald-950/50 h-1.5 rounded-full overflow-hidden mt-2">
                            <div class="bg-emerald-500 h-full rounded-full transition-all duration-500" 
                                 style="width: {{ $totalTasks > 0 ? min(100, round(($completedTasks / $totalTasks) * 100)) : 0 }}%"></div>
                        </div>
                    </div>

                    <!-- Metric 2: Focus Time -->
                    <div class="p-3.5 rounded-2xl border transition-all"
                         :class="cardTheme === 'dark' ? 'bg-slate-800/60 border-slate-700/60' : 'bg-blue-50/40 border-blue-100/90'">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-700 dark:text-blue-400">
                                {{ __('Waktu Fokus') }}
                            </span>
                            <div class="w-6 h-6 rounded-lg bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black text-blue-600 dark:text-blue-400 leading-none mb-1">
                            {{ $focusFormatted }}
                        </div>
                        <div class="text-[10px] font-medium"
                             :class="cardTheme === 'dark' ? 'text-slate-400' : 'text-slate-500'">
                            {{ __('total konsentrasi produktif') }}
                        </div>
                        <div class="mt-2 flex items-center gap-1 text-[9px] font-bold text-blue-600 dark:text-blue-400">
                            <span>⚡ Deep Work</span>
                        </div>
                    </div>

                    <!-- Metric 3: Completion Rate -->
                    <div class="p-3.5 rounded-2xl border transition-all"
                         :class="cardTheme === 'dark' ? 'bg-slate-800/60 border-slate-700/60' : 'bg-amber-50/40 border-amber-100/90'">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                                {{ __('Tingkat Selesai') }}
                            </span>
                            <div class="w-6 h-6 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            </div>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400 leading-none mb-1">
                            {{ $completionRate }}%
                        </div>
                        <div class="text-[10px] font-medium"
                             :class="cardTheme === 'dark' ? 'text-slate-400' : 'text-slate-500'">
                            {{ __('akurasi penyelesaian') }}
                        </div>
                        <!-- Mini progress bar -->
                        <div class="w-full bg-amber-200/50 dark:bg-amber-950/50 h-1.5 rounded-full overflow-hidden mt-2">
                            <div class="bg-amber-500 h-full rounded-full transition-all duration-500" 
                                 style="width: {{ min(100, $completionRate) }}%"></div>
                        </div>
                    </div>

                    <!-- Metric 4: Streak Consistency -->
                    <div class="p-3.5 rounded-2xl border transition-all"
                         :class="cardTheme === 'dark' ? 'bg-slate-800/60 border-slate-700/60' : 'bg-indigo-50/40 border-indigo-100/90'">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-700 dark:text-indigo-400">
                                {{ __('Konsistensi') }}
                            </span>
                            <div class="w-6 h-6 rounded-lg bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </div>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black text-indigo-600 dark:text-indigo-400 leading-none mb-1">
                            {{ $streakCount }} {{ __('Hari') }}
                        </div>
                        <div class="text-[10px] font-medium"
                             :class="cardTheme === 'dark' ? 'text-slate-400' : 'text-slate-500'">
                            {{ __('kebiasaan terjaga rapi') }}
                        </div>
                        <div class="mt-2 flex items-center gap-1 text-[9px] font-bold text-indigo-600 dark:text-indigo-400">
                            <span>🛡️ Disiplin Rutin</span>
                        </div>
                    </div>

                </div>

                <!-- 5. Minimalist Affirmation Quote -->
                <div class="p-3 rounded-2xl text-center border relative z-10"
                     :class="cardTheme === 'dark' ? 'bg-slate-800/40 border-slate-800/80 text-slate-300' : 'bg-slate-50/80 border-slate-200/80 text-slate-600'">
                    <p class="text-[11px] italic font-medium leading-relaxed">
                        &ldquo;{{ __('Produktivitas yang konsisten adalah kunci utama meraih impian.') }}&rdquo;
                    </p>
                </div>

                <!-- 6. Subtle Footer Brand Watermark -->
                <div class="mt-4 pt-3 flex items-center justify-between border-t text-[10px] relative z-10"
                     :class="cardTheme === 'dark' ? 'border-slate-800/80 text-slate-500' : 'border-slate-100 text-slate-400'">
                    <div class="flex items-center gap-1.5 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                        <span>atoma.app • Habit & Focus Tracker</span>
                    </div>
                    <span class="font-bold tracking-wider">#StayConsistent</span>
                </div>
            </div>

            <!-- Download Button -->
            <button @click="downloadInfographic()" 
                    :disabled="downloading"
                    class="w-full mt-5 py-3.5 bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold rounded-2xl shadow-lg shadow-blue-600/30 transition-all active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed select-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                <span x-text="downloading ? '{{ __('Memproses Kartu Laporan...') }}' : '{{ __('Unduh Kartu Laporan (PNG)') }}'"></span>
            </button>
        </div>
    </div>
</div>
