<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LeagueService;

class SettleWeeklyLeaguesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'league:settle-weekly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Settle weekly league promotions and demotions with H+1 Sunday settlement grace period at Monday 12:00 PM GMT+7';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Weekly League Settlement (GMT+7 Asia/Jakarta)...');

        $results = LeagueService::settleWeeklyLeagues();

        $this->info("Settlement Completed Successfully!");
        $this->table(
            ['Category', 'Count'],
            [
                ['Promoted Users', $results['promoted']],
                ['Demoted Users', $results['demoted']],
                ['Maintained Rank', $results['maintained']],
                ['Master League Assigned', $results['master']],
            ]
        );

        return Command::SUCCESS;
    }
}
