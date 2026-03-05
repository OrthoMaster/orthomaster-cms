<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDailyAppOpensReportJob;
use App\Jobs\GenerateUserAgentsReportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateUserAgentsReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:user-agents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate user agents reports';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        GenerateUserAgentsReportJob::dispatch();

        Log::channel('tracking')->info('Completed job.', ['user-agents-report']);
        return Command::SUCCESS;
    }
}
