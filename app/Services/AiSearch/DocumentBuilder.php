<?php

namespace App\Services\AiSearch;

use Illuminate\Support\Facades\DB;

class DocumentBuilder
{
    /** Ligne de chapitre des highlights : "mm:ss Titre" ou "h:mm:ss Titre". */
    private const CHAPTER_PATTERN = '/^\s*(?:(\d{1,2}):)?(\d{1,2}):(\d{2})\s+(.+?)\s*$/u';

    /** Longueur max d'un titre en base (colonne ai_search_documents.title). */
    private const TITLE_MAX = 500;

    /**
     * Construit les documents indexables des médias demandés.
     * Un média absent du résultat (inexistant, pas flash, pas publié) doit sortir de l'index.
     *
     * @param  int[]  $contentIds
     * @return array<int, array{document: array, chunks: array}>  clé = content_id
     */
    public function build(array $contentIds): array
    {
        if (empty($contentIds)) {
            return [];
        }

        $rows = DB::table('content as c')
            ->leftJoin('videos as v', 'v.id', '=', 'c.video_id')
            ->leftJoin('past_events as pe', 'pe.id', '=', 'c.id_past_event')
            // Les médias v1 n'ont souvent que content.event_id, sans édition.
            ->leftJoin('events as ev', 'ev.id', '=', DB::raw('COALESCE(pe.id_event, c.event_id)'))
            ->whereIn('c.id_content', $contentIds)
            ->where('c.type', 'flash')
            ->where('c.published', '1')
            ->select([
                'c.id_content', 'c.title', 'c.description', 'c.version', 'c.author',
                'v.highlights',
                'ev.id as event_id', 'ev.name as event_name',
                'pe.id as past_event_id', 'pe.edition_year',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Requête séparée plutôt que GROUP_CONCAT : évite les soucis de ONLY_FULL_GROUP_BY
        // et garde les id des experts pour les facettes.
        $experts = DB::table('expert_video as xv')
            ->join('experts as e', 'e.id', '=', 'xv.expert_id')
            ->whereIn('xv.content_id', $rows->pluck('id_content'))
            ->orderBy('e.name')
            ->get(['xv.content_id', 'e.id', 'e.name'])
            ->groupBy('content_id');

        $documents = [];
        foreach ($rows as $row) {
            $documents[(int) $row->id_content] = $this->buildOne(
                $row,
                ($experts[$row->id_content] ?? collect())->unique('id')->values()
            );
        }

        return $documents;
    }

    private function buildOne(object $row, $expertRows): array
    {
        $title = mb_substr($this->cleanInline($row->title), 0, self::TITLE_MAX);

        $expertNames = $expertRows->pluck('name')->map(fn ($n) => $this->cleanInline($n))->filter()->values()->all();
        $expertIds = $expertRows->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        // v1 et médias anciens : pas de lien expert_video, seul le champ libre author existe.
        if (empty($expertNames) && filled($row->author)) {
            $expertNames = [$this->cleanInline($row->author)];
        }

        $eventName = filled($row->event_name) ? $this->cleanInline($row->event_name) : null;
        $editionYear = filled($row->edition_year) ? $this->cleanInline($row->edition_year) : null;
        $eventLabel = trim(($eventName ?? '') . ' ' . ($editionYear ?? '')) ?: null;

        $paragraphs = $this->splitDescription((string) $row->description);
        $chapters = $this->parseHighlights((string) $row->highlights);

        /* --- Morceaux à embedder --- */

        $chunks = [];
        $mainLines = array_filter([
            $title,
            $expertNames ? 'Intervenants : ' . implode(', ', $expertNames) : null,
            $eventLabel ? 'Congrès : ' . $eventLabel : null,
        ]);
        // Le titre fait déjà partie du texte : pas de préfixe pour ce morceau.
        $chunks[] = $this->chunk('main', 0, null, '', implode("\n", $mainLines));

        foreach ($paragraphs as $i => $text) {
            $chunks[] = $this->chunk('description', $i, null, $title, $text);
        }
        foreach ($chapters as $i => $chapter) {
            $chunks[] = $this->chunk('chapter', $i, $chapter['seconds'], $title, $chapter['label']);
        }

        /* --- Document à plat --- */

        $document = [
            'content_id'    => (int) $row->id_content,
            'version'       => $row->version,
            'title'         => $title,
            'experts'       => $expertNames ? implode(' | ', $expertNames) : null,
            'expert_ids'    => json_encode($expertIds),
            'event_id'      => $row->event_id ? (int) $row->event_id : null,
            'event_name'    => $eventName,
            'past_event_id' => $row->past_event_id ? (int) $row->past_event_id : null,
            'edition_year'  => $editionYear,
            'search_text'   => implode("\n", array_filter([
                $title,
                implode(', ', $expertNames),
                $eventLabel,
                implode("\n", $paragraphs),
                implode("\n", array_column($chapters, 'label')),
            ])),
        ];

        // Le modèle et la dimension entrent dans le hash : changer de modèle réindexe tout.
        $document['content_hash'] = hash('sha256', json_encode([
            config('ai_search.embedding.model'),
            config('ai_search.embedding.dimensions'),
            $document,
            array_map(fn ($c) => [$c['kind'], $c['timestamp_sec'], $c['text']], $chunks),
        ]));

        return ['document' => $document, 'chunks' => $chunks];
    }

    private function chunk(string $kind, int $position, ?int $seconds, string $title, string $text): array
    {
        return [
            'kind'          => $kind,
            'position'      => $position,
            'timestamp_sec' => $seconds,
            'title'         => $title,
            'text'          => $text,
            'text_hash'     => hash('sha256', $title . "\n" . $text),
        ];
    }

    /* ===================== NETTOYAGE ET DÉCOUPAGE ===================== */

    /**
     * Regroupe les paragraphes jusqu'à environ N mots : les intertitres courts
     * ("Le contexte clinique") restent ainsi attachés au paragraphe qui les suit.
     *
     * @return string[]
     */
    private function splitDescription(string $description): array
    {
        $target = config('ai_search.indexing.description_chunk_words');
        $paragraphs = array_filter(
            array_map(fn ($p) => $this->cleanInline($p), preg_split('/\R+/u', $this->decode($description))),
            fn ($p) => $p !== ''
        );

        $chunks = [];
        $buffer = [];
        $words = 0;
        foreach ($paragraphs as $paragraph) {
            $buffer[] = $paragraph;
            $words += str_word_count($paragraph, 0, 'àâäéèêëîïôöùûüÿçœæÀÂÄÉÈÊËÎÏÔÖÙÛÜŸÇŒÆ\'’-');
            if ($words >= $target) {
                $chunks[] = implode("\n", $buffer);
                $buffer = [];
                $words = 0;
            }
        }
        if ($buffer) {
            $chunks[] = implode("\n", $buffer);
        }

        return $chunks;
    }

    /**
     * @return array<int, array{seconds: ?int, label: string}>
     */
    private function parseHighlights(string $highlights): array
    {
        $chapters = [];
        foreach (preg_split('/\R/u', $this->decode($highlights)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match(self::CHAPTER_PATTERN, $line, $m)) {
                $seconds = ((int) ($m[1] ?: 0)) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];
                $chapters[] = ['seconds' => $seconds, 'label' => $this->cleanInline($m[4])];
            } else {
                // Ligne sans horodatage : on garde le texte, il reste utile pour la recherche.
                $chapters[] = ['seconds' => null, 'label' => $this->cleanInline($line)];
            }
        }

        return $chapters;
    }

    private function decode(string $value): string
    {
        return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function cleanInline(?string $value): string
    {
        $value = str_replace("\u{00A0}", ' ', $this->decode((string) $value));

        return trim(preg_replace('/\s+/u', ' ', $value));
    }
}
