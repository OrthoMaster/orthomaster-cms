<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingWindow;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Hors calendrier par créneaux.
 *
 * Le demandeur propose 1 à 3 créneaux, chacun sur un ou plusieurs jours :
 *  - consultation : de « date heure » à « date heure », par pas de 30 minutes, au moins la durée estimée ;
 *  - thème : de « date demi-journée » à « date demi-journée » (incluses), au moins le nombre de
 *    demi-journées du thème.
 * L'expert choisit en acceptant l'horaire exact dans l'un d'eux :
 *  - consultation : une heure de début (la fin = début + durée) ;
 *  - thème : la première demi-journée, suivie des demi-journées consécutives nécessaires.
 * Un horaire déjà passé (ou, pour un thème, une demi-journée déjà retenue) ne peut plus être choisi.
 */
class BookingWindowService
{
    public const MAX_WINDOWS = 3;
    /** Étendue maximale d'un créneau, en jours (liste des choix raisonnable). */
    public const MAX_SPAN_DAYS = 14;

    public const PERIODS = ['morning', 'afternoon'];

    public function __construct(
        private ExpertSlotService $slots,
        private ExpertBookingSetupService $setup,
        private ConsultationService $consultations
    ) {
    }

    /* ===================== SAISIE DU DEMANDEUR ===================== */

    /**
     * @param  array<int, array{start_date:string, start_time:string, end_date:string, end_time:string}>  $input
     * @return array<int, array> lignes prêtes pour booking_windows
     */
    public function consultationWindows(int $expertId, array $input, int $minutes): array
    {
        $this->assertCount($input);

        $tz     = $this->setup->resolved($expertId)['timezone'];
        $bounds = $this->consultations->bounds($expertId);
        $step   = ConsultationService::STEP_MINUTES;
        $rows   = [];

        foreach (array_values($input) as $i => $w) {
            $n = $i + 1;
            foreach (['start_date', 'end_date'] as $k) {
                if (!is_string($w[$k] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $w[$k])) {
                    $this->fail("Créneau {$n} : date invalide.");
                }
            }
            foreach (['start_time', 'end_time'] as $k) {
                if (!is_string($w[$k] ?? null) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $w[$k])) {
                    $this->fail("Créneau {$n} : heure invalide.");
                }
                if ((int) substr($w[$k], 3, 2) % $step !== 0) {
                    $this->fail("Créneau {$n} : les heures vont de demi-heure en demi-heure.");
                }
            }

            $start = Carbon::parse($w['start_date'] . ' ' . $w['start_time'], $tz);
            $end   = Carbon::parse($w['end_date'] . ' ' . $w['end_time'], $tz);

            if ($end->lte($start)) {
                $this->fail("Créneau {$n} : la fin doit suivre le début.");
            }
            if ($start->diffInMinutes($end) < $minutes) {
                $this->fail("Créneau {$n} : il doit durer au moins la durée estimée.");
            }
            if ($start->diffInDays($end) > self::MAX_SPAN_DAYS) {
                $this->fail("Créneau {$n} : " . self::MAX_SPAN_DAYS . ' jours au plus.');
            }
            if ($start->lt($bounds['earliest_at']) || $w['end_date'] > $bounds['last_date']) {
                $this->fail("Créneau {$n} : ces dates ne sont pas réservables chez cet expert.");
            }

            $rows[] = [
                'position'     => $n,
                'start_date'   => $w['start_date'],
                'start_time'   => $w['start_time'],
                'start_period' => null,
                'end_date'     => $w['end_date'],
                'end_time'     => $w['end_time'],
                'end_period'   => null,
                'start_at'     => $start->copy()->utc(),
                'end_at'       => $end->copy()->utc(),
            ];
        }

        $this->assertNoOverlap($rows, fn ($a, $b) => $a['start_at']->lt($b['end_at']) && $b['start_at']->lt($a['end_at']));

