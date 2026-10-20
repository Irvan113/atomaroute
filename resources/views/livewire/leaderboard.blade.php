<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use App\Models\User;
use App\Models\Friendship;
use App\Models\LeaderboardSeasonHistory;

new class extends Component {
    public $timeRange = 'monthly';
    public $selectedPeriodKey = '2026-08';
    public $leaderboardScope = 'global';

    public function mount()
    {
        $this->selectedPeriodKey = $this->getDefaultPeriodKey($this->timeRange);
    }

    public function setTimeRange($range)
    {
        if (in_array($range, ['weekly', 'monthly', 'yearly', 'all_time'])) {
            $this->timeRange = $range;
            $this->selectedPeriodKey = $this->getDefaultPeriodKey($range);
        }
    }

    public function getDefaultPeriodKey($range)
    {
        $now = now('Asia/Jakarta');
        return match($range) {
            'weekly' => $now->format('Y-\WW'),
            'monthly' => $now->format('Y-m'),
            'yearly' => $now->format('Y'),
            'all_time' => 'all_time',
            default => $now->format('Y-m')
        };
    }

    public function setLeaderboardScope($scope)
    {
        if (in_array($scope, ['global', 'friends'])) {
            $this->leaderboardScope = $scope;
        }
    }

    public function challengeFriend($friendId)
    {
        $friend = User::find($friendId);
        if (!$friend) return;

        $user = auth()->user();
        if ($user) {
            event(new \App\Events\NudgeSent($user, $friend, 'challenge'));
        }
        $this->dispatch('toast', message: "⚔️ Tantangan produktivitas dikirim ke {$friend->name}!", type: 'success');
    }

    #[Computed]
    public function availablePeriods()
    {
        $tz = 'Asia/Jakarta';
        $startDate = \Carbon\Carbon::create(2026, 5, 1, 0, 0, 0, $tz)->startOfMonth();
        $now = \Carbon\Carbon::now($tz);

        $monthNames = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];

        if ($this->timeRange === 'weekly') {
            $weeks = [];
            $currW = $now->copy()->startOfWeek();
            $prevW = $now->copy()->subWeek()->startOfWeek();
            while ($currW->greaterThanOrEqualTo($startDate->copy()->startOfWeek())) {
                $endW = $currW->copy()->endOfWeek();
                $key = $currW->format('Y-\WW');
                $startMonthName = $monthNames[$currW->format('m')];
                $endMonthName = $monthNames[$endW->format('m')];
                
                $label = $currW->format('d') . ' ' . $startMonthName . ' - ' . $endW->format('d') . ' ' . $endMonthName . ' ' . $endW->format('Y');
                if ($key === $now->format('Y-\WW')) {
                    $label .= ' (Minggu Ini)';
                    $shortLabel = 'Minggu Ini';
                } elseif ($key === $prevW->format('Y-\WW')) {
                    $shortLabel = 'Minggu Lalu';
                } else {
                    $shortLabel = $currW->format('d') . ' - ' . $endW->format('d') . ' ' . substr($endMonthName, 0, 3);
                }
                $weeks[] = ['key' => $key, 'label' => $label, 'short_label' => $shortLabel, 'is_current' => ($key === $now->format('Y-\WW'))];
                $currW->subWeek();
            }
            return $weeks;
        }

        if ($this->timeRange === 'monthly') {
            $months = [];
            $currM = $now->copy()->startOfMonth();
            while ($currM->greaterThanOrEqualTo($startDate)) {
                $key = $currM->format('Y-m');
                $monthName = $monthNames[$currM->format('m')];
                $label = $monthName . ' ' . $currM->format('Y');
                $shortLabel = $monthName;
                if ($key === $now->format('Y-m')) {
                    $label .= ' (Bulan Ini)';
                    $shortLabel = $monthName . ' (Ini)';
                } elseif ($key === '2026-05') {
                    $label .= ' (Rilis Awal)';
                }
                $months[] = ['key' => $key, 'label' => $label, 'short_label' => $shortLabel, 'is_current' => ($key === $now->format('Y-m'))];
                $currM->subMonth();
            }
            return $months;
        }

        if ($this->timeRange === 'yearly') {
            $years = [];
            $currY = (int) $now->format('Y');
            $startYear = 2026;
            for ($y = $currY; $y >= $startYear; $y--) {
                $label = (string) $y;
                $shortLabel = (string) $y;
                if ($y === $currY) {
                    $label .= ' (Tahun Ini)';
                    $shortLabel = $y . ' (Tahun Ini)';
                }
                $years[] = ['key' => (string)$y, 'label' => $label, 'short_label' => $shortLabel, 'is_current' => ($y === $currY)];
            }
            return $years;
        }

        return [['key' => 'all_time', 'label' => 'All-Time Hall of Fame', 'is_current' => true]];
    }

    #[Computed]
    public function leaderboardPlayers()
    {
        $userId = auth()->id();
        $isCurrent = ($this->selectedPeriodKey === $this->getDefaultPeriodKey($this->timeRange));

        $query = User::query();

        if ($this->leaderboardScope === 'friends') {
            $friendships = Friendship::where('status', 'accepted')
                ->where(function($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhere('friend_id', $userId);
                })->get();
                
            $friendIds = $friendships->map(function($f) use ($userId) {
                return (int) ($f->user_id == $userId ? $f->friend_id : $f->user_id);
            })->all();
            $friendIds[] = (int) $userId;
            $friendIds = array_unique($friendIds);
            
            $query->whereIn('id', $friendIds);
        }

        if ($isCurrent) {
            $xpColumn = match($this->timeRange) {
                'weekly' => 'weekly_xp',
                'monthly' => 'monthly_xp',
                'yearly' => 'xp',
                'all_time' => 'xp',
                default => 'monthly_xp'
            };

            if ($this->leaderboardScope === 'global') {
                $cacheKey = "leaderboard_global_{$this->timeRange}_{$this->selectedPeriodKey}";
                return \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($xpColumn) {
                    $players = User::orderBy($xpColumn, 'desc')
                        ->orderBy('xp', 'desc')
                        ->orderBy('longest_streak', 'desc')
                        ->limit(20)
                        ->get();

                    foreach ($players as $p) {
                        $p->period_xp = (int) ($p->{$xpColumn} ?? $p->xp);
                    }

                    return $players;
                });
            }

            $players = $query->orderBy($xpColumn, 'desc')
                ->orderBy('xp', 'desc')
                ->orderBy('longest_streak', 'desc')
                ->limit(20)
                ->get();

            foreach ($players as $p) {
                $p->period_xp = (int) ($p->{$xpColumn} ?? $p->xp);
            }

            return $players;
        }

        // Historical Period Selection:
        $historyRecords = LeaderboardSeasonHistory::where('period_type', $this->timeRange)
            ->where('period_key', $this->selectedPeriodKey)
            ->orderBy('rank', 'asc')
            ->orderBy('xp', 'desc')
            ->get();

        if ($historyRecords->isNotEmpty()) {
            if ($this->leaderboardScope === 'friends') {
                $historyRecords = $historyRecords->filter(fn($hr) => in_array($hr->user_id, $friendIds ?? []));
            }

            $userIds = $historyRecords->pluck('user_id')->toArray();
            $usersById = User::whereIn('id', $userIds)->get()->keyBy('id');
            $players = collect();

            foreach ($historyRecords as $hr) {
                if (isset($usersById[$hr->user_id])) {
                    $u = clone $usersById[$hr->user_id];
                    $u->period_xp = (int) $hr->xp;
                    $u->period_rank = (int) $hr->rank;
                    $players->push($u);
                }
            }

            return $players->take(20);
        }

        $now = now('Asia/Jakarta');
        $isPrevWeek = ($this->timeRange === 'weekly' && $this->selectedPeriodKey === $now->copy()->subWeek()->format('Y-\WW'));
        $isPrevMonth = ($this->timeRange === 'monthly' && $this->selectedPeriodKey === $now->copy()->subMonth()->format('Y-m'));

        $fallbackColumn = $isPrevWeek ? 'prev_week_xp' : ($isPrevMonth ? 'prev_month_xp' : 'prev_year_xp');

        $players = $query->orderBy($fallbackColumn, 'desc')
            ->orderBy('xp', 'desc')
            ->limit(20)
            ->get();

        foreach ($players as $p) {
            $p->period_xp = (int) ($p->{$fallbackColumn} ?? 0);
        }

        return $players;
    }

    #[Computed]
    public function userStanding()
    {
        $userId = auth()->id();
        $players = $this->leaderboardPlayers;

        $index = $players->search(fn($p) => $p->id === $userId);

        if ($index !== false) {
            $rank = $index + 1;
            $user = $players->get($index);
            $userXp = $user->period_xp ?? $user->xp;

            $gapXp = 0;
            $nextPlayer = null;
            if ($index > 0) {
                $prevPlayer = $players->get($index - 1);
                $prevXp = $prevPlayer->period_xp ?? $prevPlayer->xp;
                $gapXp = max(0, $prevXp - $userXp + 1);
                $nextPlayer = $prevPlayer;
            }

            return [
                'rank' => $rank,
                'xp' => $userXp,
                'gap_xp' => $gapXp,
                'next_player' => $nextPlayer,
                'user' => $user,
            ];
        }

        $user = auth()->user();
        if (!$user) return null;
        
        $xpColumn = match($this->timeRange) {
            'weekly' => 'weekly_xp',
            'monthly' => 'monthly_xp',
            'yearly' => 'xp',
            'all_time' => 'xp',
            default => 'weekly_xp'
        };

        $userXp = (int) ($user->{$xpColumn} ?? $user->xp ?? 0);
        $higherCount = User::where($xpColumn, '>', $userXp)->count();
        $actualRank = $higherCount + 1;

        return [
            'rank' => $actualRank,
            'xp' => $userXp,
            'gap_xp' => 50,
            'next_player' => null,
            'user' => $user,
        ];
    }

    #[Computed]
    public function promotionProgress()
    {
        $user = auth()->user();
        if (!$user) return null;
        return \App\Services\LeagueService::getPromotionProgress($user);
    }

    public function claimSeasonPromotionReward()
    {
        $user = auth()->user();
        if (!$user) return;

        $currentLeague = \App\Services\LeagueService::getLeagueDetails($user->league);
        $rewardCoins = $currentLeague['reward_coins'] ?? 15;

        $user->coins += $rewardCoins;
        $user->league_season_claimed = true;
        $user->save();

        $this->dispatch('toast', message: "🎉 " . __('Hadiah Musim Diklaim! +:coins Koin 🪙', ['coins' => $rewardCoins]), type: 'success');
    }

    public function claimInstantPromotion()
    {
        $user = auth()->user();
        if (!$user) return;

        $result = \App\Services\LeagueService::promoteUserToNextLeague($user);
        if ($result['success']) {
            $leagueName = $result['new_league']['name'];
            $coins = $result['reward_coins'];
            $this->dispatch('profile-updated');
            $this->dispatch('play-sound', preset: 'fanfare', volume: 0.8);
            $this->dispatch('trigger-confetti');
            $this->dispatch('toast', message: "🎉 Selamat! Kamu resmi naik ke Liga {$leagueName} & dapat bonus +{$coins} Koin! 🪙", type: 'success');
        } else {
            $this->dispatch('toast', message: $result['message'], type: 'warning');
        }
    }

    public function getSeasonCountdown()
    {
        $defaultKey = $this->getDefaultPeriodKey($this->timeRange);
        $isCurrent = ($this->selectedPeriodKey === $defaultKey);

        if (!$isCurrent && $this->timeRange !== 'all_time') {
            return '🏆 ' . __('Rekap Final Musim (Arsip)');
        }

        $now = now('Asia/Jakarta');
        $weeklyDays = max(1, 7 - $now->dayOfWeek);
        $monthlyDays = max(1, $now->daysInMonth - $now->day + 1);
        $yearlyDays = max(1, $now->copy()->endOfYear()->dayOfYear - $now->dayOfYear + 1);

        return match($this->timeRange) {
            'weekly' => __('Reset dalam :days hari (Senin 12:00 WIB)', ['days' => $weeklyDays]),
            'monthly' => __('Reset dalam :days hari', ['days' => $monthlyDays]),
            'yearly' => __('Reset dalam :days hari', ['days' => $yearlyDays]),
            'all_time' => __('All-Time Hall of Fame 🌟'),
            default => __('Reset dalam :days hari', ['days' => $monthlyDays])
        };
    }
}; ?>

