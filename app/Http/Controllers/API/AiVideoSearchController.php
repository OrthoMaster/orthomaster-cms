<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AiSearch\AiSearchHealth;
use App\Services\AiSearch\AiSearchLogger;
use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\GeminiException;
use App\Services\AiSearch\SessionExpiredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

class AiVideoSearchController extends Controller
{
    public function __construct(
        private AiSearchHealth $health,
        private AiSearchLogger $logger,
    ) {}

    /**
     * L'app affiche le bouton de l'assistant seulement si available = true.
     *
     * GET /content/ai-search/status
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['available' => $this->health->isAvailable()],
        ]);
    }

    /**
     * Un tour de conversation. Exactement un champ parmi message, option_id, action.
     * Chaque tour, réussi ou refusé, est journalisé pour le tableau de bord.
     *
     * POST /content/ai-search
     */
    public function search(Request $request, AiSearchService $service): JsonResponse
    {
        $start = microtime(true);

        $validated = $request->validate([
            'session_id' => ['nullable', 'string', 'uuid'],
            'message'    => ['nullable', 'string', 'max:' . config('ai_search.conversation.max_message_length')],
            'option_id'  => ['nullable', 'string', 'max:10'],
            'action'     => ['nullable', 'string', 'in:show'],
        ]);

        $input = array_filter(
            ['message' => trim($validated['message'] ?? ''), 'option_id' => $validated['option_id'] ?? null, 'action' => $validated['action'] ?? null],
            fn ($value) => $value !== null && $value !== ''
        );

        $userId = (int) $request->user()->id;
        $sessionId = $validated['session_id'] ?? null;
        $inputType = match (true) {
            isset($input['action'])    => 'show',
            isset($input['option_id']) => 'option',
            default                    => 'message',
        };
        $queryText = $input['message'] ?? null;

        // Raccourci pour journaliser puis répondre, quel que soit le chemin de sortie.
        $finish = function (?string $errorCode, array $result = []) use ($start, $userId, $sessionId, $inputType, $queryText) {
            $this->logger->log(
                $userId,
                $sessionId,
                $inputType,
                $queryText,
                (int) round((microtime(true) - $start) * 1000),
                $result,
                $errorCode,
            );
        };

        if (count($input) !== 1) {
            $finish('invalid_request');
            return $this->error('invalid_request', 422);
        }

        if (! $this->health->isAvailable()) {
            $finish('ai_unavailable');
            return $this->error('ai_unavailable', 503);
        }

        // « Voir les résultats » ne coûte rien : il n'entre pas dans le quota.
        $limitKey = "ai-search:{$userId}";
        if (! isset($input['action'])) {
            if (RateLimiter::tooManyAttempts($limitKey, config('ai_search.conversation.rate_limit_per_hour'))) {
                $finish('rate_limited');
                return $this->error('rate_limited', 429);
            }
            RateLimiter::hit($limitKey, 3600);
        }

        try {
            $result = $service->handle($userId, $sessionId, $input);
            $this->health->recordSuccess();
        } catch (SessionExpiredException) {
            $finish('session_expired');
            return $this->error('session_expired', 410);
        } catch (InvalidArgumentException) {
            $finish('invalid_request');
            return $this->error('invalid_request', 422);
        } catch (GeminiException $e) {
            $this->health->recordFailure($e);
            Log::channel('api')->warning('ai-search: ' . $e->getMessage(), ['status' => $e->status]);
            $finish('ai_unavailable');

            return $this->error('ai_unavailable', 503);
        } catch (\Throwable $e) {
            Log::channel('api')->error('ai-search failed: ' . $e->getMessage(), ['exception' => $e]);
            $finish('ai_unavailable');

            return $this->error('ai_unavailable', 503);
        }

        $finish(null, $result);

        // usage et meta servent au journal, l'app n'en a pas besoin.
        unset($result['usage'], $result['meta']);

        return response()->json(['success' => true, 'data' => $result]);
    }

    private function error(string $code, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'code' => $code], $status);
    }
}