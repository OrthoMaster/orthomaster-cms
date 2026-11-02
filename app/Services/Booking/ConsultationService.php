<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingBlock;
use App\Models\ExpertBookingSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Consultation (sur mesure) : le demandeur décrit son sujet et réserve un temps d'échange
 * en ligne avec un expert précis, par tranches de 30 minutes.
 *
 *  - Prix : prix TTC de 30 minutes fixé par l'expert × nombre de tranches.
 *  - Horaire : dans une demi-journée ouverte du calendrier (heure de début toutes les 30 minutes,
 *    fin avant la fin de la demi-journée), ou hors calendrier entre 7 h et 21 h.
 *  - Rien n'est retenu : une consultation ne bloque ni les thèmes ni d'autres consultations.
 *    L'expert est averti des chevauchements avec ses rendez-vous déjà acceptés (conflicts()).
 */
class ConsultationService
{
    public const STEP_MINUTES        = 30;
    public const DEFAULT_MAX_MINUTES = 180;
    public const MAX_MINUTES_LIMIT   = 480;
    public const PRICE_MAX           = 10000;
    public const SUBJECT_MAX         = 2000;

    /** Hors calendrier : heures de début possibles. */
    public const OFF_CALENDAR_FIRST_START = '07:00';
    public const OFF_CALENDAR_LAST_START  = '21:00';

    /** Libellé enregistré dans bookings.offer_title (listes, CMS, factures). */
    public const TITLE = 'Consultation sur mesure';

    public function __construct(private ExpertSlotService $slots, private ExpertBookingSetupService $setup)
    {
    }

    /* ===================== RÉGLAGES DE L'EXPERT ===================== */

    /** @return array{enabled:bool, price:?float, max_minutes:int} */
    public function settings(int $expertId): array
    {
        $own = ExpertBookingSetting::where('expert_id', $expertId)->first();

        return [
            'enabled'     => (bool) ($own->consultation_enabled ?? false),
            'price'       => $own?->consultation_price !== null ? (float) $own->consultation_price : null,
            'max_minutes' => (int) ($own?->consultation_max_minutes ?: self::DEFAULT_MAX_MINUTES),
        ];
    }

    /** Proposée aux demandeurs : activée, avec un prix, et prise de rendez-vous de l'expert activée. */
    public function isOpen(int $expertId): bool
    {
        $s = $this->settings($expertId);

        return $s['enabled'] && $s['price'] !== null && $this->setup->resolved($expertId)['enabled_flg'];
    }

    /**
     * Mise à jour partielle : un champ absent garde sa valeur.
     * Corps : enabled?, price?, max_minutes?
     */
    public function save(int $expertId, array $input): array
    {
        $current = $this->settings($expertId);

        $data = Validator::make($input, [
            'enabled'     => ['sometimes', 'boolean'],
            'price'       => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:' . self::PRICE_MAX],
            'max_minutes' => ['sometimes', 'nullable', 'integer', 'min:' . self::STEP_MINUTES, 'max:' . self::MAX_MINUTES_LIMIT],
        ], [], [
            'enabled'     => 'activation',
            'price'       => 'prix pour 30 minutes',
            'max_minutes' => 'durée maximale',
        ])->validate();

        $next = [
            'enabled'     => array_key_exists('enabled', $data) ? (bool) $data['enabled'] : $current['enabled'],
            'price'       => array_key_exists('price', $data) ? ($data['price'] === null ? null : round((float) $data['price'], 2)) : $current['price'],
            'max_minutes' => array_key_exists('max_minutes', $data) ? ($data['max_minutes'] ?? self::DEFAULT_MAX_MINUTES) : $current['max_minutes'],
        ];

        if ($next['max_minutes'] % self::STEP_MINUTES !== 0) {
            throw ValidationException::withMessages(['max_minutes' => 'La durée maximale doit être un multiple de 30 minutes.']);
        }
        if ($next['enabled'] && $next['price'] === null) {
            throw ValidationException::withMessages(['price' => 'Indiquez votre prix pour 30 minutes avant d\'activer les consultations.']);
        }

        ExpertBookingSetting::updateOrCreate(
            ['expert_id' => $expertId],
            [
                'consultation_enabled'     => $next['enabled'],
                'consultation_price'       => $next['price'],
                'consultation_max_minutes' => $next['max_minutes'],
            ]
        );

        return $this->settings($expertId);
    }

