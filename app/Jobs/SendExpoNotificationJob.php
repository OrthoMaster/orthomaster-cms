// app/Jobs/SendExpoNotificationJob.php
<?php

namespace App\Jobs;

use App\Services\ExpoNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendExpoNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public array $expoPushTokens,
        public string $title,
        public string $body,
        public array $data = [],
        public array $options = []
    ) {}

    public function handle(ExpoNotificationService $expoService): void
    {
        $expoService->sendToMultipleDevices(
            $this->expoPushTokens,
            $this->title,
            $this->body,
            $this->data,
            $this->options
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('api')->error('SendExpoNotificationJob failed', [
            'message' => $exception->getMessage(),
            'tokens' => $this->expoPushTokens,
        ]);
    }
}