        return $rows;
    }

    /**
     * @param  array<int, array{start_date:string, start_period:string, end_date:string, end_period:string}>  $input
     * @return array<int, array> lignes prêtes pour booking_windows
     */
    public function themeWindows(int $expertId, array $input, int $blocksCount): array
    {
        $this->assertCount($input);

        $bounds = $this->slots->bounds($expertId);
        $rows   = [];
        $keys   = [];

        foreach (array_values($input) as $i => $w) {
            $n = $i + 1;
            foreach (['start_date', 'end_date'] as $k) {
                if (!is_string($w[$k] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $w[$k])) {
                    $this->fail("Créneau {$n} : date invalide.");
                }
            }
            foreach (['start_period', 'end_period'] as $k) {
                if (!in_array($w[$k] ?? null, self::PERIODS, true)) {
                    $this->fail("Créneau {$n} : demi-journée invalide.");
                }
            }
            if (Carbon::parse($w['start_date'])->diffInDays(Carbon::parse($w['end_date']), false) > self::MAX_SPAN_DAYS) {
                $this->fail("Créneau {$n} : " . self::MAX_SPAN_DAYS . ' jours au plus.');
            }

            $halfDays = $this->halfDays($expertId, $w['start_date'], $w['start_period'], $w['end_date'], $w['end_period']);
            if ($halfDays->isEmpty()) {
                $this->fail("Créneau {$n} : la fin doit suivre le début.");
            }
            if ($halfDays->count() < $blocksCount) {
                $this->fail("Créneau {$n} : il doit couvrir au moins {$blocksCount} demi-journée(s).");
            }
            if ($halfDays->first()['start_at']->lt($bounds['earliest_at']) || $w['end_date'] > $bounds['last_date']) {
                $this->fail("Créneau {$n} : ces dates ne sont pas réservables chez cet expert.");
            }

            $keys[] = $halfDays->map(fn ($h) => $h['date'] . '|' . $h['period'])->all();
            $rows[] = [
                'position'     => $n,
                'start_date'   => $w['start_date'],
                'start_time'   => null,
                'start_period' => $w['start_period'],
                'end_date'     => $w['end_date'],
                'end_time'     => null,
                'end_period'   => $w['end_period'],
                'start_at'     => $halfDays->first()['start_at'],
                'end_at'       => $halfDays->last()['end_at'],
            ];
        }

        foreach ($keys as $i => $a) {
            foreach (array_slice($keys, $i + 1) as $b) {
                if (array_intersect($a, $b)) {
                    $this->fail('Les créneaux proposés se chevauchent.');
                }
            }
        }

        return $rows;
    }

    /* ===================== CHOIX DE L'EXPERT ===================== */

    /**
     * Horaires que l'expert peut choisir. Thème seulement (la consultation se calcule à partir
     * du créneau et de la durée : voir isValidConsultationStart).
     *
     * @return array<int, array{window:int, value:string, date:string, period:string, start_local:string, end_local:string, start_at:string, end_at:string, available:bool}>
     */
    public function themeOptions(Booking $booking): array
    {
        $windows = $this->windowsOf($booking);
        if ($booking->isConsultation() || $windows->isEmpty()) {
            return [];
        }

        $count = max(1, (int) $booking->blocks_count);
        $held  = $this->slots->heldKeys(
            $booking->expert_id,
            $windows->min(fn ($w) => $w->start_date->toDateString()),
            $windows->max(fn ($w) => $w->end_date->toDateString())
        );
        $now = now();
        $options = [];

        foreach ($windows as $w) {
            $halfDays = $this->halfDays($booking->expert_id, $w->start_date->toDateString(), $w->start_period, $w->end_date->toDateString(), $w->end_period)->values();
            for ($i = 0; $i + $count <= $halfDays->count(); $i++) {
                $run   = $halfDays->slice($i, $count)->values();
                $first = $run->first();
                $last  = $run->last();
                $options[] = [
                    'window'      => $w->position,
                    'value'       => $first['date'] . '|' . $first['period'],
                    'date'        => $first['date'],
                    'period'      => $first['period'],
                    'start_local' => $first['start_local'],
                    'end_local'   => $last['end_local'],
                    'end_date'    => $last['date'],
                    'start_at'    => $first['start_at']->toIso8601String(),
                    'end_at'      => $last['end_at']->toIso8601String(),
                    'available'   => $first['start_at']->gt($now)
                        && !$run->contains(fn ($h) => isset($held[$h['date'] . '|' . $h['period']])),
                ];
            }
        }

        return $options;
    }

    /**
     * Demi-journées à créer pour l'horaire choisi par l'expert.
     * Consultation : $value = « Y-m-d H:i ». Thème : $value = « Y-m-d|période ».
     *
     * @return array<int, array>
     */
    public function blocksFor(Booking $booking, ?string $value): array
    {
        if (!$value) {
            $this->fail('Choisissez l\'horaire du rendez-vous dans un des créneaux proposés.', 'window_start');
        }

        if ($booking->isConsultation()) {
            return [$this->consultationBlock($booking, $value)];
        }

        $option = collect($this->themeOptions($booking))->firstWhere('value', $value);
        if (!$option || !$option['available']) {
            $this->fail("Cet horaire n'est plus possible : choisissez-en un autre.", 'window_start');
        }

        $window = $this->windowsOf($booking)->firstWhere('position', $option['window']);
        $halfDays = $this->halfDays($booking->expert_id, $window->start_date->toDateString(), $window->start_period, $window->end_date->toDateString(), $window->end_period)->values();
        $index = $halfDays->search(fn ($h) => $h['date'] . '|' . $h['period'] === $value);

        return $halfDays->slice($index, max(1, (int) $booking->blocks_count))
            ->map(fn ($h) => $h + ['location_id' => null, 'location' => null, 'off_calendar' => true])
            ->values()
            ->all();
    }

    /** Dernier début possible parmi les créneaux : borne le délai de réponse. */
    public function latestStart(Booking $booking): ?Carbon
    {
        $windows = $this->windowsOf($booking);
        if ($windows->isEmpty()) {
            return null;
        }

        if ($booking->isConsultation()) {
            $minutes = (int) $booking->duration_minutes;

            return $windows->map(fn (BookingWindow $w) => $w->end_at->copy()->subMinutes($minutes))->max();
        }

        $last = collect($this->themeOptions($booking))->max('start_at');

        return $last ? Carbon::parse($last) : null;
    }

    /** Créneaux pour l'application ; « past » : plus aucun horaire possible dans ce créneau. */
    public function payload(Booking $booking): array
    {
        $windows = $this->windowsOf($booking);
        if ($windows->isEmpty()) {
            return [];
        }

        $now     = now();
        $minutes = (int) $booking->duration_minutes;
        $options = $booking->isConsultation() ? [] : collect($this->themeOptions($booking));

        return $windows->map(fn (BookingWindow $w) => [
            'position'     => $w->position,
            'start_date'   => $w->start_date->toDateString(),
            'start_time'   => $w->start_time,
            'start_period' => $w->start_period,
            'end_date'     => $w->end_date->toDateString(),
            'end_time'     => $w->end_time,
            'end_period'   => $w->end_period,
            'start_at'     => $w->start_at->toIso8601String(),
            'end_at'       => $w->end_at->toIso8601String(),
            'past'         => $booking->isConsultation()
                ? $w->end_at->copy()->subMinutes($minutes)->lte($now)
                : !$options->where('window', $w->position)->contains(fn ($o) => Carbon::parse($o['start_at'])->gt($now)),
        ])->values()->all();
    }

    /* ===================== OUTILS ===================== */

    private function consultationBlock(Booking $booking, string $value): array
    {
        if (!preg_match('/^(\d{4}-\d{2}-\d{2}) (([01]\d|2[0-3]):([0-5]\d))$/', $value, $m)) {
            $this->fail('Horaire invalide.', 'window_start');
        }
        if ((int) $m[4] % ConsultationService::STEP_MINUTES !== 0) {
            $this->fail("L'heure de début doit tomber sur une demi-heure.", 'window_start');
        }

        $settings = $this->setup->resolved($booking->expert_id);
        $tz       = $settings['timezone'];
        $minutes  = (int) $booking->duration_minutes;
        $start    = Carbon::parse($m[1] . ' ' . $m[2], $tz);
        $end      = $start->copy()->addMinutes($minutes);

        $fits = $this->windowsOf($booking)->contains(
            fn (BookingWindow $w) => $start->gte($w->start_at) && $end->lte($w->end_at)
        );
        if (!$fits) {
            $this->fail("Cet horaire ne tient pas dans les créneaux proposés.", 'window_start');
        }
        if ($start->lte(now())) {
            $this->fail('Cet horaire est déjà passé : choisissez-en un autre.', 'window_start');
        }

        return [
            'date'         => $m[1],
            'period'       => $m[2] < $settings['afternoon_start'] ? 'morning' : 'afternoon',
            'start_at'     => $start->copy()->utc(),
            'end_at'       => $end->copy()->utc(),
            'start_local'  => $m[2],
            'end_local'    => $end->format('H:i'),
            'location_id'  => null,
            'location'     => null,
            'off_calendar' => true,
        ];
    }

    /**
     * Demi-journées consécutives, bornes incluses, aux horaires de l'expert.
     *
     * @return Collection<int, array{date:string, period:string, start_at:Carbon, end_at:Carbon, start_local:string, end_local:string}>
     */
    public function halfDays(int $expertId, string $fromDate, string $fromPeriod, string $toDate, string $toPeriod): Collection
    {
        $settings = $this->setup->resolved($expertId);
        $tz       = $settings['timezone'];
        $list     = collect();

        if ($toDate < $fromDate) {
            return $list;
        }

        $day = Carbon::parse($fromDate);
        $end = Carbon::parse($toDate);
        while ($day->lte($end)) {
            $date = $day->toDateString();
            foreach (self::PERIODS as $p) {
                if ($date === $fromDate && $p === 'morning' && $fromPeriod === 'afternoon') {
                    continue;
                }
                if ($date === $toDate && $p === 'afternoon' && $toPeriod === 'morning') {
                    continue;
                }
                $list->push([
                    'date'        => $date,
                    'period'      => $p,
                    'start_at'    => Carbon::parse($date . ' ' . $settings[$p . '_start'], $tz)->utc(),
                    'end_at'      => Carbon::parse($date . ' ' . $settings[$p . '_end'], $tz)->utc(),
                    'start_local' => $settings[$p . '_start'],
                    'end_local'   => $settings[$p . '_end'],
                ]);
            }
            $day->addDay();
        }

        return $list;
    }

    private function windowsOf(Booking $booking): Collection
    {
        return $booking->relationLoaded('windows') ? $booking->windows : $booking->windows()->get();
    }

    private function assertCount(array $input): void
    {
        if (count($input) < 1 || count($input) > self::MAX_WINDOWS) {
            $this->fail('Proposez entre 1 et ' . self::MAX_WINDOWS . ' créneaux.');
        }
    }

    private function assertNoOverlap(array $rows, callable $overlaps): void
    {
        foreach ($rows as $i => $a) {
            foreach (array_slice($rows, $i + 1) as $b) {
                if ($overlaps($a, $b)) {
                    $this->fail('Les créneaux proposés se chevauchent.');
                }
            }
        }
    }

    private function fail(string $message, string $field = 'windows'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
