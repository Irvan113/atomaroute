<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\StreakHistory;
use App\Services\StreakEvaluatorService;
use Carbon\Carbon;

new class extends Component {
    public $isOpen = false;
    public $year;
    public $month;
    public $selectedDay;
    public $currentStreak = 0;
    public $longestStreak = 0;
    public $streakCoupons = 0;

    public function mount()
    {
        $today = Carbon::today();
        $this->year = $today->year;
        $this->month = $today->month;
        $this->selectedDay = $today->day;
        $this->refreshUserStreakStats();
    }

    public function refreshUserStreakStats()
    {
        $user = auth()->user();
        if ($user) {
            $user = StreakEvaluatorService::evaluate($user);
            $this->currentStreak = $user->current_streak;
            $this->longestStreak = $user->longest_streak;
            $this->streakCoupons = $user->streak_coupons;
        }
    }

    #[On('open-streak-modal')]
    public function openModal()
    {
        $this->isOpen = true;
        $this->refreshUserStreakStats();
    }

    #[On('streak-updated')]
    public function onStreakUpdated($streakDays = null, $currentStreak = null, $longestStreak = null)
    {
        if ($currentStreak !== null) $this->currentStreak = $currentStreak;
        if ($longestStreak !== null) $this->longestStreak = $longestStreak;
        $this->refreshUserStreakStats();
    }

    public function prevMonth()
    {
        $current = Carbon::createFromDate($this->year, $this->month, 1)->subMonth();
        $this->year = $current->year;
        $this->month = $current->month;

        if ($this->year === Carbon::today()->year && $this->month === Carbon::today()->month) {
            $this->selectedDay = Carbon::today()->day;
        } else {
            $this->selectedDay = 1;
        }
    }

    public function nextMonth()
    {
        $current = Carbon::createFromDate($this->year, $this->month, 1)->addMonth();
        $this->year = $current->year;
        $this->month = $current->month;

        if ($this->year === Carbon::today()->year && $this->month === Carbon::today()->month) {
            $this->selectedDay = Carbon::today()->day;
        } else {
            $this->selectedDay = 1;
        }
    }

    public function goToToday()
    {
        $today = Carbon::today();
        $this->year = $today->year;
        $this->month = $today->month;
        $this->selectedDay = $today->day;
    }

    public function selectDay($day)
    {
        $this->selectedDay = $day;
    }

    public function closeModal()
    {
        $this->isOpen = false;
    }
}; ?>

