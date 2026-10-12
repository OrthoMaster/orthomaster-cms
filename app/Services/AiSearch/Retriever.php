<?php

namespace App\Services\AiSearch;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Retriever
{
    /** Longueur de l'extrait renvoyé pour chaque candidat (affichage et prompt). */
    private const SNIPPET_LENGTH = 200;

    /**
     * Mots vides français retirés avant MATCH : la liste par défaut d'InnoDB est anglaise,
     * si bien que « aux », « des », « pour »… faisaient remonter des titres sans rapport.
     * Les mots de moins de 3 lettres sont déjà ignorés par InnoDB.
     */
    private const FRENCH_STOPWORDS = [
        'aux', 'avec', 'ces', 'cet', 'cette', 'chez', 'comme', 'dans', 'des', 'elle', 'elles',
        'est', 'et', 'eux', 'les', 'leur', 'leurs', 'lui', 'mais', 'mes', 'moi', 'mon', 'nos',
        'notre', 'nous', 'par', 'pas', 'plus', 'pour', 'quand', 'que', 'quel', 'quelle',
        'quelles', 'quels', 'qui', 'quoi', 'sans', 'ses', 'son', 'sont', 'sous', 'sur', 'ton',
        'tous', 'tout', 'toute', 'toutes', 'tres', 'très', 'une', 'vos', 'votre', 'vous',
        'entre', 'après', 'apres', 'avant', 'dont', 'ont', 'été', 'etre', 'être', 'faire',
        'video', 'vidéo', 'videos', 'vidéos', 'cherche', 'trouver', 'parle', 'parlent',
    ];

    /** Un nom de famille seul ne compte que s'il est assez long pour ne pas être un mot courant. */
    private const MIN_SURNAME_LENGTH = 4;

    public function __construct(
        private GeminiClient $gemini,
        private VectorStore $store,
    ) {}

    /**
     * Candidats pour une requête : fusion de la recherche vectorielle, plein texte
     * et correspondance exacte des intervenants.
     *
     * @param  array{event_ids?: int[], past_event_ids?: int[], expert_ids?: int[], exclude_expert_ids?: int[]}  $filters
     * @return array{candidates: array<int, array>, timings: array<string, int>}
     */
    public function search(string $query, array $filters = []): array
    {
        $cfg = config('ai_search.retrieval');

        $t0 = microtime(true);
        $queryVector = $this->gemini->embedQuery($query);
        $t1 = microtime(true);
        $vector = $this->store->search($queryVector, $cfg['vector_pool'], $cfg['min_vector_score']);
        $t2 = microtime(true);
        $experts = $this->expertSearch($query);
        // Les mots d'un nom déjà reconnu sont retirés du plein texte : sinon « Guillaume »
        // fait remonter tous les autres Guillaume avec le même score.
        $keyword = $this->keywordSearch($query, $experts['tokens'], $cfg['keyword_pool'], $cfg['title_weight']);
        $t3 = microtime(true);

        // RRF : on fusionne des rangs et non des scores, car cosinus et score MATCH
        // n'ont pas la même échelle et ne sont pas comparables directement.
        $fused = [];
        $rank = 0;
        foreach (array_keys($vector) as $contentId) {
            $fused[$contentId] = ($fused[$contentId] ?? 0) + 1 / ($cfg['rrf_k'] + ++$rank);
        }
        $rank = 0;
        foreach (array_keys($keyword) as $contentId) {
            $fused[$contentId] = ($fused[$contentId] ?? 0) + 1 / ($cfg['rrf_k'] + ++$rank);
        }
        // Un intervenant nommé explicitement est un signal fort : chaque vidéo concernée
        // reçoit l'équivalent d'un premier rang.
        foreach (array_keys($experts['matches']) as $contentId) {
            $fused[$contentId] = ($fused[$contentId] ?? 0) + 1 / ($cfg['rrf_k'] + 1);
        }

        $documents = $this->loadDocuments(array_keys($fused));

        // Le document peut manquer si le média vient d'être retiré entre-temps.
        $fused = array_filter(
            $fused,
            fn ($score, $contentId) => isset($documents[$contentId]) && $this->passesFilters($documents[$contentId], $filters),
            ARRAY_FILTER_USE_BOTH
        );
        arsort($fused);
        $fused = array_slice($fused, 0, $cfg['max_candidates'], true);

        $chunkIds = array_column(array_intersect_key($vector, $fused), 'chunk_id');
        $chunks = DB::table('ai_search_chunks')
            ->whereIn('id', $chunkIds)
            ->get(['id', 'kind', 'timestamp_sec', 'text'])
            ->keyBy('id');

        $candidates = [];
        foreach ($fused as $contentId => $score) {
            $document = $documents[$contentId];
            $hit = $vector[$contentId] ?? null;
            $chunk = $hit ? ($chunks[$hit['chunk_id']] ?? null) : null;

            $candidates[] = [
                'content_id'    => $contentId,
                'title'         => $document->title,
                'experts'       => $document->experts,
                'expert_ids'    => $document->expert_ids,
                'event_id'      => $document->event_id,
                'event_name'    => $document->event_name,
                'past_event_id' => $document->past_event_id,
                'edition_year'  => $document->edition_year,
                'score'         => $score,
                'vector_score'  => $hit['score'] ?? null,
                'keyword_score' => $keyword[$contentId] ?? null,
                'expert_match'  => isset($experts['matches'][$contentId]),
                'match'         => $chunk ? [
                    'kind'          => $chunk->kind,
                    'timestamp_sec' => $chunk->timestamp_sec !== null ? (int) $chunk->timestamp_sec : null,
                    'text'          => mb_substr($chunk->text, 0, self::SNIPPET_LENGTH),
                ] : null,
            ];
        }

        return [
            'candidates' => $candidates,
            'timings'    => [
                'embed_ms'   => (int) round(($t1 - $t0) * 1000),
                'vector_ms'  => (int) round(($t2 - $t1) * 1000),
                'keyword_ms' => (int) round(($t3 - $t2) * 1000),
                'total_ms'   => (int) round((microtime(true) - $t0) * 1000),
            ],
        ];
    }

