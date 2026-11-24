<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Booking\BookingCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Revenus de l'expert connecté (onglet Expertise → Revenus de l'application).
 * GET /booking/me/revenue?year=AAAA
 *
 * Seulement la part de l'expert : rendez-vous payants débités (acceptés, même annulés ensuite),
 * datés du jour du débit (premier « accepted » du journal, heure de Paris) ; part = HT, commission
 * déduite, diminuée au prorata de ce qui a été remboursé au demandeur (même calcul que le tableau
 * Revenus du CMS). Pas de TVA ni de commission dans la réponse.
 */
class ExpertRevenueController extends Controller
{
    private const TZ = 'Europe/Paris';

    public function index(Request $request): JsonResponse
    {
        $expertId = DB::table('experts')->where('user_id', $request->user()->id)->value('id');
        abort_unless($expertId, 403, "Vous n'êtes pas enregistré comme expert.");

        $captured = DB::table('booking_events')
            ->select('booking_id')
            ->selectRaw('MIN(created_at) as captured_at')
            ->where('type', 'accepted')
            ->groupBy('booking_id');

        // Années où l'expert a été payé (sélecteur), l'année en cours toujours proposée.
        $app = config('app.timezone', 'UTC');
        $all = Booking::query()
            ->joinSub($captured, 'c', 'c.booking_id', '=', 'bookings.id')
            ->where('bookings.expert_id', $expertId)
            ->where('bookings.payment_required', 1)
            ->with(['user', 'blocks'])
            ->get(['bookings.*', 'c.captured_at']);

        $now = Carbon::now(self::TZ);
        $years = $all->map(fn ($b) => (int) Carbon::parse($b->captured_at, $app)->setTimezone(self::TZ)->year)
            ->push($now->year)
            ->unique()->sortDesc()->values();

        $year = (int) ($request->integer('year') ?: $now->year);

        $items = $all
            ->map(fn (Booking $b) => $this->item($b, Carbon::parse($b->captured_at, $app)->setTimezone(self::TZ)))
            ->filter(fn ($i) => (int) substr($i['captured_at'], 0, 4) === $year)
            ->sortByDesc('captured_at')
            ->values();

        $months = collect(range(1, 12))->map(function (int $m) use ($items) {
            $list = $items->filter(fn ($i) => (int) substr($i['captured_at'], 5, 2) === $m);

            return [
                'month'  => $m,
                'count'  => $list->count(),
                'payout' => round($list->sum('payout'), 2),
            ];
        });

        return response()->json(['success' => true, 'data' => [
            'year'     => $year,
            'years'    => $years,
            'currency' => 'EUR',
            'total'    => [
                'count'          => $items->count(),
                'payout'         => round($items->sum('payout'), 2),
                'refunded_count' => $items->where('refunded', '!=', 'none')->count(),
            ],
            'months'   => $months,
            'items'    => $items,
        ]]);
    }

    private function item(Booking $b, Carbon $capturedAt): array
    {
        $ttc = (float) $b->amount_ttc;
        $refunded = min($ttc, (float) ($b->refunded_amount ?? 0));
        $kept = $ttc > 0 ? ($ttc - $refunded) / $ttc : 0;
        $gross = $b->expertPayout();

        $first = $b->blocks->sortBy('start_at')->first();
        $consultation = ($b->kind ?? Booking::KIND_OFFER) === Booking::KIND_CONSULTATION;

        return [
            'id'            => $b->id,
            'reference'     => $b->reference(),
            // Jour du débit, heure de Paris (« AAAA-MM-JJ… ») : le regroupement par mois s'y fie.
            'captured_at'   => $capturedAt->toIso8601String(),
            'kind'          => $b->kind ?? Booking::KIND_OFFER,
            'title'         => $consultation ? 'Consultation' : ($b->offer_title ?: 'Thème'),
            'mode'          => $b->mode,
            'requester'     => $b->user ? BookingCheckoutService::displayName($b->user) : 'Confrère',
            'first_start'   => $first?->start_at?->toIso8601String(),
            'blocks_count'  => $consultation ? null : max(1, $b->blocks->count() ?: (int) $b->blocks_count),
            'duration_minutes' => $consultation ? $b->duration_minutes : null,
            'cancelled'     => $b->status === Booking::STATUS_CANCELLED,
            'payout_gross'  => round($gross, 2),
            'payout'        => round($gross * $kept, 2),
            // none | partial | full : remboursement fait au demandeur.
            'refunded'      => $refunded <= 0 ? 'none' : ($refunded >= $ttc - 0.005 ? 'full' : 'partial'),
        ];
    }
}