    /** Durées proposées : 30, 60… jusqu'au maximum de l'expert. */
    public function durations(int $expertId): array
    {
        return range(self::STEP_MINUTES, $this->settings($expertId)['max_minutes'], self::STEP_MINUTES);
    }

    /** Forme publique (fiche de l'expert, liste) ; null si la consultation n'est pas proposée. */
    public function publicPayload(int $expertId): ?array
    {
        if (!$this->isOpen($expertId)) {
            return null;
        }

        $s = $this->settings($expertId);

        return [
            'price_30'     => $s['price'],
            'currency'     => 'eur',
            'step_minutes' => self::STEP_MINUTES,
            'max_minutes'  => $s['max_minutes'],
            'durations'    => $this->durations($expertId),
            'subject_max'  => self::SUBJECT_MAX,
            'off_calendar' => [
                'first_start' => self::OFF_CALENDAR_FIRST_START,
                'last_start'  => self::OFF_CALENDAR_LAST_START,
            ],
        ];
    }

    /** Montants : prix de 30 minutes × nombre de tranches, TTC. */
    public function pricing(int $expertId, int $minutes): array
    {
        $price = (float) ($this->settings($expertId)['price'] ?? 0);

        return BookingCheckoutService::pricingFromTtc($price * ($minutes / self::STEP_MINUTES));
    }

    /* ===================== HORAIRES ===================== */

    /**
     * Heures de début possibles pour une durée, regroupées par jour, dans les demi-journées
     * ouvertes du calendrier général (en ligne). Les demi-journées déjà retenues restent proposées.
     *
     * @return array<int, array{date:string, times:array}>
     */
    public function slots(int $expertId, ?string $from, ?string $to, int $minutes): array
    {
        $this->assertDuration($expertId, $minutes);
        $tz = $this->setup->resolved($expertId)['timezone'];

        return $this->slots->availableBlocks($expertId, $from, $to, false, null, true)
            ->groupBy('date')
            ->map(fn (Collection $blocks, string $date) => [
                'date'  => $date,
                'times' => $blocks->flatMap(fn (array $b) => $this->startsIn($b, $minutes, $tz))->values(),
            ])
            ->filter(fn (array $day) => $day['times']->isNotEmpty())
            ->values()
            ->all();
    }

