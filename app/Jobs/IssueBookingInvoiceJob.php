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
 * Facture d'un rendez-vous débité (numéro Odoo, PDF, e-mail).
 * Odoo indisponible : nouvelles tentatives espacées ; ensuite, bouton « Relancer » du CMS.
 */
class IssueBookingInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;

    public function __construct(public int $bookingId)
    {
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(BookingInvoiceService $invoices): void
    {
        $invoices->issueInvoice($this->bookingId);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('api')->error('IssueBookingInvoiceJob failed', ['bookingId' => $this->bookingId, 'error' => $e->getMessage()]);
    }
}
