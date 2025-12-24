<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
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
     * Tạo payment intent cho invoice
     */
    public function createPaymentIntent(Request $request, Invoice $invoice)
    {
        // Kiểm tra quyền truy cập invoice
        if ($invoice->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Kiểm tra trạng thái invoice
        if ($invoice->status === Invoice::STATUS_PAID) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice already paid',
            ], 400);
        }

        $result = $this->stripeService->createPaymentIntent($invoice);

        if ($result['success']) {
            return response()->json([
                'success' => true,
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
     * Xác nhận thanh toán (gọi từ app sau khi user hoàn tất)
     */
    public function confirmPayment(Request $request, Invoice $invoice)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        if ($invoice->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $result = $this->stripeService->confirmPayment($request->payment_intent_id);

        if ($result['success']) {
            $status = $result['status'];

            if ($status === 'succeeded') {
                $invoice->markAsPaid();

                return response()->json([
                    'success' => true,
                    'message' => 'Payment successful',
                    'invoice' => $invoice->fresh(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Payment status: {$status}",
                'status' => $status,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'],
        ], 400);
    }

    /**
     * Hủy thanh toán
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
}
