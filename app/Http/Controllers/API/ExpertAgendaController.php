<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingBlock;
use App\Services\Booking\BookingCheckoutService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Agenda de l'expert : toutes ses demi-journées à venir, quel que soit son rôle.
 *
 *  - role = host  : un confrère a réservé auprès de lui (il anime).
 *  - role = guest : il a lui-même réservé auprès d'un autre expert (il participe).
 *
 * Une seule liste chronologique ; chaque séance dit qui demande et qui est l'expert,
 * l'application ouvre la fiche du bon côté selon le rôle.
 *
 *  - Séances à venir : chaque demi-journée non terminée, à plat et dans l'ordre,
 *    avec son rang dans le rendez-vous (« séance 2/4 »).
 *  - En attente : demandes que l'expert a envoyées et qui attendent la réponse du confrère.
 *  - Historique : rendez-vous passés ou clos, des deux côtés.
 */
class ExpertAgendaController extends Controller
{
    public const HISTORY_PER_PAGE = 15;

    public const ROLE_HOST  = 'host';
    public const ROLE_GUEST = 'guest';

    public function __construct(private ExpertBookingRequestController $requests)
    {
    }

    /**
     * GET /booking/me/agenda
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $hosted = $this->requests->query($request)
            ->where('status', Booking::STATUS_ACCEPTED)
            ->whereHas('blocks', fn ($q) => $q->where('end_at', '>', now()))
            ->get();

        $attended = $this->guestQuery($userId)
            ->where('status', Booking::STATUS_ACCEPTED)
            ->whereHas('blocks', fn ($q) => $q->where('end_at', '>', now()))
            ->get();

        $sessions = $this->sessions($hosted, self::ROLE_HOST)
            ->concat($this->sessions($attended, self::ROLE_GUEST))
            ->sortBy('start_at')
            ->values();

        // Demandes envoyées par l'expert, sans réponse : même forme que l'onglet Rendez-vous.
        $pending = $this->guestQuery($userId)
            ->where('status', Booking::STATUS_PENDING)
            ->orderBy('respond_by')
            ->get()
            ->map(fn (Booking $b) => BookingRequestController::payload($b) + ['role' => self::ROLE_GUEST])
            ->values();

        return response()->json(['success' => true, 'data' => ['sessions' => $sessions, 'pending' => $pending]]);
    }

    /**
     * Rendez-vous passés ou clos, des deux côtés, le plus récent d'abord.
     * « requests » garde l'ancienne forme (rendez-vous animés seulement) pour l'écran actuel ;
     * « items » est la liste commune, chaque élément avec son rôle.
     * GET /booking/me/agenda/history?page=
     */
    public function history(Request $request): JsonResponse
    {
        $userId   = $request->user()->id;
        $expertId = (int) DB::table('experts')->where('user_id', $userId)->value('id');
        abort_unless($expertId, 403, "Vous n'êtes pas enregistré comme expert.");

        $page = Booking::query()
            ->submitted()
            ->with(['blocks', 'user', 'expert', 'declineReason', 'noShowReports', 'invoice'])
            ->where(function (Builder $q) use ($expertId, $userId) {
                // Animés : acceptés et entièrement passés (les demandes refusées restent dans Demandes).
                $q->where(fn (Builder $h) => $h
                    ->where('expert_id', $expertId)
                    ->where('status', Booking::STATUS_ACCEPTED)
                    ->whereDoesntHave('blocks', fn ($b) => $b->where('end_at', '>', now())))
                // Réservés : même règle que l'historique de l'onglet Rendez-vous.
                    ->orWhere(fn (Builder $g) => $g
                        ->where('user_id', $userId)
                        ->where(fn (Builder $s) => $s
                            ->whereIn('status', [Booking::STATUS_DECLINED, Booking::STATUS_EXPIRED, Booking::STATUS_CANCELLED])
                            ->orWhere(fn (Builder $a) => $a
                                ->where('status', Booking::STATUS_ACCEPTED)
                                ->whereDoesntHave('blocks', fn ($b) => $b->where('end_at', '>', now())))));
            })
            ->orderByDesc(
                BookingBlock::selectRaw('MAX(end_at)')->whereColumn('booking_blocks.booking_id', 'bookings.id')
            )
            ->paginate(self::HISTORY_PER_PAGE);

        $items = collect($page->items())->map(fn (Booking $b) => $this->roleOf($b, $userId) === self::ROLE_HOST
            ? ['role' => self::ROLE_HOST, 'booking' => ExpertBookingRequestController::payload($b)]
            : ['role' => self::ROLE_GUEST, 'booking' => BookingRequestController::payload($b)]
        )->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'items'      => $items,
                'requests'   => $items->where('role', self::ROLE_HOST)->pluck('booking')->values(),
                'pagination' => [
                    'page'     => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total'    => $page->total(),
                    'has_more' => $page->hasMorePages(),
                ],
            ],
        ]);
    }

    /* ===================== OUTILS ===================== */

    /** Rendez-vous que l'utilisateur connecté a réservés auprès d'un autre expert. */
    private function guestQuery(int $userId): Builder
    {
        return Booking::query()
            ->submitted()
            ->where('user_id', $userId)
            ->with(['blocks', 'user', 'expert', 'declineReason', 'noShowReports', 'invoice']);
    }

    private function roleOf(Booking $booking, int $userId): string
    {
        return (int) $booking->user_id === $userId ? self::ROLE_GUEST : self::ROLE_HOST;
    }

    /**
     * Demi-journées non terminées des rendez-vous, à plat.
     * Hôte : le demandeur est le confrère, l'expert est « moi ».
     * Invité : le demandeur est « moi », l'expert est le confrère (avec avatar et couleur).
     */
    private function sessions(Collection $bookings, string $role): Collection
    {
        return $bookings->flatMap(function (Booking $booking) use ($role) {
            $blocks = $booking->blocks->values();
            $total  = $blocks->count();
            $expert = $role === self::ROLE_GUEST && $booking->expert
                ? BookingExpertController::expertPayload($booking->expert, false)
                : null;

            return $blocks
                ->map(fn (BookingBlock $b, int $i) => [$b, $i + 1])
                ->filter(fn ($pair) => $pair[0]->end_at && $pair[0]->end_at->isFuture())
                ->map(fn ($pair) => [
                    'role'        => $role,
                    'booking_id'  => $booking->id,
                    'block_id'    => $pair[0]->id,
                    'date'        => $pair[0]->date->toDateString(),
                    'period'      => $pair[0]->period,
                    'start_local' => $pair[0]->start_local,
                    'end_local'   => $pair[0]->end_local,
                    'start_at'    => $pair[0]->start_at?->toIso8601String(),
                    'end_at'      => $pair[0]->end_at?->toIso8601String(),
                    'index'       => $pair[1],
                    'total'       => $total,
                    'offer_title' => $booking->offer_title,
                    'mode'        => $booking->mode,
                    'requester'   => [
                        'name' => $role === self::ROLE_HOST && $booking->user
                            ? BookingCheckoutService::displayName($booking->user)
                            : ($role === self::ROLE_HOST ? 'Confrère' : null),
                    ],
                    // Invité : l'expert rencontré. Hôte : null (c'est l'expert connecté).
                    'expert'      => $expert,
                    // Séances acceptées : lien Meet s'il existe et que la salle est ouverte (en ligne).
                    'meet_url'    => BookingRequestController::meetVisible($booking)
                        ? BookingRequestController::blockMeetUrl($pair[0])
                        : null,
                    // Rendez-vous accepté des deux côtés : les informations d'accès sont visibles.
                    'location'    => BookingRequestController::blockLocation($pair[0], true),
                ]);
        });
    }
}
