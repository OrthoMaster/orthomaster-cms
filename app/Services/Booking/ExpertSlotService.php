<?php

namespace App\Services\Booking;

use App\Models\ExpertAvailabilityException;
use App\Models\ExpertAvailabilityRule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Calcul des demi-journées réservables d'un expert.
 *
 * Ordre de priorité pour une date et une demi-journée :
 *   exception de la demi-journée > exception de la journée entière > règle récurrente.
 * Puis on retire ce qui tombe avant le délai minimum, après la limite de réservation,
 * et (phase 3) ce qui est déjà retenu par une réservation.
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

        $earliest = $now->copy()->addHours($settings['min_notice_hours']);
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
     *
     * @return Collection<int, array{date:string, period:string, start_at:Carbon, end_at:Carbon, start_local:string, end_local:string}>
     */
    public function availableBlocks(int $expertId, ?string $from = null, ?string $to = null, bool $ignoreEnabled = false): Collection
    {
        $settings = $this->setup->resolved($expertId);

        if (!$settings['enabled_flg'] && !$ignoreEnabled) {
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

        $held   = $this->heldKeys($expertId, $from, $to);
        $tz     = $settings['timezone'];
        $blocks = collect();

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date    = $day->toDateString();
            $weekday = $day->dayOfWeekIso;

            foreach (ExpertBookingSetupService::PERIODS as $period) {
                if (!$this->isOpen($date, $weekday, $period, $rules, $exceptions)) {
                    continue;
                }

                $startLocal = $settings[$period . '_start'];
                $endLocal   = $settings[$period . '_end'];
                $start      = Carbon::parse($date . ' ' . $startLocal, $tz);

                if ($start->lt($bounds['earliest_at']) || isset($held[$date . '|' . $period])) {
                    continue;
                }

                $blocks->push([
                    'date'        => $date,
                    'period'      => $period,
                    'start_at'    => $start->copy()->utc(),
                    'end_at'      => Carbon::parse($date . ' ' . $endLocal, $tz)->utc(),
                    'start_local' => $startLocal,
                    'end_local'   => $endLocal,
                ]);
            }
        }

        return $blocks;
    }

    private function isOpen(string $date, int $weekday, string $period, Collection $rules, Collection $exceptions): bool
    {
        $exception = $exceptions->get($date . '|' . $period) ?? $exceptions->get($date . '|');
        if ($exception) {
            return $exception->type === 'add';
        }

        return $rules->contains(fn (ExpertAvailabilityRule $rule) =>
            $rule->weekday === $weekday
            && $rule->period === $period
            && (!$rule->valid_from || $rule->valid_from->toDateString() <= $date)
            && (!$rule->valid_until || $rule->valid_until->toDateString() >= $date)
        );
    }

    /**
     * Demi-journées déjà retenues par une réservation en attente ou acceptée.
     * Vide tant que la table booking_blocks n'existe pas (phase 3).
     *
     * @return array<string, true> clés « date|période »
     */
    private function heldKeys(int $expertId, string $from, string $to): array
    {
        return [];
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