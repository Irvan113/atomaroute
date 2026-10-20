<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\User;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('gamification:settle-daily')
    ->dailyAt('00:01')
    ->timezone('Asia/Jakarta')
    ->description('Daily Gamification H+1 Settlement for all users with pending XP');

Schedule::call(function () {
    $users = User::where('monthly_xp', '>', 0)
        ->orderBy('monthly_xp', 'desc')
        ->get();
    $monthKey = Carbon::now('Asia/Jakarta')->subDays(1)->format('Y-m');

    $rank = 1;
    foreach ($users as $user) {
        $user->prev_month_xp = $user->monthly_xp;
        $user->monthly_xp = 0;
        $user->monthly_xp_reset_at = Carbon::now()->startOfMonth();
        $user->save();

        \App\Models\LeaderboardSeasonHistory::create([
            'user_id' => $user->id,
            'period_type' => 'monthly',
            'period_key' => $monthKey,
            'xp' => $user->prev_month_xp,
            'rank' => $rank++,
            'league' => $user->league ?? 'bronze_3',
        ]);
    }
})->monthlyOn(1, '00:00')->timezone('Asia/Jakarta');

Schedule::command('league:settle-weekly')
    ->weeklyOn(1, '12:00')
    ->timezone('Asia/Jakarta')
    ->description('Weekly League Settlement with H+1 Grace Period on Monday 12:00 PM WIB');

Schedule::command('backup:clean')
    ->dailyAt('01:30')
    ->timezone('Asia/Jakarta')
    ->description('Clean up old backups per retention strategy');

Schedule::command('backup:run --only-db')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->description('Automated daily database backup');

