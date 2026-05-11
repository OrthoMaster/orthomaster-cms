<?php

namespace App\Jobs;

use App\Mail\RemindManagerMail;
use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailRemindManagerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 30;

    public function __construct(
        public User $manager,
    ) {}

    public function handle(): void
    {
        Mail::to($this->manager->email)->send(
            new RemindManagerMail(
                $this->manager,
                $this->manager->email
            )
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('api')->error('SendEmailRemindMangerJob failed', [
            'message' => $exception->getMessage(),
            'user' => $this->manager?->email
        ]);
    }
}
