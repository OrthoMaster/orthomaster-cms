<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    public function getAll(Request $request)
    {
        try {
            // Seulement les pièces numérotées : une facture de rendez-vous attend son numéro
            // Odoo quelques instants après le débit ; sans numéro, rien à télécharger.
            $invoices = Invoice::getInvoiceByUser($request->user()->id)
                ->filter(fn ($invoice) => !empty($invoice->no))
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
