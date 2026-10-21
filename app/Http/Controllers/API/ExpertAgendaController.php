<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingBlock;
use App\Services\Booking\BookingCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agenda de l'expert : demi-journées des rendez-vous acceptés.
 *
 *  - Séances à venir : chaque demi-journée non terminée, à plat et dans l'ordre,
 *    avec son rang dans le rendez-vous (« séance 2/4 »).
 *  - Historique : rendez-vous acceptés entièrement passés.
 */
class ExpertAgendaController extends Controller
{
    public const HISTORY_PER_PAGE = 15;

    public function __construct(private ExpertBookingRequestController $requests)
    {
    }

    /**
     * GET /booking/me/agenda
     */
    public function index(Request $request): JsonResponse
    {
        $bookings = $this->requests->query($request)
            ->where('status', Booking::STATUS_ACCEPTED)
            ->whereHas('blocks', fn ($q) => $q->where('end_at', '>', now()))
            ->get();

        $sessions = $bookings
            ->flatMap(function (Booking $booking) {
                $blocks = $booking->blocks->values();
                $total  = $blocks->count();

                return $blocks
                    ->map(fn (BookingBlock $b, int $i) => [$b, $i + 1])
                    ->filter(fn ($pair) => $pair[0]->end_at && $pair[0]->end_at->isFuture())
                    ->map(fn ($pair) => [
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
                        'requester'   => [
                            'name' => $booking->user ? BookingCheckoutService::displayName($booking->user) : 'Confrère',
                        ],
                        'meet_url'    => null,
                    ]);
            })
            ->sortBy('start_at')
            ->values();

        return response()->json(['success' => true, 'data' => ['sessions' => $sessions]]);
    }

    /**
     * Rendez-vous acceptés dont toutes les demi-journées sont passées.
     * GET /booking/me/agenda/history?page=
     */
    public function history(Request $request): JsonResponse
    {
        $page = $this->requests->query($request)
            ->where('status', Booking::STATUS_ACCEPTED)
            ->whereDoesntHave('blocks', fn ($q) => $q->where('end_at', '>', now()))
            ->orderByDesc(
                BookingBlock::selectRaw('MAX(end_at)')->whereColumn('booking_blocks.booking_id', 'bookings.id')
            )
            ->paginate(self::HISTORY_PER_PAGE);

        return response()->json([
            'success' => true,
            'data'    => [
                'requests'   => collect($page->items())
                    ->map(fn (Booking $b) => ExpertBookingRequestController::payload($b))
                    ->values(),
                'pagination' => [
                    'page'     => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total'    => $page->total(),
                    'has_more' => $page->hasMorePages(),
                ],
            ],
        ]);
    }
}