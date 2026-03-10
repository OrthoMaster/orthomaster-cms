<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailAfterUpdateNewVersionJob;
use App\Models\OldVersionUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendEmailsAfterUpdateNewVersionWithOldUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:after-update-new-version-old-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send emails after update new version for old users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::channel('tracking')->info('Sending emails after update new version for old users.', []);
        $query = OldVersionUser::whereNotExists(function ($query2) {
            $query2->select('id_user')
                ->from('users')
                ->whereColumn('users.id_user', 'user.id_user');
        });
        $query->chunk(100, function ($users) {
            foreach ($users as $user) {
                SendEmailAfterUpdateNewVersionJob::dispatch($user);
            }
        });

        Log::channel('tracking')->info('Completed job.', ['emails-after-update-new-version-old-users']);
        $this->info('Done!');
        return Command::SUCCESS;
    }
}
