<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use App\Models\Task;
use Carbon\Carbon;

new class extends Component {
    #[Computed]
    public function activityData()
    {
        $userId = auth()->id();
        
        // We'll show the last 12 weeks (84 days)
        $startDate = Carbon::today()->subWeeks(12)->startOfWeek();
        $endDate = Carbon::today()->endOfWeek();
        
        $tasks = Task::where('user_id', $userId)
            ->where('is_completed', true)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->get();
            
        $completedCounts = [];
        foreach ($tasks as $task) {
            $dateString = Carbon::parse($task->date)->format('Y-m-d');
            if (!isset($completedCounts[$dateString])) {
                $completedCounts[$dateString] = 0;
            }
            $completedCounts[$dateString]++;
        }
        
        $grid = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $dateString = $currentDate->format('Y-m-d');
            $count = $completedCounts[$dateString] ?? 0;
            
            // Determine level (0-4) for color intensity
            $level = 0;
            if ($count > 0) {
                if ($count <= 2) $level = 1;
                elseif ($count <= 4) $level = 2;
                elseif ($count <= 6) $level = 3;
                else $level = 4;
            }
            
            $grid[] = [
                'date' => $dateString,
                'count' => $count,
                'level' => $level,
                'day_of_week' => $currentDate->dayOfWeek, // 0 = Sunday, 1 = Monday, etc.
                'is_today' => $currentDate->isToday(),
            ];
            
            $currentDate->addDay();
        }
        
        return $grid;
    }
}; ?>

<div class="bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700/60 rounded-[2rem] p-8 sm:p-10">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-xl font-black tracking-tight text-gray-900 dark:text-white">Activity Graph</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium mt-1">Your task completion over the last 12 weeks.</p>
        </div>
        <div class="text-3xl drop-shadow-sm">📈</div>
    </div>
    
    <div class="overflow-x-auto pb-4 hide-scrollbar">
        <div class="inline-flex flex-col gap-1">
            <!-- 7 rows for days of the week -->
            @for ($day = 0; $day < 7; $day++)
                <div class="flex gap-1">
                    <div class="w-8 text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase tracking-widest flex items-center h-4 mr-2">
                        @if ($day == 1) Mon
                        @elseif ($day == 3) Wed
                        @elseif ($day == 5) Fri
                        @endif
                    </div>
                    
                    @foreach (collect($this->activityData)->filter(fn($item) => $item['day_of_week'] == $day) as $cell)
                        @php
                            $bgClass = 'bg-gray-100 dark:bg-gray-700/50';
                            if ($cell['level'] == 1) $bgClass = 'bg-green-200 dark:bg-green-900/60';
                            elseif ($cell['level'] == 2) $bgClass = 'bg-green-300 dark:bg-green-700/80';
                            elseif ($cell['level'] == 3) $bgClass = 'bg-green-400 dark:bg-green-600';
                            elseif ($cell['level'] == 4) $bgClass = 'bg-green-500 dark:bg-green-500';
                            
                            $borderClass = $cell['is_today'] ? 'ring-2 ring-indigo-500 dark:ring-indigo-400 z-10' : '';
                        @endphp
                        <div title="{{ $cell['date'] }}: {{ $cell['count'] }} tasks completed" 
                             class="w-4 h-4 rounded-sm {{ $bgClass }} {{ $borderClass }} transition-transform hover:scale-125 cursor-pointer">
                        </div>
                    @endforeach
                </div>
            @endfor
        </div>
    </div>
    
    <div class="flex items-center gap-2 mt-4 text-xs font-bold text-gray-500 dark:text-gray-400 justify-end">
        <span>Less</span>
        <div class="w-3 h-3 rounded-sm bg-gray-100 dark:bg-gray-700/50"></div>
        <div class="w-3 h-3 rounded-sm bg-green-200 dark:bg-green-900/60"></div>
        <div class="w-3 h-3 rounded-sm bg-green-300 dark:bg-green-700/80"></div>
        <div class="w-3 h-3 rounded-sm bg-green-400 dark:bg-green-600"></div>
        <div class="w-3 h-3 rounded-sm bg-green-500 dark:bg-green-500"></div>
        <span>More</span>
    </div>
</div>