    /**
     * Candidats sans requête textuelle (« les vidéos de CDS 2024 ») : simple parcours des
     * documents qui respectent les filtres, du plus récent au plus ancien.
     * Aucun appel API : il n'y a rien à vectoriser.
     *
     * @return array{candidates: array<int, array>, timings: array<string, int>}
     */
    public function browse(array $filters): array
    {
        $t0 = microtime(true);

        $query = DB::table('ai_search_documents')->orderByDesc('content_id');
        if (! empty($filters['event_ids'])) {
            $query->whereIn('event_id', $filters['event_ids']);
        }
        if (! empty($filters['past_event_ids'])) {
            $query->whereIn('past_event_id', $filters['past_event_ids']);
        }

        $documents = $this->loadDocuments($query->pluck('content_id')->map(fn ($id) => (int) $id)->all());
        krsort($documents);

        $candidates = [];
        foreach ($documents as $contentId => $document) {
            if (! $this->passesFilters($document, $filters)) {
                continue;
            }

            $candidates[] = [
                'content_id'    => $contentId,
                'title'         => $document->title,
                'experts'       => $document->experts,
                'expert_ids'    => $document->expert_ids,
                'event_id'      => $document->event_id,
                'event_name'    => $document->event_name,
                'past_event_id' => $document->past_event_id,
                'edition_year'  => $document->edition_year,
                'score'         => null,
                'vector_score'  => null,
                'keyword_score' => null,
                'expert_match'  => false,
                'match'         => null,
            ];

            if (count($candidates) >= config('ai_search.retrieval.max_candidates')) {
                break;
            }
        }

        $elapsed = (int) round((microtime(true) - $t0) * 1000);

        return [
            'candidates' => $candidates,
            'timings'    => ['embed_ms' => 0, 'vector_ms' => 0, 'keyword_ms' => $elapsed, 'total_ms' => $elapsed],
        ];
    }

    /* ===================== PLEIN TEXTE ===================== */

    /**
     * Mode NATURAL LANGUAGE plutôt que BOOLEAN : la saisie libre de l'utilisateur
     * (guillemets, +, -, *) ne peut pas casser la requête.
     *
     * @param  string[]  $excludedTokens  mots déjà traités par la recherche d'intervenants (forme normalisée)
     * @return array<int, float>  clé = content_id, trié par score décroissant
     */
    private function keywordSearch(string $query, array $excludedTokens, int $limit, float $titleWeight): array
    {
        $terms = $this->keywordTerms($query, $excludedTokens);
        if ($terms === '') {
            return [];
        }

        return DB::table('ai_search_documents')
            ->selectRaw(
                'content_id, (MATCH(title, experts) AGAINST (? IN NATURAL LANGUAGE MODE) * ?'
                . ' + MATCH(search_text) AGAINST (? IN NATURAL LANGUAGE MODE)) AS score',
                [$terms, $titleWeight, $terms]
            )
            ->whereRaw(
                '(MATCH(title, experts) AGAINST (? IN NATURAL LANGUAGE MODE)'
                . ' OR MATCH(search_text) AGAINST (? IN NATURAL LANGUAGE MODE))',
                [$terms, $terms]
            )
            ->orderByDesc('score')
            ->limit($limit)
            ->pluck('score', 'content_id')
            ->map(fn ($score) => (float) $score)
            ->all();
    }

