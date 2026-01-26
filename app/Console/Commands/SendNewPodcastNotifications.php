<?php

namespace App\Console\Commands;

use App\Jobs\SendExpoNotificationJob;
use App\Models\Podcast;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendNewPodcastNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:new-podcast';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send new podcast notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $podcast = Podcast::whereDate('published_at', today())
                ->with('topic')
                ->first();

        if (!$podcast) {
            Log::channel('tracking')->warning('No job to run.', ['new-podcast']);
            $this->warn('No new podcast for today.');
            return Command::FAILURE;
        }

        $query = User::where('notification_flg', true)
            ->whereHas('topics', fn($q) => $q->where('topics.id', $podcast->topic_id));

        $totalUsers = $query->count();
        if ($totalUsers === 0) {
            Log::channel('tracking')->warning('No users to push noti.', ['new-podcast', 'topic: '.$podcast->topic_id]);
            $this->info('No users for this topic.');
            return Command::SUCCESS;
        }

        $this->info("Sending to {$totalUsers} users...");
        Log::channel('tracking')->info('Sending to users.', ['new-podcast', 'user_total: '.$totalUsers, 'topic: '.$podcast->topic_id]);
        $query->chunk(100, function ($users) use ($podcast) {
            SendExpoNotificationJob::dispatch(
                userIds: $users->pluck('id')->toArray(),
                title: $podcast->notification_title ?? 'Nouveau podcast 🎧',
                body: $podcast->notification_body ?? $podcast->title,
                data: ['action' => 'podcast'],
                options: ['badge' => 1]
            );
        });

        Log::channel('tracking')->info('Completed job.', ['new-podcast']);
        $this->info('Done!');
        return Command::SUCCESS;
    }
}
