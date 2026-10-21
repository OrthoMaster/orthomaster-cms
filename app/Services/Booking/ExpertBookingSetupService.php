<?php

namespace App\Services\Booking;

use App\Models\ExpertAvailabilityException;
use App\Models\ExpertAvailabilityRule;
use App\Models\ExpertBookingSetting;
use App\Models\ExpertOffer;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Configuration des rendez-vous d'un expert : réglages, offres, disponibilités.
 *
 * Partagé entre le CMS (admin) et l'API de l'application (expert) : la validation
 * vit ici pour que les deux interfaces appliquent exactement les mêmes règles.
 * Les erreurs remontent en ValidationException, que Laravel convertit en
 * redirection avec erreurs côté web et en 422 JSON côté API.
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
        'price'              => 'prix',
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

    /* ===================== OFFRES ===================== */

    public function saveOffer(int $expertId, array $input, ?ExpertOffer $offer = null): ExpertOffer
    {
        $data = Validator::make($input, [
            'title'        => ['required', 'string', 'max:150'],
            'description'  => ['nullable', 'string', 'max:5000'],
            'blocks_count' => ['required', 'integer', 'min:1', 'max:' . self::MAX_BLOCKS_PER_OFFER],
            'price'        => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'active_flg'   => ['required', 'boolean'],
            'order_dsp'    => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [], self::ATTRIBUTES)->validate();

        if (!$offer) {
            $offer            = new ExpertOffer();
            $offer->expert_id = $expertId;
            // Une nouvelle offre se place en dernier si aucun ordre n'est donné.
            $data['order_dsp'] ??= (int) ExpertOffer::where('expert_id', $expertId)->max('order_dsp') + 1;
        }

        $data['order_dsp'] ??= $offer->order_dsp;

        $offer->fill($data);
        $offer->save();

        return $offer;
    }

    /**
     * Suppression logique : les réservations existantes conservent leur offre.
     */
    public function deleteOffer(ExpertOffer $offer): void
    {
        $offer->delete();
    }

    /* ===================== DISPONIBILITÉS RÉCURRENTES ===================== */

    /**
     * Crée en une fois toutes les combinaisons jours × demi-journées
     * (ex. tous les vendredis après-midi). Les combinaisons qui chevauchent
     * une règle existante sont ignorées plutôt que dupliquées.
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
            'valid_from'  => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
        ], [], self::ATTRIBUTES)->validate();

        $from     = $data['valid_from'] ?? null;
        $until    = $data['valid_until'] ?? null;
        $existing = ExpertAvailabilityRule::where('expert_id', $expertId)->get();
        $created  = 0;
        $skipped  = 0;

        DB::transaction(function () use ($expertId, $data, $from, $until, $existing, &$created, &$skipped) {
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
                        'valid_from'  => $from,
                        'valid_until' => $until,
                    ]);
                    $created++;
                }
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function deleteRule(ExpertAvailabilityRule $rule): void
    {
        $rule->delete();
    }

    private function rangesOverlap(?string $aFrom, ?string $aUntil, ?string $bFrom, ?string $bUntil): bool
    {
        // NULL = sans borne ; des dates Y-m-d se comparent correctement en chaîne.
        return ($aFrom === null || $bUntil === null || $aFrom <= $bUntil)
            && ($bFrom === null || $aUntil === null || $bFrom <= $aUntil);
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
        $today = Carbon::now($this->resolved($expertId)['timezone'])->toDateString();
        if ($from < $today) {
            throw ValidationException::withMessages(['date_from' => 'La date ne peut pas être dans le passé.']);
        }

        $days = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        if ($days > self::MAX_EXCEPTION_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'date_to' => sprintf('La plage ne peut pas dépasser %d jours.', self::MAX_EXCEPTION_RANGE_DAYS),
            ]);
        }

        // Phase 3 : refuser un blocage sur une date portant une réservation acceptée.

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

    public function deleteException(ExpertAvailabilityException $exception): void
    {
        $exception->delete();
    }
}