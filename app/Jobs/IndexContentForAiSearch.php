<?php

namespace App\Jobs;

use App\Services\AiSearch\Indexer;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Réindexe un média après modification.
 *
 * Unique jusqu'au démarrage : plusieurs enregistrements rapprochés (formulaire, blocs,
 * experts) ne créent qu'un seul job, mais une modification faite pendant l'indexation
 * en relance bien un nouveau.
 */
class IndexContentForAiSearch implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [60, 300];

    public int $uniqueFor = 600;

    public function __construct(public int $contentId)
    {
        $this->onQueue(config('ai_search.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->contentId;
    }

    public function handle(Indexer $indexer): void
    {
        $indexer->indexContent($this->contentId);
    }
}
