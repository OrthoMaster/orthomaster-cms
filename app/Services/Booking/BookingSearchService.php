<?php

namespace App\Services\Booking;

use App\Models\Expert;
use App\Models\ExpertOffer;
use App\Services\AiSearch\AiSearchLogger;
use App\Services\AiSearch\GeminiClient;
use App\Services\AiSearch\VectorStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Recherche intelligente des experts et des thèmes, en un seul tour (pas de conversation).
 *
 *  1. Chaque expert / thème réservable a un vecteur d'embedding (table booking_search_vectors),
 *     recalculé seulement quand son texte change (hash) : pas d'observer à maintenir,
 *     la synchronisation se fait au moment de la recherche, par lot.
 *  2. Candidats : similarité vectorielle + bonus des mots présents dans le titre / le texte
 *     (un nom d'expert tapé tel quel remonte toujours).
 *  3. Gemini reclasse les candidats et écrit une raison courte (« Spécialiste de… »).
 *
 * Si Gemini n'est pas configuré ou échoue : classement par les mots seuls, sans raison.
 * Réutilise le client Gemini, la configuration et le journal de la recherche des médias.
 */
class BookingSearchService
{
    public const TYPE_EXPERTS = 'experts';
    public const TYPE_THEMES  = 'themes';

    private const VECTOR_TYPES = [self::TYPE_EXPERTS => 'expert', self::TYPE_THEMES => 'theme'];

    /** Candidats envoyés à Gemini, et résultats renvoyés au plus. */
    private const CANDIDATES = 20;
    private const MAX_RESULTS = 20;
    /** Sous ce score (vecteur + mots), un candidat n'est pas proposé sans l'avis de Gemini. */
    private const FALLBACK_MIN_SCORE = 0.45;
    private const TEXT_MAX = 1500;
    private const PROMPT_TEXT_MAX = 500;
    private const CACHE_MINUTES = 10;

    private const STOPWORDS = [
        'aux', 'avec', 'ces', 'cette', 'chez', 'comme', 'dans', 'des', 'est', 'les', 'leur', 'mais',
        'mes', 'mon', 'nos', 'notre', 'par', 'pas', 'plus', 'pour', 'que', 'quel', 'quelle', 'qui',
        'sans', 'ses', 'son', 'sont', 'sur', 'tous', 'tout', 'une', 'vos', 'votre', 'vous', 'expert',
        'experts', 'theme', 'themes', 'cherche', 'trouver', 'besoin', 'aide', 'avoir', 'faire',
    ];

    public function __construct(private GeminiClient $gemini, private AiSearchLogger $logger)
    {
    }

    /**
     * @param  int[]  $eligibleIds  experts ou thèmes que l'utilisateur peut voir
     * @return array{results: array<int, array{id:int, reason:?string}>, used_llm: bool}
     */
    public function search(string $type, string $query, array $eligibleIds, ?int $userId = null): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query));
        if ($query === '' || !$eligibleIds) {
            return ['results' => [], 'used_llm' => false];
        }

        sort($eligibleIds);
        $cacheKey = 'booking_search:' . $type . ':' . sha1(mb_strtolower($query) . '|' . implode(',', $eligibleIds));

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_MINUTES), function () use ($type, $query, $eligibleIds, $userId) {
            $startedAt = microtime(true);
            $docs      = $this->documents($type, $eligibleIds);
            $usage     = ['input_tokens' => 0, 'output_tokens' => 0, 'thought_tokens' => 0];
            $usedLlm   = false;
            $embedChars = 0;
            $error     = null;

            $lexical = $this->lexicalScores($query, $docs);
            $scores  = $lexical;
            // Scores vectoriels calculés (Gemini a échoué seulement au reclassement).
            $vectorScored = false;

            try {
                if (!config('ai_search.gemini.api_key')) {
                    throw new \RuntimeException('Gemini non configuré');
                }

                $embedChars = $this->syncVectors($type, $docs);
                $vectors    = $this->vectors($type, array_keys($docs));
                $queryVec   = $this->gemini->embedQuery($query);
                $embedChars += mb_strlen($query);

                foreach ($docs as $id => $doc) {
                    $similarity  = isset($vectors[$id]) ? $this->dot($queryVec, $vectors[$id]) : 0.0;
                    $scores[$id] = $similarity + ($lexical[$id] ?? 0.0);
                }
                arsort($scores);
                $vectorScored = true;

                $candidates = array_slice(array_keys($scores), 0, self::CANDIDATES, true);
                [$results, $usage] = $this->rerank($type, $query, $candidates, $docs);
                $usedLlm = true;
            } catch (\Throwable $e) {
                $error = config('ai_search.gemini.api_key') ? 'gemini_error' : 'not_configured';
                Log::info('booking-search: repli sur la recherche par mots', ['error' => $e->getMessage()]);

                arsort($scores);
                $results = collect($scores)
                    ->filter(fn ($score) => $vectorScored ? $score >= self::FALLBACK_MIN_SCORE : $score > 0)
                    ->keys()
                    ->take(self::MAX_RESULTS)
                    ->map(fn ($id) => ['id' => (int) $id, 'reason' => null])
                    ->all();
            }

            $this->logger->log($userId, null, 'booking_' . $type, $query, (int) round((microtime(true) - $startedAt) * 1000), [
                'usage' => $usage,
                'meta'  => [
                    'used_llm'        => $usedLlm,
                    'result_count'    => count($results),
                    'candidate_count' => count($docs),
                    'mode'            => 'booking',
                    'embed_chars'     => $embedChars,
                ],
            ], $usedLlm ? null : $error);

            return ['results' => array_values($results), 'used_llm' => $usedLlm];
        });
    }

    /**
     * Texte indexé de chaque expert / thème.
     *
     * @return array<int, array{title:string, text:string}>
     */
    public function documents(string $type, array $ids): array
    {
        if ($type === self::TYPE_THEMES) {
            $offers  = ExpertOffer::whereIn('id', $ids)->get();
            $experts = Expert::with('domains')->whereIn('id', $offers->pluck('expert_id')->unique())->get()->keyBy('id');

            return $offers->mapWithKeys(function (ExpertOffer $offer) use ($experts) {
                $expert = $experts->get($offer->expert_id);
                $text   = implode("\n", array_filter([
                    $offer->description,
                    $expert ? 'Expert : ' . trim($expert->name . ', ' . $expert->credentials, ', ') : null,
                    $expert && $expert->domains->isNotEmpty() ? 'Domaines : ' . $expert->domains->pluck('label')->implode(', ') : null,
                ]));

                return [$offer->id => ['title' => (string) $offer->title, 'text' => Str::limit($text, self::TEXT_MAX, '')]];
            })->all();
        }

        $experts = Expert::with('domains')->whereIn('id', $ids)->get();
        $offers  = ExpertOffer::active()->whereIn('expert_id', $ids)->get(['id', 'expert_id', 'title'])->groupBy('expert_id');

        return $experts->mapWithKeys(function (Expert $expert) use ($offers) {
            $text = implode("\n", array_filter([
                $expert->credentials,
                $expert->domains->isNotEmpty() ? 'Domaines : ' . $expert->domains->pluck('label')->implode(', ') : null,
                $offers->has($expert->id) ? 'Thèmes : ' . $offers[$expert->id]->pluck('title')->implode(' ; ') : null,
                $expert->lede,
            ]));

            return [$expert->id => ['title' => trim($expert->honorific . ' ' . $expert->name), 'text' => Str::limit($text, self::TEXT_MAX, '')]];
        })->all();
    }

    /* ===================== VECTEURS ===================== */

    /** Calcule les vecteurs manquants ou périmés. Renvoie le nombre de caractères envoyés. */
    private function syncVectors(string $type, array $docs): int
    {
        $vectorType = self::VECTOR_TYPES[$type];
        $model      = config('ai_search.embedding.model');
        $known      = DB::table('booking_search_vectors')
            ->where('type', $vectorType)
            ->whereIn('ref_id', array_keys($docs))
            ->pluck('hash', 'ref_id');

        $stale = [];
        foreach ($docs as $id => $doc) {
            $hash = sha1($model . '|' . $doc['title'] . '|' . $doc['text']);
            if (($known[$id] ?? null) !== $hash) {
                $stale[$id] = $doc + ['hash' => $hash];
            }
        }

        if (!$stale) {
            return 0;
        }

        $vectors = $this->gemini->embedDocuments(array_values(array_map(
            fn (array $d) => ['title' => $d['title'], 'text' => $d['text']],
            $stale
        )));

        $now  = now();
        $rows = [];
        foreach (array_keys($stale) as $i => $id) {
            if (!isset($vectors[$i])) {
                continue;
            }
            $rows[] = [
                'type'       => $vectorType,
                'ref_id'     => $id,
                'hash'       => $stale[$id]['hash'],
                'model'      => $model,
                'dims'       => count($vectors[$i]),
                'vector'     => VectorStore::pack($vectors[$i]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('booking_search_vectors')->upsert($rows, ['type', 'ref_id'], ['hash', 'model', 'dims', 'vector', 'updated_at']);

        return array_sum(array_map(fn (array $d) => mb_strlen($d['title'] . $d['text']), $stale));
    }

    /** @return array<int, float[]> */
    private function vectors(string $type, array $ids): array
    {
        return DB::table('booking_search_vectors')
            ->where('type', self::VECTOR_TYPES[$type])
            ->where('model', config('ai_search.embedding.model'))
            ->whereIn('ref_id', $ids)
            ->get(['ref_id', 'vector'])
            ->mapWithKeys(fn ($row) => [(int) $row->ref_id => array_values(unpack('g*', $row->vector))])
            ->all();
    }

    private function dot(array $a, array $b): float
    {
        $sum = 0.0;
        $n   = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $sum += $a[$i] * $b[$i];
        }

        return $sum;
    }

    /* ===================== MOTS ===================== */

    /**
     * Bonus des mots de la requête présents dans le titre (fort) ou le texte (faible).
     *
     * @return array<int, float>
     */
    private function lexicalScores(string $query, array $docs): array
    {
        $tokens = collect(preg_split('/[^\p{L}\p{N}]+/u', Str::lower(Str::ascii($query))))
            ->filter(fn ($t) => mb_strlen($t) >= 3 && !in_array($t, self::STOPWORDS, true))
            ->unique()
            ->values();

        $scores = [];
        foreach ($docs as $id => $doc) {
            $title = Str::lower(Str::ascii($doc['title']));
            $text  = Str::lower(Str::ascii($doc['text']));
            $score = 0.0;
            foreach ($tokens as $token) {
                if (str_contains($title, $token)) {
                    $score += 0.2;
                } elseif (str_contains($text, $token)) {
                    $score += 0.05;
                }
            }
            $scores[$id] = min($score, 0.5);
        }

        return $scores;
    }

    /* ===================== GEMINI ===================== */

    /**
     * @return array{0: array<int, array{id:int, reason:?string}>, 1: array}
     */
    private function rerank(string $type, string $query, array $candidateIds, array $docs): array
    {
        $what = $type === self::TYPE_THEMES ? 'thèmes de formation proposés par des experts' : 'experts';

        $system = "Tu aides des professionnels de l'orthopédie et de la pharmacie à trouver des {$what} "
            . "pour un rendez-vous d'échange. On te donne une recherche et une liste de candidats numérotés par id. "
            . "Garde seulement les candidats réellement pertinents pour la recherche, du plus pertinent au moins pertinent, "
            . 'au plus ' . self::MAX_RESULTS . '. Pour chacun, écris en français une raison très courte '
            . '(12 mots au plus, sans répéter le nom) qui dit pourquoi il correspond. '
            . "N'invente rien : appuie-toi uniquement sur le texte des candidats. Si aucun ne correspond, renvoie une liste vide.";

        $lines = collect($candidateIds)->map(fn ($id) => json_encode([
            'id'    => (int) $id,
            'titre' => $docs[$id]['title'],
            'texte' => Str::limit($docs[$id]['text'], self::PROMPT_TEXT_MAX, '…'),
        ], JSON_UNESCAPED_UNICODE))->implode("\n");

        $schema = [
            'type'       => 'object',
            'properties' => [
                'results' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'id'     => ['type' => 'integer'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['id', 'reason'],
                    ],
                ],
            ],
            'required' => ['results'],
        ];

        $response = $this->gemini->generateJson($system, "Recherche : {$query}\n\nCandidats :\n{$lines}", $schema);

        // Le modèle propose, le code dispose : seuls les candidats envoyés, une fois chacun.
        $allowed = array_flip(array_map('intval', $candidateIds));
        $seen    = [];
        $results = [];
        foreach ($response['data']['results'] ?? [] as $row) {
            $id = (int) ($row['id'] ?? 0);
            if (!isset($allowed[$id]) || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $results[] = ['id' => $id, 'reason' => Str::limit(trim((string) ($row['reason'] ?? '')), 120, '…') ?: null];
            if (count($results) >= self::MAX_RESULTS) {
                break;
            }
        }

        return [$results, $response['usage']];
    }
}
