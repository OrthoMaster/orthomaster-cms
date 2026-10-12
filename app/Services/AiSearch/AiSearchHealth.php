<?php

namespace App\Services\AiSearch;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Disjoncteur de l'assistant : décide si le bouton doit être visible dans l'app.
 *
 * La remise en service se fait par simple expiration du verrou en cache : pas besoin de
 * sonde périodique, la première requête suivante sert de test.
 */
class AiSearchHealth
{
    private const KEY_DOWN = 'ai_search:health:down';
    private const KEY_FAILURES = 'ai_search:health:failures';
    private const KEY_INDEX = 'ai_search:health:index_ready';

    /** L'index ne se vide pas d'une minute à l'autre : inutile d'interroger la base à chaque appel. */
    private const INDEX_CHECK_MINUTES = 5;

    public function isAvailable(): bool
    {
        return config('ai_search.enabled')
            && filled(config('ai_search.gemini.api_key'))
            && ! Cache::has(self::KEY_DOWN)
            && $this->indexReady();
    }

    public function recordSuccess(): void
    {
        Cache::forget(self::KEY_FAILURES);
    }

    public function recordFailure(GeminiException $e): void
    {
        $cfg = config('ai_search.health');

        if ($e->isOutOfCredit()) {
            Cache::put(self::KEY_DOWN, 'credit', now()->addMinutes($cfg['credit_cooldown_minutes']));
            return;
        }

        // add() n'écrase pas une valeur existante : le compteur démarre à 0 puis s'incrémente.
        Cache::add(self::KEY_FAILURES, 0, now()->addMinutes($cfg['cooldown_minutes']));
        $failures = Cache::increment(self::KEY_FAILURES);

        if ($failures >= $cfg['failure_threshold']) {
            Cache::put(self::KEY_DOWN, 'errors', now()->addMinutes($cfg['cooldown_minutes']));
            Cache::forget(self::KEY_FAILURES);
        }
    }

    private function indexReady(): bool
    {
        return Cache::remember(
            self::KEY_INDEX,
            now()->addMinutes(self::INDEX_CHECK_MINUTES),
            fn () => DB::table('ai_search_documents')->exists()
        );
    }
}
