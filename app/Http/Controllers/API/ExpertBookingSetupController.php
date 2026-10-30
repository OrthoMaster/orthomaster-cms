<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Expert;
use App\Models\ExpertAvailabilityException;
use App\Models\ExpertAvailabilityRule;
use App\Models\ExpertBookingSetting;
use App\Models\ExpertLocation;
use App\Models\ExpertOffer;
use App\Models\ExpertOfferDate;
use App\Models\ExpertOfferMode;
use App\Models\ExpertOfferSlot;
use App\Support\Brochures\BrochureDraft;
use App\Models\ExpertOfferImage;
use App\Services\Booking\ExpertOfferImageService;
use App\Services\Booking\ExpertProfileService;
use App\Services\Booking\ExpertBookingSetupService;
use App\Services\Booking\ExpertSlotService;
use App\Services\Google\GooglePlacesService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * API de l'application : l'expert configure ses propres rendez-vous.
 *
 * Chaque écriture renvoie l'état complet dans data (même forme que GET /booking/me) :
 * l'application remplace son état local d'un coup, sans recharger ni recalculer.
 * Les erreurs de validation viennent du service et sortent en 422.
 */
class ExpertBookingSetupController extends Controller
{
    /** Jeton de session Places généré par l'application à chaque recherche. */
    private const PLACES_SESSION_PATTERN = '/^[A-Za-z0-9-]{8,64}$/';
    private const PLACE_ID_PATTERN       = '/^[A-Za-z0-9_-]{10,300}$/';

    public function __construct(
        private ExpertBookingSetupService $setup,
        private ExpertSlotService $slots,
        private ExpertOfferImageService $images,
        private ExpertProfileService $profile,
        private GooglePlacesService $places,
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
     * Aperçu tel que les utilisateurs le verront, même désactivé :
     * grille générale, ou calendrier d'une offre dans un mode.
     * GET /booking/me/preview?offer_id=&mode=&from=Y-m-d&to=Y-m-d
     */
    public function preview(Request $request): JsonResponse
    {
        $expertId = $this->requireExpert($request);

        $request->validate([
            'offer_id' => ['nullable', 'integer'],
            'mode'     => ['required_with:offer_id', Rule::in(array_keys(ExpertOfferMode::MODE_LABELS))],
            'from'     => ['nullable', 'date_format:Y-m-d'],
            'to'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $offerMode = null;
        if ($request->filled('offer_id')) {
            $offer     = ExpertOffer::where('expert_id', $expertId)->with('modes')->findOrFail($request->input('offer_id'));
            $offerMode = $offer->modes->firstWhere('mode', $request->input('mode'));
            abort_unless($offerMode, 404);
        }

        $blocks = $this->slots->availableBlocks($expertId, $request->input('from'), $request->input('to'), true, $offerMode);
        $bounds = $this->slots->bounds($expertId);

        return $this->success(BookingExpertController::calendarPayload($bounds, $blocks));
    }

    /**
     * Demi-journées proposables pour le calendrier « Dates précises » d'un mode :
     * jusqu'à la limite de réservation, dates fermées exclues.
     * GET /booking/me/grid-dates?mode=online|onsite
     */
    public function gridDates(Request $request): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $request->validate(['mode' => ['required', Rule::in(array_keys(ExpertOfferMode::MODE_LABELS))]]);

        $dates = collect($this->setup->gridDates($expertId, $request->input('mode')))
            ->map(function (?string $label, string $key) {
                [$date, $period] = explode('|', $key);

                return ['date' => $date, 'period' => $period, 'location_label' => $label];
            })
            ->values();

        return $this->success(['dates' => $dates]);
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
            'min_notice_days'    => $own?->min_notice_days,
            'max_advance_months' => $own?->max_advance_months,
            'morning_start'      => $time($own?->morning_start),
            'morning_end'        => $time($own?->morning_end),
            'afternoon_start'    => $time($own?->afternoon_start),
            'afternoon_end'      => $time($own?->afternoon_end),
        ];

        $this->setup->saveSettings($expertId, array_merge($current, $request->only([
            'enabled_flg',
            'timezone',
            'min_notice_days',
            'max_advance_months',
            'morning_start',
            'morning_end',
            'afternoon_start',
            'afternoon_end',
        ])));

        return $this->success($this->payload($expertId));
    }

    /* ===================== LIEUX ===================== */

    /**
     * Nouveau lieu à partir d'un résultat Google : le serveur relit l'adresse chez Google,
     * l'application n'envoie jamais d'adresse saisie à la main.
     * Le lieu créé accompagne l'état (result.location_id), pour le sélectionner aussitôt.
     * POST /booking/me/locations
     * Corps : place_id, session?, label, access_details?
     */
    public function storeLocation(Request $request): JsonResponse
    {
        $expertId = $this->requireExpert($request);

        $placeId = (string) $request->input('place_id');
        if (preg_match(self::PLACE_ID_PATTERN, $placeId) !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Choisissez une adresse dans la liste.',
                'errors'  => ['place_id' => ['Choisissez une adresse dans la liste.']],
            ], 422);
        }

