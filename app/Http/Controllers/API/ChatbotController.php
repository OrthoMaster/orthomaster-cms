<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Google\Client;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    /**
     * Ask and answer
     */
    public function ask(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:20000',
            'chat_conversation_id' => 'nullable|string',
        ]);

        try {
            // Get or Create new conversation
            $userId = $request->user()?->id;
            $conversation = $this->resolveConversation(
                $request->input('chat_conversation_id'),
                $userId,
                $request->input('query'),
                $request->domain ?? ''
            );

            // Save user's message
            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'role'            => 'user',
                'content'         => $request->input('query'),
                'sent_at'         => now(),
            ]);

            // Call Dialogflow CX
            $botAnswer = $this->callDialogflow(
                $conversation->session_id,
                $request->input('query'),
                $request->user()->profession?->name ?? ''
            );

            // Save bot's message
            $botMessage = ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'role'            => 'bot',
                'content'         => $botAnswer,
                'sent_at'         => now(),
            ]);

            return response()->json([
                'answer'          => $botAnswer,
                'role'            => 'bot',
                'chat_conversation_id' => $conversation->session_id,
                'message_id'      => $botMessage->id,
                'sent_at'         => $botMessage->sent_at->toISOString(),
            ]);
        } catch (Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['chatbot-ask']);
            return response()->json([
                'error' => 'Lỗi kết nối Oscar Agent',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current conversation
     */
    public function conversations(Request $request)
    {
        $userId = $request->user()?->id;

        $conversations = ChatConversation::with('latestMessage')
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->paginate(15);

        return response()->json([
            'data' => $conversations->map(function ($conv) {
                $latest = $conv->latestMessage->first();
                return [
                    'chat_conversation_id' => $conv->session_id,
                    'last_message'    => $latest?->content ? Str::limit($latest->content, 60) : null,
                    'last_at'         => $latest?->sent_at?->toISOString(),
                    'created_at'      => $conv->created_at->toISOString(),
                ];
            }),
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'last_page'    => $conversations->lastPage(),
                'has_more'     => $conversations->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get history
     */
    public function history(Request $request, string $conversationId)
    {
        $request->validate([
            'cursor' => 'nullable|integer',
            'limit'  => 'nullable|integer|min:5|max:50',
        ]);

        $userId = $request->user()?->id;
        $limit  = $request->integer('limit', 20);
        $cursor = $request->integer('cursor'); // 0 = first load

        // Search conversation và
        $conversation = ChatConversation::where('session_id', $conversationId)
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->firstOrFail();

        // Query tin nhắn: cursor-based pagination
        $query = $conversation->messages()
            ->orderBy('sent_at', 'desc')
            ->limit($limit + 1);

        if ($cursor > 0) {
            $query->where('id', '<', $cursor);
        }

        $messages = $query->get();

        // Check old messages
        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages = $messages->slice(0, $limit);
        }

        // Reverse order
        $messages = $messages->reverse()->values();

        // Group by day
        $grouped = $messages->groupBy(function ($msg) {
            return $msg->sent_at->toDateString(); // "2025-03-20"
        })->map(function ($dayMessages, $date) {
            return [
                'date'     => $date,
                'label'    => $this->formatDateLabel($date),
                'messages' => $dayMessages->map(fn($m) => $this->formatMessage($m)),
            ];
        })->values();

        return response()->json([
            'chat_conversation_id' => $conversationId,
            'title'           => $conversation->title,
            'data'            => $grouped,
            'pagination'      => [
                'has_more'   => $hasMore,
                'next_cursor' => $hasMore ? $messages->first()?->id : null,
            ],
        ]);
    }


    private function resolveConversation(?string $sessionId, ?int $userId, string $firstMessage, ?string $domain): ChatConversation
    {
        if ($sessionId) {
            $conv = ChatConversation::where('session_id', $sessionId)->first();
            if ($conv) {
                $conv->touch(); // Save updated_at
                return $conv;
            }
        }

        // Tạo session mới
        $newSessionId = $sessionId ?? 'session-' . ($userId ?? 'guest') . '-' . time();

        return ChatConversation::create([
            'user_id'    => $userId,
            'session_id' => $newSessionId,
            'domain'      => $domain ?? '',
        ]);
    }

    private function callDialogflow(string $sessionId, string $query, string $userJob): string
    {
        $accessToken = $this->getGoogleAccessToken();

        $projectId = config('services.google.project_id');
        $agentId   = config('services.google.agent_id');
        $location  = config('services.google.location');

        $url = "https://{$location}-dialogflow.googleapis.com/v3/projects/{$projectId}/locations/{$location}/agents/{$agentId}/sessions/{$sessionId}:detectIntent";

        $payload = [
            'queryInput' => [
                'text'         => ['text' => $query],
                'languageCode' => 'fr',
            ],
            'queryParams' => [
                'parameters' => ['job' => $userJob],
            ],
        ];

        $response = Http::withToken($accessToken)->post($url, $payload);

        if ($response->failed()) {
            throw new Exception('Agent API Error: ' . $response->body());
        }

        $messages   = $response->json('queryResult.responseMessages', []);
        $fullAnswer = '';
        foreach ($messages as $msg) {
            if (isset($msg['text']['text'][0])) {
                $fullAnswer .= $msg['text']['text'][0] . "\n";
            }
        }

        return trim($fullAnswer) ?: 'Veuillez réessayer !';
    }

    private function getGoogleAccessToken()
    {
        $keyFile = base_path('storage/app/chatbot/orthomaster-b5a0e-0e4f2ac0c577.json');
        if (!file_exists($keyFile)) {
            Log::channel('api')->error('Keyfile: {$keyFile}');
        }

        $client = new Client();
        $client->setAuthConfig($keyFile);
        $client->addScope('https://www.googleapis.com/auth/cloud-platform');

        $token = $client->fetchAccessTokenWithAssertion();
        return $token['access_token'];
    }

    private function formatMessage(ChatMessage $msg): array
    {
        return [
            'id'      => $msg->id,
            'role'    => $msg->role,       // "user" | "bot"
            'content' => $msg->content,
            'sent_at' => $msg->sent_at->toISOString(), // "2025-03-20T14:30:00.000Z"
            'time'    => $msg->sent_at->format('H:i'), // "14:30"
        ];
    }

    private function formatDateLabel(string $date): string
    {
        $d = \Carbon\Carbon::parse($date);
        if ($d->isToday())     return "Aujourd'hui";
        if ($d->isYesterday()) return 'Hier';
        return $d->format('d/m/Y');
    }
}
