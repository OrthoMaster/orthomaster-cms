<?php

namespace App\Jobs;

use App\Mail\BookingNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Envoi d'un e-mail des rendez-vous experts (file d'attente : l'action ne l'attend pas). */
class SendBookingMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 30;

    public function __construct(
        public string $to,
        public BookingNotificationMail $mail,
    ) {
    }

    public function handle(): void
    {
        Mail::to($this->to)->send($this->mail);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('api')->error('SendBookingMailJob failed', [
            'to'      => $this->to,
            'subject' => $this->mail->subjectLine,
            'error'   => $exception->getMessage(),
        ]);
    }
}
