<?php

namespace App\Services\Booking;

use App\Models\BookingBlock;
use App\Models\ExpertAvailabilityException;
use App\Models\ExpertAvailabilityRule;
use App\Models\ExpertBookingSetting;
use App\Models\ExpertLocation;
use App\Models\ExpertOffer;
use App\Models\ExpertOfferDate;
use App\Models\ExpertOfferMode;
use App\Models\ExpertOfferSlot;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Configuration des rendez-vous d'un expert : réglages, lieux, offres, disponibilités.
 *
 * Partagé entre le CMS (admin) et l'API de l'application (expert) : la validation
 * vit ici pour que les deux interfaces appliquent exactement les mêmes règles.
 * Les erreurs remontent en ValidationException, que Laravel convertit en
 * redirection avec erreurs côté web et en 422 JSON côté API.
 *
 * Règle 2b : une modification de la grille qui retirerait une demi-journée dont une offre
 * active a besoin (« Certaines demi-journées » ou « Dates précises ») est refusée ;
 * l'expert doit d'abord modifier l'offre.
 *
 * Règle 2a : une demi-journée ajoutée à la main (exception « add ») ne sert qu'aux
 * « Dates précises » en ligne : l'expert ouvre un jour spécial puis y propose une offre.
 * Elle n'entre pas dans « Toutes / Certaines demi-journées », et n'a pas de lieu
 * (le présentiel demande une demi-journée de la grille avec lieu).
 */
class ExpertBookingSetupService
{
    /** Bornes d'un bloc (demi-journée), en minutes : décision produit. */
    public const BLOCK_MIN_MINUTES = 180;
    public const BLOCK_MAX_MINUTES = 300;

    /** Repli si un réglage Voyager est absent ou vidé par erreur. */
    public const FALLBACK_DEFAULTS = [
        'morning_start'      => '09:00',
        'morning_end'        => '12:00',
        'afternoon_start'    => '14:00',
        'afternoon_end'      => '17:00',
        'min_notice_days'    => 1,
        'max_advance_months' => 6,
    ];

    public const DEFAULT_TIMEZONE = 'Europe/Paris';

    public const PERIODS = ['morning', 'afternoon'];

    /** Plafonds de saisie, pour éviter des valeurs absurdes. */
    public const MAX_NOTICE_DAYS      = 30;
    public const MAX_ADVANCE_MONTHS   = 12;
    public const MAX_BLOCKS_PER_OFFER = 20;
    /** « Dates précises » : une date ferme au moins la veille, pour que l'expert ne soit jamais pris de court. */
    public const MIN_BOOK_AHEAD_DAYS  = 1;
    public const MAX_BOOK_AHEAD_DAYS  = 90;
    public const MAX_OFFER_DATES      = 200;

    /** Une saisie d'exceptions couvre au plus un an : évite des milliers de lignes sur une faute de frappe. */
    public const MAX_EXCEPTION_RANGE_DAYS = 366;

    private const TIME_KEYS = ['morning_start', 'morning_end', 'afternoon_start', 'afternoon_end'];

    /** Libellés français pour les messages de validation. */
    private const ATTRIBUTES = [
        'enabled_flg'        => 'activation',
        'timezone'           => 'fuseau horaire',
        'min_notice_days'    => 'délai minimum',
        'max_advance_months' => 'réservation au plus tard',
        'morning_start'      => 'début du matin',
        'morning_end'        => 'fin du matin',
        'afternoon_start'    => "début de l'après-midi",
        'afternoon_end'      => "fin de l'après-midi",
        'title'              => 'titre',
        'description'        => 'description',
        'blocks_count'       => 'durée',
        'active_flg'         => 'statut',
        'order_dsp'          => 'ordre',
        'weekdays'           => 'jours',
        'periods'            => 'demi-journées',
        'valid_from'         => 'date de début',
        'valid_until'        => 'date de fin',
        'date_from'          => 'date de début',
        'date_to'            => 'date de fin',
        'period'             => 'demi-journée',
        'type'               => 'type',
        'location_id'        => 'lieu',
        'label'              => 'nom du lieu',
        'address_line'       => 'adresse',
        'postal_code'        => 'code postal',
        'city'               => 'ville',
        'country'            => 'pays',
        'lat'                => 'latitude',
        'lng'                => 'longitude',
        'access_details'     => "informations d'accès",
    ];

    /* ===================== VALEURS PAR DÉFAUT ===================== */

    /**
     * Valeurs système lues dans les réglages Voyager (groupe Booking).
     */
    public static function systemDefaults(): array
    {
        $defaults = [];
        foreach (self::FALLBACK_DEFAULTS as $key => $fallback) {
            $value = setting('booking.default_' . $key);
            $defaults[$key] = ($value === null || $value === '') ? $fallback : $value;
        }

        $defaults['min_notice_days']    = (int) $defaults['min_notice_days'];
        $defaults['max_advance_months'] = (int) $defaults['max_advance_months'];

        return $defaults;
    }

    /**
     * Réglages effectifs d'un expert : ses valeurs propres, sinon celles du système.
     * Les heures sont toujours au format HH:MM.
     */
    public function resolved(int $expertId): array
    {
        $setting  = ExpertBookingSetting::where('expert_id', $expertId)->first();
        $defaults = self::systemDefaults();

        $resolved = [
            'enabled_flg' => (bool) ($setting->enabled_flg ?? false),
            'timezone'    => $setting->timezone ?? self::DEFAULT_TIMEZONE,
        ];

        foreach (array_keys(self::FALLBACK_DEFAULTS) as $key) {
            $own            = $setting?->{$key};
            $resolved[$key] = $own === null ? $defaults[$key] : $own;
        }

        // Les colonnes TIME reviennent en HH:MM:SS ; on aligne sur le format de saisie.
        foreach (self::TIME_KEYS as $key) {
            $resolved[$key] = substr((string) $resolved[$key], 0, 5);
        }

        return $resolved;
    }

