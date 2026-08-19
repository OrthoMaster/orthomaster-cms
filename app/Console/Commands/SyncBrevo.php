<?php

namespace App\Console\Commands;

use App\Http\Controllers\SyncBrevoController;
use App\Jobs\GenerateDailyAppOpensReportJob;
use App\Jobs\GenerateUserAgentsReportJob;
use App\Jobs\SyncUserToBrevo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncBrevo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:brevo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Brevo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $syncBrevoController = new SyncBrevoController();
        $syncBrevoController->syncUsers();
        return Command::SUCCESS;
    }
}