    /**
     * Horaire demandé, vérifié : dans le calendrier, ou hors calendrier entre 7 h et 21 h.
     *
     * @return array{date:string, period:string, start_at:Carbon, end_at:Carbon, start_local:string, end_local:string, location_id:null, location:null, off_calendar:bool}
     */
    public function resolve(int $expertId, string $date, string $startTime, int $minutes): array
    {
        $this->assertDuration($expertId, $minutes);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $startTime)) {
            throw ValidationException::withMessages(['start_time' => 'Horaire invalide.']);
        }
        if ((int) substr($startTime, 3, 2) % self::STEP_MINUTES !== 0) {
            throw ValidationException::withMessages(['start_time' => "L'heure de début doit tomber sur une demi-heure."]);
        }

        $settings = $this->setup->resolved($expertId);
        $bounds   = $this->slots->bounds($expertId);
        $tz       = $settings['timezone'];
        $start    = Carbon::parse($date . ' ' . $startTime, $tz);
        $end      = $start->copy()->addMinutes($minutes);

        if ($start->lt($bounds['earliest_at']) || $date > $bounds['last_date']) {
            throw ValidationException::withMessages(['date' => "Cette date n'est pas réservable chez cet expert."]);
        }

        // Dans une demi-journée ouverte : horaire du calendrier.
        $inCalendar = $this->slots->availableBlocks($expertId, $date, $date, false, null, true)
            ->contains(fn (array $b) => $start->gte($b['start_at']) && $end->lte($b['end_at']));

        if (!$inCalendar && ($startTime < self::OFF_CALENDAR_FIRST_START || $startTime > self::OFF_CALENDAR_LAST_START)) {
            throw ValidationException::withMessages([
                'start_time' => 'Hors calendrier, la consultation doit commencer entre 7 h et 21 h.',
            ]);
        }

        return [
            'date'         => $date,
            // Rattachée à la demi-journée de son début, pour les listes et le lien Meet.
            'period'       => $startTime < $settings['afternoon_start'] ? 'morning' : 'afternoon',
            'start_at'     => $start->copy()->utc(),
            'end_at'       => $end->copy()->utc(),
            'start_local'  => $startTime,
            'end_local'    => $end->format('H:i'),
            'location_id'  => null,
            'location'     => null,
            'off_calendar' => !$inCalendar,
        ];
    }

    /* ===================== CHEVAUCHEMENTS ===================== */

    /**
     * Rendez-vous déjà acceptés de l'expert qui chevauchent une demande : simple avertissement,
     * rien n'est bloqué. Vide pour une demande déjà tranchée.
     */
    public function conflicts(Booking $booking): array
    {
        if ($booking->status !== Booking::STATUS_PENDING) {
            return [];
        }

        $blocks = $booking->relationLoaded('blocks') ? $booking->blocks : $booking->blocks()->get();
        if ($blocks->isEmpty()) {
            return [];
        }

        return BookingBlock::query()
            ->join('bookings', 'bookings.id', '=', 'booking_blocks.booking_id')
            ->where('booking_blocks.expert_id', $booking->expert_id)
            ->where('bookings.id', '!=', $booking->id)
            ->where('bookings.status', Booking::STATUS_ACCEPTED)
            ->where(function ($q) use ($blocks) {
                foreach ($blocks as $b) {
                    $q->orWhere(fn ($w) => $w
                        ->where('booking_blocks.start_at', '<', $b->end_at)
                        ->where('booking_blocks.end_at', '>', $b->start_at));
                }
            })
            ->orderBy('booking_blocks.start_at')
            ->get(['booking_blocks.*', 'bookings.offer_title', 'bookings.kind'])
            ->map(fn (BookingBlock $b) => [
                'booking_id'  => $b->booking_id,
                'title'       => $b->offer_title,
                'kind'        => $b->kind,
                'date'        => $b->date->toDateString(),
                'start_local' => $b->start_local,
                'end_local'   => $b->end_local,
            ])
            ->values()
            ->all();
    }

    /* ===================== OUTILS ===================== */

    private function assertDuration(int $expertId, int $minutes): void
    {
        if (!in_array($minutes, $this->durations($expertId), true)) {
            throw ValidationException::withMessages(['duration_minutes' => 'Durée non proposée par cet expert.']);
        }
    }

    /** Heures de début dans une demi-journée, toutes les 30 minutes, fin incluse dans la demi-journée. */
    private function startsIn(array $block, int $minutes, string $tz): array
    {
        $times = [];
        $start = $block['start_at']->copy();

        while ($start->copy()->addMinutes($minutes)->lte($block['end_at'])) {
            $local   = $start->copy()->setTimezone($tz);
            $times[] = [
                'start_local' => $local->format('H:i'),
                'end_local'   => $local->copy()->addMinutes($minutes)->format('H:i'),
                'start_at'    => $start->copy()->utc()->toIso8601ZuluString(),
                'end_at'      => $start->copy()->addMinutes($minutes)->utc()->toIso8601ZuluString(),
            ];
            $start->addMinutes(self::STEP_MINUTES);
        }

        return $times;
    }
}