<div class="pb-28 sm:pb-8 max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <img src="/images/icons/award-rank.png" alt="Award" class="w-8 h-8 sm:w-9 sm:h-9 object-contain inline-block drop-shadow-xs">
                <span>{{ __('Papan Peringkat (Leaderboard)') }}</span>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium mt-1">
                {{ __('Adu produktivitas, capai liga tertinggi, dan raih posisi teratas di ATOMA!') }}
            </p>
        </div>

        <!-- Scope Switcher: Global vs Friends -->
        <div class="bg-gray-100 dark:bg-gray-800 p-1 rounded-2xl flex items-center gap-1 shadow-inner border border-gray-200/50 dark:border-gray-700/50 shrink-0 self-start sm:self-auto">
            <button type="button" 
                    wire:click="setLeaderboardScope('global')" 
                    class="px-3.5 py-1.5 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer {{ $leaderboardScope === 'global' ? 'bg-atoma-blue dark:bg-indigo-600 text-white shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                <span>🌍</span> {{ __('Global') }}
            </button>
            <button type="button" 
                    wire:click="setLeaderboardScope('friends')" 
                    class="px-3.5 py-1.5 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer {{ $leaderboardScope === 'friends' ? 'bg-atoma-blue dark:bg-indigo-600 text-white shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                <span>👥</span> {{ __('Teman') }}
            </button>
        </div>
    </div>

    <!-- Time Range Tabs + Season Period Picker -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-gray-850 p-3 sm:p-4 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-xs">
        <!-- Time Tabs: Weekly | Monthly | Yearly | All-Time -->
        <div class="bg-gray-100 dark:bg-gray-800 p-1 rounded-xl flex items-center gap-1 overflow-x-auto hide-scrollbar">
            <button type="button" 
                    wire:click="setTimeRange('weekly')" 
                    class="flex-1 sm:flex-none text-center py-1.5 px-3.5 rounded-lg text-xs font-black transition-all cursor-pointer {{ $timeRange === 'weekly' ? 'bg-atoma-blue dark:bg-indigo-600 text-white shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                {{ __('Weekly') }}
            </button>
            <button type="button" 
                    wire:click="setTimeRange('monthly')" 
                    class="flex-1 sm:flex-none text-center py-1.5 px-3.5 rounded-lg text-xs font-black transition-all cursor-pointer {{ $timeRange === 'monthly' ? 'bg-atoma-blue dark:bg-indigo-600 text-white shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                {{ __('Monthly') }}
            </button>
            <button type="button" 
                    wire:click="setTimeRange('yearly')" 
                    class="flex-1 sm:flex-none text-center py-1.5 px-3.5 rounded-lg text-xs font-black transition-all cursor-pointer {{ $timeRange === 'yearly' ? 'bg-atoma-blue dark:bg-indigo-600 text-white shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                {{ __('Yearly') }}
            </button>
            <button type="button" 
                    wire:click="setTimeRange('all_time')" 
                    class="flex-1 sm:flex-none text-center py-1.5 px-3.5 rounded-lg text-xs font-black transition-all cursor-pointer {{ $timeRange === 'all_time' ? 'bg-atoma-blue dark:bg-indigo-600 text-white shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                {{ __('All-Time') }}
            </button>
        </div>

        <!-- Period Select & Countdown -->
        <div class="flex items-center gap-2">
            @if($timeRange !== 'all_time')
                <select wire:model.live="selectedPeriodKey" class="text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-2.5 py-1.5 text-gray-800 dark:text-gray-200 font-bold focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 cursor-pointer">
                    @foreach($this->availablePeriods as $p)
                        <option value="{{ $p['key'] }}" class="bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200">{{ $p['label'] }}</option>
                    @endforeach
                </select>
            @endif

            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-[11px] font-bold bg-atoma-ice dark:bg-indigo-950/60 text-atoma-blue dark:text-indigo-300 border border-atoma-aqua/60 dark:border-indigo-500/30 shrink-0">
                <span>⏳</span> {{ $this->getSeasonCountdown() }}
            </span>
        </div>
    </div>

    <!-- Gamified League Promotion Banner -->
    @if($promo = $this->promotionProgress)
        <div class="bg-gradient-to-r from-atoma-blue/15 via-atoma-cyan/10 to-transparent dark:from-indigo-950/40 dark:via-gray-850 dark:to-gray-850 border border-atoma-blue/30 dark:border-indigo-500/30 rounded-2xl p-4 sm:p-5 text-gray-900 dark:text-white shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5 cursor-pointer group select-none" @click="$dispatch('open-rank-modal')" title="{{ __('Klik untuk melihat bocoran semua rank & liga') }}">
                    <div class="relative">
                        <x-tier-badge league="{{ $promo['current_league']['key'] ?? 'bronze_3' }}" size="lg" class="group-hover:scale-110 transition-transform" />
                        <span class="absolute -bottom-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-atoma-blue text-[9px] font-black text-white shadow-xs">
                            🔍
                        </span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-black text-atoma-blue dark:text-indigo-300 uppercase tracking-wider">{{ __('Liga Saat Ini') }}</span>
                            <span class="text-[10px] bg-atoma-ice/80 dark:bg-gray-800 text-atoma-blue dark:text-atoma-cyan px-2 py-0.5 rounded-full font-bold border border-atoma-aqua/40 group-hover:border-atoma-blue transition-colors">
                                {{ __('Bocoran Rank') }} &rarr;
                            </span>
                        </div>
                        <div class="text-base sm:text-lg font-black flex items-center gap-1.5 mt-0.5">
                            <span>{{ $promo['current_league']['name'] ?? 'Bronze III' }}</span>
                            <span>{{ $promo['current_league']['icon'] ?? '🥉' }}</span>
                        </div>
                        <!-- Visual Progress Bar -->
                        <div class="w-44 sm:w-56 bg-gray-200 dark:bg-gray-700 h-2 rounded-full overflow-hidden mt-1.5">
                            <div class="bg-gradient-to-r from-atoma-blue to-atoma-cyan h-full rounded-full transition-all duration-500" style="width: {{ $promo['percentage'] }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 sm:self-center">
                    <div class="text-left sm:text-right">
                        <div class="text-[11px] text-gray-500 dark:text-gray-400 font-medium">{{ __('Target Promosi Liga:') }}</div>
                        <div class="text-xs font-black text-atoma-blue dark:text-indigo-300">{{ $promo['progress_text'] ?? '0 XP' }}</div>
                    </div>

                    @if(!empty($promo['is_qualified']))
                        <div class="px-3.5 py-1.5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-black rounded-xl flex items-center gap-1.5 shadow-2xs">
                            <span>✅</span>
                            <span>{{ __('Terkualifikasi Promosi (Senin 12:00 WIB)') }}</span>
                        </div>
                    @endif

                    @if(!empty($promo['can_claim_reward']))
                        <button type="button" 
                                wire:click="claimSeasonPromotionReward" 
                                class="px-3.5 py-2 bg-amber-500 hover:bg-amber-400 text-gray-900 text-xs font-black rounded-xl shadow-md transition-all active:scale-95 cursor-pointer flex items-center gap-1">
                            <span>🪙</span>
                            <span>{{ __('Klaim Hadiah 🪙') }}</span>
                        </button>
                    @endif

                    <button type="button" 
                            @click="$dispatch('open-rank-modal')" 
                            class="px-3 py-2 bg-white/80 dark:bg-gray-800/80 hover:bg-white dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-bold rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs transition-all cursor-pointer flex items-center gap-1.5"
                            title="{{ __('Bocoran Semua Rank & Liga') }}">
                        <svg class="w-4 h-4 text-atoma-blue dark:text-atoma-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>{{ __('Info Rank') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @php
        $players = $this->leaderboardPlayers;
        $rank1 = $players->get(0);
        $rank2 = $players->get(1);
        $rank3 = $players->get(2);
        $remainingPlayers = $players->slice(3)->values();
    @endphp

    @if($players->count() > 0)
        <!-- Floating 3-Podium (Responsive for all screen sizes) -->
        <div class="pt-6 pb-2 flex items-end justify-center gap-1.5 sm:gap-6">
            <!-- Rank 2 (Left) -->
            <div class="flex flex-col items-center text-center w-20 sm:w-28">
                @if($rank2)
                    @php
                        $rank2Xp = $rank2->period_xp ?? $rank2->xp;
                        $isUser2 = $rank2->id === auth()->id();
                    @endphp
                    <a href="{{ route('user.profile', $rank2->id) }}" wire:navigate class="relative mb-2 block group cursor-pointer" title="{{ __('Lihat profil :name', ['name' => $rank2->name]) }}">
                        <div class="w-14 h-14 sm:w-20 sm:h-20 rounded-full p-0.5 border-3 border-atoma-cyan/70 shadow-md bg-white dark:bg-gray-800 overflow-hidden group-hover:scale-105 transition-transform">
                            <img src="{{ $rank2->avatar_url }}" alt="{{ $rank2->name }}" class="w-full h-full rounded-full object-cover">
                        </div>
                        <span class="absolute -bottom-2 left-1/2 -translate-x-1/2 w-5 h-5 sm:w-6 sm:h-6 rounded-full bg-atoma-blue dark:bg-indigo-600 text-white font-black text-[11px] sm:text-xs flex items-center justify-center shadow-md border-2 border-white dark:border-gray-900">
                            2
                        </span>
                    </a>
                    <a href="{{ route('user.profile', $rank2->id) }}" wire:navigate class="font-black text-[11px] sm:text-sm text-gray-900 dark:text-white truncate w-full mt-1 flex items-center justify-center gap-1 hover:text-atoma-blue dark:hover:text-atoma-cyan transition-colors" title="{{ __('Lihat profil :name', ['name' => $rank2->name]) }}">
                        <span class="truncate">{{ $rank2->name }}</span>
                        @if(!empty($rank2->equipped_badges_details))
                            <div class="flex items-center -space-x-1 shrink-0">
                                @foreach($rank2->equipped_badges_details as $b)
                                    <img src="{{ asset($b['svg_path']) }}" class="w-3.5 h-3.5 sm:w-4 sm:h-4 object-contain shrink-0" title="{{ $b['title'] }}">
                                @endforeach
                            </div>
                        @endif
                    </a>
                    <span class="text-[10px] sm:text-xs font-bold text-gray-500 dark:text-gray-400 mt-0.5 flex items-center justify-center gap-1">
                        <span>🔥</span> {{ number_format($rank2Xp) }} XP
                    </span>
                @endif
            </div>

            <!-- Rank 1 (Center) -->
            <div class="flex flex-col items-center text-center w-24 sm:w-36 -translate-y-2 sm:-translate-y-3">
                @if($rank1)
                    @php
                        $rank1Xp = $rank1->period_xp ?? $rank1->xp;
                        $isUser1 = $rank1->id === auth()->id();
                    @endphp
                    <div class="text-2xl sm:text-4xl -mb-1 filter drop-shadow-md animate-bounce" style="animation-duration: 2.5s;">
                        👑
                    </div>
                    <a href="{{ route('user.profile', $rank1->id) }}" wire:navigate class="relative mb-2 block group cursor-pointer" title="{{ __('Lihat profil :name', ['name' => $rank1->name]) }}">
                        <div class="w-18 h-18 sm:w-24 sm:h-24 rounded-full p-1 border-4 border-atoma-blue dark:border-indigo-400/80 shadow-xl dark:shadow-[0_0_20px_rgba(99,102,241,0.25)] bg-white dark:bg-gray-800 overflow-hidden group-hover:scale-105 transition-transform">
                            <img src="{{ $rank1->avatar_url }}" alt="{{ $rank1->name }}" class="w-full h-full rounded-full object-cover">
                        </div>
                        <span class="absolute -bottom-2.5 left-1/2 -translate-x-1/2 w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-atoma-blue dark:bg-indigo-600 text-white font-black text-xs sm:text-sm flex items-center justify-center shadow-lg border-2 border-white dark:border-gray-900">
                            1
                        </span>
                    </a>
                    <a href="{{ route('user.profile', $rank1->id) }}" wire:navigate class="font-black text-xs sm:text-base text-gray-900 dark:text-white truncate w-full mt-1 flex items-center justify-center gap-1 hover:text-atoma-blue dark:hover:text-atoma-cyan transition-colors" title="{{ __('Lihat profil :name', ['name' => $rank1->name]) }}">
                        <span class="truncate">{{ $rank1->name }}</span>
                        @if(!empty($rank1->equipped_badges_details))
                            <div class="flex items-center -space-x-1 shrink-0">
                                @foreach($rank1->equipped_badges_details as $b)
                                    <img src="{{ asset($b['svg_path']) }}" class="w-3.5 h-3.5 sm:w-4 sm:h-4 object-contain shrink-0" title="{{ $b['title'] }}">
                                @endforeach
                            </div>
                        @endif
                    </a>
                    <span class="text-xs sm:text-sm font-black text-atoma-blue dark:text-atoma-cyan mt-0.5 flex items-center justify-center gap-1">
                        <span>🔥</span> {{ number_format($rank1Xp) }} XP
                    </span>
                @endif
            </div>

            <!-- Rank 3 (Right) -->
            <div class="flex flex-col items-center text-center w-20 sm:w-28">
                @if($rank3)
                    @php
                        $rank3Xp = $rank3->period_xp ?? $rank3->xp;
                        $isUser3 = $rank3->id === auth()->id();
                    @endphp
                    <a href="{{ route('user.profile', $rank3->id) }}" wire:navigate class="relative mb-2 block group cursor-pointer" title="{{ __('Lihat profil :name', ['name' => $rank3->name]) }}">
                        <div class="w-14 h-14 sm:w-20 sm:h-20 rounded-full p-0.5 border-3 border-atoma-aqua/80 shadow-md bg-white dark:bg-gray-800 overflow-hidden group-hover:scale-105 transition-transform">
                            <img src="{{ $rank3->avatar_url }}" alt="{{ $rank3->name }}" class="w-full h-full rounded-full object-cover">
                        </div>
                        <span class="absolute -bottom-2 left-1/2 -translate-x-1/2 w-5 h-5 sm:w-6 sm:h-6 rounded-full bg-atoma-blue dark:bg-indigo-600 text-white font-black text-[11px] sm:text-xs flex items-center justify-center shadow-md border-2 border-white dark:border-gray-900">
                            3
                        </span>
                    </a>
                    <a href="{{ route('user.profile', $rank3->id) }}" wire:navigate class="font-black text-[11px] sm:text-sm text-gray-900 dark:text-white truncate w-full mt-1 flex items-center justify-center gap-1 hover:text-atoma-blue dark:hover:text-atoma-cyan transition-colors" title="{{ __('Lihat profil :name', ['name' => $rank3->name]) }}">
                        <span class="truncate">{{ $rank3->name }}</span>
                        @if(!empty($rank3->equipped_badges_details))
                            <div class="flex items-center -space-x-1 shrink-0">
                                @foreach($rank3->equipped_badges_details as $b)
                                    <img src="{{ asset($b['svg_path']) }}" class="w-3.5 h-3.5 sm:w-4 sm:h-4 object-contain shrink-0" title="{{ $b['title'] }}">
                                @endforeach
                            </div>
                        @endif
                    </a>
                    <span class="text-[10px] sm:text-xs font-bold text-gray-500 dark:text-gray-400 mt-0.5 flex items-center justify-center gap-1">
                        <span>🔥</span> {{ number_format($rank3Xp) }} XP
                    </span>
                @endif
            </div>
        </div>

        <!-- Rankings List Header -->
        <div class="flex items-center justify-between text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider px-5 pt-2">
            <div class="flex items-center gap-8">
                <span class="w-6 text-center">{{ __('Rank') }}</span>
                <span>{{ __('Player') }}</span>
            </div>
            <span>{{ __('Points') }}</span>
        </div>

        <!-- Rankings List (#4 onwards) -->
        <div class="space-y-2.5">
            @forelse($remainingPlayers as $index => $player)
                @php
                    $realRank = $index + 4;
                    $playerXp = $player->period_xp ?? $player->xp;
                    $isUser = $player->id === auth()->id();
                @endphp
                <div wire:key="leaderboard-player-{{ $player->id }}"
                     class="rounded-2xl p-3 sm:p-3.5 flex items-center justify-between transition-all {{ $isUser ? 'bg-atoma-blue dark:bg-indigo-950/70 text-white dark:text-indigo-100 shadow-md ring-2 ring-atoma-cyan/40 dark:ring-1 dark:ring-indigo-500/40 border border-transparent dark:border-indigo-500/40' : 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-750 border border-gray-100 dark:border-gray-700/60 shadow-xs' }}">
                    
                    <!-- Left: Rank Number, Avatar & Name -->
                    <div class="flex items-center gap-3.5 min-w-0">
                        <span class="w-6 text-center font-black text-xs sm:text-sm {{ $isUser ? 'text-white dark:text-indigo-200' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $realRank }}
                        </span>
                        <a href="{{ route('user.profile', $player->id) }}" wire:navigate class="relative shrink-0 group block" title="{{ __('Lihat profil :name', ['name' => $player->name]) }}">
                            <img src="{{ $player->avatar_url }}" alt="{{ $player->name }}" class="w-9 h-9 rounded-full object-cover group-hover:scale-105 transition-transform {{ $isUser ? 'border-2 border-white/60 dark:border-indigo-400/60' : 'border border-gray-200 dark:border-gray-700' }}">
                            @if(!empty($player->equipped_badges_details))
                                @php $primaryBadge = $player->equipped_badges_details[0]; @endphp
                                <div class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-white dark:bg-gray-900 p-0.5 shadow-xs border border-gray-200 dark:border-gray-700 flex items-center justify-center" title="{{ $primaryBadge['title'] }}">
                                    <img src="{{ asset($primaryBadge['svg_path']) }}" class="w-full h-full object-contain">
                                </div>
                            @endif
                        </a>
                        <a href="{{ route('user.profile', $player->id) }}" wire:navigate class="font-extrabold text-xs sm:text-sm truncate flex items-center gap-1.5 hover:underline" title="{{ __('Lihat profil :name', ['name' => $player->name]) }}">
                            <span class="truncate">{{ $player->name }}{{ $isUser ? ' (' . __('You') . ')' : '' }}</span>
                            @if(!empty($player->equipped_badges_details))
                                <div class="flex items-center -space-x-1 shrink-0">
                                    @foreach($player->equipped_badges_details as $b)
                                        <img src="{{ asset($b['svg_path']) }}" class="w-4 h-4 object-contain shrink-0" title="{{ $b['title'] }}">
                                    @endforeach
                                </div>
                            @endif
                        </a>
                    </div>

                    <!-- Right: Points / Challenge -->
                    <div class="flex items-center gap-2 shrink-0 pl-2">
                        <span class="font-black text-xs sm:text-sm {{ $isUser ? 'text-white dark:text-indigo-200' : 'text-atoma-blue dark:text-atoma-cyan' }}">
                            {{ number_format($playerXp) }} XP
                        </span>
                        @if($leaderboardScope === 'friends' && !$isUser)
                            <button type="button" 
                                    wire:click="challengeFriend({{ $player->id }})" 
                                    class="px-2 py-0.5 bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan rounded-lg text-[10px] font-black hover:bg-atoma-blue hover:text-white transition-all cursor-pointer" 
                                    title="{{ __('Tantang adu produktivitas') }}">
                                ⚔️
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                @if($players->count() <= 3)
                    <div class="text-center py-6 text-xs text-gray-400">
                        {{ __('Selesaikan tugas harianmu dan jadilah yang teratas di leaderboard!') }}
                    </div>
                @endif
            @endforelse
        </div>

    @else
        <!-- Empty State -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-10 text-center border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="w-16 h-16 rounded-full bg-atoma-ice dark:bg-atoma-blue/20 flex items-center justify-center mx-auto mb-3 text-3xl">
                🏆
            </div>
            <h4 class="text-base font-black text-gray-900 dark:text-white mb-1">{{ __('Belum Ada Poin di Periode Ini') }}</h4>
            <p class="text-xs text-gray-500 max-w-sm mx-auto">{{ __('Selesaikan tugas harian, checklist milestone, atau sesi Pomodoro untuk mengumpulkan XP pertama!') }}</p>
        </div>
    @endif

    <!-- Sticky Floating "Your Standing" Card -->
    @if($standing = $this->userStanding)
        <div class="sticky bottom-4 z-40 bg-gradient-to-r from-atoma-ice/95 via-white/95 to-atoma-ice/95 dark:from-gray-900/95 dark:via-gray-850/95 dark:to-gray-900/95 backdrop-blur-md rounded-2xl p-3.5 sm:p-4 border border-atoma-aqua/60 dark:border-gray-700/80 shadow-xl flex items-center justify-between text-xs sm:text-sm text-gray-900 dark:text-white font-bold transition-transform transform hover:scale-[1.01]">
            <div class="flex items-center gap-2 truncate min-w-0">
                <span class="bg-atoma-blue dark:bg-indigo-600 text-white text-xs px-2.5 py-0.5 rounded-full font-black shrink-0">#{{ $standing['rank'] }}</span>
                <span class="truncate">{{ $standing['user']->name }} ({{ __('YOU') }}) • {{ number_format($standing['xp']) }} XP</span>
            </div>
            <div class="shrink-0 text-right text-xs pl-2">
                @if($standing['rank'] === 1)
                    <span class="text-amber-500 dark:text-amber-400 font-extrabold flex items-center gap-1">👑 {{ __('Pemimpin Liga') }}</span>
                @elseif($standing['gap_xp'] > 0 && $standing['next_player'])
                    <span class="text-atoma-blue dark:text-atoma-cyan font-extrabold">🚀 {{ __('Butuh :gap XP lagi ke #:rank', ['gap' => number_format($standing['gap_xp']), 'rank' => $standing['rank'] - 1]) }}</span>
                @else
                    <span class="text-atoma-blue dark:text-atoma-cyan font-extrabold">⚔️ {{ __('Pertahankan Peringkatmu!') }}</span>
                @endif
            </div>
        </div>
    @endif
</div>
