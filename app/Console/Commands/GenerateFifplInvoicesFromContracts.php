<?php

namespace App\Console\Commands;

use App\Jobs\SetScheduleFifplInvoiceJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateFifplInvoicesFromContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fifpl:pre-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate fifpl invoice from contract';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        SetScheduleFifplInvoiceJob::dispatch();

        Log::channel('tracking')->info('Completed job.', ['generate-fifpl-invoices']);
        return Command::SUCCESS;
    }
}
