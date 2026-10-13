<?php

namespace App\Services\AiSearch;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Écrit une ligne dans ai_search_logs pour chaque tour de conversation.
 * Ne doit jamais faire échouer la réponse à l'utilisateur : toute erreur est seulement journalisée.
 */
class AiSearchLogger
{
    /** Ordre de grandeur courant : un token ≈ 4 caractères en français. */
    private const CHARS_PER_TOKEN = 4;

    /**
     * @param  array  $result  réponse de AiSearchService::handle() (usage + meta), ou [] en cas d'erreur
     */
    public function log(
        ?int $userId,
        ?string $sessionId,
        string $inputType,
        ?string $queryText,
        int $latencyMs,
        array $result = [],
        ?string $errorCode = null,
    ): void {
        if (! config('ai_search.logging.enabled')) {
            return;
        }

        try {
            $meta = $result['meta'] ?? [];
            $usage = $result['usage'] ?? [];
            $usedLlm = (bool) ($meta['used_llm'] ?? false);

            DB::table('ai_search_logs')->insert([
                'user_id'          => $userId,
                'session_id'       => $result['session_id'] ?? $sessionId,
                'round'            => $meta['round'] ?? null,
                'input_type'       => $inputType,
                'action'           => $meta['action'] ?? null,
                'status'           => $errorCode ? 'error' : 'ok',
                'error_code'       => $errorCode,
                'result_count'     => $meta['result_count'] ?? null,
                'candidate_count'  => $meta['candidate_count'] ?? null,
                'retrieval_mode'   => $meta['mode'] ?? null,
                'used_llm'         => $usedLlm,
                'model'            => $usedLlm ? config('ai_search.chat.model') : null,
                'input_tokens'     => (int) ($usage['input_tokens'] ?? 0),
                'output_tokens'    => (int) ($usage['output_tokens'] ?? 0),
                'thought_tokens'   => (int) ($usage['thought_tokens'] ?? 0),
                'embedding_tokens' => (int) ceil(($meta['embed_chars'] ?? 0) / self::CHARS_PER_TOKEN),
                'latency_ms'       => $latencyMs,
                'llm_ms'           => $meta['llm_ms'] ?? null,
                'query_text'       => config('ai_search.logging.log_queries') && $queryText !== null
                    ? mb_substr($queryText, 0, 500)
                    : null,
                'created_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ai-search: journalisation impossible', ['error' => $e->getMessage()]);
        }
    }
}