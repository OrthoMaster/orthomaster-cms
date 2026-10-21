<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;

/**
 * Webhook Stripe pour les rendez-vous experts : filet de sécurité derrière l'application.
 *
 *  - payment_intent.amount_capturable_updated : empreinte posée. Si l'application n'a pas
 *    pu confirmer (fermée, réseau coupé juste après la feuille de paiement), la demande
 *    est transmise à l'expert quand même ; sinon rien à faire.
 *  - payment_intent.succeeded : capture faite. Normalement par l'acceptation (déjà notée) ;
 *    faite ailleurs (tableau de bord Stripe) sur une demande non acceptée : signalée au journal.
 *  - payment_intent.canceled : empreinte annulée hors de nos actions (tableau de bord,
 *    expiration après 7 jours) : la demande encore ouverte est annulée, créneaux libérés.
 *  - charge.refunded : remboursement fait dans le tableau de bord Stripe, ou cumul perdu
 *    après une erreur de base : le cumul remboursé de la demande est remis à jour.
 *
 * Tout est idempotent : Stripe peut renvoyer un événement plusieurs fois, dans le désordre.
 * Les paiements de cours ne passent pas ici (handle() renvoie false).
 */
class BookingStripeWebhookService
{
    public function __construct(private BookingCheckoutService $checkout)
    {
    }

    /**
     * Traite l'événement s'il concerne un rendez-vous ; renvoie false sinon, pour laisser
     * la logique existante (cours) s'en occuper.
     */
    public function handle(Event $event): bool
    {
        $object  = $event->data->object;
        $booking = $this->bookingFor($object);

        if (!$booking) {
            return false;
        }

        switch ($event->type) {
            case 'payment_intent.amount_capturable_updated':
                $this->authorized($booking, $object);
                break;

            case 'payment_intent.succeeded':
                $this->captured($booking, $object);
                break;

            case 'payment_intent.canceled':
                $this->canceled($booking, $object);
                break;

            case 'charge.refunded':
                $this->refunded($booking, $object);
                break;

            default:
                // created, payment_failed… : l'application gère l'échec dans la feuille de paiement.
                break;
        }

        return true;
    }

    /* ===================== ÉVÉNEMENTS ===================== */

    private function authorized(Booking $booking, object $intent): void
    {
        if ($booking->status !== Booking::STATUS_AWAITING_PAYMENT) {
            return; // déjà transmise par l'application, ou abandonnée
        }

        $ok = $this->checkout->confirmPaid($booking, (string) $intent->status, 'webhook');

        Log::info("Stripe webhook: booking #{$booking->id} " . ($ok ? 'submitted' : 'slot taken, cancelled'));
    }

    private function captured(Booking $booking, object $intent): void
    {
        if ($booking->status === Booking::STATUS_ACCEPTED) {
            // L'acceptation a déjà noté la facture payée ; on complète si besoin.
            Invoice::where('id', $booking->invoice_id)
                ->where('status', '!=', Invoice::STATUS_PAID)
                ->update(['status' => Invoice::STATUS_PAID, 'paid_at' => now()]);

            return;
        }

        // Débit fait hors de l'application sur une demande non acceptée : à vérifier à la main.
        $this->logOnce($booking, 'payment_captured_outside', [
            'payment_intent' => $intent->id,
            'amount'         => round(((int) $intent->amount_received) / 100, 2),
            'status'         => $booking->status,
        ]);
    }

    private function canceled(Booking $booking, object $intent): void
    {
        // Nos propres annulations (refus, expiration, retrait) ont déjà clos la demande :
        // cancel() ne fait alors rien et renvoie false.
        $this->checkout->cancel($booking, Booking::CANCELLED_BY_SYSTEM, [
            'reason' => 'Empreinte bancaire annulée sur Stripe (' . ($intent->cancellation_reason ?: 'sans motif') . ').',
            'source' => 'webhook',
        ]);
    }

    private function refunded(Booking $booking, object $charge): void
    {
        $total = round(((int) $charge->amount_refunded) / 100, 2);

        DB::transaction(function () use ($booking, $total, $charge) {
            // Même verrou que les remboursements du CMS : on attend leur fin, puis on relit.
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->first();
            $known  = round((float) $locked->refunded_amount, 2);

            if ($total <= $known) {
                return; // déjà compté (remboursement fait depuis le CMS)
            }

            $locked->update([
                'refunded_amount' => $total,
                'refunded_at'     => now(),
            ]);

            BookingEvent::log($locked, 'refund_synced', BookingEvent::ACTOR_SYSTEM, [
                'amount'   => round($total - $known, 2),
                'total'    => $total,
                'charge'   => $charge->id,
                'source'   => 'stripe',
            ]);
        });
    }

    /* ===================== OUTILS ===================== */

    /**
     * Rendez-vous concerné par l'objet Stripe, ou null (paiement de cours, inconnu).
     * PaymentIntent : métadonnées posées à la création (type=booking, booking_id).
     * Charge : retrouvé par l'intention de paiement de la facture.
     */
    private function bookingFor(object $object): ?Booking
    {
        if (($object->object ?? null) === 'payment_intent') {
            $meta = $object->metadata ? $object->metadata->toArray() : [];
            if (($meta['type'] ?? null) !== 'booking' || empty($meta['booking_id'])) {
                return null;
            }

            $booking = Booking::with('blocks')->find((int) $meta['booking_id']);

            // Contrôle croisé : l'intention doit être celle de la facture de la demande.
            if ($booking && $booking->invoice_id) {
                $piId = Invoice::where('id', $booking->invoice_id)->value('stripe_payment_intent_id');
                if ($piId !== $object->id) {
                    Log::warning("Stripe webhook: PI {$object->id} does not match booking #{$booking->id}");

                    return null;
                }
            }

            return $booking;
        }

        if (($object->object ?? null) === 'charge' && !empty($object->payment_intent)) {
            $piId    = is_string($object->payment_intent) ? $object->payment_intent : $object->payment_intent->id;
            $invoice = Invoice::where('stripe_payment_intent_id', $piId)->first();

            return $invoice ? Booking::with('blocks')->where('invoice_id', $invoice->id)->first() : null;
        }

        return null;
    }

    /** Événement de journal écrit une seule fois (Stripe renvoie parfois le même). */
    private function logOnce(Booking $booking, string $type, array $meta): void
    {
        if ($booking->events()->where('type', $type)->exists()) {
            return;
        }

        BookingEvent::log($booking, $type, BookingEvent::ACTOR_SYSTEM, $meta);
    }
}