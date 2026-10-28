<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingBlock;
use App\Models\BookingEvent;
use App\Models\BookingCommissionRate;
use App\Models\BookingPolicy;
use App\Models\Expert;
use App\Models\ExpertOffer;
use App\Models\ExpertOfferMode;
use App\Models\Invoice;
use App\Models\User;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Passage d'une sélection de demi-journées à une demande de rendez-vous.
 *
 *  1. prepare()  : contrôle la sélection, crée la demande (awaiting_payment) et sa facture.
 *                  Rien n'est encore retenu : un paiement abandonné ne bloque personne.
 *  2. submit()   : après l'autorisation du paiement (ou directement si le paiement est
 *                  désactivé), retient les demi-journées et transmet la demande à l'expert.
 *                  Si une autre demande a pris un créneau entre-temps, l'index unique
 *                  sur hold_key le signale et la demande est annulée.
 *
 * Le montant est seulement réservé sur la carte (capture manuelle) : il est débité à
 * l'acceptation de l'expert, et libéré s'il refuse ou ne répond pas à temps.
 */
class BookingCheckoutService
{
    public const DEFAULT_TVA_RATE = 20.0;
    public const MESSAGE_MAX      = 300;
    public const ADDRESS_MAX      = 1000;

    /** Délai de réponse de l'expert : le plus court des deux. */
    public const RESPOND_WITHIN_HOURS       = 72;
    public const RESPOND_BEFORE_START_HOURS = 12;
    /** Repli du réglage Voyager booking.min_respond_hours. */
    public const DEFAULT_MIN_RESPOND_HOURS  = 2;

    public const TERMS_URL = 'https://www.orthomaster.fr/politique-de-confidentialit%C3%A9';

    public function __construct(private ExpertSlotService $slots, private StripeService $stripe)
    {
    }

    /* ===================== RÉGLAGES ===================== */

    public static function paymentEnabled(): bool
    {
        return (bool) setting('booking.payment_enabled');
    }

    /**
     * Temps de réponse minimal laissé à l'expert, quand la règle des 12 h tombe trop tôt
     * (demande envoyée tard le soir pour le lendemain matin).
     */
    public static function minRespondHours(): float
    {
        $value = setting('booking.min_respond_hours');

        return is_numeric($value) ? (float) $value : self::DEFAULT_MIN_RESPOND_HOURS;
    }

    /** Nom affiché et facturé : nom puis prénom, le prénom seulement s'il est renseigné. */
    public static function displayName(User $user): string
    {
        return trim(implode(' ', array_filter([$user->name, $user->first_name ?? null])));
    }

    public static function tvaRate(): float
    {
        $value = setting('booking.tva_rate');

        return is_numeric($value) ? (float) $value : self::DEFAULT_TVA_RATE;
    }

    /**
     * Montants d'une réservation : le prix du mode est le TTC de toute l'offre.
     * Le HT est déduit du TTC, et la TVA est la différence : HT + TVA retombe
     * toujours exactement sur le TTC affiché, sans écart d'un centime.
     *
     * @return array{amount_ht:float, tva_rate:float, tva_amount:float, amount_ttc:float, currency:string}
     */
    public static function pricing(ExpertOfferMode $mode, ?string $currency = null): array
    {
        $ttc  = round((float) $mode->price, 2);
        $rate = self::tvaRate();
        $ht   = $rate > 0 ? round($ttc / (1 + $rate / 100), 2) : $ttc;

        return [
            'amount_ht'  => $ht,
            'tva_rate'   => $rate,
            'tva_amount' => round($ttc - $ht, 2),
            'amount_ttc' => $ttc,
            'currency'   => $currency ?: 'eur',
        ];
    }

    /* ===================== ÉTAPE 1 : PRÉPARER ===================== */

