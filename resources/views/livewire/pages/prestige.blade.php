<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Services\PrestigeService;
use App\Services\RpgStatsService;
use App\Services\GamificationService;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public $user;
    public array $tiers = [];
    public array $levelProgress = [];
    public array $rpgStats = [];
    public array $dailyExp = [];
    public int $selectedCalcLevel = 1;
    public string $currentSeasonName = '';
    public bool $showPendingModal = false;
    public array $pendingBreakdown = [];

    public function mount()
    {
        $this->refreshData();
        $this->selectedCalcLevel = $this->user->prestige_level ?? 1;
    }

    public function refreshData()
    {
        $this->user = auth()->user();
        $this->tiers = PrestigeService::TIERS;
        $this->levelProgress = PrestigeService::getLevelProgress($this->user);
        $this->rpgStats = RpgStatsService::calculateAllStats($this->user);
        $this->dailyExp = GamificationService::getDailyExpBreakdown($this->user);
        $this->currentSeasonName = PrestigeService::getCurrentSeasonName();
    }

    public function openPendingBreakdown()
    {
        $this->pendingBreakdown = \App\Services\PendingXpService::getBreakdown(auth()->user());
        $this->showPendingModal = true;
    }

    public function closePendingBreakdown()
    {
        $this->showPendingModal = false;
    }

    public function getCalcXpProperty(): int
    {
        return PrestigeService::xpRequiredForLevel($this->selectedCalcLevel);
    }

    public function getCumulativeXpProperty(): int
    {
        $sum = 0;
        for ($i = 1; $i <= $this->selectedCalcLevel; $i++) {
            $sum += PrestigeService::xpRequiredForLevel($i);
        }
        return $sum;
    }
}; ?>

