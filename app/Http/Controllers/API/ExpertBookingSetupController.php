<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Expert;
use App\Models\ExpertAvailabilityException;
use App\Models\ExpertAvailabilityRule;
use App\Models\ExpertBookingSetting;
use App\Models\ExpertOffer;
use App\Services\Booking\ExpertBookingSetupService;
use App\Services\Booking\ExpertSlotService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API de l'application : l'expert configure ses propres rendez-vous.
 *
 * Chaque écriture renvoie l'état complet dans data (même forme que GET /booking/me) :
 * l'application remplace son état local d'un coup, sans recharger ni recalculer.
 * Les erreurs de validation viennent du service et sortent en 422.
 */
class ExpertBookingSetupController extends Controller
{
    public function __construct(
        private ExpertBookingSetupService $setup,
        private ExpertSlotService $slots,
    ) {
    }

    /* ===================== LECTURE ===================== */

    /**
     * État complet de la configuration. Un utilisateur non expert reçoit
     * is_expert = false (et non une erreur), pour que l'application masque l'écran.
     * GET /booking/me
     */
    public function show(Request $request): JsonResponse
    {
        $expertId = $this->expertId($request);

        if (!$expertId) {
            return $this->success(['is_expert' => false]);
        }

        return $this->success($this->payload($expertId));
    }

    /**
     * Aperçu de la grille telle que les utilisateurs la verront, même désactivée.
     * GET /booking/me/preview?from=Y-m-d&to=Y-m-d
     */
    public function preview(Request $request): JsonResponse
    {
        $expertId = $this->requireExpert($request);

        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $blocks = $this->slots->availableBlocks($expertId, $request->input('from'), $request->input('to'), true);
        $bounds = $this->slots->bounds($expertId);

        return $this->success(BookingExpertController::calendarPayload($bounds, $blocks));
    }

    /* ===================== RÉGLAGES ===================== */

    /**
     * Mise à jour partielle : un champ absent garde sa valeur actuelle,
     * un champ envoyé à null revient à la valeur système.
     * Permet à l'application de basculer l'activation seule, sans renvoyer tout le formulaire.
     * PUT /booking/me/settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $own      = ExpertBookingSetting::where('expert_id', $expertId)->first();
        $resolved = $this->setup->resolved($expertId);
        $time     = fn (?string $value) => $value ? substr($value, 0, 5) : null;

        $current = [
            'enabled_flg'        => $resolved['enabled_flg'],
            'timezone'           => $resolved['timezone'],
            'min_notice_hours'   => $own?->min_notice_hours,
            'max_advance_months' => $own?->max_advance_months,
            'morning_start'      => $time($own?->morning_start),
            'morning_end'        => $time($own?->morning_end),
            'afternoon_start'    => $time($own?->afternoon_start),
            'afternoon_end'      => $time($own?->afternoon_end),
        ];

        $this->setup->saveSettings($expertId, array_merge($current, $request->only([
            'enabled_flg',
            'timezone',
            'min_notice_hours',
            'max_advance_months',
            'morning_start',
            'morning_end',
            'afternoon_start',
            'afternoon_end',
        ])));

        return $this->success($this->payload($expertId));
    }

    /* ===================== OFFRES ===================== */

    /**
     * POST /booking/me/offers
     */
    public function storeOffer(Request $request): JsonResponse
    {
        $expertId = $this->requireExpert($request);

        $this->setup->saveOffer($expertId, $this->offerInput($request));

        return $this->success($this->payload($expertId), 201);
    }

    /**
     * PUT /booking/me/offers/{offer}
     */
    public function updateOffer(Request $request, ExpertOffer $offer): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $this->assertOwned($offer->expert_id, $expertId);

        $this->setup->saveOffer($expertId, $this->offerInput($request), $offer);

