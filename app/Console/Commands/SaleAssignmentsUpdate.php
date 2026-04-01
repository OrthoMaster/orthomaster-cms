<?php

namespace App\Console\Commands;

use App\Http\Controllers\SaleAssignmentController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SaleAssignmentsUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:update-assignments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update sale assigments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new SaleAssignmentController();
        $controller->assign();

        Log::channel('tracking')->info('Completed job.', ['sale-assignments']);
        return Command::SUCCESS;
    }
}
