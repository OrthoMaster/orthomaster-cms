<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingConversation;
use App\Models\BookingMessage;
use App\Services\Booking\BookingChatService;
use App\Services\Booking\BookingCheckoutService;
use App\Services\Booking\ContactBlockedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Messagerie expert ↔ demandeur dans l'application. REST + relève périodique :
 * l'application appelle /messages?after_id= toutes les quelques secondes tant que
 * l'écran du fil est ouvert, et /conversations/unread pour le badge.
 *
 * Un même compte peut être des deux côtés (expert qui réserve un confrère) :
 * chaque fil dit le rôle de l'utilisateur, comme l'agenda (host = il est l'expert).
 */
class BookingChatController extends Controller
{
    public const PER_PAGE = 20;

    public const ROLE_HOST  = 'host';
    public const ROLE_GUEST = 'guest';

    /** Aperçu du dernier message dans la liste des fils. */
    private const PREVIEW_LENGTH = 120;

    public function __construct(private BookingChatService $chat)
    {
    }

    /**
     * Fils de l'utilisateur, le plus récent d'abord.
     * GET /booking/conversations?page=
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $page = $this->chat->conversationsQuery($userId)
            ->with(['user', 'expert'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        $conversations = collect($page->items());
        $unread = $this->chat->unreadCounts($conversations, $userId);
        $lastMessages = BookingMessage::whereIn('id', $conversations->pluck('last_message_id')->filter())->get()->keyBy('id');

        return $this->success([
            'items' => $conversations
                ->map(fn (BookingConversation $c) => $this->conversationPayload(
                    $c,
                    $userId,
                    $lastMessages->get($c->last_message_id),
                    $unread[$c->id] ?? 0
                ))
                ->values(),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
        ]);
    }

    /**
     * Badge : total des non-lus et nombre de fils concernés.
     * GET /booking/conversations/unread
     */
    public function unread(Request $request): JsonResponse
    {
        return $this->success($this->chat->unreadSummary($request->user()->id));
    }

    /**
     * « Échanger avec l'expert » depuis un thème, avant de réserver : fil ouvert à l'écriture,
     * encadré du thème en tête des nouveaux messages. Renvoie l'en-tête du fil.
     * POST /booking/conversations/start  { offer_id, mode? }
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'offer_id' => ['required', 'integer'],
            'mode'     => ['nullable', 'in:' . implode(',', array_keys(\App\Models\ExpertOfferMode::MODE_LABELS))],
        ]);

        $userId = $request->user()->id;
        $conversation = $this->chat->startFromOffer($userId, (int) $request->input('offer_id'), $request->input('mode'));

        return $this->success($this->detail($conversation, $userId), 201);
    }

    /**
     * Fil lié à un rendez-vous, pour le bouton « Message » de la fiche.
     * GET /booking/conversations/by-booking/{booking}
     */
    public function byBooking(Request $request, int $bookingId): JsonResponse
    {
        $userId = $request->user()->id;
        $expertId = DB::table('experts')->where('user_id', $userId)->value('id');

        $booking = Booking::submitted()
            ->where('id', $bookingId)
            ->where(function ($q) use ($userId, $expertId) {
                $q->where('user_id', $userId);
                if ($expertId) {
                    $q->orWhere('expert_id', $expertId);
                }
            })
            ->firstOrFail();

        $conversation = $this->chat->conversationFor($booking->expert_id, $booking->user_id);

        return $this->success($this->detail($conversation, $userId));
    }

    /**
     * En-tête du fil : l'autre partie, les rendez-vous du couple, l'état d'écriture.
     * GET /booking/conversations/{conversation}
     */
    public function show(Request $request, int $conversationId): JsonResponse
    {
        $conversation = $this->find($request, $conversationId);

        return $this->success($this->detail($conversation, $request->user()->id));
    }

    /**
     * Messages du fil, du plus ancien au plus récent.
     *  - ?after_id=  : relève (nouveaux messages seulement) ;
     *  - ?before_id= : page plus ancienne (défilement vers le haut) ;
     *  - sans paramètre : les derniers messages.
     * Lire le fil (premier chargement ou relève) marque ses messages comme lus.
     * GET /booking/conversations/{conversation}/messages
     */
    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $conversation = $this->find($request, $conversationId);
        $side = $this->chat->sideOf($conversation, $request->user()->id);

        $afterId  = $request->filled('after_id') ? (int) $request->input('after_id') : null;
        $beforeId = $request->filled('before_id') ? (int) $request->input('before_id') : null;

        $result = $this->chat->messages($conversation, $afterId, $beforeId);

        // L'historique plus ancien ne fait pas avancer la lecture.
        if ($beforeId === null && $result['messages']->isNotEmpty()) {
            $this->chat->markRead($conversation, $side, $result['messages']->last()->id);
        }

        $conversation->loadMissing(['user', 'expert']);

        return $this->success([
            'messages'           => $result['messages']
                ->map(fn (BookingMessage $m) => $this->chat->messagePayload($m, $conversation, $side))
                ->values(),
            'has_more'           => $result['has_more'],
            'can_write'          => $conversation->isOpen(),
            // Pour « Vu » sous mes derniers messages.
            'other_last_read_id' => $conversation->lastReadId(BookingConversation::otherSide($side)),
        ]);
    }

    /**
     * Envoie un message. client_id (facultatif, ≤ 64) : identifiant généré par l'application,
     * un renvoi après coupure réseau renvoie le message déjà créé.
     * POST /booking/conversations/{conversation}/messages  { body, client_id? }
     */
    public function send(Request $request, int $conversationId): JsonResponse
    {
        $request->validate([
            'body'      => ['required', 'string'],
            'client_id' => ['nullable', 'string', 'max:64'],
        ]);

        $conversation = $this->find($request, $conversationId);
        $userId = $request->user()->id;
        $side = $this->chat->sideOf($conversation, $userId);

        $this->chat->syncOpened($conversation);

        try {
            $message = $this->chat->send($conversation, $side, $userId, (string) $request->input('body'), $request->input('client_id'));
        } catch (ContactBlockedException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'contact_blocked',
                'reason'  => $e->reason,
                'message' => $e->getMessage(),
            ], 422);
        }

        $conversation->loadMissing(['user', 'expert']);

        return $this->success($this->chat->messagePayload($message, $conversation, $side), 201);
    }

    /* ===================== OUTILS ===================== */

    /** Fil auquel l'utilisateur participe, 404 sinon (on ne révèle pas l'existence des autres). */
    private function find(Request $request, int $conversationId): BookingConversation
    {
        $conversation = BookingConversation::findOrFail($conversationId);
        abort_unless($this->chat->sideOf($conversation, $request->user()->id), 404);

        return $conversation;
    }

    private function detail(BookingConversation $conversation, int $userId): array
    {
        $this->chat->syncOpened($conversation);
        $conversation->loadMissing(['user', 'expert']);

        $unread = $this->chat->unreadCounts(collect([$conversation]), $userId);
        $last = $conversation->last_message_id ? BookingMessage::find($conversation->last_message_id) : null;

        $bookings = Booking::submitted()
            ->where('expert_id', $conversation->expert_id)
            ->where('user_id', $conversation->user_id)
            ->with('blocks')
            ->orderByDesc('requested_at')
            ->get();

        return $this->conversationPayload($conversation, $userId, $last, $unread[$conversation->id] ?? 0) + [
            // Rendez-vous du couple, pour ouvrir la fiche depuis le fil.
            'bookings' => $bookings->map(fn (Booking $b) => [
                'id'          => $b->id,
                'offer_title' => $b->offer_title,
                'mode'        => $b->mode,
                'status'      => $this->displayStatus($b),
                'first_start' => $b->blocks->min('start_at')?->toIso8601String(),
                'last_end'    => $b->blocks->max('end_at')?->toIso8601String(),
            ])->values(),
        ];
    }

    private function conversationPayload(BookingConversation $conversation, int $userId, ?BookingMessage $last, int $unread): array
    {
        $side = $this->chat->sideOf($conversation, $userId);
        $isHost = $side === BookingConversation::SIDE_EXPERT;

        $preview = null;
        if ($last) {
            $payload = $this->chat->messagePayload($last, $conversation, $side);
            $preview = [
                'kind'       => $payload['kind'],
                'mine'       => $payload['mine'],
                'body'       => mb_strimwidth(preg_replace('/\s+/', ' ', $payload['body']), 0, self::PREVIEW_LENGTH, '…'),
                'created_at' => $payload['created_at'],
            ];
        }

        return [
            'id'           => $conversation->id,
            'role'         => $isHost ? self::ROLE_HOST : self::ROLE_GUEST,
            // L'autre partie : l'expert (fiche publique) ou le confrère demandeur (nom seulement).
            'expert'       => !$isHost && $conversation->expert
                ? BookingExpertController::expertPayload($conversation->expert, false)
                : null,
            'requester'    => $isHost
                ? ['name' => $conversation->user ? BookingCheckoutService::displayName($conversation->user) : 'Confrère']
                : null,
            'can_write'    => $conversation->isOpen(),
            'last_message' => $preview,
            'unread'       => $unread,
        ];
    }

    /** Même lecture que les écrans Rendez-vous : acceptée et passée = « completed ». */
    private function displayStatus(Booking $booking): string
    {
        if ($booking->status === Booking::STATUS_ACCEPTED) {
            $lastEnd = $booking->blocks->max('end_at');

            return $lastEnd && $lastEnd->isPast() ? 'completed' : 'accepted';
        }

        return $booking->status;
    }

    private function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
