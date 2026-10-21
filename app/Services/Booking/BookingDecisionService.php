<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingDeclineReason;
use App\Models\BookingEvent;
use App\Models\Invoice;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Issue d'une demande transmise : acceptation, refus, expiration.
 *
 *  - accept()  : débite le montant réservé (capture Stripe), la facture passe à payée.
 *  - decline() : libère l'empreinte et les demi-journées, avec un motif.
 *  - expire()  : même effet que le refus, sans motif, quand le délai de réponse est passé.
 *
 * Chaque décision verrouille la ligne de la demande (SELECT … FOR UPDATE) jusqu'à la fin
 * de la transaction, appel Stripe compris. Deux décisions simultanées (l'expert accepte
 * pendant que le confrère annule, double tap, job d'expiration, webhook Stripe) passent
 * donc l'une après l'autre : la seconde relit l'état à jour et s'arrête.
 */
class BookingDecisionService
{
    public const DECLINE_NOTE_MAX = 300;

    public function __construct(private StripeService $stripe)
    {
    }

    /* ===================== ACCEPTER ===================== */

    /**
     * $actor / $adminId : l'administration peut répondre au nom de l'expert depuis le CMS.
     */
    public function accept(Booking $booking, string $actor = BookingEvent::ACTOR_EXPERT, ?int $adminId = null): Booking
    {
        $expired = false;

        $booking = $this->withPending($booking, function (Booking $booking) use ($actor, $adminId, &$expired) {
            // Délai dépassé mais job pas encore passé : on applique l'expiration tout de suite.
            if ($booking->respond_by && $booking->respond_by->isPast()) {
                $this->expireLocked($booking);
                $expired = true;

                return $booking;
            }

            $invoice = $booking->invoice_id ? Invoice::find($booking->invoice_id) : null;

            if ($booking->payment_required) {
                if (!$invoice || !$invoice->stripe_payment_intent_id) {
                    throw ValidationException::withMessages(['booking' => 'Paiement introuvable pour cette demande.']);
                }

                $result = $this->stripe->capturePayment($invoice->stripe_payment_intent_id);
                if (!$result['success']) {
                    throw ValidationException::withMessages([
                        'booking' => "Le paiement n'a pas pu être encaissé : " . $result['error'],
                    ]);
                }

                $invoice->markAsPaid();
            }

            $booking->update([
                'status'       => Booking::STATUS_ACCEPTED,
                'responded_at' => now(),
            ]);

            BookingEvent::log(
                $booking,
                'accepted',
                $actor,
                ['captured' => $booking->payment_required ? (float) $booking->amount_ttc : 0],
                $actor === BookingEvent::ACTOR_ADMIN ? BookingEvent::ACTOR_EXPERT : null,
                $adminId
            );

            return $booking;
        });

        // Levée après la transaction, sinon l'expiration serait annulée avec elle.
        if ($expired) {
            throw ValidationException::withMessages([
                'booking' => 'Le délai de réponse est dépassé : la demande a expiré.',
            ]);
        }

        return $booking->fresh(['blocks']);
    }

    /* ===================== REFUSER ===================== */

    public function decline(Booking $booking, array $input, string $actor = BookingEvent::ACTOR_EXPERT, ?int $adminId = null): Booking
    {
        $reasonId = $input['reason_id'] ?? null;
        $reason   = $reasonId ? BookingDeclineReason::active()->find($reasonId) : null;
        if (!$reason) {
            throw ValidationException::withMessages(['reason_id' => 'Veuillez choisir un motif.']);
        }

        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > self::DECLINE_NOTE_MAX) {
            throw ValidationException::withMessages([
                'note' => 'La note ne doit pas dépasser ' . self::DECLINE_NOTE_MAX . ' caractères.',
            ]);
        }

        $booking = $this->withPending($booking, function (Booking $booking) use ($reason, $note, $actor, $adminId) {
            $this->release($booking, Booking::STATUS_DECLINED, [
                'responded_at'      => now(),
                'decline_reason_id' => $reason->id,
                'decline_note'      => $note ?: null,
            ]);

            BookingEvent::log(
                $booking,
                'declined',
                $actor,
                array_filter(['reason' => $reason->label, 'note' => $note ?: null]),
                $actor === BookingEvent::ACTOR_ADMIN ? BookingEvent::ACTOR_EXPERT : null,
                $adminId
            );

            return $booking;
        });

        return $booking->fresh(['blocks']);
    }

    /* ===================== EXPIRER ===================== */

    /**
     * Expire la demande si elle est toujours « pending » ; renvoie false si quelqu'un
     * l'a tranchée entre-temps (rien n'est alors modifié).
     */
    public function expire(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $locked = $this->lock($booking->id);
            if (!$locked || $locked->status !== Booking::STATUS_PENDING) {
                return false;
            }

            $this->expireLocked($locked);

            return true;
        });
    }

    /**
     * Demandes « pending » dont le délai est passé ; renvoie le nombre traité.
     */
    public function expireOverdue(): int
    {
        $count = 0;

        Booking::where('status', Booking::STATUS_PENDING)
            ->whereNotNull('respond_by')
            ->where('respond_by', '<=', now())
            ->orderBy('id')
            ->each(function (Booking $booking) use (&$count) {
                try {
                    if ($this->expire($booking)) {
                        $count++;
                    }
                } catch (\Throwable $e) {
                    // Une demande en échec ne bloque pas les suivantes ; elle sera reprise au passage suivant.
                    Log::error("Booking #{$booking->id} expiration failed: " . $e->getMessage());
                }
            });

        return $count;
    }

    /* ===================== OUTILS ===================== */

    /** Relit la demande en verrouillant sa ligne jusqu'à la fin de la transaction en cours. */
    private function lock(int $bookingId): ?Booking
    {
        $booking = Booking::whereKey($bookingId)->lockForUpdate()->first();

        return $booking?->load('blocks');
    }

    /**
     * Exécute $fn dans une transaction, sur la demande verrouillée et toujours « pending ».
     * Une demande déjà tranchée est refusée au lieu d'être débitée ou libérée deux fois.
     */
    private function withPending(Booking $booking, callable $fn): Booking
    {
        return DB::transaction(function () use ($booking, $fn) {
            $locked = $this->lock($booking->id);

            if (!$locked || $locked->status !== Booking::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'booking' => 'Cette demande a déjà reçu une réponse ou a été annulée.',
                ]);
            }

            return $fn($locked);
        });
    }

    /** Expiration d'une demande déjà verrouillée et vérifiée « pending ». */
    private function expireLocked(Booking $booking): void
    {
        $this->release($booking, Booking::STATUS_EXPIRED);
        BookingEvent::log($booking, 'expired', BookingEvent::ACTOR_SYSTEM);
    }

    /**
     * Libère l'empreinte bancaire et les demi-journées, puis clôt la demande.
     * Appelée sous verrou. L'échec de l'annulation Stripe n'empêche pas la clôture :
     * une empreinte non capturée expire d'elle-même au bout de 7 jours.
     */
    private function release(Booking $booking, string $status, array $extra = []): void
    {
        $invoice = $booking->invoice_id ? Invoice::find($booking->invoice_id) : null;
        if ($invoice && $invoice->stripe_payment_intent_id
            && !in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED], true)) {
            $this->stripe->cancelPayment($invoice->stripe_payment_intent_id);
        }

        $booking->blocks()->update(['hold_key' => null]);
        $booking->update(['status' => $status] + $extra);

        if ($invoice && $invoice->status !== Invoice::STATUS_PAID) {
            $invoice->update(['status' => Invoice::STATUS_CANCELLED]);
        }
    }
}