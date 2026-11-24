<?php

namespace App\Observers;

use App\Jobs\IssueBookingCreditNoteJob;
use App\Jobs\IssueBookingInvoiceJob;
use App\Models\BookingEvent;

/**
 * Factures des rendez-vous experts, déclenchées par le journal (voir BookingInvoiceService) :
 *  - accepted                  → facture (si payant ; la tâche vérifie le débit) ;
 *  - refunded / refund_synced  → avoir du montant remboursé.
 * Après validation : une transaction annulée ne facture rien.
 */
class BookingEventInvoiceObserver
{
    public bool $afterCommit = true;

    public function created(BookingEvent $event): void
    {
        try {
            match ($event->type) {
                'accepted'                  => IssueBookingInvoiceJob::dispatch((int) $event->booking_id),
                'refunded', 'refund_synced' => IssueBookingCreditNoteJob::dispatch((int) $event->id),
                default                     => null,
            };
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
