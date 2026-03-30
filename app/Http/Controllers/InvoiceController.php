<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Odoo\OdooInvoiceController;
use App\Mail\SendFifplCompletedDocumentMail;
use App\Models\Cart;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use App\Organization;
use App\Services\OdooService;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    // Generate normal invoice
    public function generateInvoiceDocument($id)
    {
        $invoice = Invoice::find($id);
        $cart = Cart::where('invoice_id', $id)->first();

        if (!$cart) {
            return;
        }

        $user = User::find($invoice->user_id);
        // Sync
        $invoice->client_name = $user->name . ' ' . $user->first_name;
        $invoice->client_email = $user->email;

        $invoice->amount_ht = $invoice->amount + $invoice->discount_amount - $invoice->tva_amount;
        $invoice->tva = $cart->tva;
        $invoice->unit_price = $cart->unit_price;

        $invoiceNo = '';
        do {
            $currentYear = date('Y');
            $yearlyInvoiceCount = Invoice::whereYear('created_at', $currentYear)->count() + 1;

            // Format: P + 2 no year + 4 no
            $invoiceNo = strtoupper('F-' . substr($currentYear, -2) . '-' . str_pad($yearlyInvoiceCount, 4, '0', STR_PAD_LEFT));
        } while (Invoice::where('no', $invoiceNo)->exists());

        $invoice->no = $invoiceNo;
        $invoice->save();

        $generatePdfController = new GeneratePdfController();
        $generatePdfController->paymentInvoice($invoice);
        return;
    }

    // Generate fifpl invoice - no pay
    public function generateFiflplInvoiceNotPayByCart($cartId)
    {
        $controller = new OdooInvoiceController(app(OdooService::class));
        $generatePdfController = new GeneratePdfController();

        $cart = Cart::find($cartId);
        if (!$cart) {
            return false;
        }

        $cart->load(['members', 'items', 'items.package']);

        // Create label of item
        $label = implode(', ', $cart->items->map(fn($item) => $item->package->name)->toArray());

        try {
            foreach ($cart->members as $member) {
                $memberContract = Contract::where('cart_id', $cart->id)
                    ->where('beneficiary_id', $member->id)
                    ->where('type', 'signed')
                    ->with('company')
                    ->first();

                // Get Invoice No                    
                $invoice = $controller->createInvoice([
                    'partner_id' => $member->id_odoo_partner,
                    'invoice_date' => Carbon::parse($memberContract->created_at)->addMonth()->format('Y-m-d'),
                    'invoice_date_due' => Carbon::parse($memberContract->created_at)->addMonth(2)->format('Y-m-d'),
                    'ref' => $memberContract->no,
                    'lines' => [[
                        'name' => $label,
                        'price_unit' => $cart->price_total_ht,
                        'quantity' => 1,
                        'tax_ids'    => [[6, 0, [3]]],
                        'ref'
                    ]]
                ]);

                if (!$invoice) {
                    Log::channel('api')->error('FAILED: Can not create invoice with Odoo', ['cart-' . $cart->id, 'user-' . $member->id]);
                    continue;
                }

                $invoiceNo = $invoice['name'];
                $invoiceOdooId = $invoice['id'];

                $invoice = Invoice::create([
                    'no' => $invoiceNo,
                    'user_id' => $member->id,
                    'amount' => $cart->price_total,
                    'currency' => 'eur',
                    'status' => Invoice::STATUS_PENDING,
                    'id_odoo' => $invoiceOdooId,

                    'unit_price' => $cart->price_total_ht,
                    'tva' => $cart->tva,
                    'tva_amount' => $cart->tva_total,
                    'amount_ht' => $cart->price_total_ht,
                    'sent_at' => Carbon::parse($memberContract->created_at)->addMonth(),

                    'client_name' => $member->name . ($member->first_name ? ' ' : '') . $member->first_name,
                    'client_email' => $member->email,
                    'client_company' => $memberContract->company->name,
                    'client_address' => $memberContract->company->address
                ]);
                $memberContract->invoice_id = $invoice->id;
                $memberContract->save();

                $invoicePdfLink = $generatePdfController->fifplPaymentInvoice(
                    $invoice->no,
                    $memberContract->no,
                    $memberContract->created_at->addMonth()->format('d/m/Y'),
                    $memberContract->created_at->addMonth(2)->format('d/m/Y'),
                    $memberContract->created_at->addMonth()->format('d/m/Y') . ' - ' . $memberContract->created_at->addMonth(2)->format('d/m/Y'),
                    $memberContract->company,
                    $member,
                    $cart->items,
                    Organization::FIFPL_UNIT_PRICE->value,
                    (float) setting('system.tva'),
                    $cart->duration_total_days,
                    null,
                );

                //
                $invoice->document = $invoicePdfLink;
                $invoice->save();

                $controller->uploadInvoicePdf(
                    $invoiceNo,
                    $invoicePdfLink,
                    str_replace('/', '_', $invoiceNo) . '.pdf',
                    'Facture - CMS'
                );
            }

            Log::info('Generated FIFPL invoice - no pay', ['cart-' . $cartId]);
            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage(), ['cart-' . $cartId, 'create-no-pay-invoice-fifpl']);
            return false;
        }
    }

    // Generate fifpl invoice - paid
    public function generateFifplInvoiceDocument($invoiceId)
    {
        $controller = new OdooInvoiceController(app(OdooService::class));
        $generatePdfController = new GeneratePdfController();

        $invoice = Invoice::find($invoiceId);
        $memberContract = Contract::where('invoice_id', $invoiceId)
            ->where('beneficiary_id', $invoice->user_id)
            ->where('type', 'signed')
            ->with('company')
            ->first();
        $member = User::find($memberContract->beneficiary_id);
        $cart = Cart::find($memberContract->cart_id);
        $cart->load(['members', 'items', 'items.package']);

        if (!$invoice || !$memberContract) {
            return false;
        }

        try {
            // Invoice
            $invoicePdfLink = $generatePdfController->fifplPaymentInvoice(
                $invoice->no,
                $memberContract->no,
                $memberContract->created_at->addMonth()->format('d/m/Y'),
                $memberContract->created_at->addMonth(2)->format('d/m/Y'),
                $memberContract->created_at->addMonth()->format('d/m/Y') . ' - ' . $memberContract->created_at->addMonth(2)->format('d/m/Y'),
                $memberContract->company,
                $member,
                $cart->items,
                Organization::FIFPL_UNIT_PRICE->value,
                (float) setting('system.tva'),
                $cart->duration_total_days,
                now()->format('d/m/Y'),
            );
            $invoice->document = $invoicePdfLink;
            $invoice->save();

            // Certif link
            $cetifPdfLink = $generatePdfController->fifplCertificate(
                $member->id,
                $member->name . ' ' . $member->first_name,
                $memberContract->created_at->addMonth(),
                $memberContract->created_at->addMonth(2),
                $cart->items,
                $cart->duration_total,
                sizeof($cart->items),
                $invoice->amount_ht,
                $invoice->amount
            );
            $invoice->certification = $cetifPdfLink;
            $invoice->sent_at = $memberContract->created_at->addMonth(2);
            $invoice->save();

            // Sync Odoo
            $controller->uploadInvoicePdf(
                $invoice->no,
                $invoicePdfLink,
                str_replace('/', '_', $invoice->no) . '.pdf',
                'Facture acquittée - CMS'
            );
            $controller->markInvoiceAsPaid($invoice->id_odoo, $invoice->amount, $invoice->paid_at->format('Y-m-d'));


            // Send email when out of end date
            if (now() >= $memberContract->created_at->addMonth(2)) {
                Mail::to($invoice->user->email)->send(new SendFifplCompletedDocumentMail($invoice->user_id, $invoice));
            }

            Log::info('Generated FIFPL invoice & certif', ['inv-' . $invoiceId]);
            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage(), ['inv-' . $invoiceId, 'create-fifpl-invoice-and-certif']);
            return false;
        }
    }
}
