<?php

namespace App\Jobs;

use App\Mail\RemindManagerMail;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailRemindMangerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(
        public Company $company,
    ) {}

    public function handle(): void
    {
        foreach ($this->company->managers() as $manager) {
            Mail::to($manager->email)->send(
                new RemindManagerMail(
                    $manager,
                    $manager->email
                )
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('api')->error('SendEmailRemindMangerJob failed', [
            'message' => $exception->getMessage(),
            'company' => $this->company?->name
        ]);
    }
}
