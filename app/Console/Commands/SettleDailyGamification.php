<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class SettleDailyGamification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gamification:settle-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process H+1 Midnight Settlement for all users with pending XP';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::where('pending_xp', '>', 0)->get();
        $settledCount = 0;

        foreach ($users as $user) {
            $res = $user->processDailySettlement();
            if (!empty($res['settled'])) {
                $settledCount++;
            }
        }

        $this->info("Successfully processed daily settlement for {$settledCount} users.");
        return Command::SUCCESS;
    }
}
