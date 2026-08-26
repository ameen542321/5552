<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
   public function handle()
{
    \App\Models\Log::where('created_at', '<', now()->subDays(3))->delete();

    $this->info('Logs older than 3 days deleted.');
}

}
