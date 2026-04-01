<?php

namespace App\Jobs;

use App\Http\Controllers\InvoiceController;
use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SetScheduleFifplInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct() {}

    public function handle(): void
    {
        $invoiceController = new InvoiceController();

        $cartIds = Contract::where('organization', 'FIFPL')
            ->where('type', 'signed')
            ->whereNull('invoice_id')
            ->distinct()
            ->pluck('cart_id');

        foreach ($cartIds as $cartId) {
            $invoiceController->generateFiflplInvoiceNotPayByCart($cartId);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('api')->error('SetScheduleFifplInvoiceJob failed', [
            'message' => $exception->getMessage(),
        ]);
    }
}
