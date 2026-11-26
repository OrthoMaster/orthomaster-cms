<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Invoice;
use App\Services\Opco\OpcoInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    public function getAll(Request $request)
    {
        try {
            // Seulement les pièces numérotées : une facture de rendez-vous attend son numéro
            // Odoo quelques instants après le débit ; sans numéro, rien à télécharger.
            $invoices = Invoice::getInvoiceByUser($request->user()->id);

            // Factures OPCO-EP : adressées à l'OPCO, jamais montrées dans l'application.
            $opcoInvoiceIds = Contract::where('organization', OpcoInvoiceService::ORGANIZATION)
                ->where('type', 'signed')
                ->whereIn('invoice_id', $invoices->pluck('id'))
                ->pluck('invoice_id')
                ->flip();

            $invoices = $invoices
                ->filter(fn ($invoice) => !empty($invoice->no) && !isset($opcoInvoiceIds[$invoice->id]))
                ->values();
            $contracts = Contract::getByUser($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => array(
                    'invoices' => $invoices,
                    'contracts' => $contracts,
                )
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['invoices', $request->user()->id]);
            return response()->json([
                'success' => false,
            ], 500);
        }
    }
}
