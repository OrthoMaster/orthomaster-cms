<?php

namespace App\Observers;

use App\Jobs\IndexContentForAiSearch;
use App\Models\OldContent;

class OldContentAiSearchObserver
{
    public function saved(OldContent $content): void
    {
        // Un média qui quitte le type flash doit aussi sortir de l'index :
        // getOriginal() donne encore l'ancienne valeur à ce stade de l'événement.
        if ($content->type !== 'flash' && $content->getOriginal('type') !== 'flash') {
            return;
        }

        // afterCommit : si l'enregistrement est dans une transaction annulée, rien n'est indexé.
        IndexContentForAiSearch::dispatch((int) $content->getKey())->afterCommit();
    }

    public function deleted(OldContent $content): void
    {
        if ($content->type === 'flash') {
            IndexContentForAiSearch::dispatch((int) $content->getKey())->afterCommit();
        }
    }
}
