<?php

namespace App\Jobs;

use App\Services\Booking\BookingInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Avoir d'un remboursement (numéro Odoo, PDF, e-mail).
 * Odoo indisponible : nouvelles tentatives espacées ; ensuite, bouton « Relancer » du CMS.
 */
class IssueBookingCreditNoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;

    public function __construct(public int $eventId)
    {
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(BookingInvoiceService $invoices): void
    {
        $invoices->issueCreditNote($this->eventId);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('api')->error('IssueBookingCreditNoteJob failed', ['eventId' => $this->eventId, 'error' => $e->getMessage()]);
    }
}