        return $this->success($this->payload($expertId));
    }

    /**
     * DELETE /booking/me/offers/{offer}
     */
    public function destroyOffer(Request $request, ExpertOffer $offer): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $this->assertOwned($offer->expert_id, $expertId);

        $this->setup->deleteOffer($offer);

        return $this->success($this->payload($expertId));
    }

    private function offerInput(Request $request): array
    {
        // L'application envoie toujours active_flg ; true par défaut pour une création sans le champ.
        return $request->only(['title', 'description', 'blocks_count', 'price', 'order_dsp']) + [
            'active_flg' => $request->has('active_flg') ? $request->boolean('active_flg') : true,
        ];
    }

    /* ===================== DISPONIBILITÉS ===================== */

    /**
     * Le résultat (créés / ignorés) accompagne l'état, pour le toast de l'application.
     * POST /booking/me/rules
     */
    public function storeRules(Request $request): JsonResponse
    {
        $expertId = $this->requireExpert($request);

        $result = $this->setup->addRules($expertId, $request->only(['weekdays', 'periods', 'valid_from', 'valid_until']));

        return $this->success($this->payload($expertId) + ['result' => $result], 201);
    }

    /**
     * DELETE /booking/me/rules/{rule}
     */
    public function destroyRule(Request $request, ExpertAvailabilityRule $rule): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $this->assertOwned($rule->expert_id, $expertId);

        $this->setup->deleteRule($rule);

        return $this->success($this->payload($expertId));
    }

    /**
     * POST /booking/me/exceptions
     */
    public function storeExceptions(Request $request): JsonResponse
    {
        $expertId = $this->requireExpert($request);

        $count = $this->setup->addExceptions($expertId, $request->only(['date_from', 'date_to', 'period', 'type']));

        return $this->success($this->payload($expertId) + ['result' => ['dates' => $count]], 201);
    }

    /**
     * DELETE /booking/me/exceptions/{exception}
     */
    public function destroyException(Request $request, ExpertAvailabilityException $exception): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $this->assertOwned($exception->expert_id, $expertId);

        $this->setup->deleteException($exception);

        return $this->success($this->payload($expertId));
    }

    /* ===================== OUTILS ===================== */

    /** Enveloppe commune des API de l'application : { success, data }. */
    private function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    private function expertId(Request $request): ?int
    {
        $id = DB::table('experts')->where('user_id', $request->user()->id)->value('id');

        return $id ? (int) $id : null;
    }

    private function requireExpert(Request $request): int
    {
        $expertId = $this->expertId($request);
        abort_unless($expertId, 403, "Ce compte n'est pas un compte expert.");

        return $expertId;
    }

    /** 404 plutôt que 403 : ne pas confirmer l'existence d'un élément d'un autre expert. */
    private function assertOwned(int $ownerId, int $expertId): void
    {
        abort_unless($ownerId === $expertId, 404);
    }

    /**
     * Forme unique de l'état renvoyé à l'application.
     * Les champs « own » valent null quand l'expert suit la valeur système ;
     * « resolved » donne la valeur effective à afficher.
     */
    private function payload(int $expertId): array
    {
        $own      = ExpertBookingSetting::where('expert_id', $expertId)->first();
        $resolved = $this->setup->resolved($expertId);
        $today    = Carbon::now($resolved['timezone'])->toDateString();
        $time     = fn (?string $value) => $value ? substr($value, 0, 5) : null;

        $offers = ExpertOffer::where('expert_id', $expertId)
            ->orderBy('order_dsp')
            ->orderBy('id')
            ->get()
            ->map(fn (ExpertOffer $offer) => BookingExpertController::offerPayload($offer) + [
                'active_flg' => $offer->active_flg,
                'order_dsp'  => $offer->order_dsp,
            ])
            ->values();

        $rules = ExpertAvailabilityRule::where('expert_id', $expertId)
            ->orderBy('weekday')
            ->orderByRaw("FIELD(period, 'morning', 'afternoon')")
            ->orderBy('valid_from')
            ->get()
            ->map(fn (ExpertAvailabilityRule $rule) => [
                'id'          => $rule->id,
                'weekday'     => $rule->weekday,
                'period'      => $rule->period,
                'valid_from'  => $rule->valid_from?->toDateString(),
                'valid_until' => $rule->valid_until?->toDateString(),
                'expired'     => $rule->valid_until !== null && $rule->valid_until->toDateString() < $today,
            ])
            ->values();

        // Les exceptions passées n'ont plus d'effet.
        $exceptions = ExpertAvailabilityException::where('expert_id', $expertId)
            ->where('date', '>=', $today)
            ->orderBy('date')
            ->orderByRaw('period IS NOT NULL')
            ->orderByRaw("FIELD(period, 'morning', 'afternoon')")
            ->get()
            ->map(fn (ExpertAvailabilityException $exception) => [
                'id'     => $exception->id,
                'date'   => $exception->date->toDateString(),
                'period' => $exception->period,
                'type'   => $exception->type,
            ])
            ->values();

        return [
            'is_expert' => true,
            'expert_id' => $expertId,
            // Profil affiché en tête de l'onglet Expertise, même forme que côté utilisateur.
            'expert'    => BookingExpertController::expertPayload(Expert::with('domains')->findOrFail($expertId)),
            'settings'  => [
                'own' => [
                    'min_notice_hours'   => $own?->min_notice_hours,
                    'max_advance_months' => $own?->max_advance_months,
                    'morning_start'      => $time($own?->morning_start),
                    'morning_end'        => $time($own?->morning_end),
                    'afternoon_start'    => $time($own?->afternoon_start),
                    'afternoon_end'      => $time($own?->afternoon_end),
                ],
                'resolved' => $resolved,
                'defaults' => ExpertBookingSetupService::systemDefaults(),
            ],
            // Bornes de saisie, pour que l'application valide avant l'envoi avec les mêmes règles.
            'limits' => [
                'block_min_minutes'    => ExpertBookingSetupService::BLOCK_MIN_MINUTES,
                'block_max_minutes'    => ExpertBookingSetupService::BLOCK_MAX_MINUTES,
                'max_notice_hours'     => ExpertBookingSetupService::MAX_NOTICE_HOURS,
                'max_advance_months'   => ExpertBookingSetupService::MAX_ADVANCE_MONTHS,
                'max_blocks_per_offer' => ExpertBookingSetupService::MAX_BLOCKS_PER_OFFER,
                'max_exception_days'   => ExpertBookingSetupService::MAX_EXCEPTION_RANGE_DAYS,
            ],
            'offers'     => $offers,
            'rules'      => $rules,
            'exceptions' => $exceptions,
        ];
    }
}