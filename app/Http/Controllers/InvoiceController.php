<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Invoice;
use App\Models\User;
use App\Status;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function generateInvoiceDocument($id) {
        $invoice = Invoice::find($id);
        $cart = Cart::where('invoice_id', $id)->first();
        
        if (!$cart) {
            return;
        }

        $user = User::find($invoice->user_id);
        // Sync
        $invoice->client_name = $user->name.' '.$user->first_name;
        $invoice->client_email = $user->email;

        $invoice->amount_ht = $invoice->amount + $invoice->discount_amount - $invoice->tva_amount;
        $invoice->tva = $cart->tva;
        $invoice->unit_price = $cart->unit_price;

        $invoiceNo = '';
        do {
            $currentYear = date('Y');
            $yearlyInvoiceCount = Invoice::whereYear('created_at', $currentYear)->count() + 1;
            
            // Format: P + 2 no year + 4 no
            $invoiceNo = strtoupper('F-' . substr($currentYear, -2) .'-'. str_pad($yearlyInvoiceCount, 4, '0', STR_PAD_LEFT));
        } while (Invoice::where('no', $invoiceNo)->exists());
       
        $invoice->no = $invoiceNo;
        $invoice->save();

        $generatePdfController = new GeneratePdfController();
        $generatePdfController->paymentInvoice($invoice);
        return;
    }
}
