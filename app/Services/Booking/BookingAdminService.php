<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingNoShowReport;
use App\Models\BookingPolicy;
use App\Models\ExpertOfferMode;
use App\Models\Invoice;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Actions de l'administration sur un rendez-vous, souvent à la demande d'une partie
 * (appel du demandeur ou de l'expert) : annulation au nom de l'un ou de l'autre,
 * réponse au nom de l'expert, traitement des signalements d'absence.
 *
 * Remboursements : montant proposé selon la politique copiée à la réservation,
 * modifiable par l'admin, plafonné à ce qui reste à rembourser.
 *
 * Chaque opération qui touche à l'argent verrouille la ligne de la demande (FOR UPDATE)
 * jusqu'à la fin de la transaction, appel Stripe compris : un double clic, deux admins
 * ou le webhook « charge.refunded » ne peuvent pas rembourser ni compter deux fois.
 */
class BookingAdminService
{
    public const PARTY_USER   = 'user';
    public const PARTY_EXPERT = 'expert';
    public const PARTIES      = [self::PARTY_USER, self::PARTY_EXPERT];

    public const REASON_MAX = 500;

    public function __construct(
        private StripeService $stripe,
        private BookingCheckoutService $checkout,
        private BookingDecisionService $decisions
    ) {
    }

    /* ===================== MONTANTS ===================== */

    /**
     * Encore remboursable : montant débité moins les remboursements déjà faits.
     * Débité = accepté par l'expert, y compris un rendez-vous annulé après acceptation.
     */
    public static function refundable(Booking $booking): float
    {
        $captured = $booking->status === Booking::STATUS_ACCEPTED
            || ($booking->status === Booking::STATUS_CANCELLED && $booking->responded_at !== null);
        if (!$booking->payment_required || !$captured) {
            return 0.0;
        }

        return max(0, round((float) $booking->amount_ttc - (float) $booking->refunded_amount, 2));
    }

    /** Politique en vigueur au moment de la réservation (repli : celle du jour, pour le même mode). */
    public static function policyOf(Booking $booking): ?BookingPolicy
    {
        return ($booking->booking_policy_id ? BookingPolicy::find($booking->booking_policy_id) : null)
            ?? BookingPolicy::currentFor(null, $booking->mode ?: ExpertOfferMode::ONLINE);
    }

    /**
     * Remboursement proposé pour une annulation d'un rendez-vous accepté.
     *  - au nom de l'expert : tout ;
     *  - au nom du demandeur : tout si l'annulation intervient assez tôt
     *    (free_cancel_hours avant la première demi-journée), sinon moins la pénalité.
     *
     * @return array{amount:float, rule:string}
     */
    public static function suggestedCancelRefund(Booking $booking, string $party): array
    {
        $max = self::refundable($booking);
        if ($max <= 0) {
            return ['amount' => 0.0, 'rule' => 'Aucun montant débité : rien à rembourser.'];
        }

        if ($party === self::PARTY_EXPERT) {
            return ['amount' => $max, 'rule' => "Annulation par l'expert : remboursement intégral."];
        }

        $policy     = self::policyOf($booking);
        $freeHours  = $policy?->free_cancel_hours ?? 72;
        $penaltyPct = $policy?->late_cancel_penalty_pct ?? 50;
        $firstStart = $booking->blocks->min('start_at');
        $hoursLeft  = $firstStart ? now()->diffInHours($firstStart, false) : 0;

        if ($hoursLeft >= $freeHours) {
            return [
                'amount' => $max,
                'rule'   => "Annulation plus de {$freeHours} h avant la première demi-journée : remboursement intégral.",
            ];
        }

        return [
            'amount' => round($max * (1 - $penaltyPct / 100), 2),
            'rule'   => "Annulation moins de {$freeHours} h avant : pénalité de " . rtrim(rtrim(number_format($penaltyPct, 2, ',', ''), '0'), ',') . ' %.',
        ];
    }

    /**
     * Remboursement proposé pour un signalement d'absence, au prorata des demi-journées
     * déclarées manquées : 100 % si l'expert était absent, user_noshow_refund_pct si
     * c'est le demandeur.
     *
     * @return array{amount:float, rule:string}
     */
    public static function suggestedNoShowRefund(BookingNoShowReport $report): array
    {
        $booking = $report->booking;
        $max     = self::refundable($booking);
        if ($max <= 0) {
            return ['amount' => 0.0, 'rule' => 'Aucun montant débité : rien à rembourser.'];
        }

        $total  = max(1, $booking->blocks->count());
        $missed = count($report->block_ids ?? []);
        $share  = (float) $booking->amount_ttc * $missed / $total;

        if ($report->reporter === BookingNoShowReport::REPORTER_USER) {
            return [
                'amount' => min($max, round($share, 2)),
                'rule'   => "Absence de l'expert : remboursement des {$missed}/{$total} demi-journée(s) concernée(s).",
            ];
        }

        $pct = self::policyOf($booking)?->user_noshow_refund_pct ?? 0;

        return [
            'amount' => min($max, round($share * $pct / 100, 2)),
            'rule'   => "Absence du demandeur : remboursement de {$pct} % des {$missed}/{$total} demi-journée(s) concernée(s).",
        ];
    }

    /* ===================== ANNULER ===================== */

    /**
     * Annule au nom du demandeur ou de l'expert.
     *  - En attente : rien n'a été débité, l'empreinte est libérée.
     *  - Accepté et pas encore terminé : remboursement de $refund (0 accepté).
     */
    public function cancel(Booking $booking, string $party, int $adminId, ?float $refund, ?string $reason): Booking
    {
        if (!in_array($party, self::PARTIES, true)) {
            throw ValidationException::withMessages(['party' => "Précisez au nom de qui l'annulation est faite."]);
        }

        $reason = trim((string) $reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => "Indiquez le motif de l'annulation (il reste dans le journal)."]);
        }
        $reason = mb_substr($reason, 0, self::REASON_MAX);

        if ($booking->status === Booking::STATUS_PENDING) {
            $done = $this->checkout->cancel($booking, $party, [
                'actor'        => BookingEvent::ACTOR_ADMIN,
                'on_behalf_of' => $party,
                'admin_id'     => $adminId,
                'reason'       => $reason,
            ]);
            if (!$done) {
                throw ValidationException::withMessages(['booking' => "La demande vient de changer d'état : rechargez la page."]);
            }
            $booking->update(['cancel_reason' => $reason, 'cancelled_by_admin_id' => $adminId]);

            return $booking->fresh(['blocks']);
        }

        $refundAmount = 0.0;

        DB::transaction(function () use ($booking, $party, $reason, $adminId, $refund, &$refundAmount) {
            $locked = $this->lock($booking);

            if ($locked->status !== Booking::STATUS_ACCEPTED) {
                throw ValidationException::withMessages(['booking' => 'Ce rendez-vous ne peut plus être annulé.']);
            }

            $lastEnd = $locked->blocks->max('end_at');
            if ($lastEnd && $lastEnd->isPast()) {
                throw ValidationException::withMessages([
                    'booking' => "Ce rendez-vous est terminé : utilisez plutôt le traitement d'un signalement d'absence.",
                ]);
            }

            $refundAmount = $this->checkedRefund($locked, $refund);
            $this->refund($locked, $refundAmount, ['reason' => 'cancel', 'party' => $party, 'note' => $reason], $adminId);

            $locked->blocks()->update(['hold_key' => null]);
            $locked->update([
                'status'                => Booking::STATUS_CANCELLED,
                'cancelled_at'          => now(),
                'cancelled_by'          => $party,
                'cancel_reason'         => $reason,
                'cancelled_by_admin_id' => $adminId,
            ]);

            BookingEvent::log($locked, 'cancelled', BookingEvent::ACTOR_ADMIN, [
                'reason'   => $reason,
                'refund'   => $refundAmount,
                'released' => true,
            ], $party, $adminId);
        });

        return $booking->fresh(['blocks']);
    }

    /* ===================== REMBOURSER ===================== */

    /** Motifs proposés (texte repris tel quel sur l'avoir). */
    public const REFUND_MOTIFS = [
        "Annulation du rendez-vous par l'expert",
        "Absence de l'expert",
        'Geste commercial',
    ];

    /**
     * Remboursement d'un rendez-vous débité, sans changer son état (geste, annulation tardive
     * de l'expert déjà enregistrée, etc.). Donne un avoir (RVE) via le journal « refunded ».
     *  - stripe : remboursement sur la carte ;
     *  - manual : remboursement fait hors Stripe (virement…), seulement enregistré ici.
     */
    public function refundOnly(Booking $booking, ?float $amount, string $motif, string $method, ?string $reference, int $adminId): Booking
    {
        $motif = mb_substr(trim($motif), 0, self::REASON_MAX);
        if ($motif === '') {
            throw ValidationException::withMessages(['motif' => "Indiquez le motif : il figure sur l'avoir."]);
        }
        if (!in_array($method, ['stripe', 'manual'], true)) {
            throw ValidationException::withMessages(['method' => 'Choisissez comment le remboursement est fait.']);
        }
        $reference = trim((string) $reference);
        if ($method === 'manual' && $reference === '') {
            throw ValidationException::withMessages(['reference' => 'Indiquez la référence du virement (ou du remboursement fait hors Stripe).']);
        }

        DB::transaction(function () use ($booking, $amount, $motif, $method, $reference, $adminId) {
            $locked = $this->lock($booking);
            $value = $this->checkedRefund($locked, $amount);
            if ($value <= 0) {
                throw ValidationException::withMessages(['refund' => 'Indiquez un montant à rembourser.']);
            }

            if ($method === 'stripe') {
                $this->refund($locked, $value, ['reason' => 'refund', 'motif' => $motif], $adminId);

                return;
            }

            $locked->update([
                'refunded_amount' => round((float) $locked->refunded_amount + $value, 2),
                'refunded_at'     => now(),
            ]);
            BookingEvent::log($locked, 'refunded', BookingEvent::ACTOR_ADMIN, [
                'amount'    => $value,
                'method'    => 'manual',
                'reference' => mb_substr($reference, 0, 191),
                'reason'    => 'refund',
                'motif'     => $motif,
            ], null, $adminId);
        });

        return $booking->fresh(['blocks']);
    }

    /* ===================== RÉPONDRE AU NOM DE L'EXPERT ===================== */

    public function accept(Booking $booking, int $adminId, ?int $locationId = null, string|array|null $windowStart = null, ?array $blockLocations = null): Booking
    {
        return $this->decisions->accept($booking, BookingEvent::ACTOR_ADMIN, $adminId, $locationId, $windowStart, $blockLocations);
    }

    public function decline(Booking $booking, array $input, int $adminId): Booking
    {
        return $this->decisions->decline($booking, $input, BookingEvent::ACTOR_ADMIN, $adminId);
    }

    /* ===================== SIGNALEMENTS D'ABSENCE ===================== */

    public function resolveNoShow(BookingNoShowReport $report, ?float $refund, ?string $note, int $adminId): BookingNoShowReport
    {
        DB::transaction(function () use ($report, $refund, $note, $adminId) {
            // Demande d'abord, signalement ensuite : toujours le même ordre, pas d'interblocage.
            $booking = $this->lock($report->booking_id);
            $locked  = $this->lockReport($report);

            $amount = $this->checkedRefund($booking, $refund);
            $this->refund($booking, $amount, ['reason' => 'noshow', 'report_id' => $locked->id], $adminId);

            $locked->update([
                'status'               => BookingNoShowReport::STATUS_RESOLVED,
                'resolved_at'          => now(),
                'resolved_by_admin_id' => $adminId,
                'resolution_note'      => $this->note($note),
                'refund_amount'        => $amount,
            ]);

            BookingEvent::log($booking, 'noshow_resolved', BookingEvent::ACTOR_ADMIN, array_filter([
                'report_id' => $locked->id,
                'reporter'  => $locked->reporter,
                'refund'    => $amount,
                'note'      => $this->note($note),
            ], fn ($v) => $v !== null), null, $adminId);
        });

        return $report->fresh();
    }

    public function dismissNoShow(BookingNoShowReport $report, ?string $note, int $adminId): BookingNoShowReport
    {
        DB::transaction(function () use ($report, $note, $adminId) {
            $this->lock($report->booking_id);
            $locked = $this->lockReport($report);

            $locked->update([
                'status'               => BookingNoShowReport::STATUS_DISMISSED,
                'resolved_at'          => now(),
                'resolved_by_admin_id' => $adminId,
                'resolution_note'      => $this->note($note),
                'refund_amount'        => 0,
            ]);

            BookingEvent::log($locked->booking_id, 'noshow_dismissed', BookingEvent::ACTOR_ADMIN, array_filter([
                'report_id' => $locked->id,
                'reporter'  => $locked->reporter,
                'note'      => $this->note($note),
            ]), null, $adminId);
        });

        return $report->fresh();
    }

    /* ===================== OUTILS ===================== */

    private function checkedRefund(Booking $booking, ?float $refund): float
    {
        $refund = round((float) ($refund ?? 0), 2);
        $max    = self::refundable($booking);

        if ($refund < 0 || $refund > $max) {
            throw ValidationException::withMessages([
                'refund' => 'Le remboursement doit être compris entre 0 et ' . number_format($max, 2, ',', ' ') . ' €.',
            ]);
        }

        return $refund;
    }

    /**
     * Rembourse sur Stripe puis cumule sur la demande. Rien n'est modifié si Stripe refuse.
     * Appelée sous verrou : si la base échoue après le remboursement Stripe, la transaction
     * est annulée et le webhook « charge.refunded » remettra le cumul à jour.
     */
    private function refund(Booking $booking, float $amount, array $metadata, int $adminId): void
    {
        if ($amount <= 0) {
            return;
        }

        $invoice = $booking->invoice_id ? Invoice::find($booking->invoice_id) : null;
        if (!$invoice || !$invoice->stripe_payment_intent_id) {
            throw ValidationException::withMessages(['refund' => 'Paiement introuvable : remboursement impossible.']);
        }

        $result = $this->stripe->refundPayment(
            $invoice->stripe_payment_intent_id,
            $amount,
            $metadata + ['booking_id' => $booking->id]
        );
        if (!$result['success']) {
            throw ValidationException::withMessages(['refund' => 'Stripe a refusé le remboursement : ' . $result['error']]);
        }

        $booking->update([
            'refunded_amount' => round((float) $booking->refunded_amount + $amount, 2),
            'refunded_at'     => now(),
        ]);

        BookingEvent::log($booking, 'refunded', BookingEvent::ACTOR_ADMIN, [
            'amount'    => $amount,
            'method'    => 'stripe',
            'refund_id' => $result['refund_id'],
        ] + $metadata, null, $adminId);
    }

    /** Relit la demande en verrouillant sa ligne jusqu'à la fin de la transaction. */
    private function lock(Booking|int $booking): Booking
    {
        $id = $booking instanceof Booking ? $booking->id : $booking;

        return Booking::whereKey($id)->lockForUpdate()->firstOrFail()->load('blocks');
    }

    /** Relit le signalement sous verrou et refuse un signalement déjà traité. */
    private function lockReport(BookingNoShowReport $report): BookingNoShowReport
    {
        $locked = BookingNoShowReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
        $this->assertOpen($locked);

        return $locked;
    }

    private function assertOpen(BookingNoShowReport $report): void
    {
        if ($report->status !== BookingNoShowReport::STATUS_OPEN) {
            throw ValidationException::withMessages(['report' => 'Ce signalement a déjà été traité.']);
        }
    }

    private function note(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : mb_substr($note, 0, self::REASON_MAX);
    }
}