        try {
            $place = $this->places->details($placeId, $this->placesSession($request));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        }

        $location = $this->setup->saveLocation($expertId, [
            'label'           => $request->input('label'),
            'access_details'  => $request->input('access_details'),
            'address_line'    => $place['address_line'],
            'postal_code'     => $place['postal_code'],
            'city'            => $place['city'],
            'country'         => $place['country'],
            'lat'             => $place['lat'],
            'lng'             => $place['lng'],
            'google_place_id' => $place['google_place_id'],
        ]);

        return $this->success($this->payload($expertId) + ['result' => ['location_id' => $location->id]], 201);
    }

    /**
     * Seuls le nom et les informations d'accès se modifient ; l'adresse reste celle de Google.
     * PUT /booking/me/locations/{location}
     * Corps : label, access_details?
     */
    public function updateLocation(Request $request, ExpertLocation $location): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $this->assertOwned($location->expert_id, $expertId);

        $this->setup->updateLocationDetails($location, $request->only(['label', 'access_details']));

        return $this->success($this->payload($expertId));
    }

    /**
     * Le nombre de demi-journées détachées accompagne l'état, pour le toast.
     * POST /booking/me/locations/{location}/archive
     */
    public function archiveLocation(Request $request, ExpertLocation $location): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $this->assertOwned($location->expert_id, $expertId);

        $detached = $this->setup->archiveLocation($location);

        return $this->success($this->payload($expertId) + ['result' => ['detached_rules' => $detached]]);
    }

    /**
     * POST /booking/me/locations/{location}/restore
     */
    public function restoreLocation(Request $request, ExpertLocation $location): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $this->assertOwned($location->expert_id, $expertId);

        $this->setup->restoreLocation($location);

        return $this->success($this->payload($expertId));
    }

    /* ===================== RECHERCHE D'ADRESSE ===================== */
    // Relais Google Places : la clé reste sur le serveur, l'application envoie un jeton de session.

    /**
     * GET /booking/me/places/autocomplete?q=&session=
     */
    public function placesAutocomplete(Request $request): JsonResponse
    {
        $this->requireExpert($request);

        try {
            $suggestions = $this->places->autocomplete(
                mb_substr((string) $request->input('q'), 0, 200),
                $this->placesSession($request)
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        }

        return $this->success(['suggestions' => $suggestions]);
    }

    /**
     * Adresse découpée comme un lieu ; clôt la session de recherche.
     * GET /booking/me/places/{placeId}?session=
     */
    public function placeDetails(Request $request, string $placeId): JsonResponse
    {
        $this->requireExpert($request);
        abort_unless(preg_match(self::PLACE_ID_PATTERN, $placeId) === 1, 404);

        try {
            $place = $this->places->details($placeId, $this->placesSession($request));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        }

        return $this->success(['place' => $place]);
    }

    private function placesSession(Request $request): ?string
    {
        $token = (string) $request->input('session');

        return preg_match(self::PLACES_SESSION_PATTERN, $token) === 1 ? $token : null;
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

    /* ===================== PROFIL ===================== */

    /**
     * PUT /booking/me/profile
     * Corps : honorific, name, credentials, lede, palette, domain_ids[]
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $expertId = $this->requireExpert($request);

        $this->profile->update(
            Expert::findOrFail($expertId),
            $request->only(['honorific', 'name', 'credentials', 'lede', 'palette', 'domain_ids'])
        );

        return $this->success($this->payload($expertId));
    }

    /**
     * Nouvelle photo de profil (version web de l'application : sélecteur de fichier du navigateur).
     * POST /booking/me/avatar  (multipart, champ « avatar »)
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $expertId = $this->requireExpert($request);

        $this->profile->updateAvatar(Expert::findOrFail($expertId), $request->file('avatar'));

        return $this->success($this->payload($expertId));
    }

    /**
     * Ajoute un domaine à la liste commune ; renvoie l'existant si le libellé est déjà pris.
     * POST /booking/me/domains
     * Corps : label
     */
    public function storeDomain(Request $request): JsonResponse
    {
        $this->requireExpert($request);
        $domain = $this->profile->createDomain($request->input('label'));

        return $this->success(['domain' => ['id' => $domain->id, 'name' => $domain->label]], 201);
    }

    /* ===================== IMAGES D'UN THÈME ===================== */
    // L'ajout se fait depuis le CMS ; l'expert légende, réordonne et supprime.

    /**
     * PUT /booking/me/offers/{offer}/images/order
     * Corps : ids[] dans le nouvel ordre
     */
    public function reorderOfferImages(Request $request, ExpertOffer $offer): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $this->assertOwned($offer->expert_id, $expertId);

        $this->images->reorder($offer, (array) $request->input('ids', []));

        return $this->success($this->payload($expertId));
    }

    /**
     * PUT /booking/me/offers/{offer}/images/{image}
     * Corps : caption
     */
    public function updateOfferImage(Request $request, ExpertOffer $offer, ExpertOfferImage $image): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $this->assertOwned($offer->expert_id, $expertId);
        abort_unless($image->offer_id === $offer->id, 404);

        $this->images->updateCaption($image, $request->input('caption'));

        return $this->success($this->payload($expertId));
    }

    /**
     * DELETE /booking/me/offers/{offer}/images/{image}
     */
    public function destroyOfferImage(Request $request, ExpertOffer $offer, ExpertOfferImage $image): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $this->assertOwned($offer->expert_id, $expertId);
        abort_unless($image->offer_id === $offer->id, 404);

        $this->images->delete($image);

        return $this->success($this->payload($expertId));
    }

    /**
     * Corps : title, description, blocks_count, order_dsp, active_flg,
     * modes { online|onsite: { enabled, price, schedule_type, book_ahead_days, slots[], dates[] } }
     * (même forme que modes_config renvoyé dans l'état), et pour le présentiel
     * modes.onsite.locations { « jour|période »: location_id } : lieu des demi-journées
     * de la grille qui n'en ont pas encore, fixé dans la grille à l'enregistrement.
     */
    private function offerInput(Request $request): array
    {
        $modes = [];
        foreach (array_keys(ExpertOfferMode::MODE_LABELS) as $mode) {
            $raw = (array) $request->input("modes.$mode", []);
            $raw['enabled'] = filter_var($raw['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (isset($raw['price']) && is_string($raw['price'])) {
                $raw['price'] = str_replace([' ', ','], ['', '.'], $raw['price']);
            }
            $modes[$mode] = $raw;
        }

        // L'application envoie toujours active_flg ; true par défaut pour une création sans le champ.
        return $request->only(['title', 'description', 'blocks_count', 'order_dsp']) + [
            'active_flg' => $request->has('active_flg') ? $request->boolean('active_flg') : true,
            'modes'      => $modes,
        ];
    }

    /* ===================== DISPONIBILITÉS ===================== */

    /**
     * Le résultat (créés / ignorés) accompagne l'état, pour le toast de l'application.
     * POST /booking/me/rules
     * Corps : weekdays[], periods[], location_id?, valid_from?, valid_until?
     */
    public function storeRules(Request $request): JsonResponse
    {
        $expertId = $this->requireExpert($request);

        $result = $this->setup->addRules(
            $expertId,
            $request->only(['weekdays', 'periods', 'location_id', 'valid_from', 'valid_until'])
        );

        return $this->success($this->payload($expertId) + ['result' => $result], 201);
    }

    /**
     * Lieu d'une demi-journée de la grille ; location_id null = en ligne seulement.
     * Refusé (422) si une offre en présentiel en dépend.
     * PUT /booking/me/rules/{rule}/location
     */
    public function updateRuleLocation(Request $request, ExpertAvailabilityRule $rule): JsonResponse
    {
        $expertId = $this->requireExpert($request);
        $this->assertOwned($rule->expert_id, $expertId);

        $this->setup->setRuleLocation($rule, $request->filled('location_id') ? (int) $request->input('location_id') : null);

        return $this->success($this->payload($expertId));
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

        // « Fermer les réservations » reprend par défaut le délai minimum de l'expert.
        $defaultAhead = max(ExpertBookingSetupService::MIN_BOOK_AHEAD_DAYS, (int) $resolved['min_notice_days']);

        $offerModels = ExpertOffer::where('expert_id', $expertId)
            ->with(['images', 'modes'])
            ->orderBy('order_dsp')
            ->orderBy('id')
            ->get();

        // Calendriers de toutes les offres en deux requêtes, indexés « offre|mode ».
        $offerIds   = $offerModels->pluck('id');
        $offerSlots = ExpertOfferSlot::whereIn('offer_id', $offerIds)->get()
            ->groupBy(fn ($s) => $s->offer_id . '|' . $s->mode)
            ->map(fn ($group) => $group->pluck('key_string')->values()->all());
        $offerDates = ExpertOfferDate::whereIn('offer_id', $offerIds)->where('date', '>=', $today)
            ->orderBy('date')->orderByRaw("FIELD(period, 'morning', 'afternoon')")->get()
            ->groupBy(fn ($d) => $d->offer_id . '|' . $d->mode)
            ->map(fn ($group) => $group->pluck('key_string')->values()->all());

        $offers = $offerModels
            ->map(fn (ExpertOffer $offer) => BookingExpertController::offerPayload($offer) + [
                'active_flg'   => $offer->active_flg,
                'order_dsp'    => $offer->order_dsp,
                // Configuration complète des deux modes, actifs ou non : pré-remplit le formulaire
                // et se renvoie telle quelle dans « modes » à l'enregistrement.
                'modes_config' => collect(ExpertOfferMode::MODE_LABELS)->map(function ($label, $mode) use ($offer, $offerSlots, $offerDates, $defaultAhead) {
                    $m = $offer->modes->firstWhere('mode', $mode);

                    return [
                        'enabled'         => (bool) $m?->active_flg,
                        'price'           => $m ? (float) $m->price : null,
                        'schedule_type'   => $m->schedule_type ?? ExpertOfferMode::SCHEDULE_ALL,
                        'book_ahead_days' => $m?->book_ahead_days ?? $defaultAhead,
                        'slots'           => $offerSlots->get($offer->id . '|' . $mode, []),
                        'dates'           => $offerDates->get($offer->id . '|' . $mode, []),
                    ];
                }),
            ])
            ->values();

        $locationModels = ExpertLocation::where('expert_id', $expertId)
            ->withCount('rules')
            ->orderByRaw('archived_at IS NOT NULL')
            ->orderBy('label')
            ->get();
        $locked = $this->setup->lockedLocationIds($locationModels->pluck('id')->all());

        $locations = $locationModels
            ->map(fn (ExpertLocation $l) => $l->publicPayload() + [
                'google_place_id' => $l->google_place_id,
                'access_details'  => $l->access_details,
                'archived'        => $l->archived_at !== null,
                'rules_count'     => $l->rules_count,
                // Adresse figée : des rendez-vous en attente ou à venir s'y tiennent.
                'address_locked'  => in_array($l->id, $locked, true),
            ])
            ->values();

        $rules = ExpertAvailabilityRule::where('expert_id', $expertId)
            ->with('location')
            ->orderBy('weekday')
            ->orderByRaw("FIELD(period, 'morning', 'afternoon')")
            ->orderBy('valid_from')
            ->get()
            ->map(fn (ExpertAvailabilityRule $rule) => [
                'id'             => $rule->id,
                'weekday'        => $rule->weekday,
                'period'         => $rule->period,
                'location_id'    => $rule->location_id,
                'location_label' => $rule->location?->label,
                'valid_from'     => $rule->valid_from?->toDateString(),
                'valid_until'    => $rule->valid_until?->toDateString(),
                'expired'        => $rule->valid_until !== null && $rule->valid_until->toDateString() < $today,
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

        // Demi-journées de la grille utilisables par mode (« jour|période » => nom du lieu).
        $grid = collect($this->setup->gridSlots($expertId))
            ->map(fn (array $slots) => collect($slots)->map(function (?string $label, string $key) {
                [$weekday, $period] = explode('|', $key);

                return ['weekday' => (int) $weekday, 'period' => $period, 'location_label' => $label];
            })->values());

        return [
            'is_expert' => true,
            'expert_id' => $expertId,
            // Profil affiché en tête de l'onglet Expertise, même forme que côté utilisateur.
            'expert'    => BookingExpertController::expertPayload(Expert::with('domains')->findOrFail($expertId)),
            'settings'  => [
                'own' => [
                    'min_notice_days'    => $own?->min_notice_days,
                    'max_advance_months' => $own?->max_advance_months,
                    'morning_start'      => $time($own?->morning_start),
                    'morning_end'        => $time($own?->morning_end),
                    'afternoon_start'    => $time($own?->afternoon_start),
                    'afternoon_end'      => $time($own?->afternoon_end),
                ],
                'resolved' => $resolved,
                'defaults' => ExpertBookingSetupService::systemDefaults(),
            ],
            // Palettes proposées dans le profil : clé, nom, couleur.
            'palettes' => collect(BrochureDraft::PALETTES)
                ->map(fn ($p, $key) => ['key' => $key, 'name' => $p[0], 'color' => $p[1]])
                ->values(),
            // Libellés des modes et des calendriers, pour ne pas les dupliquer dans l'application.
            'modes'          => collect(ExpertOfferMode::MODE_LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'schedule_types' => collect(ExpertOfferMode::SCHEDULE_LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            // Bornes de saisie, pour que l'application valide avant l'envoi avec les mêmes règles.
            'limits' => [
                'block_min_minutes'    => ExpertBookingSetupService::BLOCK_MIN_MINUTES,
                'block_max_minutes'    => ExpertBookingSetupService::BLOCK_MAX_MINUTES,
                'max_notice_days'      => ExpertBookingSetupService::MAX_NOTICE_DAYS,
                'max_advance_months'   => ExpertBookingSetupService::MAX_ADVANCE_MONTHS,
                'max_blocks_per_offer' => ExpertBookingSetupService::MAX_BLOCKS_PER_OFFER,
                'max_offer_images'     => ExpertOfferImageService::MAX_IMAGES,
                'image_caption_max'    => ExpertOfferImageService::CAPTION_MAX,
                'profile'              => [
                    'honorific_max'   => ExpertProfileService::HONORIFIC_MAX,
                    'honorifics'      => ExpertProfileService::honorificOptions(),
                    'name_max'        => ExpertProfileService::NAME_MAX,
                    'credentials_max' => ExpertProfileService::CREDENTIALS_MAX,
                    'lede_max'        => ExpertProfileService::LEDE_MAX,
                    'domain_max'      => ExpertProfileService::DOMAIN_MAX,
                    'avatar_max_kb'   => ExpertProfileService::AVATAR_MAX_KB,
                    'avatar_min_edge' => ExpertProfileService::AVATAR_MIN_EDGE,
                ],
                'max_exception_days'   => ExpertBookingSetupService::MAX_EXCEPTION_RANGE_DAYS,
                'book_ahead'           => [
                    'min'     => ExpertBookingSetupService::MIN_BOOK_AHEAD_DAYS,
                    'max'     => ExpertBookingSetupService::MAX_BOOK_AHEAD_DAYS,
                    'default' => $defaultAhead,
                ],
                'max_offer_dates'      => ExpertBookingSetupService::MAX_OFFER_DATES,
                'location'             => ['label_max' => 60, 'access_max' => 1000],
                'places'               => [
                    'enabled'   => GooglePlacesService::enabled(),
                    'min_input' => GooglePlacesService::MIN_INPUT_LENGTH,
                ],
            ],
            'offers'     => $offers,
            'locations'  => $locations,
            'grid'       => $grid,
            'rules'      => $rules,
            'exceptions' => $exceptions,
        ];
    }
}