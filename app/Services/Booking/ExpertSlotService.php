<?php

namespace App\Services\Booking;

use App\Models\BookingBlock;
use App\Models\ExpertAvailabilityException;
use App\Models\ExpertAvailabilityRule;
use App\Models\ExpertLocation;
use App\Models\ExpertOfferDate;
use App\Models\ExpertOfferMode;
use App\Models\ExpertOfferSlot;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Calcul des demi-journées réservables d'un expert, ou d'une offre dans un mode donné.
 *
 * Ordre de priorité pour une date et une demi-journée :
 *   exception de la demi-journée > exception de la journée entière > règle récurrente.
 * Puis on retire ce qui tombe avant le délai minimum, après la limite de réservation,
 * et ce qui est déjà retenu par une demande en attente ou acceptée.
 *
 * Pour une offre, en plus :
 *   - une demi-journée ajoutée à la main (exception « add ») ne compte pas (règle 2a) ;
 *   - en présentiel, la règle doit porter un lieu actif ;
 *   - le calendrier de l'offre filtre la grille (toutes / certaines demi-journées / dates précises),
 *     et une date précise ferme book_ahead_days jours avant ;
 *   - la retenue reste par expert (2c) : un créneau pris l'est pour toutes ses offres.
 *
 * Rien n'est stocké : le calcul est refait à chaque appel, pour que toute modification
 * de l'expert soit visible immédiatement.
 */
class ExpertSlotService
{
    /** Plage maximale par appel : un calendrier mensuel avec marge, sans calcul démesuré. */
    public const MAX_RANGE_DAYS = 62;

    /** Plage par défaut quand l'application ne précise pas de date de fin. */
    public const DEFAULT_RANGE_DAYS = 31;

    public function __construct(private ExpertBookingSetupService $setup)
    {
    }

    /**
     * Bornes de réservation actuelles d'un expert, en dates locales (Y-m-d).
     * Sert au calendrier de l'application pour griser les mois hors limites.
     *
     * @return array{timezone:string, earliest_at:Carbon, first_date:string, last_date:string}
     */
    public function bounds(int $expertId): array
    {
        $settings = $this->setup->resolved($expertId);
        $now      = Carbon::now($settings['timezone']);

        // Jours calendaires : avec 1 jour, toute la journée de demain est ouverte, dès le matin.
        $earliest = $now->copy()->startOfDay()->addDays($settings['min_notice_days']);
        $last     = $now->copy()->addMonthsNoOverflow($settings['max_advance_months']);

        return [
            'timezone'    => $settings['timezone'],
            'earliest_at' => $earliest,
            'first_date'  => $earliest->toDateString(),
            'last_date'   => $last->toDateString(),
        ];
    }

