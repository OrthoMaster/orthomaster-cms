<?php

namespace App\Jobs;

use App\Mail\AfterUpdateNewVersionMail;
use App\Models\User;
use App\Status;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailAfterUpdateNewVersionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(
        public User $user,
    ) {}

    public function handle(): void
    {
        Mail::to($this->user->email)->send(
            new AfterUpdateNewVersionMail(
                $this->user,
                $this->user->email
            )
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('api')->error('SendEmailAfterUpdateNewVersionJob failed', [
            'message' => $exception->getMessage(),
            'user' => $this->user?->email
        ]);
    }
}
