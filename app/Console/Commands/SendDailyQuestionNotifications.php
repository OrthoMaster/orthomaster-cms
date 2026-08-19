<?php

namespace App\Console\Commands;

use App\Jobs\SendExpoNotificationJob;
use App\Models\DailyQuestion;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyQuestionNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:daily-question';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily question push notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dailyQuestion = DailyQuestion::whereDate('published_at', today())
            ->with(['topic', 'answers'])
            ->first();

        if (!$dailyQuestion) {
            Log::channel('tracking')->warning('No job to run.', ['daily-question']);
            $this->warn('No daily question for today.');
            return Command::FAILURE;
        }

        $query = User::where('notification_flg', true)
            ->whereHas('topics', fn($q) => $q->where('topics.id', $dailyQuestion->topic_id));

        $totalUsers = $query->count();
        if ($totalUsers === 0) {
            Log::channel('tracking')->warning('No users to push noti.', ['daily-question', 'topic: ' . $dailyQuestion->topic_id]);
            $this->info('No users for this topic.');
            return Command::SUCCESS;
        }

        $this->info("Sending to {$totalUsers} users...");
        Log::channel('tracking')->info('Sending to users.', ['daily-question', 'user_total: ' . $totalUsers, 'topic: ' . $dailyQuestion->topic_id]);
        $query->chunk(100, function ($users) use ($dailyQuestion) {
            SendExpoNotificationJob::dispatch(
                userIds: $users->pluck('id')->toArray(),
                title: $dailyQuestion->notification_title ?? 'Question du jour',
                body: $dailyQuestion->notification_body ?? $dailyQuestion->question,
                data: [
                    'action' => 'daily-question',
                    'id' => $dailyQuestion->id,
                ],
                // options: ['badge' => 1]
            );
        });

        Log::channel('tracking')->info('Completed job.', ['daily-question']);
        $this->info('Done!');
        return Command::SUCCESS;
    }
}
