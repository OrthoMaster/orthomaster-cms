<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Sujets de diffusion d'un contenu.
 *
 * Distincts du sujet principal porté par `topic_id` : celui-ci classe le
 * contenu, tandis que ceux-ci déterminent qui reçoit la notification.
 */
trait HandlesContentTopics
{
    /**
     * Sujets sélectionnables : les sous-sujets, et les sujets qui n'en
     * regroupent aucun. Un sujet parent ne sert qu'à regrouper.
     */
    protected function topicOptions()
    {
        return DB::table('topics as t')
            ->leftJoin('topics as p', 'p.id', '=', 't.parent_id')
            ->select('t.id')
            ->selectRaw("CASE WHEN p.id IS NULL THEN t.name ELSE CONCAT(p.name, ' · ', t.name) END as name")
            ->where('t.active_flg', 1)
            ->whereNotExists(function ($q) {
                $q->from('topics as c')->whereColumn('c.parent_id', 't.id')->selectRaw('1');
            })
            ->orderByRaw("CASE WHEN p.id IS NULL THEN t.name ELSE CONCAT(p.name, ' · ', t.name) END")
            ->get();
    }

    /**
     * Sujets déjà rattachés à un contenu.
     */
    protected function selectedTopicIds(?int $contentId): array
    {
        if (!$contentId) {
            return [];
        }

        return DB::table('topic_content')
            ->where('content_type', $this->contentType())
            ->where('content_id', $contentId)
            ->pluck('topic_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Remplace les sujets d'un contenu par ceux transmis.
     */
    protected function syncTopics(int $contentId, array $topicIds): void
    {
        $type = $this->contentType();

        DB::table('topic_content')
            ->where('content_type', $type)
            ->where('content_id', $contentId)
            ->delete();

        $ids = array_values(array_unique(array_filter(array_map('intval', $topicIds))));

        if (empty($ids)) {
            return;
        }

        $now = now();

        DB::table('topic_content')->insert(
            array_map(fn ($topicId) => [
                'topic_id'     => $topicId,
                'content_type' => $type,
                'content_id'   => $contentId,
                'created_at'   => $now,
                'updated_at'   => $now,
            ], $ids)
        );
    }

    /**
     * Réponse après enregistrement. Ouverte dans une fenêtre, la page
     * se contente de prévenir son parent, qui referme et rafraîchit.
     */
    protected function afterSave(\Illuminate\Http\Request $request, string $route, string $message)
    {
        if ($request->boolean('embed')) {
            return response()->view('partials.embed-done');
        }

        return redirect()->route($route)->with('message', $message);
    }

    /** Identifiant du type de contenu, défini par chaque contrôleur */
    abstract protected function contentType(): string;
}