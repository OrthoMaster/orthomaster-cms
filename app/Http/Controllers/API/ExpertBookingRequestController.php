<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingBlock;
use App\Models\BookingDeclineReason;
use App\Models\BookingNoShowReport;
use App\Services\Booking\BookingCheckoutService;
use App\Services\Booking\BookingDecisionService;
use App\Services\Booking\BookingNoShowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Demandes reçues par l'expert connecté.
 *
 *  - Demandes : transmises et sans réponse, la plus urgente d'abord.
 *  - Historique : acceptées, refusées, expirées, annulées par le demandeur.
 *
 * Les rendez-vous acceptés à venir relèvent de l'onglet Agenda.
 */
class ExpertBookingRequestController extends Controller
{
    public const HISTORY_PER_PAGE = 15;

    public function __construct(
        private BookingDecisionService $decisions,
        private BookingNoShowService $noShows
    ) {
    }

    /* ===================== LISTES ===================== */

    /**
     * GET /booking/me/requests
     */
    public function index(Request $request): JsonResponse
    {
        $pending = $this->query($request)
            ->where('status', Booking::STATUS_PENDING)
            ->orderBy('respond_by')
            ->get();

        return $this->success([
            'pending' => $pending->map(fn (Booking $b) => self::payload($b))->values(),
        ]);
    }

    /**
     * GET /booking/me/requests/history?page=
     */
    public function history(Request $request): JsonResponse
    {
        $page = $this->query($request)
            ->where('status', '!=', Booking::STATUS_PENDING)
            ->orderByDesc('requested_at')
            ->paginate(self::HISTORY_PER_PAGE);

        return $this->success([
            'requests'   => collect($page->items())->map(fn (Booking $b) => self::payload($b))->values(),
            'pagination' => [
                'page'     => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total'    => $page->total(),
                'has_more' => $page->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /booking/me/requests/{booking}
     */
    public function show(Request $request, int $bookingId): JsonResponse
    {
        return $this->success($this->detail($this->find($request, $bookingId)));
    }

    /**
     * Motifs proposés au refus.
     * GET /booking/me/decline-reasons
     */
    public function reasons(): JsonResponse
    {
        return $this->success([
            'reasons'  => BookingDeclineReason::active()->get(['id', 'label']),
            'note_max' => BookingDecisionService::DECLINE_NOTE_MAX,
        ]);
    }

    /* ===================== RÉPONSES ===================== */

    /**
     * POST /booking/me/requests/{booking}/accept
     */
    public function accept(Request $request, int $bookingId): JsonResponse
    {
        $booking = $this->decisions->accept($this->find($request, $bookingId));

        return $this->success($this->detail($booking->load(['user', 'declineReason', 'noShowReports'])));
    }

    /**
     * POST /booking/me/requests/{booking}/decline
     * Corps : reason_id, note?
     */
    public function decline(Request $request, int $bookingId): JsonResponse
    {
        $booking = $this->decisions->decline($this->find($request, $bookingId), $request->only(['reason_id', 'note']));

        return $this->success($this->detail($booking->load(['user', 'declineReason', 'noShowReports'])));
    }

    /**
     * Signale l'absence du demandeur.
     * POST /booking/me/requests/{booking}/no-show
     * Corps : block_ids[], note?
     */
    public function reportNoShow(Request $request, int $bookingId): JsonResponse
    {
        $booking = $this->find($request, $bookingId);
        $this->noShows->report($booking, BookingNoShowReport::REPORTER_EXPERT, $request->user()->id, $request->only(['block_ids', 'note']));

        return $this->success($this->detail($booking->load('noShowReports')));
    }

    /* ===================== OUTILS ===================== */

    /** Fiche complète, avec l'état du signalement d'absence côté expert. */
    private function detail(Booking $booking): array
    {
        return self::payload($booking, true) + [
            'noshow' => $this->noShows->payload($booking, BookingNoShowReport::REPORTER_EXPERT),
        ];
    }

    private function expertId(Request $request): int
    {
        $id = DB::table('experts')->where('user_id', $request->user()->id)->value('id');
        abort_unless($id, 403, "Vous n'êtes pas enregistré comme expert.");

        return (int) $id;
    }

    /** Public : l'agenda réutilise la même requête (demandes de l'expert connecté). */
    public function query(Request $request): Builder
    {
        return Booking::query()
            ->submitted()
            ->where('expert_id', $this->expertId($request))
            ->with(['blocks', 'user', 'declineReason', 'noShowReports']);
    }

    private function find(Request $request, int $bookingId): Booking
    {
        return $this->query($request)->findOrFail($bookingId);
    }

    private static function displayStatus(Booking $booking): string
    {
        if ($booking->status === Booking::STATUS_ACCEPTED) {
            $lastEnd = $booking->blocks->max('end_at');

            return $lastEnd && $lastEnd->isPast() ? 'completed' : 'accepted';
        }

        return $booking->status;
    }

    public static function payload(Booking $booking, bool $detailed = false): array
    {
        $data = [
            'id'               => $booking->id,
            'status'           => self::displayStatus($booking),
            'offer_title'      => $booking->offer_title,
            'mode'             => $booking->mode,
            'blocks_count'     => $booking->blocks_count,
            // Le demandeur : nom affiché seulement ; l'e-mail reste côté plateforme.
            'requester'        => [
                'name' => $booking->user ? BookingCheckoutService::displayName($booking->user) : 'Confrère',
            ],
            // L'expert voit toujours ses propres informations d'accès.
            'blocks'           => $booking->blocks
                ->map(fn (BookingBlock $b) => BookingRequestController::blockPayload($b, true, BookingRequestController::meetVisible($booking)))
                ->values(),
            'respond_by'       => $booking->respond_by?->toIso8601String(),
            'requested_at'     => $booking->requested_at?->toIso8601String(),
            'responded_at'     => $booking->responded_at?->toIso8601String(),
            'cancelled_by'     => $booking->cancelled_by,
            'can_respond'      => $booking->status === Booking::STATUS_PENDING,
        ];

        if ($detailed) {
            $data += [
                'message'          => $booking->message,
                'payment_required' => $booking->payment_required,
                'amount_ht'        => (float) $booking->amount_ht,
                'commission_rate'  => (float) $booking->commission_rate,
                'payout'           => $booking->expertPayout(),
                'currency'         => $booking->currency,
                'decline_reason'   => $booking->declineReason?->label,
                'decline_note'     => $booking->decline_note,
            ];
        }

        return $data;
    }

    private function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}