    /**
     * Requête débarrassée des mots vides, des mots trop courts pour l'index InnoDB
     * et des mots appartenant à un intervenant déjà reconnu.
     *
     * @param  string[]  $excludedTokens
     */
    private function keywordTerms(string $query, array $excludedTokens): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);

        $kept = array_filter(
            $words,
            fn ($word) => mb_strlen($word) >= 3
                && ! in_array($word, self::FRENCH_STOPWORDS, true)
                && ! in_array($this->normalizeName($word), $excludedTokens, true)
        );

        return implode(' ', $kept);
    }

    /* ===================== INTERVENANTS ===================== */

    /**
     * Repère les intervenants nommés dans la requête, sans tenir compte des accents ni
     * de la casse. Nécessaire parce que MATCH ignore les mots de moins de 3 lettres
     * (« Lê ») et ne fait pas la différence entre deux « Guillaume ».
     *
     * @return array{matches: array<int, true>, tokens: string[]}  matches : clé = content_id ;
     *         tokens : mots de la requête consommés par un nom reconnu
     */
    private function expertSearch(string $query): array
    {
        $normalizedQuery = ' ' . $this->normalizeName($query) . ' ';
        $matches = [];
        $tokens = [];

        $documents = DB::table('ai_search_documents')
            ->whereNotNull('experts')
            ->get(['content_id', 'experts']);

        foreach ($documents as $document) {
            foreach (explode(' | ', $document->experts) as $name) {
                $matchedTokens = $this->matchName($this->normalizeName($name), $normalizedQuery);
                if ($matchedTokens !== null) {
                    $matches[(int) $document->content_id] = true;
                    $tokens = array_merge($tokens, $matchedTokens);
                    break;
                }
            }
        }

        return ['matches' => $matches, 'tokens' => array_values(array_unique($tokens))];
    }

    /**
     * Nom complet trouvé, ou nom de famille seul s'il est assez distinctif.
     * Le prénom seul ne suffit jamais : trop d'homonymes.
     *
     * @return string[]|null  mots reconnus, ou null si le nom n'est pas dans la requête
     */
    private function matchName(string $name, string $normalizedQuery): ?array
    {
        if ($name === '') {
            return null;
        }

        $parts = explode(' ', $name);

        if (str_contains($normalizedQuery, " {$name} ")) {
            return $parts;
        }

        $surname = end($parts);
        if (count($parts) > 1
            && mb_strlen($surname) >= self::MIN_SURNAME_LENGTH
            && str_contains($normalizedQuery, " {$surname} ")) {
            return [$surname];
        }

        return null;
    }

    private function normalizeName(string $value): string
    {
        $ascii = Str::lower(Str::ascii($value));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }

    /* ===================== DOCUMENTS ===================== */

    /**
     * @param  int[]  $contentIds
     * @return array<int, object>
     */
    private function loadDocuments(array $contentIds): array
    {
        if (empty($contentIds)) {
            return [];
        }

        return DB::table('ai_search_documents')
            ->whereIn('content_id', $contentIds)
            ->get(['content_id', 'title', 'experts', 'expert_ids', 'event_id', 'event_name', 'past_event_id', 'edition_year'])
            ->map(function ($doc) {
                $doc->expert_ids = json_decode($doc->expert_ids ?? '[]', true) ?: [];
                $doc->event_id = $doc->event_id !== null ? (int) $doc->event_id : null;
                $doc->past_event_id = $doc->past_event_id !== null ? (int) $doc->past_event_id : null;

                return $doc;
            })
            ->keyBy('content_id')
            ->all();
    }

    private function passesFilters(object $document, array $filters): bool
    {
        if (! empty($filters['event_ids']) && ! in_array($document->event_id, $filters['event_ids'], true)) {
            return false;
        }
        if (! empty($filters['past_event_ids']) && ! in_array($document->past_event_id, $filters['past_event_ids'], true)) {
            return false;
        }
        if (! empty($filters['expert_ids']) && ! array_intersect($document->expert_ids, $filters['expert_ids'])) {
            return false;
        }
        if (! empty($filters['exclude_expert_ids']) && array_intersect($document->expert_ids, $filters['exclude_expert_ids'])) {
            return false;
        }

        return true;
    }
}