<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InvoiceController;
use App\Models\Booking;
use App\Models\BookingPolicy;
use App\Models\Cart;
use App\Models\ExpertOffer;
use App\Models\ExpertOfferMode;
use App\Models\Invoice;
use App\Models\SystemNotification;
use App\Services\Booking\BookingCheckoutService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected $stripeService;
    protected $bookingCheckout;

    public function __construct(StripeService $stripeService, BookingCheckoutService $bookingCheckout)
    {
        $this->stripeService = $stripeService;
        $this->bookingCheckout = $bookingCheckout;
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

    /* ===================== RENDEZ-VOUS EXPERT ===================== */

    /**
     * Récapitulatif avant paiement : identité de l'utilisateur, montants calculés par le
     * serveur à partir du prix TTC du mode choisi (l'application ne calcule pas la TVA),
     * et conditions d'annulation de ce mode.
     * GET /payments/bookings/checkout?offer_id=&mode=online|onsite
     */
    public function bookingCheckout(Request $request): JsonResponse
    {
        $offer = ExpertOffer::active()->with('modes')->find($request->query('offer_id'));
        // Sans mode (ancienne version de l'application) : en ligne, le seul mode d'avant.
        $offerMode = $offer?->activeMode((string) $request->query('mode', ExpertOfferMode::ONLINE));

        if (!$offerMode) {
            return response()->json([
                'success' => false,
                'message' => "Ce thème n'est plus proposé sous cette forme.",
            ], 404);
        }

        $user   = $request->user();
        $policy = BookingPolicy::currentFor(null, $offerMode->mode);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'name' => BookingCheckoutService::displayName($user),
                    'email' => $user->email,
                ],
                'mode' => $offerMode->mode,
                'pricing' => BookingCheckoutService::pricing($offerMode, $offer->currency),
                'policy' => $policy ? [
                    'free_cancel_hours'       => $policy->free_cancel_hours,
                    'late_cancel_penalty_pct' => $policy->late_cancel_penalty_pct,
                    'user_noshow_refund_pct'  => $policy->user_noshow_refund_pct,
                ] : null,
                // Faux aussi pour une offre à 0 € : l'application affiche « offert ».
                'payment_enabled' => BookingCheckoutService::paymentRequired((float) $offerMode->price),
                'terms_url' => BookingCheckoutService::TERMS_URL,
                'message_max' => BookingCheckoutService::MESSAGE_MAX,
            ],
        ]);
    }

    /**
     * Crée la demande (en attente de paiement), sa facture et l'intention de paiement à
     * capture manuelle. Paiement désactivé : la demande part directement chez l'expert.
     * POST /payments/bookings/create-intent
     * Corps : offer_id, mode (online|onsite), blocks[{date, period}], address, message?
     */
    public function createBookingPaymentIntent(Request $request): JsonResponse
    {
        // Erreurs de saisie : réponse 422 standard { message, errors } de Laravel.
        // Sans mode (ancienne version de l'application) : en ligne, le seul mode d'avant.
        $booking = $this->bookingCheckout->prepare($request->user(), $request->only([
            'offer_id', 'blocks', 'address', 'message',
        ]) + ['mode' => $request->input('mode', ExpertOfferMode::ONLINE)]);

        if (!$booking->payment_required) {
            if (!$this->bookingCheckout->submit($booking)) {
                $this->bookingCheckout->cancel($booking);

                return $this->bookingSlotTaken();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'requires_payment' => false,
                    'booking' => BookingCheckoutService::payload($booking->fresh('blocks')),
                ],
            ]);
        }

        $invoice = $booking->invoice;
        $result = $this->stripeService->createPaymentIntent(
            $invoice,
            ['capture_method' => 'manual'],
            ['booking_id' => $booking->id, 'type' => 'booking']
        );

        if (!$result['success']) {
            $this->bookingCheckout->cancel($booking);

            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'requires_payment' => true,
                'booking' => BookingCheckoutService::payload($booking),
                'invoice_id' => $invoice->id,
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
                'publishable_key' => config('services.stripe.key'),
            ],
        ]);
    }

    /**
     * Appelé par l'application quand la feuille de paiement Stripe s'est terminée.
     * Capture manuelle : un paiement autorisé est à l'état « requires_capture » ;
     * le débit réel aura lieu à l'acceptation de l'expert.
     * PUT /payments/bookings/{booking}/confirm
     */
    public function confirmBookingPayment(Request $request, Booking $booking): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Déjà transmise (double appel, reprise après coupure réseau) : rien à refaire.
        if (in_array($booking->status, Booking::HOLDING_STATUSES, true)) {
            return response()->json([
                'success' => true,
                'data' => ['booking' => BookingCheckoutService::payload($booking->load('blocks'))],
            ]);
        }

        $invoice = $booking->invoice;

        // Le webhook Stripe est passé avant et a trouvé un créneau déjà pris : la demande
        // a été annulée et l'empreinte libérée. (Un abandon volontaire ne revient pas ici.)
        if ($booking->status === Booking::STATUS_CANCELLED && $booking->requested_at === null
            && $invoice && $invoice->stripe_payment_intent_id === $request->payment_intent_id) {
            return $this->bookingSlotTaken();
        }

        if ($booking->status !== Booking::STATUS_AWAITING_PAYMENT || !$invoice
            || $invoice->stripe_payment_intent_id !== $request->payment_intent_id) {
            return response()->json([
                'success' => false,
                'message' => "Cette demande n'est plus en attente de paiement.",
            ], 400);
        }

        $result = $this->stripeService->confirmPayment($request->payment_intent_id);
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        $status = $result['status'];
        if (!in_array($status, ['requires_capture', 'succeeded'], true)) {
            return response()->json([
                'success' => false,
                'message' => "État du paiement: {$status}",
                'status' => $status,
            ], 400);
        }

        // Transmet la demande (ou l'annule si un créneau a été pris) ; verrouillé, donc sans
        // conflit avec le webhook « amount_capturable_updated » qui arrive au même moment.
        if (!$this->bookingCheckout->confirmPaid($booking, $status, 'app')) {
            return $this->bookingSlotTaken();
        }

        return response()->json([
            'success' => true,
            'data' => ['booking' => BookingCheckoutService::payload($booking->fresh('blocks'))],
        ]);
    }

    /**
     * Abandon avant paiement (retour, fermeture du récapitulatif).
     * PUT /payments/bookings/{booking}/cancel
     */
    public function cancelBookingPayment(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($booking->status !== Booking::STATUS_AWAITING_PAYMENT) {
            return response()->json([
                'success' => false,
                'message' => "Cette demande ne peut plus être annulée ici.",
            ], 400);
        }

        // false : le paiement vient d'aboutir (webhook) et la demande est partie chez l'expert.
        if (!$this->bookingCheckout->cancel($booking)) {
            return response()->json([
                'success' => false,
                'message' => "Cette demande ne peut plus être annulée ici.",
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => ['booking' => BookingCheckoutService::payload($booking->fresh('blocks'))],
        ]);
    }

    private function bookingSlotTaken(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'slot_taken',
            'message' => "Une des demi-journées vient d'être réservée par un autre confrère. Aucun montant n'a été débité : merci d'en choisir une autre.",
        ], 409);
    }
}