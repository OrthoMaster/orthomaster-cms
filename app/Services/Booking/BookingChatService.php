<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingBlock;
use App\Models\BookingConversation;
use App\Models\BookingEvent;
use App\Models\BookingMessage;
use App\Models\Expert;
use App\Models\ExpertOffer;
use App\Models\ExpertOfferMode;
use App\Support\Booking\ChatContactFilter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Messagerie expert ↔ demandeur : un fil par couple, tous rendez-vous confondus.
 *
 *  - Les événements des rendez-vous (demande, acceptation, refus, expiration, annulation)
 *    sont recopiés du journal dans le fil, au moment où ils se produisent.
 *  - Le demandeur peut ouvrir le fil depuis un thème, avant toute réservation
 *    (« Échanger avec l'expert ») : un encadré rappelle à l'expert de quel thème il s'agit.
 *  - Sinon, écrire devient possible au premier rendez-vous accepté ; ensuite sans limite.
 *  - Téléphones, e-mails et liens sont refusés (ChatContactFilter).
 *  - Non-lus : messages plus récents que le dernier lu, hors ceux de la partie elle-même.
 *
 * L'administration ne fait que lire (CMS).
 */
class BookingChatService
{
    public const BODY_MAX = 2000;

    /** Premier chargement d'un fil et pages d'historique. */
    public const PAGE_SIZE = 30;

    /** Réponse d'une relève (polling) : au-delà, l'application recharge le fil. */
    public const POLL_MAX = 100;

    /** Encadré « contact depuis un thème », inscrit quand le demandeur ouvre le fil depuis une offre. */
    public const EVENT_INQUIRY = 'inquiry';

    /** Types du journal recopiés dans le fil ; le reste (Meet, remboursements…) reste interne. */
    public const EVENT_TYPES = ['submitted', 'accepted', 'declined', 'expired', 'cancelled'];

    /** Dates citées dans un événement ; au-delà : « +N ». */
    private const EVENT_DATES_SHOWN = 3;

    private const PERIODS = ['morning' => 'matin', 'afternoon' => 'après-midi'];

    /* ===================== FILS ===================== */

    /**
     * Fil du couple, créé au besoin. insertOrIgnore : deux événements simultanés
     * (webhook + application) ne créent pas deux fils.
     */
    public function conversationFor(int $expertId, int $userId): BookingConversation
    {
        DB::table('booking_conversations')->insertOrIgnore([
            'expert_id'  => $expertId,
            'user_id'    => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return BookingConversation::where('expert_id', $expertId)->where('user_id', $userId)->firstOrFail();
    }

    /**
     * Filet de sécurité pour les fils créés sans passer par l'événement « accepted »
     * (rendez-vous antérieurs à la messagerie, reprise pas encore lancée) : ouvre le fil
     * si le couple a déjà eu un rendez-vous accepté, même annulé ensuite.
     */
    public function syncOpened(BookingConversation $conversation): void
    {
        if ($conversation->opened_at) {
            return;
        }

        // Acceptation = réponse sans motif de refus (un refus renseigne toujours le motif).
        $acceptedAt = Booking::where('expert_id', $conversation->expert_id)
            ->where('user_id', $conversation->user_id)
            ->whereNotNull('responded_at')
            ->whereNull('decline_reason_id')
            ->min('responded_at');

        if ($acceptedAt) {
            DB::table('booking_conversations')->where('id', $conversation->id)->whereNull('opened_at')
                ->update(['opened_at' => $acceptedAt]);
            $conversation->opened_at = Carbon::parse($acceptedAt);
        }
    }

    /**
     * Côté de l'utilisateur connecté dans ce fil, ou null s'il n'y participe pas.
     * Un expert peut aussi être demandeur ailleurs : on regarde le fil, pas le compte.
     */
    public function sideOf(BookingConversation $conversation, int $userId): ?string
    {
        if ($conversation->user_id === $userId) {
            return BookingConversation::SIDE_USER;
        }

        $expertUserId = DB::table('experts')->where('id', $conversation->expert_id)->value('user_id');

        return (int) $expertUserId === $userId ? BookingConversation::SIDE_EXPERT : null;
    }

    /**
     * Fils de l'utilisateur, des deux côtés, le plus récent d'abord.
     * Requête de base partagée par la liste et le compteur de non-lus.
     */
    public function conversationsQuery(int $userId)
    {
        $expertId = DB::table('experts')->where('user_id', $userId)->value('id');

        return BookingConversation::query()
            ->where(function ($q) use ($userId, $expertId) {
                $q->where('user_id', $userId);
                if ($expertId) {
                    $q->orWhere('expert_id', $expertId);
                }
            });
    }

    /* ===================== CONTACT DEPUIS UN THÈME ===================== */

    /**
     * Le demandeur contacte l'expert depuis un thème, avant de réserver.
     * Ouvre le fil à l'écriture et y inscrit un encadré « à propos de tel thème »,
     * sauf si le dernier message du fil est déjà cet encadré pour ce thème (double appui).
     *
     * @throws ValidationException thème indisponible, ou son propre thème
     */
    public function startFromOffer(int $userId, int $offerId, ?string $mode = null): BookingConversation
    {
        $offer = ExpertOffer::active()->with('modes')->find($offerId);
        if (!$offer) {
            throw ValidationException::withMessages(['offer_id' => "Ce thème n'est plus proposé."]);
        }

        $expertUserId = DB::table('experts')->where('id', $offer->expert_id)->value('user_id');
        if (!$expertUserId) {
            throw ValidationException::withMessages(['offer_id' => "Cet expert ne peut pas être contacté pour le moment."]);
        }
        if ((int) $expertUserId === $userId) {
            throw ValidationException::withMessages(['offer_id' => 'Vous ne pouvez pas vous contacter vous-même.']);
        }

        // Format consulté au moment du contact, s'il est encore proposé.
        $mode = $mode && $offer->activeMode($mode) ? $mode : null;

        $conversation = $this->conversationFor($offer->expert_id, $userId);

        DB::transaction(function () use ($conversation, $offer, $mode, $userId) {
            $locked = BookingConversation::whereKey($conversation->id)->lockForUpdate()->first();

            if (!$locked->opened_at) {
                $locked->update(['opened_at' => now()]);
            }

            $last = $locked->last_message_id ? BookingMessage::find($locked->last_message_id) : null;
            $sameInquiry = $last
                && $last->event_type === self::EVENT_INQUIRY
                && (int) ($last->data['offer_id'] ?? 0) === $offer->id;
            if ($sameInquiry) {
                return;
            }

            $message = BookingMessage::create([
                'conversation_id' => $locked->id,
                'kind'            => BookingMessage::KIND_EVENT,
                'sender'          => BookingMessage::SENDER_USER,
                'sender_user_id'  => $userId,
                'event_type'      => self::EVENT_INQUIRY,
                // Copie au moment du contact : un thème renommé plus tard ne change pas l'historique.
                'data'            => array_filter([
                    'offer_id'       => $offer->id,
                    'offer_title'    => $offer->title,
                    'duration_label' => $offer->duration_label,
                    'mode'           => $mode,
                ], fn ($v) => $v !== null),
                'created_at'      => now(),
            ]);

            $this->touch($locked, $message);
            $this->markRead($locked, BookingConversation::SIDE_USER, $message->id);
        });

        return $conversation->refresh();
    }

    /* ===================== ÉVÉNEMENTS ===================== */

    /**
     * Recopie un événement du journal dans le fil. Appelé après validation de la
     * transaction (observer) et par la reprise : l'unicité de booking_event_id
     * évite les doublons dans les deux cas.
     */
    public function recordEvent(BookingEvent $event): ?BookingMessage
    {
        if (!in_array($event->type, self::EVENT_TYPES, true)) {
            return null;
        }

        $booking = Booking::with('blocks')->find($event->booking_id);
        if (!$booking || !$booking->requested_at) {
            return null;
        }

        $conversation = $this->conversationFor($booking->expert_id, $booking->user_id);

        $data = $event->data ?? [];
        $message = null;

        DB::transaction(function () use ($conversation, $booking, $event, $data, &$message) {
            $inserted = DB::table('booking_messages')->insertOrIgnore([
                'conversation_id'  => $conversation->id,
                'kind'             => BookingMessage::KIND_EVENT,
                'sender'           => $this->eventSender($event),
                'booking_id'       => $booking->id,
                'booking_event_id' => $event->id,
                'event_type'       => $event->type,
                // Copie au moment de l'événement : un thème renommé plus tard ne change pas l'historique.
                'data'             => json_encode(array_filter([
                    'offer_title' => $booking->offer_title,
                    'mode'        => $booking->mode,
                    'blocks'      => $booking->blocks
                        ->map(fn (BookingBlock $b) => ['date' => $b->date->toDateString(), 'period' => $b->period])
                        ->values()
                        ->all(),
                    'reason'      => $event->type === 'declined' ? ($data['reason'] ?? null) : null,
                    'note'        => $event->type === 'declined' ? ($data['note'] ?? null) : null,
                ], fn ($v) => $v !== null)),
                // Heure de l'événement, pas de la recopie (la reprise passe après coup).
                'created_at'       => $event->created_at ?? now(),
            ]);

            if (!$inserted) {
                return;
            }

            $message = BookingMessage::where('booking_event_id', $event->id)->first();
            $this->touch($conversation, $message);

            if ($event->type === 'accepted' && !$conversation->opened_at) {
                DB::table('booking_conversations')
                    ->where('id', $conversation->id)
                    ->whereNull('opened_at')
                    ->update(['opened_at' => $event->created_at ?? now()]);
            }
        });

        return $message;
    }

    /**
     * Partie à qui l'événement est attribué dans le fil. L'admin qui agit à la demande
     * d'une partie est montré comme cette partie (« X a annulé »), comme dans le journal.
     */
    private function eventSender(BookingEvent $event): string
    {
        $actor = $event->on_behalf_of ?: $event->actor;

        return in_array($actor, [BookingMessage::SENDER_USER, BookingMessage::SENDER_EXPERT, BookingMessage::SENDER_ADMIN], true)
            ? $actor
            : BookingMessage::SENDER_SYSTEM;
    }

    /* ===================== ENVOI ===================== */

    /**
     * Message écrit par une partie. $clientId : identifiant de l'application ; un renvoi
     * après coupure réseau rend le message déjà enregistré au lieu d'en créer un second.
     *
     * @throws ValidationException      texte vide, trop long ou fil pas encore ouvert
     * @throws ContactBlockedException  coordonnées détectées
     */
    public function send(BookingConversation $conversation, string $side, int $userId, string $body, ?string $clientId = null): BookingMessage
    {
        $body = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $body)));

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Le message est vide.']);
        }
        if (mb_strlen($body) > self::BODY_MAX) {
            throw ValidationException::withMessages(['body' => 'Le message ne doit pas dépasser ' . self::BODY_MAX . ' caractères.']);
        }
        if (!$conversation->isOpen()) {
            throw ValidationException::withMessages([
                'body' => "La messagerie s'ouvre quand vous contactez l'expert depuis un thème, ou dès qu'un rendez-vous est accepté.",
            ]);
        }

        if ($clientId) {
            $existing = BookingMessage::where('conversation_id', $conversation->id)->where('client_id', $clientId)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($reason = ChatContactFilter::check($body)) {
            DB::table('booking_conversations')->where('id', $conversation->id)->increment('blocked_attempts');

            throw new ContactBlockedException($reason);
        }

        return DB::transaction(function () use ($conversation, $side, $userId, $body, $clientId) {
            $message = BookingMessage::create([
                'conversation_id' => $conversation->id,
                'kind'            => BookingMessage::KIND_TEXT,
                'sender'          => $side,
                'sender_user_id'  => $userId,
                'body'            => $body,
                'client_id'       => $clientId,
                'created_at'      => now(),
            ]);

            $this->touch($conversation, $message);
            // Écrire, c'est avoir lu tout ce qui précède.
            $this->markRead($conversation, $side, $message->id);

            return $message;
        });
    }

    /** Dernier message du fil ; la condition garde le plus récent si deux écritures se croisent. */
    private function touch(BookingConversation $conversation, BookingMessage $message): void
    {
        DB::table('booking_conversations')
            ->where('id', $conversation->id)
            ->where(fn ($q) => $q->whereNull('last_message_id')->orWhere('last_message_id', '<', $message->id))
            ->update([
                'last_message_id' => $message->id,
                'last_message_at' => $message->created_at,
                'updated_at'      => now(),
            ]);
    }

    /* ===================== LECTURE ===================== */

    /**
     * Messages du fil.
     *  - $afterId  : relève, seulement les nouveaux (POLL_MAX au plus, du plus ancien au plus récent).
     *  - $beforeId : page d'historique plus ancienne.
     *  - aucun     : les derniers messages.
     *
     * @return array{messages: Collection<BookingMessage>, has_more: bool}
     */
    public function messages(BookingConversation $conversation, ?int $afterId = null, ?int $beforeId = null): array
    {
        $query = BookingMessage::where('conversation_id', $conversation->id);

        if ($afterId !== null) {
            $rows = $query->where('id', '>', $afterId)->orderBy('id')->limit(self::POLL_MAX + 1)->get();
            $hasMore = $rows->count() > self::POLL_MAX;

            return ['messages' => $rows->take(self::POLL_MAX)->values(), 'has_more' => $hasMore];
        }

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query->orderByDesc('id')->limit(self::PAGE_SIZE + 1)->get();

        return [
            'messages' => $rows->take(self::PAGE_SIZE)->reverse()->values(),
            'has_more' => $rows->count() > self::PAGE_SIZE,
        ];
    }

    /** Ne recule jamais : une relève lente ne doit pas faire réapparaître des non-lus. */
    public function markRead(BookingConversation $conversation, string $side, int $upToId): void
    {
        $column = $side === BookingConversation::SIDE_EXPERT ? 'expert_last_read_id' : 'user_last_read_id';

        DB::table('booking_conversations')
            ->where('id', $conversation->id)
            ->where($column, '<', $upToId)
            ->update([$column => $upToId]);

        $conversation->{$column} = max($conversation->{$column}, $upToId);
    }

    /**
     * Non-lus par fil pour un côté donné : messages plus récents que le dernier lu,
     * sauf ceux de la partie elle-même (y compris les événements qu'elle a déclenchés).
     *
     * @param  Collection<BookingConversation>  $conversations
     * @return array<int, int>  conversation_id => non-lus
     */
    public function unreadCounts(Collection $conversations, int $userId): array
    {
        $counts = [];

        foreach ($conversations as $conversation) {
            $side = $this->sideOf($conversation, $userId);
            if (!$side || (int) $conversation->last_message_id <= $conversation->lastReadId($side)) {
                $counts[$conversation->id] = 0;
                continue;
            }

            $counts[$conversation->id] = BookingMessage::where('conversation_id', $conversation->id)
                ->where('id', '>', $conversation->lastReadId($side))
                ->where('sender', '!=', $side)
                ->count();
        }

        return $counts;
    }

    /**
     * Total des non-lus pour le badge : une requête, quel que soit le nombre de fils.
     *
     * @return array{total: int, conversations: int}
     */
    public function unreadSummary(int $userId): array
    {
        $expertId = DB::table('experts')->where('user_id', $userId)->value('id');

        $sides = [['c.user_id', $userId, 'c.user_last_read_id', BookingConversation::SIDE_USER]];
        if ($expertId) {
            $sides[] = ['c.expert_id', $expertId, 'c.expert_last_read_id', BookingConversation::SIDE_EXPERT];
        }

        $total = 0;
        $threads = 0;
        foreach ($sides as [$owner, $ownerId, $readColumn, $side]) {
            $rows = DB::table('booking_conversations as c')
                ->join('booking_messages as m', function ($join) use ($readColumn) {
                    $join->on('m.conversation_id', '=', 'c.id')->on('m.id', '>', $readColumn);
                })
                ->where($owner, $ownerId)
                ->where('m.sender', '!=', $side)
                ->selectRaw('COUNT(*) as unread, COUNT(DISTINCT c.id) as threads')
                ->first();

            $total += (int) $rows->unread;
            $threads += (int) $rows->threads;
        }

        return ['total' => $total, 'conversations' => $threads];
    }

    /* ===================== TEXTES ===================== */

    /**
     * Texte d'un événement selon qui le lit.
     * $viewer : user | expert, ou null pour le CMS (troisième personne des deux côtés).
     */
    public function eventText(BookingMessage $message, BookingConversation $conversation, ?string $viewer): string
    {
        $data   = $message->data ?? [];
        $offer  = '« ' . ($data['offer_title'] ?? 'Rendez-vous') . ' »';
        $detail = $this->eventDetail($data);
        $user   = $conversation->user ? BookingCheckoutService::displayName($conversation->user) : 'Le confrère';
        $expert = $conversation->expert ? self::expertName($conversation->expert) : "L'expert";

        $isMe = fn (string $side) => $viewer === $side;
        $by = $message->sender;

        if ($message->event_type === self::EVENT_INQUIRY) {
            $text = $isMe('user')
                ? "Vous avez contacté {$expert} à propos du thème {$offer}"
                : ($isMe('expert') ? "{$user} vous contacte à propos de votre thème {$offer}" : "{$user} a contacté {$expert} à propos du thème {$offer}");

            return $text . ($detail ? ' — ' . $detail . '.' : '.');
        }

        $text = match ($message->event_type) {
            'submitted' => $isMe('user')
                ? "Vous avez demandé un rendez-vous {$offer}"
                : "{$user} a demandé un rendez-vous {$offer}",
            'accepted' => $isMe('expert')
                ? "Vous avez accepté la demande {$offer}"
                : ($isMe('user') ? "{$expert} a accepté votre demande {$offer}" : "{$expert} a accepté la demande {$offer}"),
            'declined' => $isMe('expert')
                ? "Vous avez refusé la demande {$offer}"
                : ($isMe('user') ? "{$expert} a refusé votre demande {$offer}" : "{$expert} a refusé la demande {$offer}"),
            'expired' => "La demande {$offer} a expiré sans réponse",
            'cancelled' => match ($by) {
                'user'   => $isMe('user') ? "Vous avez annulé le rendez-vous {$offer}" : "{$user} a annulé le rendez-vous {$offer}",
                'expert' => $isMe('expert') ? "Vous avez annulé le rendez-vous {$offer}" : "{$expert} a annulé le rendez-vous {$offer}",
                'admin'  => "Le rendez-vous {$offer} a été annulé par OrthoMaster",
                default  => "Le rendez-vous {$offer} a été annulé",
            },
            default => "Rendez-vous {$offer} mis à jour",
        };

        $text .= $detail ? ' — ' . $detail . '.' : '.';

        if ($message->event_type === 'declined' && !empty($data['reason'])) {
            $text .= ' Motif : ' . $data['reason'] . (!empty($data['note']) ? ' (« ' . $data['note'] . ' »)' : '') . '.';
        }

        return $text;
    }

    /** « en ligne, 12/11 matin, 13/11 après-midi +2 » */
    private function eventDetail(array $data): string
    {
        $parts = [];

        if (!empty($data['mode'])) {
            $parts[] = mb_strtolower(ExpertOfferMode::MODE_LABELS[$data['mode']] ?? $data['mode']);
        }

        $blocks = collect($data['blocks'] ?? []);
        if ($blocks->isNotEmpty()) {
            $dates = $blocks->take(self::EVENT_DATES_SHOWN)
                ->map(fn ($b) => Carbon::parse($b['date'])->format('d/m') . ' ' . (self::PERIODS[$b['period']] ?? ''))
                ->implode(', ');
            if ($blocks->count() > self::EVENT_DATES_SHOWN) {
                $dates .= ' +' . ($blocks->count() - self::EVENT_DATES_SHOWN);
            }
            $parts[] = $dates;
        }

        return implode(', ', $parts);
    }

    public static function expertName(Expert $expert): string
    {
        return trim(($expert->honorific ? $expert->honorific . ' ' : '') . $expert->name);
    }

    /* ===================== RÉPONSES API ===================== */

    public function messagePayload(BookingMessage $message, BookingConversation $conversation, ?string $viewer): array
    {
        $isEvent = $message->isEvent();

        return [
            'id'         => $message->id,
            'kind'       => $message->kind,
            'sender'     => $message->sender,
            // Les événements ne sont pas des bulles : jamais « à moi », même si je les ai déclenchés.
            'mine'       => !$isEvent && $viewer !== null && $message->sender === $viewer,
            'body'       => $isEvent ? $this->eventText($message, $conversation, $viewer) : $message->body,
            'event'      => $isEvent ? [
                'type'       => $message->event_type,
                'booking_id' => $message->booking_id,
                // Encadré « contact depuis un thème » : de quoi l'afficher en carte dans le fil.
                'offer'      => $message->event_type === self::EVENT_INQUIRY ? [
                    'id'             => $message->data['offer_id'] ?? null,
                    'title'          => $message->data['offer_title'] ?? null,
                    'duration_label' => $message->data['duration_label'] ?? null,
                    'mode'           => $message->data['mode'] ?? null,
                ] : null,
            ] : null,
            'client_id'  => $message->client_id,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
