<?php

namespace App\Console\Commands;

use App\Jobs\SendExpoNotificationJob;
use App\Models\Infographic;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendTestNofications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $infographic = Infographic::first();

        $query = User::where('role_id', 7);

        $totalUsers = $query->count();
        if ($totalUsers === 0) {
            Log::channel('tracking')->warning('No users to push noti.', ['test']);
            $this->info('No users.');
            return Command::SUCCESS;
        }

        $this->info("Sending to {$totalUsers} users...");
        Log::channel('tracking')->info('Sending to users.');
        $query->chunk(100, function ($users) use ($infographic) {
            SendExpoNotificationJob::dispatch(
                userIds: $users->pluck('id')->toArray(),
                title: $infographic->notification_title ?? 'Infographie 🎨',
                body: $infographic->notification_body ?? $infographic->title,
                data: [
                    'action' => 'infographic',
                    'id' => $infographic->id,
                ],
                // options: ['badge' => 1]
            );
        });

        Log::channel('tracking')->info('Completed job.', ['test']);
        $this->info('Done!');
        return Command::SUCCESS;
    }
}
