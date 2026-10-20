{{-- Fitness & Sports Analytics Hub (Seminggu, Sebulan, Setahun & Pencatatan Cepat) --}}
@php
    $fitnessData = $fitnessData ?? [];
    $weekDays = $fitnessData['week_days'] ?? [];
    $monthWeeks = $fitnessData['month_weeks'] ?? [];
    $progressInsights = $fitnessData['progress_insights'] ?? [];
    $yearMonths = $fitnessData['year_months'] ?? [];
    $recentSessions = $fitnessData['recent_sessions'] ?? collect();
@endphp

<div class="space-y-6 animate-in fade-in duration-200">
    <!-- Top Action & Period Filter Header -->
    <div class="bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 rounded-3xl p-5 sm:p-7 text-white shadow-lg shadow-emerald-500/15 relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 w-44 h-44 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-[11px] font-black tracking-wide uppercase mb-2">
                    <span>⚡ Fitness & Sports Hub</span>
                </div>
                <h3 class="text-xl sm:text-2xl font-black tracking-tight">
                    {{ __('Analisis & Jurnal Olahraga') }}
                </h3>
                <p class="text-xs sm:text-sm text-emerald-100 font-medium max-w-xl mt-1">
                    {{ __('Pantau konsistensi olahraga, jarak tempuh lari, repetisi kalistenik, dan evaluasi progres mingguan hingga tahunan.') }}
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                <button type="button"
                        wire:click="openWorkoutModal"
                        class="px-4 py-2.5 bg-white hover:bg-emerald-50 text-emerald-700 active:scale-95 rounded-2xl text-xs sm:text-sm font-black shadow-md transition-all flex items-center gap-2 cursor-pointer shrink-0">
                    <span class="text-base">➕</span>
                    <span>{{ __('Catat Olahraga') }}</span>
                </button>
            </div>
        </div>

        <!-- Period Switcher: Seminggu, Sebulan, Setahun -->
        <div class="mt-6 pt-5 border-t border-white/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-xs font-bold text-emerald-100">
                <span>{{ __('Rentang Waktu Analisis:') }}</span>
            </div>
            <div class="inline-flex p-1 bg-black/20 backdrop-blur-md rounded-2xl border border-white/10 shadow-inner w-full sm:w-auto">
                <button type="button"
                        wire:click="setFitnessPeriod('week')"
                        class="flex-1 sm:flex-initial px-4 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer {{ $fitnessPeriod === 'week' ? 'bg-white text-emerald-800 shadow-sm' : 'text-emerald-100 hover:text-white' }}">
                    {{ __('Seminggu (7 Hari)') }}
                </button>
                <button type="button"
                        wire:click="setFitnessPeriod('month')"
                        class="flex-1 sm:flex-initial px-4 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer {{ $fitnessPeriod === 'month' ? 'bg-white text-emerald-800 shadow-sm' : 'text-emerald-100 hover:text-white' }}">
                    {{ __('Sebulan (4 Pekan)') }}
                </button>
                <button type="button"
                        wire:click="setFitnessPeriod('year')"
                        class="flex-1 sm:flex-initial px-4 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer {{ $fitnessPeriod === 'year' ? 'bg-white text-emerald-800 shadow-sm' : 'text-emerald-100 hover:text-white' }}">
                    {{ __('Setahun (12 Bulan)') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Metric Summary Cards (Dynamic by Period) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- Metric 1: Sesi Latihan -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 relative overflow-hidden group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Total Sesi') }}</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm font-bold">
                    🏋️
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                @if($fitnessPeriod === 'week')
                    {{ $fitnessData['week_sessions_count'] ?? 0 }} <span class="text-xs text-gray-400 font-bold">sesi</span>
                @elseif($fitnessPeriod === 'month')
                    {{ $fitnessData['month_sessions_count'] ?? 0 }} <span class="text-xs text-gray-400 font-bold">sesi</span>
                @else
                    {{ $fitnessData['year_sessions_count'] ?? 0 }} <span class="text-xs text-gray-400 font-bold">sesi</span>
                @endif
            </div>
            <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ $fitnessPeriod === 'week' ? 'Frekuensi pekan ini' : ($fitnessPeriod === 'month' ? 'Total bulan ini' : 'Akumulasi tahun ini') }}
            </p>
        </div>

        <!-- Metric 2: Jarak Tempuh Kardio / Lari -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 relative overflow-hidden group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Jarak Kardio / Lari') }}</span>
                <div class="w-8 h-8 rounded-xl bg-cyan-100 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 flex items-center justify-center text-sm font-bold">
                    🏃‍♂️
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                @if($fitnessPeriod === 'week')
                    {{ $fitnessData['week_distance_km'] ?? 0 }} <span class="text-xs text-gray-400 font-bold">km</span>
                @elseif($fitnessPeriod === 'month')
                    {{ round(collect($monthWeeks)->sum('distance'), 1) }} <span class="text-xs text-gray-400 font-bold">km</span>
                @else
                    {{ $fitnessData['year_total_distance'] ?? 0 }} <span class="text-xs text-gray-400 font-bold">km</span>
                @endif
            </div>
            <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ __('Akumulasi lari & kardio') }}
            </p>
        </div>

        <!-- Metric 3: Repetisi Kalistenik / Bodyweight -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 relative overflow-hidden group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Total Reps') }}</span>
                <div class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm font-bold">
                    💪
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                @if($fitnessPeriod === 'week')
                    {{ number_format($fitnessData['week_reps'] ?? 0, 0, ',', '.') }} <span class="text-xs text-gray-400 font-bold">reps</span>
                @elseif($fitnessPeriod === 'month')
                    {{ number_format(collect($monthWeeks)->sum('reps'), 0, ',', '.') }} <span class="text-xs text-gray-400 font-bold">reps</span>
                @else
                    {{ number_format($fitnessData['year_total_reps'] ?? 0, 0, ',', '.') }} <span class="text-xs text-gray-400 font-bold">reps</span>
                @endif
            </div>
            <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ __('Push Up, Pull Up, Sit Up') }}
            </p>
        </div>

        <!-- Metric 4: Durasi Aktif -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200/80 dark:border-gray-700/60 relative overflow-hidden group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] sm:text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Waktu Aktif') }}</span>
                <div class="w-8 h-8 rounded-xl bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-sm font-bold">
                    ⏱️
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">
                @if($fitnessPeriod === 'week')
                    {{ $fitnessData['week_duration_min'] ?? 0 }} <span class="text-xs text-gray-400 font-bold">mnt</span>
                @else
                    {{ round(($fitnessData['week_duration_min'] ?? 0) / 60, 1) }} <span class="text-xs text-gray-400 font-bold">jam</span>
                @endif
            </div>
            <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ __('Waktu bergerak fisik') }}
            </p>
        </div>
    </div>

    <!-- VIEW 1: SEMINGGU (Weekly Schedule: Hari Apa Saja User Olahraga) -->
    @if($fitnessPeriod === 'week')
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 space-y-4">
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <div>
                    <h4 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <span>📅</span>
                        <span>{{ __('Jadwal & Hari Latihan Pekan Ini (Senin – Minggu)') }}</span>
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ __('Rincian hari apa saja kamu berolahraga dan jenis latihan yang kamu lakukan.') }}
                    </p>
                </div>
                <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 px-3 py-1 rounded-xl border border-emerald-500/20">
                    {{ $fitnessData['week_sessions_count'] ?? 0 }}x Latihan dari 7 Hari
                </div>
            </div>

            <!-- 7 Days Grid Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-3 pt-2">
                @foreach($weekDays as $day)
                    <div class="rounded-2xl p-3.5 border transition-all flex flex-col justify-between min-h-[140px] {{ $day['has_workout'] ? 'bg-gradient-to-b from-emerald-50/70 to-teal-50/40 dark:from-emerald-950/40 dark:to-teal-950/20 border-emerald-300 dark:border-emerald-700/60 shadow-xs' : 'bg-gray-50 dark:bg-gray-800/40 border-gray-200/70 dark:border-gray-700/50' }} {{ $day['is_today'] ? 'ring-2 ring-emerald-500/50' : '' }}">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-black {{ $day['is_today'] ? 'text-emerald-600 dark:text-emerald-400 font-extrabold' : 'text-gray-700 dark:text-gray-300' }}">
                                    {{ $day['day_name'] }}
                                </span>
                                <span class="text-[10px] text-gray-400 font-mono">
                                    {{ $day['date_formatted'] }}
                                </span>
                            </div>

                            @if($day['has_workout'])
                                <div class="space-y-1.5 mt-2">
                                    @foreach($day['exercises'] as $exItem)
                                        <div class="text-[11px] font-bold text-emerald-900 dark:text-emerald-200 bg-white/80 dark:bg-emerald-900/40 px-2 py-1 rounded-lg border border-emerald-500/20 leading-tight">
                                            {{ $exItem }}
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-4 flex flex-col items-center justify-center text-center text-gray-400 py-2">
                                    <span class="text-lg opacity-40">💤</span>
                                    <span class="text-[10px] font-bold mt-1 text-gray-400 dark:text-gray-500">{{ __('Rest Day') }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="mt-3 pt-2 border-t {{ $day['has_workout'] ? 'border-emerald-200/60 dark:border-emerald-800/40' : 'border-gray-200/50 dark:border-gray-700/30' }} flex items-center justify-between text-[10px]">
                            @if($day['has_workout'])
                                <span class="font-black text-emerald-700 dark:text-emerald-400">✅ {{ $day['sessions_count'] }} sesi</span>
                            @else
                                <span class="text-gray-400 italic">{{ __('Pemulihan') }}</span>
                            @endif
                            @if($day['is_today'])
                                <span class="px-1.5 py-0.2 rounded bg-emerald-500 text-white font-black text-[9px] uppercase tracking-wider">{{ __('Hari Ini') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- VIEW 2: SEBULAN (Breakdown 4 Pekan W1-W4 & Progress Tracking) -->
    @if($fitnessPeriod === 'month')
        <!-- 4 Weeks Overview Cards -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 space-y-4">
            <div>
                <h4 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                    <span>📊</span>
                    <span>{{ __('Volume Latihan 4 Pekan (Minggu 1 s/d Minggu 4)') }}</span>
                </h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ __('Bandingkan konsistensi dan volume latihanmu dari pekan ke pekan.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach($monthWeeks as $wNum => $wData)
                    <div class="bg-gray-50 dark:bg-gray-800/60 rounded-2xl p-4 border border-gray-200/70 dark:border-gray-700/50 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-gray-900 dark:text-white">{{ $wData['label'] }}</span>
                            <span class="text-[10px] font-black px-2 py-0.5 rounded-full {{ $wData['sessions'] > 0 ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-gray-200 dark:bg-gray-700 text-gray-500' }}">
                                {{ $wData['sessions'] }} Sesi
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                            <div class="p-2 bg-white dark:bg-gray-750 rounded-xl border border-gray-100 dark:border-gray-700">
                                <span class="text-[10px] text-gray-400 block">{{ __('Lari/Kardio') }}</span>
                                <strong class="text-gray-900 dark:text-white font-black text-sm">{{ round($wData['distance'], 1) }} km</strong>
                            </div>
                            <div class="p-2 bg-white dark:bg-gray-750 rounded-xl border border-gray-100 dark:border-gray-700">
                                <span class="text-[10px] text-gray-400 block">{{ __('Reps Kalistenik') }}</span>
                                <strong class="text-gray-900 dark:text-white font-black text-sm">{{ number_format($wData['reps'], 0, ',', '.') }}</strong>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Progress Tracking per Gerakan (Push Up, Lari, dll.) -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 space-y-4">
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <div>
                    <h4 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <span>🚀</span>
                        <span>{{ __('Analisis Progres Latihan per Gerakan (Minggu 1 vs Minggu 4)') }}</span>
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ __('Evaluasi kenaikan repetisi, jarak, atau beban dari awal hingga akhir bulan.') }}
                    </p>
                </div>
            </div>

            @if(count($progressInsights) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                    @foreach($progressInsights as $prog)
                        <div class="bg-gradient-to-br from-gray-50 to-gray-100/50 dark:from-gray-800/80 dark:to-gray-850 rounded-2xl p-4 sm:p-5 border border-gray-200/80 dark:border-gray-700/70 shadow-xs space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h5 class="text-sm sm:text-base font-black text-gray-900 dark:text-white flex items-center gap-2">
                                        <span>{{ ($prog['unit'] === 'km') ? '🏃‍♂️' : (($prog['unit'] === 'bw') ? '💪' : '🏋️') }}</span>
                                        <span>{{ $prog['name'] }}</span>
                                    </h5>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                                        {{ $prog['total_sessions'] }}x tercatat bulan ini
                                    </span>
                                </div>
                                <span class="inline-flex items-center gap-1 text-[11px] font-black px-2.5 py-1 rounded-xl shadow-xs border {{ $prog['trend'] === 'up' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border-emerald-400/30' : ($prog['trend'] === 'down' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border-rose-400/30' : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border-gray-400/20') }}">
                                    {{ $prog['badge'] }}
                                </span>
                            </div>

                            <!-- 4 Weeks Comparison Bars -->
                            <div class="grid grid-cols-4 gap-2 pt-2 text-center">
                                <div class="p-2 rounded-xl bg-white dark:bg-gray-750 border border-gray-100 dark:border-gray-700">
                                    <div class="text-[9px] font-bold text-gray-400 uppercase">W1 (1-7)</div>
                                    <div class="text-xs sm:text-sm font-black text-gray-900 dark:text-white mt-0.5">
                                        {{ $prog['weeks']['w1'] }} <span class="text-[9px] font-normal text-gray-400">{{ $prog['unit_label'] }}</span>
                                    </div>
                                </div>
                                <div class="p-2 rounded-xl bg-white dark:bg-gray-750 border border-gray-100 dark:border-gray-700">
                                    <div class="text-[9px] font-bold text-gray-400 uppercase">W2 (8-14)</div>
                                    <div class="text-xs sm:text-sm font-black text-gray-900 dark:text-white mt-0.5">
                                        {{ $prog['weeks']['w2'] }} <span class="text-[9px] font-normal text-gray-400">{{ $prog['unit_label'] }}</span>
                                    </div>
                                </div>
                                <div class="p-2 rounded-xl bg-white dark:bg-gray-750 border border-gray-100 dark:border-gray-700">
                                    <div class="text-[9px] font-bold text-gray-400 uppercase">W3 (15-21)</div>
                                    <div class="text-xs sm:text-sm font-black text-gray-900 dark:text-white mt-0.5">
                                        {{ $prog['weeks']['w3'] }} <span class="text-[9px] font-normal text-gray-400">{{ $prog['unit_label'] }}</span>
                                    </div>
                                </div>
                                <div class="p-2 rounded-xl bg-white dark:bg-gray-750 border border-gray-100 dark:border-gray-700 {{ $prog['trend'] === 'up' ? 'ring-2 ring-emerald-500/40 bg-emerald-50/30' : '' }}">
                                    <div class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 uppercase">W4 (22-end)</div>
                                    <div class="text-xs sm:text-sm font-black text-gray-900 dark:text-white mt-0.5">
                                        {{ $prog['weeks']['w4'] }} <span class="text-[9px] font-normal text-gray-400">{{ $prog['unit_label'] }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Narrative Conclusion -->
                            <div class="text-[11px] text-gray-600 dark:text-gray-400 font-medium bg-white/60 dark:bg-gray-750/60 p-2.5 rounded-xl border border-gray-150 dark:border-gray-700/60 leading-relaxed">
                                @if($prog['trend'] === 'up')
                                    🔥 <strong>Progres Menanjak!</strong> Terjadi peningkatan volume +{{ $prog['pct_change'] }}% dari awal bulan. Kekuatan &amp; kapasitas fisikmu terus bertambah!
                                @elseif($prog['trend'] === 'down')
                                    ⚠️ <strong>Volume Menurun.</strong> Terjadi penurunan volume dibanding awal bulan. Pastikan waktu tidur cukup dan jaga jadwal latihanmu.
                                @else
                                    🎯 <strong>Konsistensi Stabil.</strong> Kamu berhasil mempertahankan volume latihan mingguan secara teratur dan stabil.
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 bg-gray-50 dark:bg-gray-800/40 rounded-2xl border border-dashed border-gray-300 dark:border-gray-700">
                    <span class="text-3xl block mb-2">🏃‍♂️</span>
                    <h5 class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ __('Belum Ada Data Latihan Bulan Ini') }}</h5>
                    <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto mt-1">
                        {{ __('Catat aktivitas olahragamu (seperti Lari atau Push Up) untuk melihat perbandingan otomatis progres 4 pekan.') }}
                    </p>
                    <button type="button" wire:click="openWorkoutModal" class="mt-3 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer">
                        {{ __('+ Catat Olahraga Sekarang') }}
                    </button>
                </div>
            @endif
        </div>
    @endif

    <!-- VIEW 3: SETAHUN (12 Bulan & Personal Record) -->
    @if($fitnessPeriod === 'year')
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 space-y-4">
            <div>
                <h4 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                    <span>🗓️</span>
                    <span>{{ __('Distribusi Latihan 12 Bulan (Januari – Desember ' . now()->year . ')') }}</span>
                </h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ __('Tinjau rekam jejak konsistensi kebugaran fisikmu sepanjang tahun.') }}
                </p>
            </div>

            <!-- 12 Months Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach($yearMonths as $m)
                    <div class="rounded-2xl p-3.5 border transition-all {{ $m['is_current'] ? 'bg-emerald-50/60 dark:bg-emerald-950/40 border-emerald-400 dark:border-emerald-600 shadow-xs' : 'bg-gray-50 dark:bg-gray-800/50 border-gray-200/70 dark:border-gray-700/50' }}">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-black {{ $m['is_current'] ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-800 dark:text-gray-200' }}">
                                {{ $m['month_name'] }}
                            </span>
                            @if($m['is_current'])
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            @endif
                        </div>
                        <div class="text-lg font-black text-gray-900 dark:text-white">
                            {{ $m['sessions_count'] }} <span class="text-[10px] text-gray-400 font-normal">sesi</span>
                        </div>
                        <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-1 space-y-0.5">
                            @if($m['distance_km'] > 0)
                                <div class="text-cyan-600 dark:text-cyan-400 font-bold">🏃 {{ $m['distance_km'] }} km</div>
                            @endif
                            @if($m['reps'] > 0)
                                <div class="text-amber-600 dark:text-amber-400 font-bold">💪 {{ $m['reps'] }} reps</div>
                            @endif
                            @if($m['distance_km'] == 0 && $m['reps'] == 0 && $m['sessions_count'] == 0)
                                <div class="text-gray-400">-</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Personal Records (PR) Hall of Fame -->
        <div class="bg-gradient-to-br from-amber-500/10 via-orange-500/5 to-transparent border border-amber-200 dark:border-amber-800/60 rounded-3xl p-5 sm:p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-base sm:text-lg font-black text-amber-900 dark:text-amber-200 flex items-center gap-2">
                        <span>🏆</span>
                        <span>{{ __('Rekor Pribadi Terbaik (Personal Records / PR)') }}</span>
                    </h4>
                    <p class="text-xs text-amber-700/80 dark:text-amber-400/80 mt-0.5">
                        {{ __('Pencapaian fisik tertinggi yang berhasil kamu ukir dalam satu sesi.') }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-amber-200/80 dark:border-amber-800/40 shadow-xs">
                    <span class="text-[10px] font-black text-amber-700 dark:text-amber-400 uppercase tracking-wider block">🏃 Jarak Lari Terjauh</span>
                    <div class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white mt-1">
                        {{ $fitnessData['pr_longest_run_km'] ?? 0 }} <span class="text-xs font-bold text-gray-400">km</span>
                    </div>
                    <span class="text-[10px] text-gray-400 mt-0.5 block">{{ __('Dalam 1 sesi lari') }}</span>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-amber-200/80 dark:border-amber-800/40 shadow-xs">
                    <span class="text-[10px] font-black text-amber-700 dark:text-amber-400 uppercase tracking-wider block">💪 Reps Terbanyak 1 Sesi</span>
                    <div class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white mt-1">
                        {{ number_format($fitnessData['pr_max_reps'] ?? 0, 0, ',', '.') }} <span class="text-xs font-bold text-gray-400">reps</span>
                    </div>
                    <span class="text-[10px] text-gray-400 mt-0.5 block">{{ __('Akumulasi set dalam 1 sesi') }}</span>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-amber-200/80 dark:border-amber-800/40 shadow-xs">
                    <span class="text-[10px] font-black text-amber-700 dark:text-amber-400 uppercase tracking-wider block">⏱️ Durasi Latihan Terlama</span>
                    <div class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white mt-1">
                        {{ $fitnessData['pr_max_duration_min'] ?? 0 }} <span class="text-xs font-bold text-gray-400">menit</span>
                    </div>
                    <span class="text-[10px] text-gray-400 mt-0.5 block">{{ __('Stamina endurance puncak') }}</span>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-amber-200/80 dark:border-amber-800/40 shadow-xs">
                    <span class="text-[10px] font-black text-amber-700 dark:text-amber-400 uppercase tracking-wider block">🏋️ Beban Terberat</span>
                    <div class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white mt-1">
                        {{ $fitnessData['pr_max_weight_kg'] ?? 0 }} <span class="text-xs font-bold text-gray-400">kg</span>
                    </div>
                    <span class="text-[10px] text-gray-400 mt-0.5 block">{{ __('Rekor angkat beban gym') }}</span>
                </div>
            </div>
        </div>
    @endif

    <!-- Riwayat Sesi Latihan Terbaru (Timeline Log) -->
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-xs border border-gray-200/80 dark:border-gray-700/60 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                    <span>📜</span>
                    <span>{{ __('Riwayat Catatan Latihan') }}</span>
                </h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ __('Daftar sesi olahraga yang telah kamu catat.') }}
                </p>
            </div>
            <span class="text-xs font-bold text-gray-400">
                {{ $recentSessions->count() }} Sesi Terbaru
            </span>
        </div>

        @if($recentSessions->count() > 0)
            <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                @foreach($recentSessions as $session)
                    <div class="py-3.5 flex items-center justify-between gap-3 group">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg font-bold shrink-0 border border-emerald-500/20">
                                @if(str_contains(strtolower($session->title), 'lari') || str_contains(strtolower($session->title), 'run'))
                                    🏃‍♂️
                                @elseif(str_contains(strtolower($session->title), 'sepeda') || str_contains(strtolower($session->title), 'cycle'))
                                    🚴‍♂️
                                @elseif(str_contains(strtolower($session->title), 'renang') || str_contains(strtolower($session->title), 'swim'))
                                    🏊‍♂️
                                @else
                                    💪
                                @endif
                            </div>
                            <div class="min-w-0">
                                <h5 class="text-sm font-black text-gray-900 dark:text-white truncate">
                                    {{ $session->title }}
                                </h5>
                                <div class="flex items-center gap-2 flex-wrap text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    <span>🗓️ {{ \Carbon\Carbon::parse($session->completed_at ?? $session->created_at)->translatedFormat('l, d M Y') }}</span>
                                    @if($session->duration_seconds > 0)
                                        <span>• ⏱️ {{ round($session->duration_seconds / 60) }} mnt</span>
                                    @endif
                                    @foreach($session->exercises as $ex)
                                        <span class="font-bold text-emerald-600 dark:text-emerald-400">
                                            • {{ $ex->exercise_name }}
                                            @if($ex->weight_unit === 'km')
                                                ({{ $ex->sets->first()?->weight_kg ?? $session->total_volume_kg }} km)
                                            @elseif($ex->weight_unit === 'bw')
                                                ({{ $ex->sets->sum('reps') }} reps)
                                            @else
                                                ({{ $ex->sets->count() }} set)
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                                @if(!empty($session->notes))
                                    <p class="text-xs text-gray-400 dark:text-gray-500 italic mt-1 truncate">
                                        "{{ $session->notes }}"
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            @if($session->xp_earned > 0)
                                <span class="text-[10px] font-black px-2 py-0.5 rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                                    +{{ $session->xp_earned }} XP
                                </span>
                            @endif
                            <button type="button"
                                    wire:click="deleteWorkoutSession({{ $session->id }})"
                                    wire:confirm="Hapus catatan latihan ini?"
                                    class="text-gray-400 hover:text-rose-500 p-1.5 rounded-lg transition-colors cursor-pointer text-xs"
                                    title="Hapus sesi">
                                🗑️
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8 text-gray-400 text-xs">
                {{ __('Belum ada riwayat latihan. Klik tombol "+ Catat Olahraga" di atas untuk mencatat olahraga pertamamu!') }}
            </div>
        @endif
    </div>
</div>