    /**
     * @param array{offer_id:int, mode:string, blocks:array<int, array{date:string, period:string}>, address:string, message?:string|null} $input
     */
    public function prepare(User $user, array $input): Booking
    {
        $data = Validator::make($input, [
            'offer_id'        => ['required', 'integer'],
            'mode'            => ['required', 'in:' . implode(',', array_keys(ExpertOfferMode::MODE_LABELS))],
            'blocks'          => ['required', 'array', 'min:1'],
            'blocks.*.date'   => ['required', 'date_format:Y-m-d'],
            'blocks.*.period' => ['required', 'in:' . implode(',', ExpertBookingSetupService::PERIODS)],
            'address'         => ['required', 'string', 'max:' . self::ADDRESS_MAX],
            'message'         => ['nullable', 'string', 'max:' . self::MESSAGE_MAX],
        ], [
            'address.required' => 'Veuillez renseigner votre adresse de facturation.',
        ], [
            'mode'    => 'type de rendez-vous',
            'blocks'  => 'demi-journées',
            'address' => 'adresse',
            'message' => 'message',
        ])->validate();

        $offer = ExpertOffer::active()->find($data['offer_id']);
        if (!$offer) {
            throw ValidationException::withMessages(['offer_id' => "Ce thème n'est plus proposé."]);
        }

        $offerMode = $offer->activeMode($data['mode']);
        if (!$offerMode) {
            throw ValidationException::withMessages([
                'mode' => "Ce thème n'est plus proposé " . mb_strtolower(ExpertOfferMode::MODE_LABELS[$data['mode']]) . '.',
            ]);
        }

        $expert = Expert::find($offer->expert_id);
        if (!$expert || !$expert->user_id) {
            throw ValidationException::withMessages(['offer_id' => "Cet expert n'accepte pas de rendez-vous pour le moment."]);
        }
        if ((int) $expert->user_id === (int) $user->id) {
            throw ValidationException::withMessages(['offer_id' => 'Vous ne pouvez pas réserver votre propre thème.']);
        }

        $blocks = $this->resolveBlocks($expert->id, $offer, $offerMode, $data['blocks']);
        $price  = self::pricing($offerMode, $offer->currency);
        $paid   = self::paymentEnabled();

        // Une nouvelle tentative remplace la précédente restée sans paiement.
        $this->abandonPrevious($user->id, $offer->id);

        return DB::transaction(function () use ($user, $expert, $offer, $offerMode, $blocks, $price, $paid, $data) {
            $invoice = null;
            if ($paid) {
                $invoice = Invoice::create([
                    'user_id'        => $user->id,
                    'amount'         => $price['amount_ttc'],
                    'unit_price'     => $price['amount_ht'],
                    'amount_ht'      => $price['amount_ht'],
                    'tva'            => $price['tva_rate'],
                    'tva_amount'     => $price['tva_amount'],
                    'currency'       => $price['currency'],
                    'status'         => Invoice::STATUS_PENDING,
                    'client_name'    => self::displayName($user),
                    'client_email'   => $user->email,
                    'client_address' => trim($data['address']),
                ]);
            }

            $booking = Booking::create([
                'user_id'           => $user->id,
                'expert_id'         => $expert->id,
                'offer_id'          => $offer->id,
                'mode'              => $offerMode->mode,
                'invoice_id'        => $invoice?->id,
                'status'            => Booking::STATUS_AWAITING_PAYMENT,
                'offer_title'       => $offer->title,
                'blocks_count'      => $offer->blocks_count,
                'currency'          => $price['currency'],
                'amount_ht'         => $price['amount_ht'],
                'tva_rate'          => $price['tva_rate'],
                'tva_amount'        => $price['tva_amount'],
                'amount_ttc'        => $price['amount_ttc'],
                'payment_required'  => $paid,
                'commission_rate'   => BookingCommissionRate::rateFor($expert->id),
                'booking_policy_id' => BookingPolicy::currentFor(null, $offerMode->mode)?->id,
                'client_address'    => trim($data['address']),
                'message'           => isset($data['message']) ? (trim($data['message']) ?: null) : null,
            ]);

            foreach ($blocks as $block) {
                $booking->blocks()->create([
                    'expert_id'   => $expert->id,
                    'date'        => $block['date'],
                    'period'      => $block['period'],
                    'start_local' => $block['start_local'],
                    'end_local'   => $block['end_local'],
                    'start_at'    => $block['start_at'],
                    'end_at'      => $block['end_at'],
                    'location_id' => $block['location_id'],
                    'location'    => $block['location']?->snapshot(),
                ]);
            }

            return $booking->load('blocks');
        });
    }

