<?php

namespace App\Console\Commands;

use App\Services\AiSearch\GeminiException;
use App\Services\AiSearch\Indexer;
use Illuminate\Console\Command;

class AiSearchIndex extends Command
{
    protected $signature = 'ai-search:index
                            {--id=* : Indexer uniquement ces médias (id_content)}
                            {--force : Recalculer tous les embeddings, même inchangés}';

    protected $description = 'Indexe les médias flash publiés pour l\'assistant de recherche';

    /**
     * Sans --id : réconciliation complète (planifiée chaque nuit).
     */
    public function handle(Indexer $indexer): int
    {
        $ids = array_map('intval', $this->option('id'));
        $force = (bool) $this->option('force');

        try {
            if ($ids) {
                foreach ($ids as $id) {
                    $this->line("#{$id} : " . $indexer->indexContent($id, $force));
                }

                return self::SUCCESS;
            }

            $bar = $this->output->createProgressBar();
            $stats = $indexer->indexAll($force, fn () => $bar->advance());
            $bar->finish();
            $this->newLine(2);

            $this->table(['Indexés', 'Inchangés', 'Retirés', 'Échecs'], [array_values($stats)]);

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (GeminiException $e) {
            $this->newLine();
            $this->error($e->isOutOfCredit()
                ? 'Crédit Gemini épuisé : rechargez le compte puis relancez.'
                : $e->getMessage());

            return self::FAILURE;
        }
    }
}
