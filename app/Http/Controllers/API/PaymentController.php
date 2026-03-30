<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InvoiceController;
use App\Models\Cart;
use App\Models\Invoice;
use App\Models\SystemNotification;
use App\Services\StripeService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Create payment intent
     */
    public function createPaymentIntent(Request $request)
    {
        $cart = Cart::find($request->id);
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => "Le panier d'achat n'existe pas.",
            ], 403);
        }

        if (!$request->amount || !is_numeric($request->amount)) {
            return response()->json([
                'success' => false,
                'message' => "Le panier d'achat n'existe pas.",
            ], 403);
        }



        if (!$cart->invoice_id) {
            $invoice = Invoice::create([
                'user_id' => $request->user()->id,
                'amount' => $request->amount,
                'currency' => 'eur'
            ]);
            $cart->invoice_id = $invoice->id;
            $cart->save();
        } else {
            $invoice = Invoice::find($cart->invoice_id);

            // Double check - recreate invoice
            if (!$invoice) {
                $invoice = Invoice::create([
                    'user_id' => $request->user()->id,
                    'amount' => $request->amount,
                    'currency' => 'eur'
                ]);
                $cart->invoice_id = $invoice->id;
                $cart->save();
            }

            $invoice->amount = $request->amount;
            $invoice->save();
        }

        $invoice->promotion_code = $request->promotionCode;
        $invoice->discount_amount = $request->discountAmount ? $request->discountAmount : 0;
        $invoice->tva_amount = $request->tvaAmount ? $request->tvaAmount : null;
        $invoice->client_company = $request->companyName;
        $invoice->client_address = $request->companyAddress;
        $invoice->save();

        // Check invoice status
        if ($invoice->status === Invoice::STATUS_PAID) {
            return response()->json([
                'success' => false,
                'message' => 'Facture déjà payée',
            ], 400);
        }

        $result = $this->stripeService->createPaymentIntent($invoice);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'invoice_id' => $invoice->id,
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
                'publishable_key' => config('services.stripe.key'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'],
        ], 400);
    }

    /**
     * Confirm payment (call from app after user complete)
     */
    public function confirmPayment(Request $request, Invoice $invoice)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        $result = $this->stripeService->confirmPayment($request->payment_intent_id);

        if ($result['success']) {
            $status = $result['status'];

            if ($status === 'succeeded') {
                $invoice->markAsPaid();

                // Not FIFPL Invoice
                if (!$invoice->id_odoo) {
                    // Disable cart to sync and create invoice paper
                    $cartController = new CartController();
                    $cartController->complete($invoice->id);

                    // Sync and create document
                    $invoiceId = $invoice->id;
                    dispatch(function () use ($invoiceId) {
                        $invController = new InvoiceController();
                        $invController->generateInvoiceDocument($invoiceId);
                    })->afterResponse();
                } else { // FIFPL
                    // Clear notification
                    SystemNotification::where('user_id', $invoice->user_id)
                        ->where('entity_type', 'FifplInvoice')
                        ->where('entity_id', $invoice->id)->delete();
                    
                    $invoiceId = $invoice->id;
                    dispatch(function () use ($invoiceId) {
                        // Create FIFPL Invoice & Certification
                        $invController = new InvoiceController();
                        $invController->generateFifplInvoiceDocument($invoiceId);
                    })->afterResponse();
                }


                return response()->json([
                    'success' => true,
                    'message' => 'Paiement réussi !',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "État du paiement: {$status}",
                'status' => $status,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'],
        ], 400);
    }

    /**
     * Cancel
     */
    public function cancelPayment(Request $request, Invoice $invoice)
    {
        if ($invoice->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!$invoice->stripe_payment_intent_id) {
            return response()->json([
                'success' => false,
                'message' => 'No payment to cancel',
            ], 400);
        }

        $result = $this->stripeService->cancelPayment($invoice->stripe_payment_intent_id);

        if ($result['success']) {
            $invoice->update(['status' => Invoice::STATUS_CANCELLED]);

            return response()->json([
                'success' => true,
                'message' => 'Payment cancelled',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'],
        ], 400);
    }

    /**
     * Get FIFPL invoice
     */
    public function getFifplInvoice(Request $request, string $invoiceId)
    {
        $invoice = Invoice::where('id', $invoiceId)->where('user_id', $request->user()->id)->first();
        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => "La facture n'existe pas.",
            ], 400);
        }

        if ($invoice->status == Invoice::STATUS_PAID) {
            return response()->json([
                'success' => false,
                'message' => "La facture a été réglée.",
            ], 400);
        }

        if (($invoice->status !== Invoice::STATUS_PENDING) && ($invoice->status !== Invoice::STATUS_PROCESSING)) {
            return response()->json([
                'success' => false,
                'message' => "La facture n'existe pas.",
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ], 200);
    }

    /**
     * Create FIFPL payment intent
     */
    public function createFifplPaymentIntent(Request $request)
    {
        $invoice = Invoice::find($request->id);
        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => "La facture n'existe pas.",
            ], 403);
        }

        if (($invoice->status !== Invoice::STATUS_PENDING) && ($invoice->status !== Invoice::STATUS_PROCESSING)) {
            return response()->json([
                'success' => false,
                'message' => "La facture est impayable.",
            ], 400);
        }

        $result = $this->stripeService->createPaymentIntent($invoice);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'invoice_id' => $invoice->id,
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
                'publishable_key' => config('services.stripe.key'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'],
        ], 400);
    }
}
