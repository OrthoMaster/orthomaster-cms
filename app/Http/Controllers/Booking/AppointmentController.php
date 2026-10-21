<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingBlock;
use App\Models\BookingEvent;
use App\Models\BookingDeclineReason;
use App\Models\BookingNoShowReport;
use App\Services\Booking\BookingAdminService;
use App\Services\Booking\BookingCheckoutService;
use App\Services\Google\GoogleMeetService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CMS : suivi et gestion des rendez-vous.
 * L'admin agit souvent à la demande d'une partie (appel téléphonique) : il peut annuler
 * au nom du demandeur ou de l'expert, répondre au nom de l'expert, traiter les absences.
 * Chaque action est inscrite au journal du rendez-vous avec l'admin qui l'a faite.
 */
class AppointmentController extends Controller
{
    public const PER_PAGE = 25;

    /** Onglets de la liste : [libellé, icône]. */
    public const TABS = [
        'pending'  => ['En attente', 'voyager-watch'],
        'upcoming' => ['À venir', 'voyager-calendar'],
        'noshow'   => ['Absences signalées', 'voyager-flag'],
        'past'     => ['Terminés', 'voyager-check'],
        'closed'   => ['Refusés / annulés', 'voyager-x'],
        'all'      => ['Tous', 'voyager-list'],
    ];

    /** Libellé et classe de pastille par état affiché. */
    public const STATUS = [
        'pending'   => ['En attente', 'warn'],
        'accepted'  => ['Accepté', 'on'],
        'completed' => ['Terminé', 'info'],
        'declined'  => ['Refusé', 'danger'],
        'expired'   => ['Expiré', 'muted'],
        'cancelled' => ['Annulé', 'muted'],
    ];

    public const PARTY_LABELS = [
        'user'   => 'le demandeur',
        'expert' => "l'expert",
        'admin'  => "l'administration",
        'system' => 'le système',
    ];

    public function __construct(private BookingAdminService $admin)
    {
    }

    /* ===================== LISTE ===================== */

