<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\BookingBlock;
use App\Services\Booking\BookingCheckoutService;
use App\Services\Booking\BookingNoShowService;
use App\Models\BookingNoShowReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rendez-vous de l'utilisateur (côté demandeur) : en attente, à venir, historique.
 *
 *  - En attente : transmises, sans réponse de l'expert.
 *  - À venir    : acceptées dont la dernière demi-journée n'est pas terminée.
 *  - Historique : acceptées et passées, refusées, expirées, annulées après envoi.
 */
class BookingRequestController extends Controller
{
    public const HISTORY_PER_PAGE = 15;

    public function __construct(
        private BookingCheckoutService $checkout,
        private BookingNoShowService $noShows
    ) {
    }

    /* ===================== LISTES ===================== */

    /**
     * GET /booking/requests
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $pending = $this->query($userId)
            ->where('status', Booking::STATUS_PENDING)
            ->orderBy('respond_by')
            ->get();

        $upcoming = $this->query($userId)
            ->where('status', Booking::STATUS_ACCEPTED)
            ->whereExists($this->blockEndingAfterNow())
            ->get()
            ->sortBy(fn (Booking $b) => $b->blocks->min('start_at'))
            ->values();

        return $this->success([
            'pending'  => $pending->map(fn (Booking $b) => self::payload($b))->values(),
            'upcoming' => $upcoming->map(fn (Booking $b) => self::payload($b))->values(),
        ]);
    }

    /**
     * Historique, le plus récent d'abord.
     * GET /booking/requests/history?page=
     */
    public function history(Request $request): JsonResponse
    {
        $page = $this->query($request->user()->id)
            ->where(function (Builder $q) {
                $q->whereIn('status', [Booking::STATUS_DECLINED, Booking::STATUS_EXPIRED, Booking::STATUS_CANCELLED])
                    ->orWhere(fn (Builder $q) => $q
                        ->where('status', Booking::STATUS_ACCEPTED)
                        ->whereNotExists($this->blockEndingAfterNow()));
            })
            ->orderByDesc('requested_at')
            ->paginate(self::HISTORY_PER_PAGE);

        return $this->success([
            'bookings'   => collect($page->items())->map(fn (Booking $b) => self::payload($b))->values(),
            'pagination' => [
                'page'     => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total'    => $page->total(),
                'has_more' => $page->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /booking/requests/{booking}
     */
    public function show(Request $request, int $bookingId): JsonResponse
    {
        $booking = $this->query($request->user()->id)->findOrFail($bookingId);

        return $this->success($this->detail($booking));
    }

    /* ===================== ACTIONS ===================== */

    /**
     * Retrait d'une demande sans réponse : gratuit, l'empreinte bancaire est libérée.
     * POST /booking/requests/{booking}/cancel
     */
    public function cancel(Request $request, int $bookingId): JsonResponse
    {
        $booking = $this->query($request->user()->id)->findOrFail($bookingId);

        if ($booking->status !== Booking::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => "Cette demande ne peut plus être annulée depuis l'application.",
            ], 400);
        }

        // false : l'expert a répondu à l'instant (verrou), la demande n'est plus annulable.
        if (!$this->checkout->cancel($booking, Booking::CANCELLED_BY_USER)) {
            return response()->json([
                'success' => false,
                'message' => "L'expert vient de répondre à cette demande : elle ne peut plus être annulée.",
            ], 409);
        }

        return $this->success($this->detail($booking->fresh(['blocks', 'expert', 'declineReason', 'noShowReports', 'invoice'])));
    }

    /**
     * Signale l'absence de l'expert.
     * POST /booking/requests/{booking}/no-show
     * Corps : block_ids[], note?
     */
    public function reportNoShow(Request $request, int $bookingId): JsonResponse
    {
        $booking = $this->query($request->user()->id)->findOrFail($bookingId);
        $this->noShows->report($booking, BookingNoShowReport::REPORTER_USER, $request->user()->id, $request->only(['block_ids', 'note']));

        return $this->success($this->detail($booking->load('noShowReports')));
    }

    /* ===================== OUTILS ===================== */

    /** Fiche complète, avec l'état du signalement d'absence côté demandeur. */
    private function detail(Booking $booking): array
    {
        return self::payload($booking, true) + [
            'noshow' => $this->noShows->payload($booking, BookingNoShowReport::REPORTER_USER),
        ];
    }

    private function query(int $userId): Builder
    {
        return Booking::query()
            ->submitted()
            ->where('user_id', $userId)
            ->with(['blocks', 'expert', 'declineReason', 'noShowReports', 'invoice']);
    }

    /** Une demi-journée de la demande se termine après maintenant. */
    private function blockEndingAfterNow(): \Closure
    {
        return fn ($q) => $q->selectRaw('1')
            ->from('booking_blocks')
            ->whereColumn('booking_blocks.booking_id', 'bookings.id')
            ->where('booking_blocks.end_at', '>', now());
    }

    /**
     * Libellé d'état pour l'application ; « completed » = acceptée et passée.
     */
    private static function displayStatus(Booking $booking): string
    {
        if ($booking->status === Booking::STATUS_ACCEPTED) {
            $lastEnd = $booking->blocks->max('end_at');

            return $lastEnd && $lastEnd->isPast() ? 'completed' : 'accepted';
        }

        return $booking->status;
    }

    /** Montant débité : la capture n'a lieu qu'à l'acceptation (facture « paid »). */
    private static function chargedAmount(Booking $booking): float
    {
        return $booking->payment_required && $booking->invoice?->status === Invoice::STATUS_PAID
            ? (float) $booking->amount_ttc
            : 0.0;
    }

    public static function payload(Booking $booking, bool $detailed = false): array
    {
        $data = [
            'id'               => $booking->id,
            'status'           => self::displayStatus($booking),
            'offer_title'      => $booking->offer_title,
            'blocks_count'     => $booking->blocks_count,
            'expert'           => $booking->expert ? BookingExpertController::expertPayload($booking->expert, false) : null,
            'blocks'           => $booking->blocks->map(fn (BookingBlock $b) => [
                'id'          => $b->id,
                'date'        => $b->date->toDateString(),
                'period'      => $b->period,
                'start_local' => $b->start_local,
                'end_local'   => $b->end_local,
                'start_at'    => $b->start_at?->toIso8601String(),
                'end_at'      => $b->end_at?->toIso8601String(),
                // Lien Meet de la demi-journée : renseigné quand l'intégration Google Meet sera en place.
                'meet_url'    => null,
            ])->values(),
            'amount_ttc'       => (float) $booking->amount_ttc,
            'currency'         => $booking->currency,
            'payment_required' => $booking->payment_required,
            'respond_by'       => $booking->respond_by?->toIso8601String(),
            'requested_at'     => $booking->requested_at?->toIso8601String(),
            'cancelled_at'     => $booking->cancelled_at?->toIso8601String(),
            'cancelled_by'     => $booking->cancelled_by,
            'can_cancel'       => $booking->status === Booking::STATUS_PENDING,
            // Argent réellement débité (capture faite) et déjà remboursé, pour les textes d'état.
            'charged_amount'   => self::chargedAmount($booking),
            'refunded_amount'  => (float) $booking->refunded_amount,
            'refunded_at'      => $booking->refunded_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data += [
                'amount_ht'      => (float) $booking->amount_ht,
                'tva_rate'       => (float) $booking->tva_rate,
                'tva_amount'     => (float) $booking->tva_amount,
                'message'        => $booking->message,
                'client_address' => $booking->client_address,
                'decline_reason' => $booking->declineReason?->label,
                'decline_note'   => $booking->decline_note,
            ];
        }

        return $data;
    }

    private function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}