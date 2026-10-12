<?php

namespace App\Services\AiSearch;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Indexer
{
    public function __construct(
        private DocumentBuilder $builder,
        private GeminiClient $gemini,
        private VectorStore $store,
    ) {}

    /**
     * Indexe (ou retire) un média selon son état actuel en base.
     *
     * @return string  'indexed' | 'unchanged' | 'removed'
     */
    public function indexContent(int $contentId, bool $force = false): string
    {
        $built = $this->builder->build([$contentId])[$contentId] ?? null;

        // Supprimé, dépublié ou plus de type flash : il ne doit plus être proposé.
        if (! $built) {
            return $this->remove($contentId) ? 'removed' : 'unchanged';
        }

        $document = $built['document'];
        $chunks = $built['chunks'];

        $currentHash = DB::table('ai_search_documents')->where('content_id', $contentId)->value('content_hash');
        if (! $force && $currentHash === $document['content_hash']) {
            return 'unchanged';
        }

        $model = config('ai_search.embedding.model');
        $dimensions = config('ai_search.embedding.dimensions');

        // Réutilise les embeddings des morceaux inchangés : seule la partie modifiée est facturée.
        $existing = $force ? collect() : DB::table('ai_search_chunks')
            ->where('content_id', $contentId)
            ->where('model', $model)
            ->where('dimensions', $dimensions)
            ->pluck('embedding', 'text_hash');

        $toEmbed = [];
        foreach ($chunks as $i => $chunk) {
            if (! isset($existing[$chunk['text_hash']])) {
                $toEmbed[$i] = ['title' => $chunk['title'], 'text' => $chunk['text']];
            }
        }

        $fresh = $toEmbed
            ? array_combine(array_keys($toEmbed), $this->gemini->embedDocuments(array_values($toEmbed)))
            : [];

        $now = now();
        $rows = [];
        foreach ($chunks as $i => $chunk) {
            $rows[] = [
                'content_id'    => $contentId,
                'kind'          => $chunk['kind'],
                'position'      => $chunk['position'],
                'timestamp_sec' => $chunk['timestamp_sec'],
                'text'          => $chunk['text'],
                'text_hash'     => $chunk['text_hash'],
                'model'         => $model,
                'dimensions'    => $dimensions,
                'embedding'     => isset($fresh[$i]) ? VectorStore::pack($fresh[$i]) : $existing[$chunk['text_hash']],
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        // Les appels API sont faits avant la transaction : elle reste courte et ne bloque
        // pas les lectures pendant l'attente réseau.
        DB::transaction(function () use ($contentId, $rows, $document, $now) {
            DB::table('ai_search_chunks')->where('content_id', $contentId)->delete();
            DB::table('ai_search_chunks')->insert($rows);
            DB::table('ai_search_documents')->updateOrInsert(
                ['content_id' => $contentId],
                array_merge($document, ['indexed_at' => $now])
            );
        }, 3);

        $this->store->flush();

        return 'indexed';
    }

    /**
     * Réconciliation complète : retire ce qui n'est plus publié, indexe ce qui a changé.
     *
     * @return array{indexed: int, unchanged: int, removed: int, failed: int}
     */
    public function indexAll(bool $force = false, ?callable $onProgress = null): array
    {
        $stats = ['indexed' => 0, 'unchanged' => 0, 'removed' => 0, 'failed' => 0];

        $publishedIds = DB::table('content')
            ->where('type', 'flash')
            ->where('published', '1')
            ->orderBy('id_content')
            ->pluck('id_content')
            ->map(fn ($id) => (int) $id)
            ->all();

        // D'abord le retrait, pour que l'index ne propose jamais un média masqué même si la suite échoue.
        $staleIds = DB::table('ai_search_documents')->whereNotIn('content_id', $publishedIds)->pluck('content_id');
        foreach ($staleIds as $id) {
            $this->remove((int) $id);
            $stats['removed']++;
        }

        foreach ($publishedIds as $id) {
            try {
                $stats[$this->indexContent($id, $force)]++;
            } catch (GeminiException $e) {
                $stats['failed']++;
                Log::warning("ai-search: indexation du média {$id} échouée", ['error' => $e->getMessage()]);

                // Sans crédit, tous les suivants échoueraient aussi : inutile d'insister.
                if ($e->isOutOfCredit()) {
                    throw $e;
                }
            }

            if ($onProgress) {
                $onProgress($id);
            }
        }

        return $stats;
    }

    public function remove(int $contentId): bool
    {
        $removed = DB::transaction(function () use ($contentId) {
            $chunks = DB::table('ai_search_chunks')->where('content_id', $contentId)->delete();
            $documents = DB::table('ai_search_documents')->where('content_id', $contentId)->delete();

            return $chunks + $documents > 0;
        }, 3);

        if ($removed) {
            $this->store->flush();
        }

        return $removed;
    }
}
