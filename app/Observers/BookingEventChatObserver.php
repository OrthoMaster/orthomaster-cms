<?php

namespace App\Observers;

use App\Models\BookingEvent;
use App\Services\Booking\BookingChatService;

/**
 * Recopie les événements des rendez-vous dans le fil de messagerie du couple.
 * Un seul point d'accroche (le journal) : les services de réservation n'ont pas à
 * connaître la messagerie.
 */
class BookingEventChatObserver
{
    /** Après validation : une annulation de transaction ne laisse pas d'événement fantôme dans le fil. */
    public bool $afterCommit = true;

    public function __construct(private BookingChatService $chat)
    {
    }

    public function created(BookingEvent $event): void
    {
        // Comme le journal : la messagerie ne doit jamais faire échouer une action sur le rendez-vous.
        try {
            $this->chat->recordEvent($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
