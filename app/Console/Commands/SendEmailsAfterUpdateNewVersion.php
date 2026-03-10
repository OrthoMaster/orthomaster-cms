<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailAfterUpdateNewVersionJob;
use App\Jobs\SendExpoNotificationJob;
use App\Models\Podcast;
use App\Models\User;
use App\Status;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendEmailsAfterUpdateNewVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:after-update-new-version';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send emails after update new version';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::channel('tracking')->info('Sending emails after update new version.', []);
        $query = User::where('status', Status::ACTIVE)->where('id', '>', 20);
        $globalIndex = 0;
        $query->chunk(100, function ($users) use (&$globalIndex) {
            foreach ($users as $user) {
                SendEmailAfterUpdateNewVersionJob::dispatch($user)
                    ->delay(now()->addSeconds($globalIndex * 2));
                $globalIndex++;
            }
        });

        Log::channel('tracking')->info('Completed job.', ['emails-after-update-new-version']);
        $this->info('Done!');
        return Command::SUCCESS;
    }
}
