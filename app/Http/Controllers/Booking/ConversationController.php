<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingConversation;
use App\Models\BookingMessage;
use App\Services\Booking\BookingChatService;
use App\Services\Booking\BookingCheckoutService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CMS : lecture des fils de messagerie expert ↔ demandeur. Aucune écriture :
 * l'administration consulte (litiges, absences, tentatives de contournement).
 */
class ConversationController extends Controller
{
    public const PER_PAGE = 25;

    /** Messages par page du fil ; « Messages plus anciens » charge la page précédente. */
    public const THREAD_PAGE = 100;

    public function __construct(private BookingChatService $chat)
    {
    }

    /* ===================== LISTE ===================== */

    /**
     * GET /booking/conversations?q=&expert=&blocked=
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));

        $textCount = DB::table('booking_messages')->selectRaw('COUNT(*)')
            ->whereColumn('booking_messages.conversation_id', 'c.id')
            ->where('booking_messages.kind', BookingMessage::KIND_TEXT);
        $bookingCount = DB::table('bookings')->selectRaw('COUNT(*)')
            ->whereColumn('bookings.expert_id', 'c.expert_id')
            ->whereColumn('bookings.user_id', 'c.user_id')
            ->whereNotNull('bookings.requested_at');

        $conversations = DB::table('booking_conversations as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->leftJoin('experts as e', 'e.id', '=', 'c.expert_id')
            ->select('c.id', 'c.opened_at', 'c.last_message_at', 'c.blocked_attempts',
                'u.name as user_name', 'u.first_name as user_first_name', 'u.email as user_email',
                'e.name as expert_name', 'e.id as expert_id')
            ->selectSub($textCount, 'text_count')
            ->selectSub($bookingCount, 'booking_count')
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%' . $search . '%';
                $q->where(fn (Builder $q) => $q->where('u.name', 'like', $like)
                    ->orWhere('u.first_name', 'like', $like)
                    ->orWhere('u.email', 'like', $like)
                    ->orWhere('e.name', 'like', $like));
            })
            ->when($request->filled('expert'), fn (Builder $q) => $q->where('c.expert_id', (int) $request->input('expert')))
            ->when($request->boolean('blocked'), fn (Builder $q) => $q->where('c.blocked_attempts', '>', 0))
            ->orderByDesc('c.last_message_at')
            ->orderByDesc('c.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $selectedExpert = $request->filled('expert')
            ? DB::table('experts')->where('id', (int) $request->input('expert'))->first(['id', 'name'])
            : null;

        return view('booking.conversations.index', compact('conversations', 'selectedExpert'));
    }

    /* ===================== FIL ===================== */

    /**
     * GET /booking/conversations/{conversationId}?before=
     */
    public function show(Request $request, int $conversationId)
    {
        $conversation = BookingConversation::with(['user', 'expert'])->findOrFail($conversationId);
        $this->chat->syncOpened($conversation);

        $query = BookingMessage::where('conversation_id', $conversation->id)
            ->when($request->filled('before'), fn ($q) => $q->where('id', '<', (int) $request->input('before')));
        $rows = $query->orderByDesc('id')->limit(self::THREAD_PAGE + 1)->get();

        $hasOlder = $rows->count() > self::THREAD_PAGE;
        $messages = $rows->take(self::THREAD_PAGE)->reverse()->values();

        // Texte des événements à la troisième personne (lecteur neutre).
        $texts = $messages->filter->isEvent()
            ->mapWithKeys(fn (BookingMessage $m) => [$m->id => $this->chat->eventText($m, $conversation, null)]);

        $bookings = Booking::submitted()
            ->where('expert_id', $conversation->expert_id)
            ->where('user_id', $conversation->user_id)
            ->with('blocks')
            ->orderByDesc('requested_at')
            ->get();

        $counts = BookingMessage::where('conversation_id', $conversation->id)
            ->where('kind', BookingMessage::KIND_TEXT)
            ->selectRaw('sender, COUNT(*) as total')
            ->groupBy('sender')
            ->pluck('total', 'sender');

        $requester = $conversation->user ? BookingCheckoutService::displayName($conversation->user) : '—';
        $expertName = $conversation->expert ? BookingChatService::expertName($conversation->expert) : '—';

        return view('booking.conversations.show', compact(
            'conversation', 'messages', 'texts', 'hasOlder', 'bookings', 'counts', 'requester', 'expertName'
        ));
    }
}
