<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailRemindManagerJob;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendEmailsRemindManagers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:remind-managers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send emails to remind the managers.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = Company::where('active_flg', true)
            ->whereHas('users', function ($q) {
                $q->where('company_user.manager_flg', true);
            })
            ->whereDoesntHave('users', function ($q) {
                $q->where('company_user.manager_flg', true)
                    ->whereHas('packages');
            })
            ->has('users', '>', 1);

        $globalIndex = 1;
        $query->chunk(100, function ($companies) use (&$globalIndex) {
            foreach ($companies as $company) {
                foreach ($company->managers as $manager) {
                    SendEmailRemindManagerJob::dispatch($manager)
                        ->delay(now()->addSeconds($globalIndex));
                    $globalIndex++;
                }
            }
        });

        Log::channel('tracking')->info('Completed job.', ['emails-remind-managers']);
        return Command::SUCCESS;
    }
}