<div class="max-w-6xl mx-auto space-y-8 pb-16">
    <!-- Breadcrumb & Page Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center text-xs font-semibold text-gray-500 hover:text-atoma-blue dark:text-gray-400 dark:hover:text-atoma-cyan transition-colors mb-2 group">
                <svg class="w-4 h-4 mr-1 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('Kembali ke Tasks') }}
            </a>
            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-gray-100 flex items-center gap-2.5">
                <div class="w-8 h-8 flex items-center justify-center p-1 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 border border-emerald-500/20 shrink-0">
                    <img src="{{ asset('images/prestige/tier_1_novice.svg') }}" alt="Prestige" class="w-full h-full object-contain">
                </div>
                <span>{{ __('Sistem Level Up & Prestige') }}</span>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ __('Panduan perkembangan tingkat produktivitas, kurva EXP, dan aturan siklus H+1 Settlement di ATOMA.') }}
            </p>
        </div>

        <!-- Quick Action / Badge Status -->
        @php
            $activeTier = PrestigeService::getTierDetails($user->prestige_tier ?? 1);
        @endphp
        <div class="inline-flex items-center gap-2.5 px-3.5 py-2 rounded-2xl {{ $activeTier['bg'] }} border {{ $activeTier['border'] }} shadow-xs">
            <div class="w-7 h-7 flex items-center justify-center shrink-0">
                <img src="{{ asset($activeTier['svg']) }}" alt="{{ $activeTier['name'] }}" class="w-full h-full object-contain drop-shadow-xs">
            </div>
            <div>
                <div class="text-xs font-black {{ $activeTier['text'] }}">
                    {{ __($activeTier['name']) }} &bull; Lv.{{ $user->prestige_level ?? 1 }}
                </div>
                <div class="text-[10px] text-gray-500 dark:text-gray-400">
                    {{ __('Tier :current dari :max', ['current' => $user->prestige_tier ?? 1, 'max' => PrestigeService::MAX_TIER]) }}
                </div>
            </div>
        </div>
    </div>

    <!-- HERO STANDING CARD -->
    <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-md p-6 sm:p-8">
        <!-- Ambient Background Glow -->
        <div class="absolute -right-20 -top-20 w-80 h-80 bg-gradient-to-br {{ $activeTier['gradient'] }} opacity-15 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <!-- Left: Avatar & Prestige Identity -->
            <div class="flex items-center gap-4 sm:gap-5">
                @php
                    $activeFrame = $user->active_frame ?? null;
                    $frameClass = 'border-4 border-atoma-aqua/40 dark:border-atoma-blue/40 shadow-sm';
                    if ($activeFrame === 'frame_neon') {
                        $frameClass = 'border-4 border-cyan-400 ring-2 ring-cyan-400/50 shadow-[0_0_15px_rgba(6,182,212,0.8)]';
                    } elseif ($activeFrame === 'frame_fire') {
                        $frameClass = 'border-4 border-orange-500 ring-2 ring-amber-500/50 shadow-[0_0_15px_rgba(249,115,22,0.8)] animate-pulse';
                    } elseif ($activeFrame === 'frame_gold') {
                        $frameClass = 'border-4 border-amber-400 ring-2 ring-yellow-300/80 shadow-[0_0_18px_rgba(245,158,11,0.8)]';
                    }
                @endphp
                <div class="relative shrink-0 aspect-square">
                    @if($activeFrame === 'frame_gold')
                        <div class="absolute -top-[28%] left-1/2 -translate-x-1/2 w-[55%] z-20 pointer-events-none drop-shadow-md">
                            <img src="{{ asset('images/frames/crown_gold.svg') }}" alt="Crown" class="w-full h-auto object-contain">
                        </div>
                    @endif
                    <div class="h-20 w-20 sm:h-24 sm:w-24 aspect-square shrink-0 rounded-full {{ $frameClass }} overflow-hidden bg-white dark:bg-gray-700">
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-full h-full aspect-square object-cover">
                    </div>
                </div>

                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-gray-100">{{ $user->name }}</h2>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-bold rounded-full {{ $activeTier['bg'] }} {{ $activeTier['text'] }} border {{ $activeTier['border'] }}">
                            <img src="{{ asset($activeTier['svg']) }}" alt="{{ $activeTier['name'] }}" class="w-3.5 h-3.5 object-contain">
                            <span>{{ __($activeTier['name']) }}</span>
                        </span>
                    </div>
                    <p class="text-sm font-semibold text-atoma-blue dark:text-atoma-cyan mt-1">
                        {{ __('Tingkat Level: Lv.:level / :max', ['level' => $user->prestige_level ?? 1, 'max' => PrestigeService::MAX_LEVEL]) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ __('Musim Saat Ini:') }} <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $currentSeasonName }}</span>
                    </p>
                </div>
            </div>

            <!-- Right: Level Progress Bar & Detailed Counts -->
            <div class="w-full md:w-80 flex flex-col gap-2">
                <div class="flex items-center justify-between text-xs font-bold text-gray-600 dark:text-gray-400">
                    <span>{{ __('Progress Menuju Lv.:level', ['level' => min(PrestigeService::MAX_LEVEL, ($user->prestige_level ?? 1) + 1)]) }}</span>
                    <span class="text-indigo-600 dark:text-indigo-400 font-extrabold">{{ $levelProgress['percentage'] }}%</span>
                </div>
                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-3.5 overflow-hidden p-0.5 border border-gray-200/80 dark:border-gray-600/80 relative shadow-inner">
                    <div class="bg-gradient-to-r {{ $activeTier['gradient'] }} h-full rounded-full transition-all duration-1000 shadow-sm" style="width: {{ $levelProgress['percentage'] }}%"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                    <span>{{ number_format($levelProgress['current_xp']) }} EXP</span>
                    <span>{{ __('Target: :xp EXP', ['xp' => number_format($levelProgress['required_xp'])]) }}</span>
                </div>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 pt-6 border-t border-gray-100 dark:border-gray-700/80">
            <!-- Pending XP Today (Clickable for Breakdown Modal) -->
            <button type="button" 
                    wire:click="openPendingBreakdown" 
                    class="w-full text-center bg-emerald-50/70 dark:bg-emerald-950/30 hover:bg-emerald-100/70 dark:hover:bg-emerald-900/50 border border-emerald-200/80 dark:border-emerald-800/60 rounded-2xl p-3 sm:p-4 transition-all duration-200 hover:shadow-md hover:scale-[1.02] active:scale-95 cursor-pointer group relative overflow-hidden"
                    title="{{ __('Klik untuk melihat rincian sumber EXP pending hari ini') }}">
                <div class="flex items-center justify-center gap-1 text-xs font-bold text-emerald-800 dark:text-emerald-300">
                    <span>{{ __('Pending Hari Ini') }}</span>
                    <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1 flex items-center justify-center gap-1">
                    <span>+{{ (int)($user->pending_xp ?? 0) }}</span>
                    <span class="text-xs font-bold">XP</span>
                </div>
                <div class="text-[10px] text-emerald-700/80 dark:text-emerald-400/80 mt-0.5">
                    {{ __('Cair otomatis tengah malam') }}
                </div>
                <div class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 group-hover:bg-emerald-500 group-hover:text-white transition-colors">
                    <x-icon.search class="w-2.5 h-2.5" />
                    <span>{{ __('Lihat Rincian') }}</span>
                </div>
            </button>

            <!-- Settlement Countdown -->
            <div x-data="{
                remaining: '',
                calc() {
                    const now = new Date();
                    const midnight = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 0, 0, 0);
                    const diff = Math.max(0, Math.floor((midnight - now) / 1000));
                    const h = Math.floor(diff / 3600);
                    const m = Math.floor((diff % 3600) / 60);
                    this.remaining = `${h}j ${m}m`;
                },
                init() {
                    this.calc();
                    setInterval(() => this.calc(), 60000);
                }
            }" class="bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200/80 dark:border-indigo-800/60 rounded-2xl p-3 sm:p-4 text-center">
                <div class="text-xs font-bold text-indigo-800 dark:text-indigo-300">{{ __('Countdown Cair') }}</div>
                <div class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1 flex items-center justify-center gap-1">
                    <svg class="w-4 h-4 text-indigo-500 animate-spin" style="animation-duration: 8s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="remaining">~</span>
                </div>
                <div class="text-[10px] text-indigo-700/80 dark:text-indigo-400/80 mt-0.5">
                    {{ __('Midnight Settlement H+1') }}
                </div>
            </div>

            <!-- Lifetime XP -->
            <div class="bg-gray-50 dark:bg-gray-700/40 border border-gray-200/80 dark:border-gray-600/60 rounded-2xl p-3 sm:p-4 text-center">
                <div class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ __('Total Lifetime XP') }}</div>
                <div class="text-xl sm:text-2xl font-black text-gray-900 dark:text-gray-100 mt-1">
                    {{ number_format($user->lifetime_xp ?? $user->xp) }}
                </div>
                <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ __('Akumulasi seumur hidup') }}
                </div>
            </div>

            <!-- Total Tasks Completed -->
            <div class="bg-gray-50 dark:bg-gray-700/40 border border-gray-200/80 dark:border-gray-600/60 rounded-2xl p-3 sm:p-4 text-center">
                <div class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ __('Tugas Diselesaikan') }}</div>
                <div class="text-xl sm:text-2xl font-black text-gray-900 dark:text-gray-100 mt-1">
                    {{ number_format($user->total_tasks_completed ?? 0) }}
                </div>
                <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ __('Total task terselesaikan') }}
                </div>
            </div>
        </div>
    </div>

    <!-- DAILY EXP BREAKDOWN & PROGRESS BARS -->
    <div class="rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-md p-6 sm:p-8 space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg sm:text-xl font-black text-gray-900 dark:text-gray-100 flex items-center gap-2.5">
                    <div class="w-7 h-7 flex items-center justify-center p-1 rounded-xl bg-blue-500/10 dark:bg-blue-500/20 border border-blue-500/20 text-blue-600 dark:text-blue-400 shrink-0">
                        <svg class="w-full h-full" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                        </svg>
                    </div>
                    <span>{{ __('Alokasi & Progress Batas EXP Harian') }}</span>
                </h2>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ __('Batas perolehan EXP harian dirancang sehat untuk mendorong konsistensi rutin tanpa eksploitasi (Maksimal 500 EXP rutin + 100 EXP Misi Rahasia).') }}
                </p>
            </div>

            <!-- Total Routine Badge -->
            <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200/80 dark:border-indigo-800/60 shadow-xs self-start sm:self-auto">
                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">{{ __('Total Rutin Hari Ini:') }}</span>
                <span class="text-sm font-black text-indigo-600 dark:text-indigo-400">
                    {{ $dailyExp['total_routine']['current'] ?? 0 }} / 500 <span class="text-[10px] font-bold">EXP</span>
                </span>
            </div>
        </div>

        <!-- Master Routine Progress Bar -->
        <div class="space-y-1.5">
            <div class="flex items-center justify-between text-xs font-bold text-gray-600 dark:text-gray-400">
                <span>{{ __('Total Batas Harian Tercapai') }}</span>
                <span class="text-indigo-600 dark:text-indigo-400 font-extrabold">{{ $dailyExp['total_routine']['percentage'] ?? 0 }}%</span>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-700/60 rounded-full h-3 overflow-hidden p-0.5 border border-gray-200/80 dark:border-gray-600/80 relative shadow-inner">
                <div class="bg-gradient-to-r from-blue-500 via-indigo-500 to-emerald-500 h-full rounded-full transition-all duration-700 shadow-sm" style="width: {{ $dailyExp['total_routine']['percentage'] ?? 0 }}%"></div>
            </div>
        </div>

        <!-- 5 Category Progress Bars Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
            <!-- 1. Task Harian -->
            <div class="bg-gray-50/70 dark:bg-gray-700/30 border border-gray-200/70 dark:border-gray-700/70 rounded-2xl p-4 flex flex-col justify-between space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">📋</span>
                        <div>
                            <div class="text-xs font-bold text-gray-900 dark:text-gray-100">{{ __('Tugas Harian') }}</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">35 High • 30 Med • 25 Low</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-black text-emerald-600 dark:text-emerald-400">
                            {{ $dailyExp['task']['current'] ?? 0 }} <span class="text-gray-400 dark:text-gray-500 font-normal">/ {{ $dailyExp['task']['cap'] ?? 250 }}</span>
                        </div>
                        <div class="text-[10px] font-bold text-gray-500">{{ $dailyExp['task']['percentage'] ?? 0 }}%</div>
                    </div>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden shadow-inner">
                    <div class="bg-gradient-to-r from-emerald-500 to-teal-400 h-full rounded-full transition-all duration-500" style="width: {{ $dailyExp['task']['percentage'] ?? 0 }}%"></div>
                </div>
            </div>

            <!-- 2. Pomodoro Fokus -->
            <div class="bg-gray-50/70 dark:bg-gray-700/30 border border-gray-200/70 dark:border-gray-700/70 rounded-2xl p-4 flex flex-col justify-between space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🍅</span>
                        <div>
                            <div class="text-xs font-bold text-gray-900 dark:text-gray-100">{{ __('Fokus Pomodoro') }}</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ $dailyExp['pomodoro']['minutes'] ?? 0 }}m / 60m • 2.5 EXP/m</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-black text-rose-600 dark:text-rose-400">
                            {{ $dailyExp['pomodoro']['current'] ?? 0 }} <span class="text-gray-400 dark:text-gray-500 font-normal">/ {{ $dailyExp['pomodoro']['cap'] ?? 150 }}</span>
                        </div>
                        <div class="text-[10px] font-bold text-gray-500">{{ $dailyExp['pomodoro']['percentage'] ?? 0 }}%</div>
                    </div>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden shadow-inner">
                    <div class="bg-gradient-to-r from-rose-500 to-red-400 h-full rounded-full transition-all duration-500" style="width: {{ $dailyExp['pomodoro']['percentage'] ?? 0 }}%"></div>
                </div>
            </div>

            <!-- 3. Flashcard Review -->
            <div class="bg-gray-50/70 dark:bg-gray-700/30 border border-gray-200/70 dark:border-gray-700/70 rounded-2xl p-4 flex flex-col justify-between space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🗂️</span>
                        <div>
                            <div class="text-xs font-bold text-gray-900 dark:text-gray-100">{{ __('Flashcard Review') }}</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ $dailyExp['flashcard']['cards'] ?? 0 }} / 10 {{ __('Kartu') }} • 5 EXP/k</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-black text-purple-600 dark:text-purple-400">
                            {{ $dailyExp['flashcard']['current'] ?? 0 }} <span class="text-gray-400 dark:text-gray-500 font-normal">/ {{ $dailyExp['flashcard']['cap'] ?? 50 }}</span>
                        </div>
                        <div class="text-[10px] font-bold text-gray-500">{{ $dailyExp['flashcard']['percentage'] ?? 0 }}%</div>
                    </div>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden shadow-inner">
                    <div class="bg-gradient-to-r from-purple-500 to-indigo-400 h-full rounded-full transition-all duration-500" style="width: {{ $dailyExp['flashcard']['percentage'] ?? 0 }}%"></div>
                </div>
            </div>

            <!-- 4. Jurnal Refleksi -->
            <div class="bg-gray-50/70 dark:bg-gray-700/30 border border-gray-200/70 dark:border-gray-700/70 rounded-2xl p-4 flex flex-col justify-between space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">📖</span>
                        <div>
                            <div class="text-xs font-bold text-gray-900 dark:text-gray-100">{{ __('Jurnal & Refleksi') }}</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('1 Refleksi/hari (≥ 50 char)') }}</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-black text-amber-600 dark:text-amber-400">
                            {{ $dailyExp['journal']['current'] ?? 0 }} <span class="text-gray-400 dark:text-gray-500 font-normal">/ {{ $dailyExp['journal']['cap'] ?? 25 }}</span>
                        </div>
                        <div class="text-[10px] font-bold text-gray-500">{{ $dailyExp['journal']['percentage'] ?? 0 }}%</div>
                    </div>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden shadow-inner">
                    <div class="bg-gradient-to-r from-amber-500 to-yellow-400 h-full rounded-full transition-all duration-500" style="width: {{ $dailyExp['journal']['percentage'] ?? 0 }}%"></div>
                </div>
            </div>

            <!-- 5. Subtask Selesai -->
            <div class="bg-gray-50/70 dark:bg-gray-700/30 border border-gray-200/70 dark:border-gray-700/70 rounded-2xl p-4 flex flex-col justify-between space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🧩</span>
                        <div>
                            <div class="text-xs font-bold text-gray-900 dark:text-gray-100">{{ __('Subtask Selesai') }}</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ $dailyExp['subtask']['count'] ?? 0 }} / 5 {{ __('Subtask') }} • 5 EXP/st</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-black text-sky-600 dark:text-sky-400">
                            {{ $dailyExp['subtask']['current'] ?? 0 }} <span class="text-gray-400 dark:text-gray-500 font-normal">/ {{ $dailyExp['subtask']['cap'] ?? 25 }}</span>
                        </div>
                        <div class="text-[10px] font-bold text-gray-500">{{ $dailyExp['subtask']['percentage'] ?? 0 }}%</div>
                    </div>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden shadow-inner">
                    <div class="bg-gradient-to-r from-sky-500 to-cyan-400 h-full rounded-full transition-all duration-500" style="width: {{ $dailyExp['subtask']['percentage'] ?? 0 }}%"></div>
                </div>
            </div>

            <!-- 6. Mystery Daily Hidden Mission Card -->
            @php
                $hiddenMission = $dailyExp['hidden_mission'] ?? null;
                $isMissionUnlocked = !empty($hiddenMission['is_unlocked']);
                $metCount = 0;
                if (!empty($hiddenMission['criteria_status'])) {
                    foreach ($hiddenMission['criteria_status'] as $cs) {
                        if (!empty($cs['met'])) $metCount++;
                    }
                }
            @endphp
            <div class="relative overflow-hidden rounded-2xl p-4 flex flex-col justify-between space-y-3 border transition-all {{ $isMissionUnlocked ? 'bg-gradient-to-br from-amber-50 to-yellow-100/60 dark:from-amber-950/40 dark:to-yellow-900/30 border-amber-300 dark:border-amber-700 shadow-sm' : 'bg-gradient-to-br from-indigo-50/70 to-purple-50/50 dark:from-indigo-950/30 dark:to-purple-950/20 border-indigo-200/80 dark:border-indigo-800/60' }}">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 {{ $isMissionUnlocked ? 'bg-amber-500 text-white shadow-xs' : 'bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 border border-indigo-400/30' }}">
                            @if($isMissionUnlocked)
                                <span class="text-base">{{ $hiddenMission['icon'] ?? '⭐' }}</span>
                            @else
                                <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </div>
                        <div>
                            <div class="text-xs font-black {{ $isMissionUnlocked ? 'text-amber-900 dark:text-amber-200' : 'text-gray-900 dark:text-gray-100' }}">
                                @if($isMissionUnlocked)
                                    {{ __('Misi Rahasia:') }} {{ __($hiddenMission['name'] ?? 'Terbuka') }}
                                @else
                                    ??? {{ __('Misi Rahasia Harian') }}
                                @endif
                            </div>
                            <div class="text-[10px] {{ $isMissionUnlocked ? 'text-amber-800/80 dark:text-amber-300/80' : 'text-indigo-600 dark:text-indigo-400' }} font-semibold">
                                @if($isMissionUnlocked)
                                    {{ __($hiddenMission['criteria_text'] ?? 'Misi Selesai') }}
                                @else
                                    {{ __('Kombinasi 2 Aktivitas Acak') }}
                                @endif
                            </div>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black {{ $isMissionUnlocked ? 'bg-amber-200 text-amber-900 dark:bg-amber-800 dark:text-amber-100' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-200' }}">
                        +100 EXP
                    </span>
                </div>

                <div>
                    @if($isMissionUnlocked)
                        <div class="flex items-center gap-1.5 text-[11px] font-bold text-amber-800 dark:text-amber-300">
                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>{{ __('Misi rahasia hari ini sukses diungkap!') }}</span>
                        </div>
                    @else
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-[10px] text-gray-500 dark:text-gray-400 font-semibold">
                                <span>{{ __('Petunjuk: Maksimalkan 2 aktivitas') }}</span>
                                <span class="font-extrabold text-indigo-600 dark:text-indigo-400">{{ $metCount }} / 2 {{ __('Selesai') }}</span>
                            </div>
                            <div class="w-full bg-indigo-100 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-indigo-500 h-full rounded-full transition-all duration-500" style="width: {{ ($metCount / 2) * 100 }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- 5 PRESTIGE TIERS SECTION -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg sm:text-xl font-black text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    <div class="w-7 h-7 flex items-center justify-center p-1 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-500 shrink-0">
                        <svg class="w-full h-full" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6" />
                            <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18" />
                            <path d="M4 22h16" />
                            <path d="M10 14.66V17c0 .55-.45 1-1 1H7" />
                            <path d="M14 14.66V17c0 .55.45 1 1 1h2" />
                            <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z" fill="currentColor" fill-opacity="0.2"/>
                        </svg>
                    </div>
                    <span>{{ __('5 Tingkatan Prestige di ATOMA') }}</span>
                </h3>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                    {!! __('Setiap tier terdiri dari 50 level. Capai Level 50 untuk mengalami :ascension ke tingkatan berikutnya!', ['ascension' => '<b>Ascension</b>']) !!}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach($tiers as $tierNum => $t)
                @php
                    $isUserCurrentTier = ($user->prestige_tier ?? 1) === $tierNum;
                    $isPastTier = ($user->prestige_tier ?? 1) > $tierNum;
                @endphp
                <div class="relative rounded-3xl p-5 border transition-all {{ $isUserCurrentTier ? 'bg-white dark:bg-gray-800 border-2 ' . $t['border'] . ' shadow-lg ring-4 ring-' . $t['color'] . '-500/20' : 'bg-white/60 dark:bg-gray-800/60 border-gray-200/70 dark:border-gray-700/70 opacity-90' }} flex flex-col justify-between overflow-hidden group">
                    <!-- Subtle top glow for active tier -->
                    @if($isUserCurrentTier)
                        <div class="absolute top-0 inset-x-0 h-1.5 bg-gradient-to-r {{ $t['gradient'] }}"></div>
                        <div class="absolute top-3 right-3">
                            <span class="px-2 py-0.5 text-[9px] font-black uppercase rounded-full {{ $t['bg'] }} {{ $t['text'] }} border {{ $t['border'] }}">
                                {{ __('Tier Aktif') }}
                            </span>
                        </div>
                    @elseif($isPastTier)
                        <div class="absolute top-3 right-3">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[9px] font-bold rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                {{ __('Selesai') }}
                            </span>
                        </div>
                    @else
                        <div class="absolute top-3 right-3">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[9px] font-bold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-600">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                {{ __('Terkunci') }}
                            </span>
                        </div>
                    @endif

                    <div>
                        <div class="w-12 h-12 mb-3 p-1.5 rounded-2xl {{ $t['bg'] }} border {{ $t['border'] }} flex items-center justify-center shadow-xs group-hover:scale-110 transition-transform">
                            <img src="{{ asset($t['svg']) }}" alt="{{ $t['name'] }}" class="w-full h-full object-contain">
                        </div>
                        <h4 class="text-base font-black text-gray-900 dark:text-gray-100 flex items-center gap-1.5">
                            {{ __($t['name']) }}
                        </h4>
                        <div class="text-xs font-semibold {{ $t['text'] }} mt-0.5">
                            {{ __('Level 1 – 50') }}
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-3 leading-relaxed">
                            @if($tierNum === 1)
                                {{ __('Fase awal membiasakan rutinitas produktif harian. Tidak ada penalti kualitas tugas sederhana.') }}
                            @elseif($tierNum === 2)
                                {{ __('Mulai menuntut ketajaman kerja. Tugas dengan subtask & estimasi waktu mulai diapresiasi tinggi.') }}
                            @elseif($tierNum === 3)
                                {{ __('Disiplin mandiri tingkat menengah. Pemanfaatan Pomodoro session memberikan bonus EXP signifikan.') }}
                            @elseif($tierNum === 4)
                                {{ __('Eksekusi tingkat master. Konsistensi tinggi dan perencanaan terstruktur H-1 diutamakan.') }}
                            @elseif($tierNum === 5)
                                {{ __('Puncak produktivitas tertinggi di ATOMA. Mahkota emas dan kehormatan abadi bagi sang master fokus.') }}
                            @endif
                        </p>
                    </div>

                    <div class="mt-5 pt-3 border-t border-gray-100 dark:border-gray-700/80 text-[11px] font-medium text-gray-500 dark:text-gray-400">
                        @if($isUserCurrentTier)
                            <div class="flex items-center justify-between font-bold text-gray-700 dark:text-gray-300">
                                <span>{{ __('Level Saat Ini:') }}</span>
                                <span class="{{ $t['text'] }}">Lv.{{ $user->prestige_level ?? 1 }}</span>
                            </div>
                        @else
                            <span>{{ __('50 Level per Tier') }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- INTERACTIVE EXP CALCULATOR & FORMULA -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Calculator Interactive Card -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-3xl border border-gray-200/80 dark:border-gray-700/80 shadow-md p-6 sm:p-7 space-y-5">
            <div>
                <h3 class="text-lg sm:text-xl font-black text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    <div class="w-7 h-7 flex items-center justify-center p-1 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-600 dark:text-indigo-400 shrink-0">
                        <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <span>{!! __('Kalkulator & Rumus Kebutuhan EXP') !!}</span>
                </h3>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {!! __('ATOMA menggunakan kurva eksponensial matematis yang terukur: :formula', ['formula' => '<code class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded-md font-mono text-indigo-600 dark:text-indigo-400 font-bold">100 × (Level)^1.5</code>']) !!}
                </p>
            </div>

            <!-- Slider Control -->
            <div class="space-y-3 bg-gray-50 dark:bg-gray-700/30 p-4 sm:p-5 rounded-2xl border border-gray-200/70 dark:border-gray-600/70">
                <div class="flex items-center justify-between">
                    <label for="levelSlider" class="text-xs sm:text-sm font-bold text-gray-700 dark:text-gray-300">
                        {{ __('Pilih Tingkat Level Target:') }}
                    </label>
                    <span class="px-3 py-1 bg-indigo-600 text-white rounded-xl font-black text-sm shadow-xs">
                        {{ __('Level') }} {{ $selectedCalcLevel }}
                    </span>
                </div>
                <input id="levelSlider" 
                       type="range" 
                       min="1" 
                       max="50" 
                       wire:model.live="selectedCalcLevel" 
                       class="w-full accent-indigo-600 h-2 bg-gray-200 dark:bg-gray-600 rounded-lg cursor-pointer">
                <div class="flex justify-between text-[10px] text-gray-400 font-semibold px-1">
                    <span>Lv.1</span>
                    <span>Lv.10</span>
                    <span>Lv.20</span>
                    <span>Lv.30</span>
                    <span>Lv.40</span>
                    <span>Lv.50 (Max)</span>
                </div>
            </div>

            <!-- Calculated Details Display -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="p-4 rounded-2xl bg-atoma-ice/60 dark:bg-gray-700/40 border border-atoma-aqua/40 dark:border-gray-600">
                    <div class="text-xs font-bold text-gray-600 dark:text-gray-400">{{ __('EXP untuk Naik dari Lv.:level', ['level' => $selectedCalcLevel]) }}</div>
                    <div class="text-xl sm:text-2xl font-black text-atoma-blue dark:text-atoma-cyan mt-1">
                        {{ number_format($this->calcXp) }} <span class="text-xs font-bold">XP</span>
                    </div>
                    <div class="text-[10px] text-gray-500 mt-0.5">100 &times; {{ $selectedCalcLevel }}<sup>1.5</sup></div>
                </div>

                <div class="p-4 rounded-2xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-800/40">
                    <div class="text-xs font-bold text-amber-800 dark:text-amber-300">{{ __('Total Kumulatif dari Lv.1') }}</div>
                    <div class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">
                        {{ number_format($this->cumulativeXp) }} <span class="text-xs font-bold">XP</span>
                    </div>
                    <div class="text-[10px] text-amber-700/80 dark:text-amber-400/80 mt-0.5">{{ __('Total akumulasi dalam 1 tier') }}</div>
                </div>

                <div class="p-4 rounded-2xl bg-purple-50/60 dark:bg-purple-950/30 border border-purple-200/60 dark:border-purple-800/40">
                    <div class="text-xs font-bold text-purple-800 dark:text-purple-300">{{ __('Koin Diperoleh (10:1)') }}</div>
                    <div class="text-xl sm:text-2xl font-black text-purple-600 dark:text-purple-400 mt-1">
                        ~{{ number_format((int)ceil($this->cumulativeXp / 10.0)) }} <span class="text-xs font-bold">{{ __('Koin') }}</span>
                    </div>
                    <div class="text-[10px] text-purple-700/80 dark:text-purple-400/80 mt-0.5">{{ __('10 XP menghasilkan 1 Koin') }}</div>
                </div>
            </div>
        </div>

        <!-- Quick Reference Milestone Table -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200/80 dark:border-gray-700/80 shadow-md p-5 sm:p-6 flex flex-col justify-between">
            <div>
                <h4 class="text-sm sm:text-base font-black text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                    <div class="w-6 h-6 flex items-center justify-center p-1 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-600 dark:text-blue-400 shrink-0">
                        <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <span>{{ __('Tabel Referensi Milestone') }}</span>
                </h4>
                <div class="overflow-x-auto text-xs">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-[11px] text-gray-400 uppercase font-bold">
                                <th class="pb-2">{{ __('Level') }}</th>
                                <th class="pb-2">{{ __('EXP Dibutuhkan') }}</th>
                                <th class="pb-2 text-right">{{ __('Kumulatif') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50 font-medium text-gray-600 dark:text-gray-300">
                            @foreach([1, 5, 10, 20, 30, 40, 50] as $lvl)
                                @php
                                    $req = PrestigeService::xpRequiredForLevel($lvl);
                                    $cum = 0;
                                    for ($k = 1; $k <= $lvl; $k++) $cum += PrestigeService::xpRequiredForLevel($k);
                                    $isCur = ($user->prestige_level ?? 1) === $lvl;
                                @endphp
                                <tr class="{{ $isCur ? 'bg-indigo-50/50 dark:bg-indigo-950/30 font-bold text-indigo-600 dark:text-indigo-400' : '' }}">
                                    <td class="py-2">Lv.{{ $lvl }}</td>
                                    <td class="py-2">{{ number_format($req) }} XP</td>
                                    <td class="py-2 text-right font-semibold">{{ number_format($cum) }} XP</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/80 text-[10px] text-gray-400">
                💡 {{ __('Level 1-10 dirancang cepat untuk membentuk momentum, sedangkan level 20+ menantang konsistensi jangka panjang.') }}
            </div>
        </div>
    </div>

    <!-- ANTI-EXPLOIT & SYSTEM RULES TRANSPARENCY -->
    <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200/80 dark:border-gray-700/80 shadow-md p-6 sm:p-8 space-y-6">
        <div>
            <h3 class="text-lg sm:text-xl font-black text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <div class="w-7 h-7 flex items-center justify-center p-1 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 shrink-0">
                    <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <span>{{ __('Aturan Sistem Anti-Exploit & Midnight Settlement (H+1)') }}</span>
            </h3>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ __('ATOMA dirancang untuk membangun kebiasaan nyata, bukan sekadar memuaskan dopamin klik instan. Berikut prinsip keadilan sistem:') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- Rule 1: Midnight Settlement -->
            <div class="p-5 rounded-2xl bg-indigo-50/50 dark:bg-gray-700/40 border border-indigo-100 dark:border-gray-600 space-y-2">
                <div class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-900/60 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ __('Daily Midnight Settlement (H+1)') }}</h4>
                <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                    {!! __('Setiap tugas yang Anda centang hari ini masuk ke dalam <b>Pending EXP</b>. Tepat pukul 00:00 tengah malam (saat login pertama H+1), sistem mencairkan seluruh pending EXP menjadi Level Up, Prestige XP, dan Koin (10 EXP = 1 Koin).') !!}
                </p>
                <div class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 mt-2">
                    ✓ {{ __('Mencegah spam done/undone berulang.') }}
                </div>
            </div>

            <!-- Rule 2: Anti-Spam & Diminishing Returns -->
            <div class="p-5 rounded-2xl bg-emerald-50/50 dark:bg-gray-700/40 border border-emerald-100 dark:border-gray-600 space-y-2">
                <div class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                    <svg class="w-5 h-5 fill-emerald-500 text-emerald-500" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ __('Batas & Diminishing Returns') }}</h4>
                <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                    {!! __('Batas harian maksimal adalah <b>150 EXP</b>. Tugas 1–3 bernilai 20 EXP, tugas 4–5 bernilai 10 EXP, tugas 6–10 bernilai 5 EXP. Tugas dengan judul kurang dari 4 karakter atau duplikat pada hari yang sama tidak memicu EXP.') !!}
                </p>
                <div class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 mt-2">
                    ✓ {{ __('Menghargai kualitas di atas kuantitas spam.') }}
                </div>
            </div>

            <!-- Rule 3: Quality Overload & Bonuses -->
            <div class="p-5 rounded-2xl bg-amber-50/50 dark:bg-gray-700/40 border border-amber-100 dark:border-gray-600 space-y-2">
                <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/60 border border-amber-200 dark:border-amber-800 flex items-center justify-center text-amber-500 shrink-0">
                    <svg class="w-5 h-5 fill-amber-400 stroke-amber-500" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ __('Bonus Kualitas & Perencanaan') }}</h4>
                <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                    {!! __('Dapatkan <b>+5 EXP Planning Bonus</b> jika tugas direncanakan H-1, <b>+15 EXP Focus Bonus</b> per sesi Pomodoro, dan bonus pelipatganda hingga <b>+50%</b> untuk tugas kompleks yang memiliki rincian subtask dan estimasi waktu.') !!}
                </p>
                <div class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 mt-2">
                    ✓ {{ __('Mendorong deep work dan keteraturan.') }}
                </div>
            </div>
        </div>
    </div>

    <!-- RPG CHARACTER ATTRIBUTES -->
    <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200/80 dark:border-gray-700/80 shadow-md p-6 sm:p-8 space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg sm:text-xl font-black text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    <div class="w-7 h-7 flex items-center justify-center p-1 rounded-xl bg-purple-500/10 border border-purple-500/20 shrink-0">
                        <img src="{{ asset('images/prestige/tier_2_practitioner.svg') }}" alt="RPG" class="w-full h-full object-contain">
                    </div>
                    <span>{{ __('Statistik Karakter RPG Produktivitas') }}</span>
                </h3>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ __('Atribut berkembang secara dinamis berdasarkan data historis eksekusi tugas nyata Anda di ATOMA.') }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <!-- Focus -->
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200/70 dark:border-gray-600/70 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-rose-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2" fill="currentColor"/></svg>
                        {{ __('Fokus') }}
                    </span>
                    <span class="text-xs font-black text-rose-500">{{ $rpgStats['focus'] ?? 0 }}</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-600 h-2 rounded-full overflow-hidden">
                    <div class="bg-rose-500 h-full rounded-full transition-all duration-500" style="width: {{ min(100, ($rpgStats['focus'] ?? 0)) }}%"></div>
                </div>
                <p class="text-[10px] text-gray-400">{{ __('Diperoleh dari sesi Pomodoro & Flowtime.') }}</p>
            </div>

            <!-- Consistency -->
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200/70 dark:border-gray-600/70 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-orange-500 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c-.5 2-2 3.5-3.5 5C6.5 9 5 11.5 5 14.5 5 18.64 8.36 22 12.5 22s7.5-3.36 7.5-7.5c0-4-3-6.5-4.5-9-1 2-2.5 2.5-3.5 2.5 0-3-1-7-1-7z"/></svg>
                        {{ __('Konsistensi') }}
                    </span>
                    <span class="text-xs font-black text-orange-500">{{ $rpgStats['consistency'] ?? 0 }}</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-600 h-2 rounded-full overflow-hidden">
                    <div class="bg-orange-500 h-full rounded-full transition-all duration-500" style="width: {{ min(100, ($rpgStats['consistency'] ?? 0)) }}%"></div>
                </div>
                <p class="text-[10px] text-gray-400">{{ __('Diperoleh dari rentetan streak harian.') }}</p>
            </div>

            <!-- Execution -->
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200/70 dark:border-gray-600/70 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-500 fill-emerald-500 shrink-0" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                        {{ __('Eksekusi') }}
                    </span>
                    <span class="text-xs font-black text-emerald-500">{{ $rpgStats['execution'] ?? 0 }}</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-600 h-2 rounded-full overflow-hidden">
                    <div class="bg-emerald-500 h-full rounded-full transition-all duration-500" style="width: {{ min(100, ($rpgStats['execution'] ?? 0)) }}%"></div>
                </div>
                <p class="text-[10px] text-gray-400">{{ __('Diperoleh dari penyelesaian tugas tepat waktu.') }}</p>
            </div>

            <!-- Organization -->
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200/70 dark:border-gray-600/70 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        {{ __('Organisasi') }}
                    </span>
                    <span class="text-xs font-black text-blue-500">{{ $rpgStats['organization'] ?? 0 }}</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-600 h-2 rounded-full overflow-hidden">
                    <div class="bg-blue-500 h-full rounded-full transition-all duration-500" style="width: {{ min(100, ($rpgStats['organization'] ?? 0)) }}%"></div>
                </div>
                <p class="text-[10px] text-gray-400">{{ __('Diperoleh dari rincian subtask & kategori.') }}</p>
            </div>
        </div>
    </div>

    <!-- PENDING XP BREAKDOWN POPUP MODAL -->
    @if($showPendingModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" 
             x-data="{ show: true }" 
             x-show="show" 
             @keydown.escape.window="$wire.closePendingBreakdown()">
            
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity cursor-pointer" 
                 wire:click="closePendingBreakdown"></div>

            <!-- Modal Panel -->
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-gray-800 text-left shadow-2xl transition-all w-full max-w-2xl border border-gray-100 dark:border-gray-700/80 my-8 z-10">
                    
                    <!-- Top Gradient Accent -->
                    <div class="h-2.5 w-full bg-gradient-to-r from-emerald-400 via-teal-500 to-indigo-500"></div>

                    <div class="p-6 sm:p-7 space-y-6">
                        <!-- Header -->
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3.5">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 dark:bg-emerald-500/20 border border-emerald-500/25 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0 shadow-xs">
                                    <x-icon.zap class="w-6 h-6" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="text-lg sm:text-xl font-black text-gray-900 dark:text-gray-100">
                                            {{ __('Rincian EXP Pending Hari Ini') }}
                                        </h3>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-emerald-100 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                                            +{{ (int)($pendingBreakdown['total_pending'] ?? $user->pending_xp) }} XP
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ __('Transparansi sumber perolehan EXP yang akan dicairkan ke Level & Koin akun tengah malam nanti.') }}
                                    </p>
                                </div>
                            </div>

                            <button type="button" 
                                    wire:click="closePendingBreakdown" 
                                    class="p-2 rounded-xl text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/60 transition-colors cursor-pointer"
                                    title="{{ __('Tutup') }}">
                                <x-icon.x class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Source Category Summary Grid -->
                        @if(!empty($pendingBreakdown['categories']))
                            @php
                                $catCount = count($pendingBreakdown['categories']);
                            @endphp
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                        {{ __('Ringkasan Kategori Sumber EXP') }}
                                    </h4>
                                    <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500">
                                        {{ $catCount }} {{ __('Kategori') }}
                                    </span>
                                </div>
                                <div class="grid gap-3 {{ $catCount === 1 ? 'grid-cols-1' : ($catCount === 2 ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-2 sm:grid-cols-3') }}">
                                    @foreach($pendingBreakdown['categories'] as $cat)
                                        <div class="p-4 rounded-2xl border {{ $cat['bg_light'] }} flex flex-col justify-between transition-all hover:shadow-xs">
                                            <div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <div class="w-7 h-7 rounded-lg flex items-center justify-center bg-white/70 dark:bg-gray-800/70 shadow-xs shrink-0">
                                                            <x-pending-xp-icon :source="$cat['key']" class="w-4 h-4" />
                                                        </div>
                                                        <span class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate">
                                                            {{ $cat['label'] }}
                                                        </span>
                                                    </div>
                                                    <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded-md bg-white/60 dark:bg-gray-800/60 shadow-xs shrink-0">
                                                        {{ $cat['percentage'] }}%
                                                    </span>
                                                </div>

                                                <div class="mt-3 flex items-baseline justify-between gap-2">
                                                    <span class="text-base sm:text-lg font-black text-gray-900 dark:text-gray-100">
                                                        +{{ number_format($cat['total']) }} <span class="text-[10px] font-bold opacity-75">XP</span>
                                                    </span>
                                                    <span class="text-[10px] opacity-70 font-medium">
                                                        {{ $cat['count'] }} {{ __('item') }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Progress share indicator -->
                                            <div class="w-full bg-black/5 dark:bg-white/10 rounded-full h-1.5 mt-3 overflow-hidden">
                                                <div class="{{ $cat['bar_color'] }} h-full rounded-full transition-all duration-500" style="width: {{ $cat['percentage'] }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Detailed Activity Log List -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    {{ __('Daftar Aktivitas Penyumbang EXP') }} ({{ count($pendingBreakdown['items'] ?? []) }})
                                </h4>
                                <span class="text-[11px] text-gray-400">
                                    {{ __('Diurutkan terbaru') }}
                                </span>
                            </div>

                            @if(empty($pendingBreakdown['items']))
                                <div class="p-8 rounded-2xl bg-gray-50 dark:bg-gray-700/30 border border-dashed border-gray-200 dark:border-gray-700 text-center space-y-2.5">
                                    <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-700/50 flex items-center justify-center mx-auto text-gray-400 dark:text-gray-500">
                                        <x-icon.sprout class="w-6 h-6" />
                                    </div>
                                    <div class="text-sm font-bold text-gray-700 dark:text-gray-300">
                                        {{ __('Belum ada EXP pending hari ini') }}
                                    </div>
                                    <p class="text-xs text-gray-400 max-w-sm mx-auto">
                                        {{ __('Selesaikan tugas, buka achievement, atau jalankan sesi fokus Pomodoro untuk mulai mengumpulkan EXP!') }}
                                    </p>
                                </div>
                            @else
                                <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                                    @foreach($pendingBreakdown['items'] as $item)
                                        <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200/70 dark:border-gray-600/60 flex items-center justify-between gap-3 hover:bg-gray-100/80 dark:hover:bg-gray-700/70 transition-all">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 border {{ $item['bg_light'] }}">
                                                    <x-pending-xp-icon :source="$item['source']" class="w-4 h-4" />
                                                </div>
                                                <div class="min-w-0 space-y-0.5">
                                                    <p class="text-xs sm:text-sm font-bold text-gray-800 dark:text-gray-200 truncate">
                                                        {{ $item['title'] }}
                                                    </p>
                                                    @if(!empty($item['description']))
                                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                                            {{ $item['description'] }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="shrink-0 text-right space-y-1">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-black bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
                                                    +{{ $item['amount'] }} XP
                                                </span>
                                                <div class="flex items-center justify-end gap-1 text-[10px] text-gray-400 dark:text-gray-500 font-mono">
                                                    <x-icon.clock class="w-3 h-3 text-gray-400" />
                                                    <span>{{ $item['time_formatted'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Midnight Settlement Notice Footer -->
                        <div class="p-4 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200/80 dark:border-indigo-800/60 flex items-start gap-3">
                            <div class="w-8 h-8 rounded-xl bg-indigo-500/15 border border-indigo-500/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5">
                                <x-icon.info class="w-4 h-4" />
                            </div>
                            <div class="text-xs leading-relaxed text-indigo-950 dark:text-indigo-200">
                                <span class="font-bold">{{ __('Sistem Settlement Tengah Malam:') }}</span>
                                {{ __('Seluruh EXP pending di atas akan otomatis dicairkan tepat pada pukul 00:00 (pergantian hari) ke Level Utama akun dan mengonversi koin bonus (+1 Koin per 10 EXP).') }}
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" 
                                    wire:click="closePendingBreakdown" 
                                    class="px-5 py-2.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-100 font-black text-xs transition-all shadow-xs cursor-pointer flex items-center gap-1.5">
                                <x-icon.check class="w-3.5 h-3.5" />
                                <span>{{ __('Tutup') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
