<?php

namespace App\Console\Commands;

use App\Jobs\SendExpoNotificationJob;
use App\Models\Infographic;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendNewInfographicNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:new-infographic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send new infographic notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $infographic = Infographic::whereDate('published_at', today())
                ->with('topic')
                ->first();

        if (!$infographic) {
            Log::channel('tracking')->warning('No job to run.', ['new-infographic']);
            $this->warn('No new infographic for today.');
            return Command::FAILURE;
        }

        $query = User::where('notification_flg', true)
            ->whereHas('topics', fn($q) => $q->where('topics.id', $infographic->topic_id));

        $totalUsers = $query->count();
        if ($totalUsers === 0) {
            Log::channel('tracking')->warning('No users to push noti.', ['new-infographic', 'topic: '.$infographic->topic_id]);
            $this->info('No users for this topic.');
            return Command::SUCCESS;
        }

        $this->info("Sending to {$totalUsers} users...");
        Log::channel('tracking')->info('Sending to users.', ['new-infographic:' .$infographic->id, 'user_total: '.$totalUsers, 'topic: '.$infographic->topic_id]);
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

        Log::channel('tracking')->info('Completed job.', ['new-infographic']);
        $this->info('Done!');
        return Command::SUCCESS;
    }
}