    /**
     * Demi-journées réservables entre deux dates locales incluses.
     * Les dates hors limites sont ramenées dans les bornes ; la plage est plafonnée.
     *
     * $ignoreEnabled sert à l'aperçu de l'expert : il voit sa grille même désactivé.
     * $offerMode limite le résultat au calendrier d'une offre dans un mode ; sans lui,
     * c'est la grille générale de l'expert (comportement d'avant les modes).
     *
     * @return Collection<int, array{date:string, period:string, start_at:Carbon, end_at:Carbon, start_local:string, end_local:string, location_id:?int, location:?ExpertLocation}>
     */
    public function availableBlocks(
        int $expertId,
        ?string $from = null,
        ?string $to = null,
        bool $ignoreEnabled = false,
        ?ExpertOfferMode $offerMode = null
    ): Collection {
        $settings = $this->setup->resolved($expertId);

        if (!$settings['enabled_flg'] && !$ignoreEnabled) {
            return collect();
        }
        if ($offerMode && !$offerMode->active_flg) {
            return collect();
        }

        $bounds = $this->bounds($expertId);
        [$from, $to] = $this->clampRange($from, $to, $bounds);

        if ($from > $to) {
            return collect();
        }

        $rules = ExpertAvailabilityRule::where('expert_id', $expertId)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $to))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $from))
            ->get();

        // Indexées par « date|période » ; la journée entière utilise la période vide.
        $exceptions = ExpertAvailabilityException::where('expert_id', $expertId)
            ->whereBetween('date', [$from, $to])
            ->get()
            ->keyBy(fn ($e) => $e->date->toDateString() . '|' . ($e->period ?? ''));

        $forOffer  = $offerMode !== null;
        $mode      = $offerMode?->mode ?? ExpertOfferMode::ONLINE;
        $allowed   = $this->offerFilter($offerMode, $from, $to, $settings['timezone']);
        $locations = $this->setup->activeLocations($expertId);
        $held      = $this->heldKeys($expertId, $from, $to);
        $tz        = $settings['timezone'];
        $blocks    = collect();

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date    = $day->toDateString();
            $weekday = $day->dayOfWeekIso;

            foreach (ExpertBookingSetupService::PERIODS as $period) {
                if ($allowed !== null) {
                    $key = $offerMode->schedule_type === ExpertOfferMode::SCHEDULE_SELECTED
                        ? $weekday . '|' . $period
                        : $date . '|' . $period;
                    if (!isset($allowed[$key])) {
                        continue;
                    }
                }

                $exception = $exceptions->get($date . '|' . $period) ?? $exceptions->get($date . '|');
                if ($exception && $exception->type !== 'add') {
                    continue;
                }

                $rule = $this->setup->ruleOn($rules, $date, $period, $mode, $locations);
                // Ajout manuel : ouvre la grille générale, pas le calendrier d'une offre (2a).
                if (!$rule && !($exception && !$forOffer)) {
                    continue;
                }

                $startLocal = $settings[$period . '_start'];
                $endLocal   = $settings[$period . '_end'];
                $start      = Carbon::parse($date . ' ' . $startLocal, $tz);

                if ($start->lt($bounds['earliest_at']) || isset($held[$date . '|' . $period])) {
                    continue;
                }

                $location = $mode === ExpertOfferMode::ONSITE && $rule ? $locations->get($rule->location_id) : null;

                $blocks->push([
                    'date'        => $date,
                    'period'      => $period,
                    'start_at'    => $start->copy()->utc(),
                    'end_at'      => Carbon::parse($date . ' ' . $endLocal, $tz)->utc(),
                    'start_local' => $startLocal,
                    'end_local'   => $endLocal,
                    'location_id' => $location?->id,
                    'location'    => $location,
                ]);
            }
        }

        return $blocks;
    }

    /**
     * Clés autorisées par le calendrier de l'offre, ou null si toute la grille convient.
     * « Certaines demi-journées » : « jour|période » ; « Dates précises » : « Y-m-d|période ».
     *
     * @return array<string, true>|null
     */
    private function offerFilter(?ExpertOfferMode $offerMode, string $from, string $to, string $tz): ?array
    {
        if (!$offerMode || $offerMode->schedule_type === ExpertOfferMode::SCHEDULE_ALL) {
            return null;
        }

        if ($offerMode->schedule_type === ExpertOfferMode::SCHEDULE_SELECTED) {
            return ExpertOfferSlot::where('offer_id', $offerMode->offer_id)
                ->where('mode', $offerMode->mode)
                ->get()
                ->mapWithKeys(fn (ExpertOfferSlot $s) => [$s->key_string => true])
                ->all();
        }

        // Même calcul que le délai minimum : avec N jours, la date D reste réservable
        // jusqu'au jour D − N inclus (N = 3 : le 15 est encore proposé le 12, plus le 13).
        $firstOpen = Carbon::now($tz)->startOfDay()->addDays((int) $offerMode->book_ahead_days)->toDateString();

        return ExpertOfferDate::where('offer_id', $offerMode->offer_id)
            ->where('mode', $offerMode->mode)
            ->whereBetween('date', [max($from, $firstOpen), $to])
            ->get()
            ->mapWithKeys(fn (ExpertOfferDate $d) => [$d->key_string => true])
            ->all();
    }

    /**
     * Demi-journées déjà retenues par une demande en attente ou acceptée.
     * hold_key n'est renseigné que pour ces demandes : il suffit de le tester.
     *
     * @return array<string, true> clés « date|période »
     */
    private function heldKeys(int $expertId, string $from, string $to): array
    {
        return BookingBlock::where('expert_id', $expertId)
            ->whereBetween('date', [$from, $to])
            ->whereNotNull('hold_key')
            ->get(['date', 'period'])
            ->mapWithKeys(fn (BookingBlock $b) => [$b->date->toDateString() . '|' . $b->period => true])
            ->all();
    }

    /** @return array{0:string, 1:string} */
    private function clampRange(?string $from, ?string $to, array $bounds): array
    {
        $from = max($from ?: $bounds['first_date'], $bounds['first_date']);
        $to   = $to ?: Carbon::parse($from)->addDays(self::DEFAULT_RANGE_DAYS - 1)->toDateString();

        $cap = Carbon::parse($from)->addDays(self::MAX_RANGE_DAYS - 1)->toDateString();
        $to  = min($to, $bounds['last_date'], $cap);

        return [$from, $to];
    }
}