    /**
     * GET /booking/appointments?tab=&q=&expert=&from=&to=
     */
    public function index(Request $request)
    {
        $tab = array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'pending';

        $counts = [];
        foreach (array_keys(self::TABS) as $key) {
            $counts[$key] = $this->tabQuery($key)->count();
        }

        $firstBlock = DB::table('booking_blocks')->selectRaw('MIN(start_at)')->whereColumn('booking_blocks.booking_id', 'b.id');
        $lastBlock  = DB::table('booking_blocks')->selectRaw('MAX(end_at)')->whereColumn('booking_blocks.booking_id', 'b.id');
        $openReport = DB::table('booking_noshow_reports')->selectRaw('COUNT(*)')
            ->whereColumn('booking_noshow_reports.booking_id', 'b.id')
            ->where('booking_noshow_reports.status', BookingNoShowReport::STATUS_OPEN);

        $search = trim((string) $request->input('q'));

        $bookings = $this->tabQuery($tab)
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->leftJoin('experts as e', 'e.id', '=', 'b.expert_id')
            ->select('b.id', 'b.status', 'b.offer_title', 'b.blocks_count', 'b.amount_ttc', 'b.refunded_amount',
                'b.payment_required', 'b.respond_by', 'b.requested_at', 'b.cancelled_by',
                'u.name as user_name', 'u.email as user_email', 'e.name as expert_name', 'e.id as expert_id')
            ->selectSub($firstBlock, 'first_start')
            ->selectSub($lastBlock, 'last_end')
            ->selectSub($openReport, 'open_reports')
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $q) use ($search) {
                    if (ctype_digit($search)) {
                        $q->orWhere('b.id', (int) $search);
                    }
                    $like = '%' . $search . '%';
                    $q->orWhere('u.name', 'like', $like)
                        ->orWhere('u.email', 'like', $like)
                        ->orWhere('e.name', 'like', $like)
                        ->orWhere('b.offer_title', 'like', $like);
                });
            })
            ->when($request->filled('expert'), fn (Builder $q) => $q->where('b.expert_id', (int) $request->input('expert')))
            // Période : au moins une demi-journée du rendez-vous tombe dans l'intervalle.
            ->when($request->filled('from') || $request->filled('to'), fn (Builder $q) => $q->whereExists(
                fn ($sub) => $sub->selectRaw('1')->from('booking_blocks')
                    ->whereColumn('booking_blocks.booking_id', 'b.id')
                    ->when($request->filled('from'), fn ($w) => $w->where('booking_blocks.date', '>=', $request->input('from')))
                    ->when($request->filled('to'), fn ($w) => $w->where('booking_blocks.date', '<=', $request->input('to')))
            ))
            ->when(
                in_array($tab, ['pending'], true),
                fn (Builder $q) => $q->orderBy('b.respond_by'),
                fn (Builder $q) => $tab === 'upcoming'
                    ? $q->orderBy('first_start')
                    : $q->orderByDesc('b.requested_at')
            )
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Seul l'expert filtré est chargé : la liste complète est cherchée à la frappe (expertsSearch).
        $selectedExpert = $request->filled('expert')
            ? DB::table('experts')->where('id', (int) $request->input('expert'))->first(['id', 'name'])
            : null;

        return view('booking.appointments.index', compact('tab', 'counts', 'bookings', 'selectedExpert'));
    }

    /**
     * Requête de base d'un onglet (demandes transmises uniquement : les paniers abandonnés
     * avant paiement ne concernent pas l'administration).
     */
    private function tabQuery(string $tab): Builder
    {
        $now = now();
        $endsAfterNow = fn ($q) => $q->selectRaw('1')->from('booking_blocks')
            ->whereColumn('booking_blocks.booking_id', 'b.id')
            ->where('booking_blocks.end_at', '>', $now);

        $query = DB::table('bookings as b')->whereNotNull('b.requested_at');

        return match ($tab) {
            'pending'  => $query->where('b.status', Booking::STATUS_PENDING),
            'upcoming' => $query->where('b.status', Booking::STATUS_ACCEPTED)->whereExists($endsAfterNow),
            'past'     => $query->where('b.status', Booking::STATUS_ACCEPTED)->whereNotExists($endsAfterNow),
            'closed'   => $query->whereIn('b.status', [Booking::STATUS_DECLINED, Booking::STATUS_EXPIRED, Booking::STATUS_CANCELLED]),
            'noshow'   => $query->whereExists(fn ($q) => $q->selectRaw('1')->from('booking_noshow_reports')
                ->whereColumn('booking_noshow_reports.booking_id', 'b.id')
                ->where('booking_noshow_reports.status', BookingNoShowReport::STATUS_OPEN)),
            default    => $query,
        };
    }

    /**
     * Recherche d'experts pour le filtre (select2, chargement à la frappe).
     * GET /booking/appointments/experts-search?q=&page=
     */
    public function expertsSearch(Request $request): JsonResponse
    {
        $perPage = 20;
        $page    = max(1, (int) $request->input('page', 1));
        $search  = trim((string) $request->input('q'));

        $query = DB::table('experts')
            ->whereNotNull('user_id')
            ->when($search !== '', fn (Builder $q) => $q->where('name', 'like', '%' . $search . '%'))
            ->orderBy('name');

        $total   = (clone $query)->count();
        $experts = $query->forPage($page, $perPage)->get(['id', 'name']);

        return response()->json([
            'results'    => $experts->map(fn ($e) => ['id' => $e->id, 'text' => $e->name])->values(),
            'pagination' => ['more' => $page * $perPage < $total],
        ]);
    }

    /* ===================== FICHE ===================== */

    /**
     * GET /booking/appointments/{bookingId}
     */
    public function show(int $bookingId)
    {
        $booking = $this->find($bookingId)->load(['blocks', 'user', 'expert', 'invoice', 'declineReason', 'noShowReports', 'events.admin']);

        $displayStatus = $booking->status;
        if ($booking->status === Booking::STATUS_ACCEPTED) {
            $lastEnd = $booking->blocks->max('end_at');
            $displayStatus = $lastEnd && $lastEnd->isPast() ? 'completed' : 'accepted';
        }

        $refundable = BookingAdminService::refundable($booking);
        $suggest = [
            'user'   => BookingAdminService::suggestedCancelRefund($booking, BookingAdminService::PARTY_USER),
            'expert' => BookingAdminService::suggestedCancelRefund($booking, BookingAdminService::PARTY_EXPERT),
        ];
        $noShowSuggest = $booking->noShowReports
            ->mapWithKeys(fn (BookingNoShowReport $r) => [$r->id => BookingAdminService::suggestedNoShowRefund($r->setRelation('booking', $booking))]);

        $canCancel = $booking->status === Booking::STATUS_PENDING || $displayStatus === 'accepted';
        $reasons   = BookingDeclineReason::active()->get(['id', 'label']);
        $requester = $booking->user ? BookingCheckoutService::displayName($booking->user) : '—';

        // Part conservée et reversement estimé (commission déduite du HT conservé).
        $kept     = max(0, (float) $booking->amount_ttc - (float) $booking->refunded_amount);
        $keptHt   = $booking->tva_rate > 0 ? $kept / (1 + (float) $booking->tva_rate / 100) : $kept;
        $payoutHt = round($keptHt * (1 - (float) $booking->commission_rate / 100), 2);

        return view('booking.appointments.show', compact(
            'booking', 'displayStatus', 'refundable', 'suggest', 'noShowSuggest',
            'canCancel', 'reasons', 'requester', 'kept', 'payoutHt'
        ));
    }

    /* ===================== ACTIONS ===================== */

    /**
     * POST /booking/appointments/{bookingId}/cancel
     * party (user|expert), refund, reason
     */
    public function cancel(Request $request, int $bookingId): RedirectResponse
    {
        $booking = $this->find($bookingId);
        $this->admin->cancel(
            $booking,
            (string) $request->input('party'),
            $request->user()->id,
            $request->filled('refund') ? (float) str_replace(',', '.', $request->input('refund')) : 0.0,
            $request->input('reason')
        );

        return $this->back($bookingId, 'Rendez-vous annulé au nom de ' . (self::PARTY_LABELS[$request->input('party')] ?? '—') . '.');
    }

    /**
     * Crée le lien Google Meet d'une demi-journée (test depuis le CMS).
     * POST /booking/appointments/{bookingId}/blocks/{blockId}/meet
     */
    public function createMeet(Request $request, int $bookingId, int $blockId, GoogleMeetService $meet): RedirectResponse
    {
        $booking = $this->find($bookingId);

        $error = DB::transaction(function () use ($booking, $blockId, $meet, $request) {
            // Verrou : un double clic ne crée pas deux salles.
            $block = BookingBlock::where('booking_id', $booking->id)->whereKey($blockId)->lockForUpdate()->firstOrFail();
            if ($block->meet_url) {
                return null;
            }

            try {
                $space = $meet->createSpace();
            } catch (\RuntimeException $e) {
                report($e);

                return $e->getMessage();
            }

            $block->update([
                'meet_url'        => $space['url'],
                'meet_space'      => $space['name'],
                'meet_created_at' => now(),
            ]);

            BookingEvent::log($booking, 'meet_created', BookingEvent::ACTOR_ADMIN, [
                'block_id' => $block->id,
                'date'     => $block->date->toDateString(),
                'period'   => $block->period,
                'url'      => $space['url'],
            ], null, $request->user()->id);

            return null;
        });

        if ($error) {
            return redirect()
                ->route('booking.appointments.show', $bookingId)
                ->with(['message' => $error, 'alert-type' => 'error']);
        }

        return $this->back($bookingId, 'Lien Google Meet créé.');
    }

    /**
     * Supprime le lien Google Meet d'une demi-journée : la salle est fermée chez Google
     * (accès restreint), puis le lien est retiré ; un nouveau lien peut ensuite être créé.
     * POST /booking/appointments/{bookingId}/blocks/{blockId}/meet/cancel
     */
    public function cancelMeet(Request $request, int $bookingId, int $blockId, GoogleMeetService $meet): RedirectResponse
    {
        $booking = $this->find($bookingId);

        $error = DB::transaction(function () use ($booking, $blockId, $meet, $request) {
            $block = BookingBlock::where('booking_id', $booking->id)->whereKey($blockId)->lockForUpdate()->firstOrFail();
            if (!$block->meet_url) {
                return null;
            }

            if ($block->meet_space) {
                try {
                    $meet->closeSpace($block->meet_space);
                } catch (\RuntimeException $e) {
                    report($e);

                    return $e->getMessage();
                }
            }

            $url = $block->meet_url;
            $block->update([
                'meet_url'        => null,
                'meet_space'      => null,
                'meet_created_at' => null,
            ]);

            BookingEvent::log($booking, 'meet_cancelled', BookingEvent::ACTOR_ADMIN, [
                'block_id' => $block->id,
                'date'     => $block->date->toDateString(),
                'period'   => $block->period,
                'url'      => $url,
            ], null, $request->user()->id);

            return null;
        });

        if ($error) {
            return redirect()
                ->route('booking.appointments.show', $bookingId)
                ->with(['message' => $error, 'alert-type' => 'error']);
        }

        return $this->back($bookingId, 'Lien Google Meet supprimé : la salle est fermée.');
    }

    /**
     * POST /booking/appointments/{bookingId}/accept
     */
    public function accept(Request $request, int $bookingId): RedirectResponse
    {
        $this->admin->accept($this->find($bookingId), $request->user()->id);

        return $this->back($bookingId, "Demande acceptée au nom de l'expert : le montant a été débité.");
    }

    /**
     * POST /booking/appointments/{bookingId}/decline
     * reason_id, note
     */
    public function decline(Request $request, int $bookingId): RedirectResponse
    {
        $this->admin->decline($this->find($bookingId), $request->only(['reason_id', 'note']), $request->user()->id);

        return $this->back($bookingId, "Demande refusée au nom de l'expert : l'empreinte bancaire a été libérée.");
    }

    /**
     * POST /booking/appointments/{bookingId}/no-show/{reportId}/resolve
     * refund, note
     */
    public function resolveNoShow(Request $request, int $bookingId, int $reportId): RedirectResponse
    {
        $report = BookingNoShowReport::where('booking_id', $bookingId)->findOrFail($reportId);
        $this->admin->resolveNoShow(
            $report,
            $request->filled('refund') ? (float) str_replace(',', '.', $request->input('refund')) : 0.0,
            $request->input('note'),
            $request->user()->id
        );

        return $this->back($bookingId, 'Signalement traité.');
    }

    /**
     * POST /booking/appointments/{bookingId}/no-show/{reportId}/dismiss
     * note
     */
    public function dismissNoShow(Request $request, int $bookingId, int $reportId): RedirectResponse
    {
        $report = BookingNoShowReport::where('booking_id', $bookingId)->findOrFail($reportId);
        $this->admin->dismissNoShow($report, $request->input('note'), $request->user()->id);

        return $this->back($bookingId, 'Signalement classé sans suite.');
    }

    /* ===================== OUTILS ===================== */

    private function find(int $bookingId): Booking
    {
        return Booking::submitted()->findOrFail($bookingId);
    }

    private function back(int $bookingId, string $message): RedirectResponse
    {
        return redirect()
            ->route('booking.appointments.show', $bookingId)
            ->with(['message' => $message, 'alert-type' => 'success']);
    }
}