<?php

namespace App\Observers;

use App\Jobs\IndexContentForAiSearch;
use App\Models\Video;
use Illuminate\Support\Facades\DB;

class VideoAiSearchObserver
{
    public function saved(Video $video): void
    {
        // Seuls les highlights de la vidéo entrent dans l'index.
        if (! $video->wasRecentlyCreated && ! $video->wasChanged('highlights')) {
            return;
        }

        DB::table('content')
            ->where('video_id', $video->getKey())
            ->where('type', 'flash')
            ->pluck('id_content')
            ->each(fn ($id) => IndexContentForAiSearch::dispatch((int) $id)->afterCommit());
    }
}
