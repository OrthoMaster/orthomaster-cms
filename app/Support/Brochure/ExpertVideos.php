<?php

namespace App\Support\Brochure;

use App\Models\Expert;

class ExpertVideos
{
    /**
     * Vidéos OrthoMaster d'un expert.
     * À brancher sur vos modèles, ex. :
     *   return $expert->videos()->latest()->get()->map(fn ($v) => [
     *       'title' => $v->title, 'description' => $v->summary, 'duration' => $v->duration_label,
     *       'url' => $v->public_url, 'thumbnail' => $v->thumbnail_url,
     *   ])->all();
     */
    public static function for(Expert $expert): array
    {
        return [];
    }
}
