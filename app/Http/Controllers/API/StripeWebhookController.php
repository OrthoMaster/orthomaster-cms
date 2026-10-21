<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use App\Services\Booking\BookingStripeWebhookService;

class StripeWebhookController extends Controller
{
    /**
     * Xử lý webhook từ Stripe
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);

            if (app(BookingStripeWebhookService::class)->handle($event)) {
                return response()->json(['received' => true]); // giữ kiểu response anh đang dùng
            }
        } catch (\UnexpectedValueException $e) {
            Log::error('Webhook Error: Invalid payload');
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Webhook Error: Invalid signature');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Xử lý các loại event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event->data->object);
                break;

            case 'payment_intent.canceled':
                $this->handlePaymentIntentCanceled($event->data->object);
                break;

            case 'payment_intent.created':
                break;
            default:
                Log::info('Unhandled webhook event: ' . $event->type);
        }

        return response()->json(['success' => true]);
    }

    private function handlePaymentIntentSucceeded($paymentIntent)
    {
        $invoice = Invoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if ($invoice) {
            $invoice->markAsPaid();
            $invoice->update([
                'payment_method' => $paymentIntent->payment_method ?? null,
            ]);

            Log::info("Invoice #{$invoice->id} marked as paid");
        }
    }

    private function handlePaymentIntentFailed($paymentIntent)
    {
        $invoice = Invoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if ($invoice) {
            $invoice->markAsFailed($paymentIntent->last_payment_error->message ?? 'Payment failed');
            Log::warning("Invoice #{$invoice->id} payment failed - #{$paymentIntent->id}");
        }
    }

    private function handlePaymentIntentCanceled($paymentIntent)
    {
        $invoice = Invoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if ($invoice) {
            $invoice->update(['status' => Invoice::STATUS_CANCELLED]);
            Log::info("Invoice #{$invoice->id} payment cancelled");
        }
    }
}