    /**
     * Vérifie que la sélection correspond à l'offre et que chaque demi-journée est encore libre.
     *
     * @return array<int, array{date:string, period:string, start_at:Carbon, end_at:Carbon, start_local:string, end_local:string, location_id:?int, location:?\App\Models\ExpertLocation}>
     */
    private function resolveBlocks(int $expertId, ExpertOffer $offer, ExpertOfferMode $offerMode, array $requested): array
    {
        $keys = collect($requested)->map(fn ($b) => $b['date'] . '|' . $b['period'])->unique()->sort()->values();

        if ($keys->count() !== (int) $offer->blocks_count) {
            throw ValidationException::withMessages([
                'blocks' => "Ce thème demande {$offer->blocks_count} demi-journée(s) distincte(s).",
            ]);
        }

        $from = substr($keys->first(), 0, 10);
        $to   = substr($keys->last(), 0, 10);

        $free = $this->slots->availableBlocks($expertId, $from, $to, false, $offerMode)
            ->keyBy(fn ($b) => $b['date'] . '|' . $b['period']);

        $missing = $keys->reject(fn ($k) => $free->has($k));
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'blocks' => "Une ou plusieurs demi-journées ne sont plus disponibles. Merci d'en choisir d'autres.",
            ]);
        }

        return $keys->map(fn ($k) => $free->get($k))->all();
    }

    /** Demandes du même utilisateur, pour le même thème, restées sans paiement. */
    private function abandonPrevious(int $userId, int $offerId): void
    {
        Booking::where('user_id', $userId)
            ->where('offer_id', $offerId)
            ->where('status', Booking::STATUS_AWAITING_PAYMENT)
            ->get()
            ->each(fn (Booking $b) => $this->cancel($b));
    }

    /* ===================== ÉTAPE 2 : TRANSMETTRE ===================== */

    /**
     * Retient les demi-journées et transmet la demande à l'expert.
     * Renvoie false si un créneau a été pris entre-temps (rien n'est alors retenu).
     *
     * La ligne est verrouillée : l'application (confirmBookingPayment) et le webhook Stripe
     * peuvent arriver en même temps, un seul transmet, l'autre voit la demande déjà « pending ».
     *
     * $source : « app » ou « webhook », noté dans le journal.
     */
    public function submit(Booking $booking, string $source = 'app'): bool
    {
        try {
            $done = DB::transaction(function () use ($booking) {
                $locked = Booking::whereKey($booking->id)->lockForUpdate()->first();
                if (!$locked || $locked->status !== Booking::STATUS_AWAITING_PAYMENT) {
                    return false;
                }

                $locked->load('blocks');
                foreach ($locked->blocks as $block) {
                    $block->update([
                        'hold_key' => BookingBlock::holdKey($locked->expert_id, $block->date->toDateString(), $block->period),
                    ]);
                }

                $locked->update([
                    'status'       => Booking::STATUS_PENDING,
                    'requested_at' => now(),
                    'respond_by'   => $this->respondBy($locked),
                ]);

                return true;
            });
        } catch (QueryException $e) {
            // 23000 : l'index unique hold_key — une autre demande a retenu un des créneaux.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            return false;
        }

        $booking->refresh()->load('blocks');

        if (!$done) {
            // Déjà transmise par l'autre canal : succès ; sinon (annulée…) : échec.
            return in_array($booking->status, Booking::HOLDING_STATUSES, true);
        }

        BookingEvent::log($booking, 'submitted', BookingEvent::ACTOR_USER, [
            'amount_ttc'       => (float) $booking->amount_ttc,
            'payment_required' => $booking->payment_required,
            'respond_by'       => $booking->respond_by?->toIso8601String(),
            'source'           => $source,
        ]);

        return true;
    }

    /**
     * Paiement autorisé (« requires_capture ») ou débité (« succeeded ») : transmet la demande
     * et met la facture à jour. Créneau pris entre-temps : la demande est annulée et
     * l'empreinte libérée, rien n'est débité. Utilisé par l'application et par le webhook.
     */
    public function confirmPaid(Booking $booking, string $paymentStatus, string $source = 'app'): bool
    {
        if (!$this->submit($booking, $source)) {
            $this->cancel($booking);

            return false;
        }

        $invoice = $booking->invoice_id ? Invoice::find($booking->invoice_id) : null;
        if ($invoice && $invoice->status !== Invoice::STATUS_PAID) {
            if ($paymentStatus === 'succeeded') {
                $invoice->update(['status' => Invoice::STATUS_PAID, 'paid_at' => now()]);
            } elseif ($invoice->status !== Invoice::STATUS_AUTHORIZED) {
                $invoice->update(['status' => Invoice::STATUS_AUTHORIZED, 'paid_at' => null]);
            }
        }

        return true;
    }

    /**
     * Le plus tôt entre 72 h après la demande et 12 h avant la première demi-journée,
     * mais jamais moins que le temps de réponse minimal (réglable dans Voyager), et
     * jamais après le début de la première demi-journée.
     */
    public function respondBy(Booking $booking): Carbon
    {
        $now   = now();
        $first = $booking->blocks->min('start_at');
        $start = $first ? Carbon::parse($first) : null;

        $limit = $now->copy()->addHours(self::RESPOND_WITHIN_HOURS);
        if ($start) {
            $limit = $limit->min($start->copy()->subHours(self::RESPOND_BEFORE_START_HOURS));
        }

        $floor = $now->copy()->addMinutes((int) round(self::minRespondHours() * 60));
        $limit = $limit->max($floor);

        return $start ? $limit->min($start) : $limit;
    }

    /**
     * Annule une demande pas encore tranchée (en attente de paiement ou de l'expert),
     * libère ses créneaux et l'empreinte bancaire éventuelle.
     *
     * La ligne est verrouillée, appel Stripe compris : si l'expert accepte au même moment,
     * une seule des deux opérations passe. Renvoie false si la demande n'était plus
     * annulable (déjà acceptée, refusée, expirée ou annulée) ; rien n'est alors modifié.
     *
     * L'échec de l'annulation Stripe n'empêche pas l'annulation locale : une empreinte
     * non capturée expire d'elle-même au bout de 7 jours.
     */
    public function cancel(Booking $booking, string $by = Booking::CANCELLED_BY_SYSTEM, array $context = []): bool
    {
        $wasSubmitted = false;

        $done = DB::transaction(function () use ($booking, $by, &$wasSubmitted) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->first();
            if (!$locked || !in_array($locked->status, [Booking::STATUS_AWAITING_PAYMENT, Booking::STATUS_PENDING], true)) {
                return false;
            }

            $wasSubmitted = $locked->requested_at !== null;

            $invoice = $locked->invoice_id ? Invoice::find($locked->invoice_id) : null;
            if ($invoice && $invoice->stripe_payment_intent_id
                && !in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED], true)) {
                $this->stripe->cancelPayment($invoice->stripe_payment_intent_id);
            }

            $locked->blocks()->update(['hold_key' => null]);
            $locked->update([
                'status'       => Booking::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $by,
            ]);

            if ($invoice && $invoice->status !== Invoice::STATUS_PAID) {
                $invoice->update(['status' => Invoice::STATUS_CANCELLED]);
            }

            return true;
        });

        $booking->refresh();

        // Les paniers abandonnés avant paiement ne polluent pas le journal.
        if ($done && $wasSubmitted) {
            BookingEvent::log(
                $booking,
                'cancelled',
                $context['actor'] ?? ($by === Booking::CANCELLED_BY_SYSTEM ? BookingEvent::ACTOR_SYSTEM : $by),
                array_filter([
                    'reason'   => $context['reason'] ?? null,
                    'source'   => $context['source'] ?? null,
                    'released' => true,
                ]),
                $context['on_behalf_of'] ?? null,
                $context['admin_id'] ?? null
            );
        }

        return $done;
    }

    /* ===================== RÉPONSES API ===================== */

    public static function payload(Booking $booking): array
    {
        return [
            'id'               => $booking->id,
            'status'           => $booking->status,
            'offer_title'      => $booking->offer_title,
            'mode'             => $booking->mode,
            'amount_ht'        => (float) $booking->amount_ht,
            'tva_rate'         => (float) $booking->tva_rate,
            'tva_amount'       => (float) $booking->tva_amount,
            'amount_ttc'       => (float) $booking->amount_ttc,
            'currency'         => $booking->currency,
            'payment_required' => $booking->payment_required,
            'respond_by'       => $booking->respond_by?->toIso8601String(),
            'blocks'           => $booking->blocks->map(fn (BookingBlock $b) => [
                'date'        => $b->date->toDateString(),
                'period'      => $b->period,
                'start_local' => $b->start_local,
                'end_local'   => $b->end_local,
                // Les informations d'accès ne sont montrées qu'une fois le rendez-vous accepté (3c).
                'location'    => $b->location ? array_merge(
                    $b->location,
                    ['access_details' => $booking->status === Booking::STATUS_ACCEPTED ? ($b->location['access_details'] ?? null) : null]
                ) : null,
            ])->values(),
        ];
    }
}