<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDailyAppOpensReportJob;
use App\Jobs\GenerateUserAgentsReportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateAppOpensReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:daily-app-opens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily open apps reports';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        GenerateDailyAppOpensReportJob::dispatch();

        Log::channel('tracking')->info('Completed job.', ['daily-open-app-report']);
        return Command::SUCCESS;
    }
}
