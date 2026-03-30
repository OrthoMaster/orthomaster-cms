<?php

namespace App\Services;

use App\Models\Invoice;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create Payment Intennt from invoice
     */
    public function createPaymentIntent(Invoice $invoice)
    {
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $invoice->amount * 100, // Stripe paid by cents
                'currency' => $invoice->currency ?? 'eur',
                'description' => $invoice->no,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'user_id' => $invoice->user_id,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            // Save payment intent ID
            $invoice->update([
                'stripe_payment_intent_id' => $paymentIntent->id,
                'status' => Invoice::STATUS_PROCESSING,
            ]);

            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Payment Intent Error: ' . $e->getMessage());
            
            $invoice->markAsFailed($e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Confirm payment successfully
     */
    public function confirmPayment(string $paymentIntentId)
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            
            return [
                'success' => true,
                'status' => $paymentIntent->status,
                'payment_intent' => $paymentIntent,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Confirm Payment Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel Payment Intent
     */
    public function cancelPayment(string $paymentIntentId)
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            $paymentIntent->cancel();

            return [
                'success' => true,
                'message' => 'Payment cancelled successfully',
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Cancel Payment Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}