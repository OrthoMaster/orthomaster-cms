<?php

namespace App\Jobs;

use App\Services\Opco\OpcoInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Factures des conventions OPCO-EP signées qui n'en ont pas encore (tâche du soir, bouton CMS). */
class GenerateOpcoInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    // Rattrapage : beaucoup de conventions d'un coup (Odoo + PDF pour chacune).
    public int $timeout = 1800;

    /** $all : rattrapage de toutes les conventions (bouton CMS) ; sinon les récentes (tâche du soir). */
    public function __construct(public bool $all = false) {}

    public function handle(OpcoInvoiceService $opco): void
    {
        $opco->generateMissing($this->all);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('api')->error('GenerateOpcoInvoicesJob failed', [
            'message' => $exception->getMessage(),
        ]);
    }
}