<div x-data="{ 
        open: @entangle('isOpen'),
        timeLeft: '',
        startTimer() {
            setInterval(() => {
                let now = new Date();
                let tomorrow = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
                let diff = tomorrow - now;
                let h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                let m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                let s = Math.floor((diff % (1000 * 60)) / 1000);
                this.timeLeft = `${h}h ${m}m ${s}s`;
            }, 1000);
        }
    }" 
     x-init="startTimer()"
     @open-streak-modal.window="open = true" 
     class="relative z-50" 
     style="display: none;" 
     x-show="open">
    
    @php
        $todayDate = \Carbon\Carbon::today();
        $currentViewDate = \Carbon\Carbon::createFromDate($year, $month, 1);
        $startMonth = $currentViewDate->copy()->startOfMonth();
        $endMonth = $currentViewDate->copy()->endOfMonth();
        $daysMonth = $currentViewDate->daysInMonth;
        $startDow = $startMonth->dayOfWeek; // 0=Sun, 1=Mon, ..., 6=Sat
        $isCurrentMonth = ($year === $todayDate->year && $month === $todayDate->month);

        // Fetch streak records for the viewed month
        $streakRecords = \App\Models\StreakHistory::where('user_id', auth()->id())
            ->whereBetween('date', [$startMonth->copy()->startOfDay()->toDateTimeString(), $endMonth->copy()->endOfDay()->toDateTimeString()])
            ->get();

        $streakDays = [];
        foreach ($streakRecords as $record) {
            $day = \Carbon\Carbon::parse($record->date)->day;
            $streakDays[$day] = $record->status;
        }

        // Also check completed tasks for viewed month to guarantee 100% detection
        $completedTasks = \App\Models\Task::where('user_id', auth()->id())
            ->where('is_completed', true)
            ->whereBetween('date', [$startMonth->copy()->startOfDay()->toDateTimeString(), $endMonth->copy()->endOfDay()->toDateTimeString()])
            ->get();

        foreach ($completedTasks as $task) {
            $day = \Carbon\Carbon::parse($task->date)->day;
            if (!isset($streakDays[$day]) || $streakDays[$day] !== 'completed') {
                $streakDays[$day] = 'completed';
            }
        }

        $isTodayCompleted = isset($streakDays[$todayDate->day]) && $streakDays[$todayDate->day] === 'completed';
        $selectedStatus = $streakDays[$selectedDay] ?? null;
        $isSelectedFuture = ($year > $todayDate->year) || ($year === $todayDate->year && $month > $todayDate->month) || ($isCurrentMonth && $selectedDay > $todayDate->day);
        $selectedDateObj = \Carbon\Carbon::createFromDate($year, $month, min($selectedDay, $daysMonth));
    @endphp

    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
    <div x-show="open" x-transition class="fixed inset-0 flex items-center justify-center p-4 sm:p-0">
        <div @click.away="$wire.closeModal()" class="bg-white dark:bg-gray-900 sm:rounded-3xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[95vh]">
            
            <!-- Orange Header Area -->
            <div class="bg-gradient-to-b from-orange-400 to-orange-500 px-6 pt-6 pb-12 relative text-white shrink-0">
                <!-- Top Nav -->
                <div class="flex justify-between items-center mb-6">
                    <button type="button" @click="$wire.closeModal()" class="text-white hover:bg-white/20 p-2 rounded-full transition-colors cursor-pointer">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                    <h3 class="text-xl font-bold tracking-wide">{{ __('Streak') }}</h3>
                    <div class="w-10"></div>
                </div>
                
                <!-- Content -->
                <div class="flex justify-between items-center pr-2 mb-6 mt-2">
                    <div class="pt-2">
                        <div class="text-6xl sm:text-7xl font-black drop-shadow-md leading-none">{{ $currentStreak }}</div>
                        <div class="text-xl sm:text-2xl font-bold mt-2 drop-shadow-md">{{ __('day streak!') }}</div>
                    </div>
                    <!-- Huge Flame Icon -->
                    <div class="relative group mt-2">
                        <div class="absolute inset-0 bg-yellow-400 blur-2xl opacity-40 rounded-full group-hover:opacity-70 transition-opacity duration-500"></div>
                        <svg class="w-24 h-24 sm:w-32 sm:h-32 text-yellow-300 drop-shadow-2xl relative z-10 animate-[pulse_3s_ease-in-out_infinite]" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"></path></svg>
                    </div>
                </div>
                
                <!-- Daily Progress Bar -->
                <div class="mt-6 bg-black/10 rounded-2xl p-4 backdrop-blur-sm border border-white/20">
                    <div class="flex justify-between items-center text-xs font-bold mb-2 uppercase tracking-wide">
                        <div class="text-sm font-bold opacity-90 tracking-wider">{{ __('TARGET HARI INI') }}</div>
                        <div class="text-sm font-bold opacity-90">{{ $isTodayCompleted ? __('1/1 SELESAI') : __('0/1 SELESAI') }}</div>
                    </div>
                    <div class="w-full bg-black/20 rounded-full h-3 shadow-inner overflow-hidden mb-2 relative">
                        <div class="absolute inset-0 bg-white/10 rounded-full"></div>
                        <div class="bg-gradient-to-r from-yellow-300 via-yellow-200 to-yellow-400 rounded-full h-full transition-all duration-1000 shadow-[0_0_15px_rgba(253,224,71,0.9)] relative overflow-hidden" style="width: {{ $isTodayCompleted ? '100%' : '0%' }}">
                            <!-- Shine effect -->
                            <div class="absolute top-0 inset-x-0 h-1/2 bg-gradient-to-b from-white/50 to-transparent"></div>
                        </div>
                    </div>
                    <div class="text-xs text-orange-100 font-medium flex justify-between items-center">
                        <span>{{ $isTodayCompleted ? __('Bagus sekali! Sampai jumpa besok.') : __('Selesaikan 1 tugas untuk mempertahankan streak.') }}</span>
                        <span class="bg-black/20 px-2 py-0.5 rounded-full flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            {{ __('Reset:') }} <span x-text="timeLeft" class="ml-1 font-bold"></span>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Middle Floating Card -->
            <div class="px-6 relative -mt-10 z-20 space-y-3 shrink-0">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-5 border-2 border-orange-100 dark:border-gray-700 hover:shadow-2xl transition-shadow cursor-default flex items-center justify-between">
                    <div>
                        <h4 class="font-black text-gray-900 dark:text-gray-100 text-lg">{{ __('Streak Terpanjang') }}</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ __('Rekor pribadi terbaik sepanjang masa') }}</p>
                    </div>
                    <div class="text-3xl font-black text-orange-500 flex items-center gap-1.5">
                        <span>{{ $longestStreak }}</span>
                        <svg class="w-6 h-6 text-orange-500 fill-orange-500 inline-block" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.316.492-.63 1.154-.925 1.933-.59 1.558-1.18 3.526-1.597 5.166-.364.08-.737.126-1.121.126-1.895 0-3.48-1.12-4.11-2.73a1 1 0 00-1.636-.264c-1.39 1.545-2.074 3.535-1.928 5.568.3 4.195 3.82 7.553 8.044 7.553 4.544 0 8.232-3.69 8.232-8.235 0-3.612-2.193-6.732-5.112-7.897z" clip-rule="evenodd"/></svg>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-4 flex items-center border border-gray-100 dark:border-gray-700">
                    <div class="w-12 h-12 bg-emerald-50 dark:bg-emerald-950/40 rounded-xl flex items-center justify-center p-1.5 mr-3 shrink-0 border border-emerald-200 dark:border-emerald-800/60 shadow-xs">
                        <img src="{{ asset('images/icons/streak_saver.svg') }}" alt="Streak Protection" class="w-full h-full object-contain">
                    </div>
                    <div class="flex-1">
                        <h4 class="font-bold text-gray-900 dark:text-gray-100 text-sm">{{ __('Streak Protection') }}</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-medium">{{ __('Kamu memiliki') }} <strong class="text-emerald-600 dark:text-emerald-400">{{ $streakCoupons }} {{ __('kupon') }}</strong> {{ __('untuk melindungi hari bolong.') }}</p>
                    </div>
                    <a href="/store" class="text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 uppercase text-[10px] font-bold bg-emerald-50 dark:bg-emerald-900/30 px-2.5 py-1 rounded-md border border-emerald-200 dark:border-emerald-800">{{ __('BELI') }}</a>
                </div>
            </div>
            
            <!-- Bottom Calendar & Rules Area -->
            <div class="px-6 pt-6 pb-6 flex-1 overflow-y-auto hide-scrollbar space-y-6">
                
                <!-- How it works -->
                <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl p-4 border border-indigo-100 dark:border-indigo-800/50">
                    <h4 class="font-bold text-indigo-900 dark:text-indigo-300 text-sm mb-2 flex items-center">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        {{ __('Cara Kerja Streak') }}
                    </h4>
                    <ul class="text-[11px] sm:text-xs text-indigo-700 dark:text-indigo-400 space-y-1.5 font-medium list-disc list-inside">
                        <li>{!! __('Selesaikan minimal :target untuk menambah 1 streak.', ['target' => '<strong>' . __('1 tugas setiap hari') . '</strong>']) !!}</li>
                        <li>{{ __('Jika terlewat 1 hari tanpa kupon perlindungan, streak akan kembali ke 0.') }}</li>
                        <li>{{ __('Menyelesaikan banyak tugas dalam 1 hari tetap dihitung 1 streak.') }}</li>
                    </ul>
                </div>

                <div class="flex justify-between items-center mb-2">
                    <h3 class="text-xl font-extrabold text-gray-900 dark:text-white">{{ __('Streak Calendar') }}</h3>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-3xl border-2 border-gray-100 dark:border-gray-700 p-5 shadow-sm">
                    <!-- Interactive Month Navigation Controls (< and >) -->
                    <div class="flex justify-between items-center mb-6 px-1">
                        <button type="button" 
                                wire:click="prevMonth" 
                                class="p-2 rounded-xl text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition-all cursor-pointer select-none active:scale-90"
                                title="{{ __('Bulan Sebelumnya') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        
                        <div class="flex items-center gap-2">
                            <div class="text-center font-black text-gray-800 dark:text-gray-100 text-sm sm:text-base">
                                {{ $currentViewDate->translatedFormat('F Y') }}
                            </div>
                            @if(!$isCurrentMonth)
                                <button type="button" 
                                        wire:click="goToToday" 
                                        class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-orange-100 text-orange-700 dark:bg-orange-950/60 dark:text-orange-300 border border-orange-200 dark:border-orange-800 hover:bg-orange-200 transition-colors cursor-pointer"
                                        title="{{ __('Kembali ke bulan ini') }}">
                                    {{ __('Bulan Ini') }}
                                </button>
                            @endif
                        </div>

                        <button type="button" 
                                wire:click="nextMonth" 
                                class="p-2 rounded-xl text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition-all cursor-pointer select-none active:scale-90"
                                title="{{ __('Bulan Berikutnya') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-7 gap-y-4 gap-x-0 text-center relative">
                        @foreach(['Min','Sen','Sel','Rab','Kam','Jum','Sab'] as $dn)
                            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">{{ __($dn) }}</div>
                        @endforeach
                        
                        {{-- Preceding Empty Days in First Week --}}
                        @for($i = 0; $i < $startDow; $i++)
                            <div></div>
                        @endfor
                        
                        {{-- Days in Current View Month --}}
                        @for($d = 1; $d <= $daysMonth; $d++)
                            @php
                                $status = $streakDays[$d] ?? null;
                                $isCompleted = $status === 'completed';
                                $isFrozen = $status === 'frozen';
                                $isActive = $isCompleted || $isFrozen;
                                
                                $dayDate = \Carbon\Carbon::createFromDate($year, $month, $d);
                                $isFuture = $dayDate->isAfter($todayDate);
                                $isToday = $isCurrentMonth && ($d === $todayDate->day);
                                $isSelected = ($selectedDay === $d);

                                // Connectors logic for consecutive completed/frozen streak days
                                $prevStatus = $streakDays[$d - 1] ?? null;
                                $nextStatus = $streakDays[$d + 1] ?? null;
                                
                                // Week column index (0 = Sunday, 6 = Saturday)
                                $colIndex = ($startDow + $d - 1) % 7;
                                $hasPrevConnector = ($prevStatus === 'completed' || $prevStatus === 'frozen') && $colIndex !== 0;
                                $hasNextConnector = ($nextStatus === 'completed' || $nextStatus === 'frozen') && $colIndex !== 6;
                            @endphp
                            <div class="flex items-center justify-center relative h-10 cursor-pointer group" wire:click="selectDay({{ $d }})">
                                <!-- Connectors for consecutive streaks -->
                                @if($isActive)
                                    <div>
                                        @if($hasPrevConnector)
                                            <div class="absolute left-0 w-1/2 h-8 z-0 {{ $isFrozen ? 'bg-blue-100 dark:bg-blue-900/40' : 'bg-orange-100 dark:bg-orange-900/40' }}"></div>
                                        @endif
                                        @if($hasNextConnector)
                                            <div class="absolute right-0 w-1/2 h-8 z-0 {{ $isFrozen ? 'bg-blue-100 dark:bg-blue-900/40' : 'bg-orange-100 dark:bg-orange-900/40' }}"></div>
                                        @endif
                                    </div>
                                @endif
                                
                                <div class="w-8 h-8 flex items-center justify-center rounded-full text-[13px] font-bold transition-all z-10 group-hover:scale-110 
                                    @if($isCompleted)
                                        bg-orange-500 text-white shadow-md
                                    @elseif($isFrozen)
                                        bg-blue-500 text-white shadow-md
                                    @elseif($isFuture)
                                        text-gray-300 dark:text-gray-600
                                    @else
                                        text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700
                                    @endif
                                    @if($isSelected)
                                        ring-2 ring-offset-2 ring-orange-400 dark:ring-offset-gray-800 scale-110
                                    @elseif($isToday && !$isActive)
                                        ring-2 ring-gray-300 dark:ring-gray-600
                                    @endif">
                                    {{ $d }}
                                </div>
                            </div>
                        @endfor
                    </div>
                    
                    <!-- Selected Date Info -->
                    <div class="mt-5 bg-gray-50 dark:bg-gray-700/50 rounded-2xl p-3.5 border border-gray-100 dark:border-gray-600 flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg mr-3 font-black
                                @if($selectedStatus === 'completed')
                                    bg-orange-100 text-orange-600 dark:bg-orange-900/50 dark:text-orange-400
                                @elseif($selectedStatus === 'frozen')
                                    bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400
                                @else
                                    bg-gray-200 text-gray-500 dark:bg-gray-600 dark:text-gray-400
                                @endif">
                                <span>{{ $selectedDay }}</span>
                            </div>
                            <div>
                                <div class="text-[10px] uppercase font-bold text-gray-400">
                                    {{ $selectedDateObj->translatedFormat('d F Y') }}
                                </div>
                                <div class="text-sm font-bold text-gray-800 dark:text-gray-200">
                                    @if($selectedStatus === 'completed')
                                        {{ __('Goal Met (Tercapai)') }}
                                    @elseif($selectedStatus === 'frozen')
                                        {{ __('Streak Dilindungi (Frozen)') }}
                                    @elseif($isSelectedFuture)
                                        {{ __('Akan Datang (Upcoming)') }}
                                    @else
                                        {{ __('Goal Missed (Terlewat)') }}
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="text-sm font-black 
                            @if($selectedStatus === 'completed')
                                text-orange-500
                            @elseif($selectedStatus === 'frozen')
                                text-blue-500
                            @else
                                text-gray-400
                            @endif">
                            <span>{{ $selectedStatus === 'completed' ? '1/1' : '0/1' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
