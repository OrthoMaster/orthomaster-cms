<?php

namespace App\Console\Commands;

use App\Services\AiSearch\GeminiException;
use App\Services\AiSearch\Retriever;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AiSearchTest extends Command
{
    protected $signature = 'ai-search:test
                            {query : Requête en langage naturel}
                            {--limit=15 : Nombre de lignes affichées}
                            {--event=* : Filtrer par events.id}
                            {--expert=* : Filtrer par experts.id}';

    protected $description = 'Affiche les candidats de la recherche hybride, pour calibrer la pertinence';

    public function handle(Retriever $retriever): int
    {
        $filters = array_filter([
            'event_ids'  => array_map('intval', $this->option('event')),
            'expert_ids' => array_map('intval', $this->option('expert')),
        ]);

        try {
            $result = $retriever->search($this->argument('query'), $filters);
        } catch (GeminiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach (array_slice($result['candidates'], 0, (int) $this->option('limit')) as $i => $c) {
            $rows[] = [
                $i + 1,
                $c['content_id'],
                Str::limit($c['title'], 45),
                Str::limit(trim(($c['event_name'] ?? '') . ' ' . ($c['edition_year'] ?? '')), 25),
                $c['vector_score'] !== null ? number_format($c['vector_score'], 3) : '—',
                $c['keyword_score'] !== null ? number_format($c['keyword_score'], 2) : '—',
                $c['match'] ? $this->describeMatch($c['match']) : '',
            ];
        }

        $this->table(['#', 'id', 'Titre', 'Congrès', 'Cosinus', 'Mots-clés', 'Meilleur passage'], $rows);

        $t = $result['timings'];
        $this->line(sprintf(
            '%d candidats — embed %d ms, vecteurs %d ms, plein texte %d ms, total %d ms',
            count($result['candidates']), $t['embed_ms'], $t['vector_ms'], $t['keyword_ms'], $t['total_ms']
        ));

        return self::SUCCESS;
    }

    private function describeMatch(array $match): string
    {
        $time = $match['timestamp_sec'] !== null
            ? sprintf(' %d:%02d', intdiv($match['timestamp_sec'], 60), $match['timestamp_sec'] % 60)
            : '';

        return "[{$match['kind']}{$time}] " . Str::limit($match['text'], 60);
    }
}
