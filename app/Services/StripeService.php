<?php

namespace App\Services;

use App\Models\Invoice;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Refund;
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
     *
     * $options : paramètres Stripe ajoutés à la création, ex. ['capture_method' => 'manual']
     * pour les rendez-vous (montant réservé, débité à l'acceptation de l'expert).
     * $metadata : complète les métadonnées par défaut (invoice_id, user_id).
     */
    public function createPaymentIntent(Invoice $invoice, array $options = [], array $metadata = [])
    {
        try {
            $paymentIntent = PaymentIntent::create(array_merge([
                // round() : 360.00 * 100 peut donner 35999.99999 en flottant.
                'amount' => (int) round($invoice->amount * 100), // Stripe paid by cents
                'currency' => $invoice->currency ?? 'eur',
                'description' => $invoice->no,
                'metadata' => array_merge([
                    'invoice_id' => $invoice->id,
                    'user_id' => $invoice->user_id,
                ], $metadata),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ], $options));

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
     * Capture a manual-capture Payment Intent (rendez-vous accepté par l'expert)
     */
    public function capturePayment(string $paymentIntentId)
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            // Déjà capturé (double appel) : rien à refaire.
            if ($paymentIntent->status !== 'succeeded') {
                $paymentIntent = $paymentIntent->capture();
            }

            return [
                'success' => true,
                'status' => $paymentIntent->status,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Capture Payment Error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refund a captured Payment Intent, fully (null) or partially (amount in euros)
     */
    public function refundPayment(string $paymentIntentId, ?float $amount = null, array $metadata = [])
    {
        try {
            $params = ['payment_intent' => $paymentIntentId, 'metadata' => $metadata];
            if ($amount !== null) {
                $params['amount'] = (int) round($amount * 100);
            }

            $refund = Refund::create($params);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'status' => $refund->status,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Refund Error: ' . $e->getMessage());

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