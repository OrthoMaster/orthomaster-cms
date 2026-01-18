<?php

namespace App\Console\Commands;

use App\Jobs\SendExpoNotificationJob;
use App\Models\DailyQuestion;
use App\Models\User;
use Illuminate\Console\Command;

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
            $this->warn('No daily question for today.');
            return Command::FAILURE;
        }

        $query = User::where('notification_flg', true)
            ->whereHas('topics', fn($q) => $q->where('topics.id', $dailyQuestion->topic_id));

        $totalUsers = $query->count();
        if ($totalUsers === 0) {
            $this->info('No users for this topic.');
            return Command::SUCCESS;
        }

        $this->info("Sending to {$totalUsers} users...");
        $query->chunk(100, function ($users) use ($dailyQuestion) {
            SendExpoNotificationJob::dispatch(
                userIds: $users->pluck('id')->toArray(),
                title: 'Question du jour',
                body: $dailyQuestion->question,
                data: ['type' => 'daily_question'],
                options: ['badge' => 1]
            );
        });

        $this->info('✅ Done!');
        return Command::SUCCESS;
    }
}
