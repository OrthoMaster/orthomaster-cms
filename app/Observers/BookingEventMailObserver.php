<?php

namespace App\Observers;

use App\Models\BookingEvent;
use App\Services\Booking\BookingMailService;

/**
 * E-mails des rendez-vous experts, déclenchés par le journal (voir BookingMailService).
 * Après validation : une transaction annulée n'envoie rien.
 */
class BookingEventMailObserver
{
    public bool $afterCommit = true;

    public function __construct(private BookingMailService $mails)
    {
    }

    public function created(BookingEvent $event): void
    {
        // Comme le journal : un e-mail ne doit jamais faire échouer une action sur le rendez-vous.
        try {
            $this->mails->forEvent($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
