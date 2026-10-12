<?php

namespace App\Services\AiSearch;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VectorStore
{
    private const CACHE_PREFIX = 'ai_search:vectors';

    /** Mémo par requête HTTP : évite de relire le cache plusieurs fois dans un même appel. */
    private ?array $loaded = null;

    /**
     * @param  float[]  $vector
     */
    public static function pack(array $vector): string
    {
        return pack('g*', ...$vector);
    }

    public function flush(): void
    {
        Cache::forget($this->cacheKey());
        $this->loaded = null;
    }

    /**
     * Meilleur morceau de chaque média, trié par similarité décroissante.
     * Les vecteurs étant normalisés, le produit scalaire est la similarité cosinus.
     *
     * @param  float[]  $queryVector
     * @return array<int, array{score: float, chunk_id: int}>  clé = content_id
     */
    public function search(array $queryVector, int $limit, ?float $minScore = null): array
    {
        $data = $this->load();
        $dims = $data['dims'];
        if ($data['count'] === 0 || count($queryVector) !== $dims) {
            return [];
        }

        $query = array_values($queryVector);
        $bytes = $dims * 4;
        $best = [];

        for ($n = 0; $n < $data['count']; $n++) {
            // unpack() renvoie un tableau indexé à partir de 1.
            $vector = unpack('g*', substr($data['blob'], $n * $bytes, $bytes));
            $dot = 0.0;
            for ($i = 0; $i < $dims; $i++) {
                $dot += $query[$i] * $vector[$i + 1];
            }

            $contentId = $data['content_ids'][$n];
            if (! isset($best[$contentId]) || $dot > $best[$contentId]['score']) {
                $best[$contentId] = ['score' => $dot, 'chunk_id' => $data['chunk_ids'][$n]];
            }
        }

        if ($minScore !== null) {
            $best = array_filter($best, fn ($hit) => $hit['score'] >= $minScore);
        }

        uasort($best, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($best, 0, $limit, true);
    }

    private function load(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $dims = config('ai_search.embedding.dimensions');

        // Un seul blob concaténé : bien plus léger à (dé)sérialiser qu'un tableau de tableaux.
        return $this->loaded = Cache::rememberForever($this->cacheKey(), function () use ($dims) {
            $chunkIds = [];
            $contentIds = [];
            $blob = '';

            $rows = DB::table('ai_search_chunks')
                ->where('model', config('ai_search.embedding.model'))
                ->where('dimensions', $dims)
                ->orderBy('id')
                ->select(['id', 'content_id', 'embedding'])
                ->cursor();

            foreach ($rows as $row) {
                // Un vecteur de mauvaise taille décalerait tous les suivants dans le blob.
                if (strlen($row->embedding) !== $dims * 4) {
                    continue;
                }
                $chunkIds[] = (int) $row->id;
                $contentIds[] = (int) $row->content_id;
                $blob .= $row->embedding;
            }

            return [
                'dims'        => $dims,
                'count'       => count($chunkIds),
                'chunk_ids'   => $chunkIds,
                'content_ids' => $contentIds,
                'blob'        => $blob,
            ];
        });
    }

    private function cacheKey(): string
    {
        return self::CACHE_PREFIX . ':' . config('ai_search.embedding.model') . ':' . config('ai_search.embedding.dimensions');
    }
}