    /* ===================== RÉGLAGES ===================== */

    public function saveSettings(int $expertId, array $input): ExpertBookingSetting
    {
        $data = Validator::make($input, [
            'enabled_flg'        => ['required', 'boolean'],
            'timezone'           => ['required', 'timezone:all'],
            'min_notice_days'    => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_NOTICE_DAYS],
            'max_advance_months' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_ADVANCE_MONTHS],
            'morning_start'      => ['nullable', 'date_format:H:i'],
            'morning_end'        => ['nullable', 'date_format:H:i'],
            'afternoon_start'    => ['nullable', 'date_format:H:i'],
            'afternoon_end'      => ['nullable', 'date_format:H:i'],
        ], [], self::ATTRIBUTES)->validate();

        // Contrôle sur les valeurs effectives : un expert peut ne personnaliser qu'une
        // borne, l'autre venant du système, et c'est la combinaison qui doit être valide.
        $defaults = self::systemDefaults();
        $times    = [];
        foreach (self::TIME_KEYS as $key) {
            $times[$key] = $data[$key] ?? $defaults[$key];
        }
        $this->assertBlocks($times);

        return ExpertBookingSetting::updateOrCreate(
            ['expert_id' => $expertId],
            [
                'enabled_flg'        => (bool) $data['enabled_flg'],
                'timezone'           => $data['timezone'],
                'min_notice_days'    => $data['min_notice_days'] ?? null,
                'max_advance_months' => $data['max_advance_months'] ?? null,
                'morning_start'      => $data['morning_start'] ?? null,
                'morning_end'        => $data['morning_end'] ?? null,
                'afternoon_start'    => $data['afternoon_start'] ?? null,
                'afternoon_end'      => $data['afternoon_end'] ?? null,
            ]
        );
    }

    private function assertBlocks(array $times): void
    {
        $minutes = fn (string $hm) => (int) substr($hm, 0, 2) * 60 + (int) substr($hm, 3, 2);
        $errors  = [];

        $blocks = [
            'morning'   => ['du matin', $times['morning_start'], $times['morning_end']],
            'afternoon' => ["de l'après-midi", $times['afternoon_start'], $times['afternoon_end']],
        ];

        foreach ($blocks as $period => [$label, $start, $end]) {
            $length = $minutes($end) - $minutes($start);
            if ($length < self::BLOCK_MIN_MINUTES || $length > self::BLOCK_MAX_MINUTES) {
                $errors[$period . '_end'] = sprintf(
                    'Le bloc %s doit durer entre %d et %d heures.',
                    $label,
                    self::BLOCK_MIN_MINUTES / 60,
                    self::BLOCK_MAX_MINUTES / 60
                );
            }
        }

        if ($minutes($times['morning_end']) > $minutes($times['afternoon_start'])) {
            $errors['afternoon_start'] = "L'après-midi doit commencer après la fin du matin.";
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /* ===================== LIEUX ===================== */

    public function saveLocation(int $expertId, array $input, ?ExpertLocation $location = null): ExpertLocation
    {
        $data = Validator::make($input, [
            'label'           => ['required', 'string', 'max:60'],
            'address_line'    => ['required', 'string', 'max:255'],
            'postal_code'     => ['nullable', 'string', 'max:20'],
            'city'            => ['required', 'string', 'max:120'],
            'country'         => ['nullable', 'string', 'size:2'],
            'lat'             => ['nullable', 'numeric', 'between:-90,90'],
            'lng'             => ['nullable', 'numeric', 'between:-180,180'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'access_details'  => ['nullable', 'string', 'max:1000'],
        ], [], self::ATTRIBUTES)->validate();

        $data['country'] = strtoupper($data['country'] ?? 'FR');

        // Un rendez-vous en attente ou à venir a été pris à cette adresse : on ne la déplace pas
        // sous les pieds du client. Le nom et les informations d'accès restent modifiables.
        if ($location?->exists && $this->addressChanged($location, $data) && $this->locationInUse($location->id)) {
            throw ValidationException::withMessages([
                'address_line' => "Des rendez-vous sont en attente ou à venir à cette adresse : elle ne peut plus être modifiée. "
                    . "Créez un nouveau lieu et rattachez-le aux demi-journées concernées.",
            ]);
        }

        $location ??= new ExpertLocation(['expert_id' => $expertId]);
        $location->fill($data);
        $location->save();

        return $location;
    }

    /**
     * Archive le lieu et retire son adresse des demi-journées qui l'utilisaient
     * (elles restent ouvertes en ligne). Refusé si une offre en présentiel en dépend.
     */
    public function archiveLocation(ExpertLocation $location): int
    {
        $rulesAfter = $this->rulesOf($location->expert_id)->map(
            fn (ExpertAvailabilityRule $r) => $r->location_id === $location->id
                ? tap(clone $r)->setAttribute('location_id', null)
                : $r
        );

        $this->assertNoConflicts(
            $this->offerConflicts($location->expert_id, $rulesAfter, [$location->id]),
            'location'
        );

        return DB::transaction(function () use ($location) {
            $detached = ExpertAvailabilityRule::where('location_id', $location->id)->update(['location_id' => null]);
            $location->update(['archived_at' => now()]);

            return $detached;
        });
    }

    /**
     * Lieux portant une demi-journée retenue (demande en attente ou acceptée) pas encore terminée.
     *
     * @return array<int, int>
     */
    public function lockedLocationIds(array $locationIds): array
    {
        if (!$locationIds) {
            return [];
        }

        return BookingBlock::whereIn('location_id', $locationIds)
            ->whereNotNull('hold_key')
            ->where('end_at', '>', now())
            ->distinct()
            ->pluck('location_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function locationInUse(int $locationId): bool
    {
        return $this->lockedLocationIds([$locationId]) !== [];
    }

    /** Adresse ou coordonnées modifiées ; le nom et les informations d'accès ne comptent pas. */
    private function addressChanged(ExpertLocation $location, array $data): bool
    {
        $text = fn ($v) => trim((string) $v);
        $geo  = fn ($v) => $v === null || $v === '' ? null : round((float) $v, 6);

        foreach (['address_line', 'postal_code', 'city', 'country', 'google_place_id'] as $key) {
            if ($text($location->{$key}) !== $text($data[$key] ?? null)) {
                return true;
            }
        }

        return $geo($location->lat) !== $geo($data['lat'] ?? null)
            || $geo($location->lng) !== $geo($data['lng'] ?? null);
    }

    public function restoreLocation(ExpertLocation $location): void
    {
        $location->update(['archived_at' => null]);
    }

    /* ===================== OFFRES ===================== */

    /**
     * $input['modes'] : [online|onsite => [enabled, price, schedule_type, book_ahead_days,
     *                    slots[] « jour|période », dates[] « Y-m-d|période »]]
     */
    public function saveOffer(int $expertId, array $input, ?ExpertOffer $offer = null): ExpertOffer
    {
        $data = Validator::make($input, [
            'title'        => ['required', 'string', 'max:150'],
            'description'  => ['nullable', 'string', 'max:5000'],
            'blocks_count' => ['required', 'integer', 'min:1', 'max:' . self::MAX_BLOCKS_PER_OFFER],
            'active_flg'   => ['required', 'boolean'],
            'order_dsp'    => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [], self::ATTRIBUTES)->validate();

        $modes = $this->validateModes($expertId, (array) ($input['modes'] ?? []));

        if ($data['active_flg'] && !array_filter($modes)) {
            throw ValidationException::withMessages([
                'modes' => 'Une offre active doit être proposée en ligne, en présentiel, ou les deux.',
            ]);
        }

        if (!$offer) {
            $offer            = new ExpertOffer();
            $offer->expert_id = $expertId;
            // Une nouvelle offre se place en dernier si aucun ordre n'est donné.
            $data['order_dsp'] ??= (int) ExpertOffer::where('expert_id', $expertId)->max('order_dsp') + 1;
        }

        $data['order_dsp'] ??= $offer->order_dsp;
        $today = $this->today($expertId);

        DB::transaction(function () use ($offer, $data, $modes, $today) {
            $offer->fill($data);
            $offer->save();

            foreach (array_keys(ExpertOfferMode::MODE_LABELS) as $mode) {
                $config = $modes[$mode];

                // Mode décoché : on garde prix et calendrier pour une réactivation.
                if ($config === null) {
                    ExpertOfferMode::where('offer_id', $offer->id)->where('mode', $mode)->update(['active_flg' => false]);
                    continue;
                }

                ExpertOfferMode::updateOrCreate(
                    ['offer_id' => $offer->id, 'mode' => $mode],
                    [
                        'price'           => $config['price'],
                        'schedule_type'   => $config['schedule_type'],
                        'book_ahead_days' => $config['book_ahead_days'],
                        'active_flg'      => true,
                    ]
                );

                $this->applyAssignments($offer->expert_id, $config['locations'] ?? [], $today);
                $this->syncSlots($offer->id, $mode, $config['slots']);
                $this->syncDates($offer->id, $mode, $config['dates'], $today);
            }
        });

        return $offer->load('modes');
    }

    /**
     * Valide la configuration de chaque mode. Un mode décoché vaut null.
     * Les erreurs portent le nom du champ du formulaire (modes.onsite.price…).
     *
     * @return array<string, array{price:float, schedule_type:string, book_ahead_days:?int, slots:array<int,string>, dates:array<int,string>}|null>
     */
    private function validateModes(int $expertId, array $input): array
    {
        $result = [];
        $errors = [];
        $grid   = $this->gridSlots($expertId);
        $today  = $this->today($expertId);

        foreach (ExpertOfferMode::MODE_LABELS as $mode => $label) {
            $raw = (array) ($input[$mode] ?? []);

            if (!filter_var($raw['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $result[$mode] = null;
                continue;
            }

            $suffix    = ' (' . mb_strtolower($label) . ')';
            $validator = Validator::make($raw, [
                'price'           => ['required', 'numeric', 'min:0', 'max:99999999.99'],
                'schedule_type'   => ['required', Rule::in(array_keys(ExpertOfferMode::SCHEDULE_LABELS))],
                'book_ahead_days' => ['nullable', 'integer', 'min:' . self::MIN_BOOK_AHEAD_DAYS, 'max:' . self::MAX_BOOK_AHEAD_DAYS],
                'slots'           => ['nullable', 'array'],
                'slots.*'         => ['string', 'regex:/^[1-7]\|(morning|afternoon)$/'],
                'dates'           => ['nullable', 'array', 'max:' . self::MAX_OFFER_DATES],
                'dates.*'         => ['string', 'regex:/^\d{4}-\d{2}-\d{2}\|(morning|afternoon)$/'],
            ], [], [
                'price'           => 'prix TTC' . $suffix,
                'schedule_type'   => 'calendrier' . $suffix,
                'book_ahead_days' => 'fermeture avant la date' . $suffix,
                'slots'           => 'demi-journées' . $suffix,
                'dates'           => 'dates' . $suffix,
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $key => $messages) {
                    $errors["modes.$mode.$key"] = $messages;
                }
                continue;
            }

            $data  = $validator->validated();
            $type  = $data['schedule_type'];
            $slots = array_values(array_unique($data['slots'] ?? []));
            $dates = array_values(array_unique($data['dates'] ?? []));

            // Présentiel depuis l'application : une demi-journée encore sans lieu reçoit son
            // lieu dans le formulaire de l'offre (« jour|période » => lieu). Il est ensuite fixé
            // dans la grille et repris par toutes les offres.
            $assign  = [];
            $modeGrid = $grid[$mode];
            if ($mode === ExpertOfferMode::ONSITE) {
                try {
                    $assign = $this->checkedAssignments($expertId, (array) ($raw['locations'] ?? []), $grid);
                } catch (ValidationException $e) {
                    $errors["modes.$mode.locations"] = $e->validator->errors()->all();
                    continue;
                }
                $modeGrid = $grid[ExpertOfferMode::ONSITE] + array_intersect_key($assign, $grid[ExpertOfferMode::ONLINE]);
            }

            if ($type === ExpertOfferMode::SCHEDULE_SELECTED) {
                $dates = [];
                if (!$slots) {
                    $errors["modes.$mode.slots"] = ['Choisissez au moins une demi-journée' . $suffix . '.'];
                }
                $missing = array_diff($slots, array_keys($modeGrid));
                if ($missing) {
                    $errors["modes.$mode.slots"] = [$this->notInGridMessage($mode, array_map(
                        fn ($k) => ExpertAvailabilityRule::slotLabel((int) explode('|', $k)[0], explode('|', $k)[1]),
                        $missing
                    ))];
                }
            } elseif ($type === ExpertOfferMode::SCHEDULE_DATES) {
                $slots = [];
                if (!$dates) {
                    $errors["modes.$mode.dates"] = ['Choisissez au moins une date' . $suffix . '.'];
                }
                $invalid = $this->invalidDates($expertId, $mode, $dates, $today, $assign);
                if ($invalid) {
                    $errors["modes.$mode.dates"] = [$this->notInGridMessage($mode, $invalid)];
                }
            } else {
                $slots = [];
                $dates = [];
                if (!$modeGrid) {
                    $errors["modes.$mode.schedule_type"] = [$mode === ExpertOfferMode::ONSITE
                        ? "Aucune demi-journée de vos disponibilités n'a de lieu : ajoutez-en un avant de proposer le présentiel."
                        : "Vous n'avez encore aucune disponibilité."];
                }
            }

            $result[$mode] = [
                'price'           => round((float) $data['price'], 2),
                'schedule_type'   => $type,
                'book_ahead_days' => $type === ExpertOfferMode::SCHEDULE_DATES
                    // Vide : on reprend le délai minimum de l'expert, comme le formulaire.
                    ? (int) ($data['book_ahead_days'] ?? max(self::MIN_BOOK_AHEAD_DAYS, (int) $this->resolved($expertId)['min_notice_days']))
                    : null,
                'slots'           => $slots,
                'dates'           => $dates,
                'locations'       => $assign,
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $result;
    }

    /**
     * Dates précises refusées : passées, hors de la grille (règle Q1), sans lieu en présentiel,
     * ou bloquées par une exception.
     *
     * @return array<int, string> libellés « 12/11/2026 matin »
     */
    private function invalidDates(int $expertId, string $mode, array $keys, string $today, array $assign = []): array
    {
        if (!$keys) {
            return [];
        }

        $rules      = $this->rulesOf($expertId);
        $locations  = $this->activeLocations($expertId);
        $exceptions = $this->exceptionsOf($expertId, $today);

        $invalid = [];
        foreach ($keys as $key) {
            [$date, $period] = explode('|', $key);

            // Une date sans lieu passe si le formulaire lui en attribue un (même demi-journée de la semaine).
            $assigned = isset($assign[Carbon::parse($date)->dayOfWeekIso . '|' . $period]);

            $ok = $date >= $today
                && ($this->dateOpen($rules, $exceptions, $date, $period, $mode, $locations)
                    || ($assigned
                        && !$this->dateClosed($exceptions, $date, $period)
                        && $this->ruleOn($rules, $date, $period, ExpertOfferMode::ONLINE, $locations) !== null));

            if (!$ok) {
                $invalid[] = self::dateLabel($date, $period);
            }
        }

        return $invalid;
    }

    /**
     * Lieux attribués dans le formulaire de l'offre : seulement des demi-journées existantes
     * de la grille, encore sans lieu (un lieu déjà fixé ne se change pas ici), et des lieux
     * actifs de l'expert.
     *
     * @return array<string, int> « jour|période » => id du lieu
     */
    private function checkedAssignments(int $expertId, array $input, array $grid): array
    {
        if (!$input) {
            return [];
        }

        $locations = $this->activeLocations($expertId);
        $assign    = [];
        $errors    = [];

        foreach ($input as $key => $locationId) {
            if ($locationId === null || $locationId === '') {
                continue;
            }
            if (!preg_match('/^[1-7]\|(morning|afternoon)$/', (string) $key) || !$locations->has((int) $locationId)) {
                $errors[] = "Un des lieux choisis n'existe plus. Rechargez l'écran.";
                continue;
            }
            // Déjà fixé dans la grille : on garde le lieu existant.
            if (isset($grid[ExpertOfferMode::ONSITE][$key])) {
                continue;
            }
            if (!array_key_exists($key, $grid[ExpertOfferMode::ONLINE])) {
                [$w, $p] = explode('|', $key);
                $errors[] = ExpertAvailabilityRule::slotLabel((int) $w, $p) . " ne fait pas partie de vos disponibilités.";
                continue;
            }
            $assign[$key] = (int) $locationId;
        }

        if ($errors) {
            throw ValidationException::withMessages(['locations' => array_values(array_unique($errors))]);
        }

        return $assign;
    }

    /**
     * Fixe le lieu des demi-journées de la grille qui n'en ont pas encore
     * (règles en cours ou à venir) ; un lieu déjà fixé n'est jamais remplacé ici.
     */
    private function applyAssignments(int $expertId, array $assign, string $today): void
    {
        foreach ($assign as $key => $locationId) {
            [$weekday, $period] = explode('|', $key);

            ExpertAvailabilityRule::where('expert_id', $expertId)
                ->where('weekday', (int) $weekday)
                ->where('period', $period)
                ->whereNull('location_id')
                ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
                ->update(['location_id' => $locationId]);
        }
    }

    /**
     * Depuis l'application : seuls le nom et les informations d'accès d'un lieu se modifient.
     * L'adresse vient de Google à la création et reste fixe ; pour une autre adresse, l'expert
     * crée un nouveau lieu.
     */
    public function updateLocationDetails(ExpertLocation $location, array $input): ExpertLocation
    {
        $data = Validator::make($input, [
            'label'          => ['required', 'string', 'max:60'],
            'access_details' => ['nullable', 'string', 'max:1000'],
        ], [], self::ATTRIBUTES)->validate();

        $location->update($data);

        return $location;
    }

    private function notInGridMessage(string $mode, array $labels): string
    {
        $where = $mode === ExpertOfferMode::ONSITE ? ' avec un lieu' : '';

        return "Ces demi-journées ne font pas partie des disponibilités{$where} : " . implode(', ', $labels) . '.';
    }

    private function syncSlots(int $offerId, string $mode, array $keys): void
    {
        ExpertOfferSlot::where('offer_id', $offerId)->where('mode', $mode)->delete();

        foreach ($keys as $key) {
            [$weekday, $period] = explode('|', $key);
            ExpertOfferSlot::create(['offer_id' => $offerId, 'mode' => $mode, 'weekday' => (int) $weekday, 'period' => $period]);
        }
    }

    /** Remplace les dates à venir ; les dates passées restent pour l'historique. */
    private function syncDates(int $offerId, string $mode, array $keys, string $today): void
    {
        ExpertOfferDate::where('offer_id', $offerId)->where('mode', $mode)->where('date', '>=', $today)->delete();

        foreach ($keys as $key) {
            [$date, $period] = explode('|', $key);
            ExpertOfferDate::create(['offer_id' => $offerId, 'mode' => $mode, 'date' => $date, 'period' => $period]);
        }
    }

    /**
     * Suppression logique : les réservations existantes conservent leur offre.
     */
    public function deleteOffer(ExpertOffer $offer): void
    {
        $offer->delete();
    }

    /* ===================== GRILLE ===================== */

    /**
     * Demi-journées de la grille encore en vigueur (aujourd'hui ou plus tard), par mode.
     * Une demi-journée avec lieu sert aussi en ligne (3a) ; en présentiel, seules celles
     * qui ont un lieu actif comptent.
     *
     * @return array{online: array<string, ?string>, onsite: array<string, string>} « jour|période » => nom du lieu
     */
    public function gridSlots(int $expertId, ?Collection $rules = null, ?Collection $locations = null): array
    {
        $rules     ??= $this->rulesOf($expertId);
        $locations ??= $this->activeLocations($expertId);
        $today       = $this->today($expertId);
        $grid        = [ExpertOfferMode::ONLINE => [], ExpertOfferMode::ONSITE => []];

        foreach ($rules as $rule) {
            if ($rule->valid_until && $rule->valid_until->toDateString() < $today) {
                continue;
            }

            $key      = $rule->weekday . '|' . $rule->period;
            $location = $rule->location_id ? $locations->get($rule->location_id) : null;

            if (!array_key_exists($key, $grid[ExpertOfferMode::ONLINE]) || $location) {
                $grid[ExpertOfferMode::ONLINE][$key] = $location?->label;
            }
            if ($location) {
                $grid[ExpertOfferMode::ONSITE][$key] ??= $location->label;
            }
        }

        // Lundi matin, lundi après-midi, mardi matin… quel que soit l'ordre de création des règles.
        $order = fn (string $k) => [(int) $k, str_ends_with($k, 'afternoon')];
        foreach ($grid as &$slots) {
            uksort($slots, fn ($a, $b) => $order($a) <=> $order($b));
        }
        unset($slots);

        return $grid;
    }

    /**
     * Dates proposables pour le calendrier « Dates précises » : d'aujourd'hui à la limite
     * de réservation, sans les demi-journées bloquées. En ligne, les demi-journées ajoutées
     * à la main (exceptions « add ») sont proposées aussi (2a), sans lieu.
     *
     * @return array<string, ?string> « Y-m-d|période » => nom du lieu
     */
    public function gridDates(int $expertId, string $mode): array
    {
        $settings  = $this->resolved($expertId);
        $from      = $this->today($expertId);
        $to        = Carbon::parse($from)->addMonthsNoOverflow($settings['max_advance_months'])->toDateString();
        $rules      = $this->rulesOf($expertId);
        $locations  = $this->activeLocations($expertId);
        $exceptions = $this->exceptionsOf($expertId, $from, $to);

        $dates = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            foreach (self::PERIODS as $period) {
                if (!$this->dateOpen($rules, $exceptions, $date, $period, $mode, $locations)) {
                    continue;
                }
                $rule = $this->ruleOn($rules, $date, $period, $mode, $locations);
                $dates[$date . '|' . $period] = $rule?->location_id ? $locations->get($rule->location_id)?->label : null;
            }
        }

        return $dates;
    }

    /**
     * Lieux où une offre en présentiel peut se tenir d'après son calendrier :
     * affichés sur la fiche de l'offre (3b). Vide pour un mode en ligne.
     *
     * @return Collection<int, ExpertLocation>
     */
    public function offerLocations(ExpertOfferMode $offerMode, int $expertId): Collection
    {
        if ($offerMode->mode !== ExpertOfferMode::ONSITE) {
            return collect();
        }

        $rules     = $this->rulesOf($expertId);
        $locations = $this->activeLocations($expertId);
        $today     = $this->today($expertId);
        $ids       = [];

        if ($offerMode->schedule_type === ExpertOfferMode::SCHEDULE_DATES) {
            foreach ($offerMode->dates($today) as $date) {
                $rule = $this->ruleOn($rules, $date->date->toDateString(), $date->period, ExpertOfferMode::ONSITE, $locations);
                if ($rule) {
                    $ids[$rule->location_id] = true;
                }
            }
        } else {
            $allowed = $offerMode->schedule_type === ExpertOfferMode::SCHEDULE_SELECTED
                ? array_flip($offerMode->slots()->pluck('key_string')->all())
                : null;

            foreach ($rules as $rule) {
                $valid = $rule->location_id
                    && $locations->has($rule->location_id)
                    && (!$rule->valid_until || $rule->valid_until->toDateString() >= $today)
                    && ($allowed === null || isset($allowed[$rule->weekday . '|' . $rule->period]));

                if ($valid) {
                    $ids[$rule->location_id] = true;
                }
            }
        }

        return $locations->only(array_keys($ids))->sortBy('label')->values();
    }

    /**
     * Règle de la grille qui ouvre cette demi-journée pour ce mode, ou null.
     * En présentiel, la règle doit porter un lieu actif.
     */
    public function ruleOn(Collection $rules, string $date, string $period, string $mode, Collection $locations): ?ExpertAvailabilityRule
    {
        $weekday = Carbon::parse($date)->dayOfWeekIso;

        return $rules->first(fn (ExpertAvailabilityRule $r) =>
            $r->weekday === $weekday
            && $r->period === $period
            && (!$r->valid_from || $r->valid_from->toDateString() <= $date)
            && (!$r->valid_until || $r->valid_until->toDateString() >= $date)
            && ($mode !== ExpertOfferMode::ONSITE || ($r->location_id && $locations->has($r->location_id)))
        );
    }

    /**
     * Demi-journée ouverte pour un calendrier « Dates précises » dans ce mode :
     * pas bloquée, et prévue par la grille — ou, en ligne, ajoutée à la main (2a).
     * Même priorité que le calcul des créneaux : exception de la demi-journée,
     * puis de la journée entière, puis la grille.
     */
    public function dateOpen(Collection $rules, Collection $exceptions, string $date, string $period, string $mode, Collection $locations): bool
    {
        $exception = $this->exceptionOn($exceptions, $date, $period);
        if ($exception?->type === 'block') {
            return false;
        }

        return $this->ruleOn($rules, $date, $period, $mode, $locations) !== null
            || ($exception?->type === 'add' && $mode === ExpertOfferMode::ONLINE);
    }

    private function dateClosed(Collection $exceptions, string $date, string $period): bool
    {
        return $this->exceptionOn($exceptions, $date, $period)?->type === 'block';
    }

    private function exceptionOn(Collection $exceptions, string $date, string $period): ?ExpertAvailabilityException
    {
        return $exceptions->get($date . '|' . $period) ?? $exceptions->get($date . '|');
    }

    /**
     * Exceptions de l'expert à partir d'une date, indexées « date|période »
     * (période vide : journée entière).
     *
     * @return Collection<string, ExpertAvailabilityException>
     */
    private function exceptionsOf(int $expertId, string $from, ?string $to = null): Collection
    {
        return ExpertAvailabilityException::where('expert_id', $expertId)
            ->where('date', '>=', $from)
            ->when($to, fn ($q) => $q->where('date', '<=', $to))
            ->get()
            ->keyBy(fn ($e) => $e->date->toDateString() . '|' . ($e->period ?? ''));
    }

    /* ===================== DISPONIBILITÉS RÉCURRENTES ===================== */

    /**
     * Crée en une fois toutes les combinaisons jours × demi-journées
     * (ex. tous les vendredis après-midi), avec un lieu facultatif. Les combinaisons
     * qui chevauchent une règle existante sont ignorées plutôt que dupliquées.
     *
     * @return array{created:int, skipped:int}
     */
    public function addRules(int $expertId, array $input): array
    {
        $data = Validator::make($input, [
            'weekdays'    => ['required', 'array', 'min:1'],
            'weekdays.*'  => ['integer', 'between:1,7'],
            'periods'     => ['required', 'array', 'min:1'],
            'periods.*'   => [Rule::in(self::PERIODS)],
            'location_id' => ['nullable', 'integer'],
            'valid_from'  => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
        ], [], self::ATTRIBUTES)->validate();

        $locationId = $this->checkedLocationId($expertId, $data['location_id'] ?? null);
        $from       = $data['valid_from'] ?? null;
        $until      = $data['valid_until'] ?? null;
        $existing   = ExpertAvailabilityRule::where('expert_id', $expertId)->get();
        $created    = 0;
        $skipped    = 0;

        DB::transaction(function () use ($expertId, $data, $locationId, $from, $until, $existing, &$created, &$skipped) {
            foreach (array_unique(array_map('intval', $data['weekdays'])) as $weekday) {
                foreach (array_unique($data['periods']) as $period) {
                    $overlaps = $existing->contains(fn (ExpertAvailabilityRule $rule) =>
                        $rule->weekday === $weekday
                        && $rule->period === $period
                        && $this->rangesOverlap(
                            $rule->valid_from?->toDateString(),
                            $rule->valid_until?->toDateString(),
                            $from,
                            $until
                        )
                    );

                    if ($overlaps) {
                        $skipped++;
                        continue;
                    }

                    ExpertAvailabilityRule::create([
                        'expert_id'   => $expertId,
                        'weekday'     => $weekday,
                        'period'      => $period,
                        'location_id' => $locationId,
                        'valid_from'  => $from,
                        'valid_until' => $until,
                    ]);
                    $created++;
                }
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Change le lieu d'une demi-journée de la grille (null : en ligne seulement).
     * Retirer le lieu est refusé si une offre en présentiel en a besoin.
     */
    public function setRuleLocation(ExpertAvailabilityRule $rule, ?int $locationId): ExpertAvailabilityRule
    {
        $locationId = $this->checkedLocationId($rule->expert_id, $locationId);

        $rulesAfter = $this->rulesOf($rule->expert_id)->map(
            fn (ExpertAvailabilityRule $r) => $r->id === $rule->id
                ? tap(clone $r)->setAttribute('location_id', $locationId)
                : $r
        );
        $this->assertNoConflicts($this->offerConflicts($rule->expert_id, $rulesAfter), 'rule');

        $rule->update(['location_id' => $locationId]);

        return $rule;
    }

    public function deleteRule(ExpertAvailabilityRule $rule): void
    {
        $rulesAfter = $this->rulesOf($rule->expert_id)->reject(fn (ExpertAvailabilityRule $r) => $r->id === $rule->id);
        $this->assertNoConflicts($this->offerConflicts($rule->expert_id, $rulesAfter), 'rule');

        $rule->delete();
    }

    private function rangesOverlap(?string $aFrom, ?string $aUntil, ?string $bFrom, ?string $bUntil): bool
    {
        // NULL = sans borne ; des dates Y-m-d se comparent correctement en chaîne.
        return ($aFrom === null || $bUntil === null || $aFrom <= $bUntil)
            && ($bFrom === null || $aUntil === null || $bFrom <= $aUntil);
    }

    private function checkedLocationId(int $expertId, mixed $locationId): ?int
    {
        if ($locationId === null || $locationId === '') {
            return null;
        }

        $exists = ExpertLocation::where('expert_id', $expertId)->active()->whereKey((int) $locationId)->exists();
        if (!$exists) {
            throw ValidationException::withMessages(['location_id' => "Ce lieu n'existe pas ou a été archivé."]);
        }

        return (int) $locationId;
    }

    /* ===================== EXCEPTIONS ===================== */

    /**
     * Ajoute ou bloque une demi-journée ou une journée entière, sur une date
     * ou une plage de dates (congés). Ressaisir une même date remplace l'exception.
     *
     * @return int nombre de dates traitées
     */
    public function addExceptions(int $expertId, array $input): int
    {
        $data = Validator::make($input, [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'period'    => ['nullable', Rule::in(self::PERIODS)],
            'type'      => ['required', Rule::in(array_keys(ExpertAvailabilityException::TYPE_LABELS))],
        ], [], self::ATTRIBUTES)->validate();

        $from   = $data['date_from'];
        $to     = $data['date_to'] ?? $from;
        $period = $data['period'] ?? null;

        // « Aujourd'hui » au sens de l'expert, pas du serveur (UTC).
        $today = $this->today($expertId);
        if ($from < $today) {
            throw ValidationException::withMessages(['date_from' => 'La date ne peut pas être dans le passé.']);
        }

        $days = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        if ($days > self::MAX_EXCEPTION_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'date_to' => sprintf('La plage ne peut pas dépasser %d jours.', self::MAX_EXCEPTION_RANGE_DAYS),
            ]);
        }

        if ($data['type'] === 'block') {
            $this->assertNoConflicts($this->blockedDateConflicts($expertId, $from, $to, $period), 'date_from');
        }

        // Fermer une date retire seulement les créneaux proposés : les rendez-vous déjà
        // demandés ou acceptés ce jour-là restent valables (décision du 23/10/2026).

        DB::transaction(function () use ($expertId, $from, $to, $period, $data) {
            foreach (CarbonPeriod::create($from, $to) as $day) {
                $date = $day->toDateString();

                if ($period === null) {
                    // La journée entière traduit la dernière intention de l'expert :
                    // elle remplace les exceptions par demi-journée de cette date.
                    ExpertAvailabilityException::where('expert_id', $expertId)
                        ->where('date', $date)
                        ->whereNotNull('period')
                        ->delete();
                }

                ExpertAvailabilityException::updateOrCreate(
                    ['expert_id' => $expertId, 'date' => $date, 'period' => $period],
                    ['type' => $data['type']]
                );
            }
        });

        return $days;
    }

    /**
     * Retirer un ajout manuel est refusé si une offre « Dates précises » propose ce jour-là (2b).
     */
    public function deleteException(ExpertAvailabilityException $exception): void
    {
        if ($exception->type === 'add') {
            $expertId = $exception->expert_id;
            $after    = $this->exceptionsOf($expertId, $this->today($expertId))
                ->reject(fn (ExpertAvailabilityException $e) => $e->id === $exception->id);

            $this->assertNoConflicts(
                $this->offerConflicts($expertId, $this->rulesOf($expertId), [], $after),
                'exception'
            );
        }

        $exception->delete();
    }

    /* ===================== RÈGLE 2b ===================== */

    /**
     * Offres actives qui perdraient une demi-journée si la grille devenait $rulesAfter
     * (si les lieux $lostLocationIds n'étaient plus utilisables, et si les exceptions
     * devenaient $exceptionsAfter — par défaut celles d'aujourd'hui).
     * « Toutes les demi-journées » n'est jamais en conflit : l'offre suit la grille.
     *
     * @return array<int, string> une ligne par offre et par mode
     */
    private function offerConflicts(int $expertId, Collection $rulesAfter, array $lostLocationIds = [], ?Collection $exceptionsAfter = null): array
    {
        $modes = $this->activeModesQuery($expertId)
            ->whereIn('m.schedule_type', [ExpertOfferMode::SCHEDULE_SELECTED, ExpertOfferMode::SCHEDULE_DATES])
            ->get();
        if ($modes->isEmpty()) {
            return [];
        }

        $locations  = $this->activeLocations($expertId)->except($lostLocationIds);
        $grid       = $this->gridSlots($expertId, $rulesAfter, $locations);
        $today      = $this->today($expertId);
        $exceptions = $exceptionsAfter ?? $this->exceptionsOf($expertId, $today);
        $conflicts  = [];

        foreach ($modes as $m) {
            $missing = [];

            if ($m->schedule_type === ExpertOfferMode::SCHEDULE_SELECTED) {
                $slots = ExpertOfferSlot::where('offer_id', $m->offer_id)->where('mode', $m->mode)->get();
                foreach ($slots as $slot) {
                    if (!array_key_exists($slot->key_string, $grid[$m->mode])) {
                        $missing[] = ExpertAvailabilityRule::slotLabel($slot->weekday, $slot->period);
                    }
                }
            } else {
                $dates = ExpertOfferDate::where('offer_id', $m->offer_id)->where('mode', $m->mode)
                    ->where('date', '>=', $today)->orderBy('date')->get();
                foreach ($dates as $date) {
                    if (!$this->dateOpen($rulesAfter, $exceptions, $date->date->toDateString(), $date->period, $m->mode, $locations)) {
                        $missing[] = self::dateLabel($date->date->toDateString(), $date->period);
                    }
                }
            }

            if ($missing) {
                $conflicts[] = $this->conflictLine($m->title, $m->mode, $missing);
            }
        }

        return $conflicts;
    }

    /** Dates précises d'offres actives qu'un blocage ferait disparaître. */
    private function blockedDateConflicts(int $expertId, string $from, string $to, ?string $period): array
    {
        $rows = $this->activeModesQuery($expertId)
            ->where('m.schedule_type', ExpertOfferMode::SCHEDULE_DATES)
            ->join('expert_offer_dates as d', fn ($j) => $j->on('d.offer_id', '=', 'm.offer_id')->on('d.mode', '=', 'm.mode'))
            ->whereBetween('d.date', [$from, $to])
            ->when($period, fn ($q) => $q->where('d.period', $period))
            ->orderBy('d.date')
            // get(colonnes) serait ignoré : la requête de base a déjà un select.
            ->addSelect('d.date', 'd.period')
            ->get();

        return $rows->groupBy(fn ($r) => $r->title . '|' . $r->mode)
            ->map(fn ($group) => $this->conflictLine(
                $group->first()->title,
                $group->first()->mode,
                $group->map(fn ($r) => self::dateLabel(substr((string) $r->date, 0, 10), $r->period))->all()
            ))
            ->values()
            ->all();
    }

    /** Modes actifs des offres actives (non supprimées) de l'expert, avec le titre de l'offre. */
    private function activeModesQuery(int $expertId)
    {
        return DB::table('expert_offer_modes as m')
            ->join('expert_offers as o', 'o.id', '=', 'm.offer_id')
            ->where('o.expert_id', $expertId)
            ->whereNull('o.deleted_at')
            ->where('o.active_flg', 1)
            ->where('m.active_flg', 1)
            ->select('m.offer_id', 'm.mode', 'm.schedule_type', 'o.title');
    }

    private function conflictLine(string $title, string $mode, array $labels): string
    {
        return sprintf('« %s » (%s) : %s', $title, mb_strtolower(ExpertOfferMode::MODE_LABELS[$mode] ?? $mode), implode(', ', $labels));
    }

    private function assertNoConflicts(array $conflicts, string $field): void
    {
        if ($conflicts) {
            throw ValidationException::withMessages([
                $field => array_merge(
                    ["Modification impossible : des offres utilisent ces demi-journées. Modifiez d'abord les offres suivantes :"],
                    $conflicts
                ),
            ]);
        }
    }

    /* ===================== OUTILS ===================== */

    /** @return Collection<int, ExpertAvailabilityRule> */
    private function rulesOf(int $expertId): Collection
    {
        return ExpertAvailabilityRule::where('expert_id', $expertId)->get();
    }

    /** @return Collection<int, ExpertLocation> indexée par id */
    public function activeLocations(int $expertId): Collection
    {
        return ExpertLocation::where('expert_id', $expertId)->active()->get()->keyBy('id');
    }

    /** « Aujourd'hui » au sens de l'expert, pas du serveur (UTC). */
    public function today(int $expertId): string
    {
        return Carbon::now($this->resolved($expertId)['timezone'])->toDateString();
    }

    /** « 12/11/2026 matin ». */
    public static function dateLabel(string $date, string $period): string
    {
        return Carbon::parse($date)->format('d/m/Y') . ' ' . mb_strtolower(ExpertAvailabilityRule::PERIOD_LABELS[$period] ?? $period);
    }
}