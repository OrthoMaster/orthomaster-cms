<?php

namespace App\Services\AiSearch;

use App\Models\OldContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Orchestration d'un tour de conversation : recherche, décision du LLM, validation.
 *
 * Le LLM propose, le code dispose : identifiants, facettes et seuils sont toujours
 * revérifiés ici, une réponse incohérente du modèle ne peut pas fuiter vers l'app.
 */
class AiSearchService
{
    /** Correspondance type d'option facette → clé de filtre de la session. */
    private const FACET_FILTER_KEYS = [
        'event'   => 'event_ids',
        'edition' => 'past_event_ids',
        'expert'  => 'expert_ids',
    ];

    /** Correspondance clé de detected_filters → facette qui en garantit la validité. */
    private const DETECTED_FILTER_FACETS = [
        'event_ids'          => 'event',
        'past_event_ids'     => 'edition',
        'expert_ids'         => 'expert',
        'exclude_expert_ids' => 'expert',
    ];

    /**
     * Option interne, jamais proposée par le LLM : groupe d'éditions d'un congrès,
     * proposé quand l'édition demandée n'existe pas.
     */
    private const OPTION_EDITION_GROUP = 'edition_group';

    /** Nombre max d'entrées par facette envoyées au LLM. */
    private const FACET_LIMIT = 10;

    private const EMPTY_USAGE = ['input_tokens' => 0, 'output_tokens' => 0, 'thought_tokens' => 0];

    public function __construct(
        private Retriever $retriever,
        private GeminiClient $gemini,
        private PromptBuilder $prompts,
        private EventMatcher $events,
    ) {}

    /**
     * @param  array{message?: string, option_id?: string, action?: string}  $input
     * @return array  réponse pour l'app, plus 'usage' et 'meta' destinés au journal
     *
     * @throws SessionExpiredException|GeminiException|InvalidArgumentException
     */
    public function handle(int $userId, ?string $sessionId, array $input): array
    {
        $session = $sessionId
            ? ConversationSession::resume($sessionId, $userId)
            : ConversationSession::start($userId);

        // Afficher les résultats du tour précédent : aucun appel API.
        if (($input['action'] ?? null) === 'show') {
            if (! $sessionId) {
                throw new InvalidArgumentException('Aucune recherche à afficher');
            }

            return $this->results($session, $this->showMessage(count($session->lastIds)), self::EMPTY_USAGE, 'show_now');
        }

        $session->round++;

        if (isset($input['option_id'])) {
            $this->applyOption($session, $input['option_id']);
        } else {
            $notFound = $this->applyMessage($session, $input['message']);
            if ($notFound) {
                return $notFound;
            }
        }

        $query = $session->query();
        if ($query === '' && ! $session->hasFilters()) {
            $session->save();

            return $this->noResults($session, 'Précisez ce que vous recherchez : un sujet, un intervenant ou un congrès.');
        }

        // Sans texte mais avec un congrès (« les vidéos de CDS 2024 ») : on parcourt ses vidéos.
        $browsing = $query === '';
        $retrieval = $browsing
            ? $this->retriever->browse($session->filters)
            : $this->retriever->search($query, $session->filters);
        $candidates = $retrieval['candidates'];

        // Rien trouvé : inutile de payer un appel LLM pour le dire.
        if (empty($candidates)) {
            $session->save();

            return $this->noResults($session, $this->noResultsMessage($session));
        }

        $facets = $this->facets($candidates);
        $llm = $this->gemini->generateJson(
            $this->prompts->system(),
            $this->prompts->user($session, $candidates, $facets, $browsing),
            $this->prompts->schema()
        );
        $decision = $this->validate($llm['data'], $candidates, $facets);

        // En parcours, chaque candidate a été demandée explicitement : le LLM peut les ordonner
        // mais pas en écarter. Sans cette garantie, une conversation vide le pousse à répondre
        // « aucune vidéo ».
        if ($browsing) {
            $decision['relevant_ids'] = array_values(array_unique(array_merge(
                $decision['relevant_ids'],
                array_column($candidates, 'content_id')
            )));
            if ($decision['action'] === 'show') {
                $decision['message'] = '';
            }
        }

        // Filtres reconnus dans la phrase (« les vidéos du Dr X ») : appliqués pour la suite
        // de la conversation et immédiatement aux candidates de ce tour.
        foreach ($decision['filters'] as $key => $ids) {
            $session->addFilter($key, $ids);
        }
        $candidates = array_values(array_filter($candidates, fn ($c) => $this->passesFilters($c, $session->filters)));
        $byId = array_column($candidates, null, 'content_id');
        $relevantIds = array_values(array_filter($decision['relevant_ids'], fn ($id) => isset($byId[$id])));

        $session->lastIds = $relevantIds;
        $session->lastMatches = array_filter(array_map(fn ($id) => $byId[$id]['match'], array_combine($relevantIds, $relevantIds)));

        $options = $decision['action'] === 'ask'
            ? $this->usefulOptions($decision['options'], array_map(fn ($id) => $byId[$id], $relevantIds))
            : [];

        $cfg = config('ai_search.conversation');
        $mustShow = $decision['action'] === 'show'
            || count($relevantIds) <= $cfg['show_threshold']
            || $session->round >= $cfg['max_rounds']
            || count($options) < 2;

        if ($mustShow) {
            $session->options = [];
            $session->save();

            // Le message du LLM a pu être rédigé comme une question : on garde le sien seulement s'il voulait afficher.
            $message = $decision['action'] === 'show' && $decision['message'] !== ''
                ? $decision['message']
                : $this->showMessage(count($relevantIds));

            return $this->results($session, $message, $llm['usage'], 'show');
        }

        return $this->question($session, $decision['message'], $options, count($relevantIds), $llm['usage'], 'ask');
    }

    /* ===================== SAISIE ===================== */

    /**
     * Enregistre la saisie et applique le congrès qu'elle cite.
     *
     * @return array|null  réponse immédiate si l'édition demandée n'existe pas, sinon null
     */
    private function applyMessage(ConversationSession $session, string $message): ?array
    {
        $event = $this->events->match(trim($message));

        if ($event['status'] === 'edition_not_found') {
            // Le sujet reste en mémoire : après le choix d'une édition, la recherche le reprend.
            if ($event['remaining'] !== '') {
                $session->messages[] = $event['remaining'];
            }

            return $this->editionNotFound($session, $event);
        }

        if ($event['status'] === 'matched') {
            $session->replaceEventFilter($event['filter_key'], $event['ids']);
            $session->choices[] = $event['label'];
        }

        // Le nom du congrès est retiré de la requête : il est déjà un filtre strict, et ses mots
        // (« chirurgie », « sport ») fausseraient la recherche par le sens.
        if ($event['remaining'] !== '') {
            $session->messages[] = $event['remaining'];
        }

        return null;
    }

    /**
     * Répond sans appel LLM et propose les éditions qui existent réellement.
     */
    private function editionNotFound(ConversationSession $session, array $event): array
    {
        $options = [];
        foreach ($event['available'] as $group) {
            $count = DB::table('ai_search_documents')->whereIn('past_event_id', $group['ids'])->count();
            if ($count > 0) {
                $options[] = [
                    'type'  => self::OPTION_EDITION_GROUP,
                    'value' => $group['ids'],
                    'label' => $group['label'],
                    'count' => $count,
                ];
            }
        }

        $intro = "Il n'y a pas d'édition {$event['requested']} de {$event['event_name']}.";

        if (! $options) {
            $session->save();

            return $this->noResults($session, "{$intro} Aucune vidéo n'est disponible pour ce congrès.");
        }

        return $this->question(
            $session,
            "{$intro} Voici les éditions disponibles :",
            array_slice($options, 0, config('ai_search.conversation.max_options')),
            null,
            self::EMPTY_USAGE,
            'edition_not_found'
        );
    }

    /* ===================== OPTIONS ===================== */

    private function applyOption(ConversationSession $session, string $optionId): void
    {
        $option = $session->options[$optionId] ?? null;
        if (! $option) {
            throw new InvalidArgumentException('Option inconnue');
        }

        match ($option['type']) {
            // Un sous-thème précise le sujet : il rejoint la requête de recherche.
            'theme' => $session->messages[] = 'Précision : ' . $option['label'],
            self::OPTION_EDITION_GROUP => $session->replaceEventFilter('past_event_ids', $option['value']),
            default => $session->addFilter(self::FACET_FILTER_KEYS[$option['type']], [(int) $option['value']]),
        };

        if ($option['type'] !== 'theme') {
            $session->choices[] = $option['label'];
        }

        $session->options = [];
    }

    /**
     * Ne garde que les options qui partagent vraiment les résultats : une facette qui
     * couvre toutes les vidéos, ou aucune, ne fait pas avancer l'utilisateur.
     *
     * @param  array<int, array>  $relevant  candidates pertinentes
     */
    private function usefulOptions(array $options, array $relevant): array
    {
        $counts = $this->facets($relevant);
        $total = count($relevant);
        $useful = [];

        foreach ($options as $option) {
            if ($option['type'] === 'theme') {
                $useful[] = $option + ['count' => null];
                continue;
            }

            $count = $counts[$option['type']][(int) $option['value']]['count'] ?? 0;
            if ($count > 0 && $count < $total) {
                $useful[] = $option + ['count' => $count];
            }
        }

        return array_slice($useful, 0, config('ai_search.conversation.max_options'));
    }

    /* ===================== VALIDATION ===================== */

    /**
     * Ramène la réponse du LLM à ce qui existe réellement : identifiants parmi les
     * candidates, facettes parmi celles calculées, libellés fournis par nos données.
     */
    private function validate(array $data, array $candidates, array $facets): array
    {
        $candidateIds = array_column($candidates, 'content_id');

        $relevantIds = [];
        foreach ((array) ($data['relevant_ids'] ?? []) as $id) {
            $id = (int) $id;
            if (in_array($id, $candidateIds, true) && ! in_array($id, $relevantIds, true)) {
                $relevantIds[] = $id;
            }
        }

        $options = [];
        $seen = [];
        foreach ((array) ($data['options'] ?? []) as $option) {
            $type = $option['type'] ?? null;
            if (! in_array($type, PromptBuilder::OPTION_TYPES, true)) {
                continue;
            }

            if ($type === 'theme') {
                $label = Str::limit(trim((string) ($option['value'] ?? $option['label'] ?? '')), 60, '');
                if ($label === '') {
                    continue;
                }
                $option = ['type' => 'theme', 'value' => $label, 'label' => $label];
            } else {
                $id = (int) ($option['value'] ?? 0);
                if (! isset($facets[$type][$id])) {
                    continue;
                }
                // Le libellé vient de nos données, pas du modèle.
                $option = ['type' => $type, 'value' => $id, 'label' => $facets[$type][$id]['label']];
            }

            $key = $option['type'] . ':' . $option['value'];
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $options[] = $option;
            }
        }

        $filters = [];
        foreach (self::DETECTED_FILTER_FACETS as $key => $facet) {
            $ids = array_values(array_filter(
                array_map('intval', (array) ($data['detected_filters'][$key] ?? [])),
                fn ($id) => isset($facets[$facet][$id])
            ));
            if ($ids) {
                $filters[$key] = $ids;
            }
        }

        $action = ($data['action'] ?? null) === 'ask' ? 'ask' : 'show';
        $message = Str::limit(trim((string) ($data['message'] ?? '')), 300);

        // Une question sans texte ne peut pas être posée.
        if ($action === 'ask' && $message === '') {
            $action = 'show';
        }

        return [
            'action'       => $action,
            'message'      => $message,
            'relevant_ids' => $relevantIds,
            'options'      => $options,
            'filters'      => $filters,
        ];
    }

    private function passesFilters(array $candidate, array $filters): bool
    {
        if ($filters['event_ids'] && ! in_array($candidate['event_id'], $filters['event_ids'], true)) {
            return false;
        }
        if ($filters['past_event_ids'] && ! in_array($candidate['past_event_id'], $filters['past_event_ids'], true)) {
            return false;
        }
        if ($filters['expert_ids'] && ! array_intersect($candidate['expert_ids'], $filters['expert_ids'])) {
            return false;
        }
        if ($filters['exclude_expert_ids'] && array_intersect($candidate['expert_ids'], $filters['exclude_expert_ids'])) {
            return false;
        }

        return true;
    }

    /* ===================== FACETTES ===================== */

    /**
     * Comptage fait par le code et non par le LLM : les nombres affichés sur les options
     * sont toujours exacts.
     *
     * @return array{event: array<int, array>, edition: array<int, array>, expert: array<int, array>}
     */
    private function facets(array $candidates): array
    {
        $events = [];
        $editions = [];
        $expertCounts = [];

        foreach ($candidates as $c) {
            if ($c['event_id']) {
                $events[$c['event_id']] ??= ['label' => $c['event_name'] ?? '', 'count' => 0];
                $events[$c['event_id']]['count']++;
            }
            if ($c['past_event_id']) {
                $label = trim(($c['event_name'] ?? '') . ' ' . ($c['edition_year'] ?? ''));
                $editions[$c['past_event_id']] ??= ['label' => $label, 'count' => 0];
                $editions[$c['past_event_id']]['count']++;
            }
            foreach ($c['expert_ids'] as $expertId) {
                $expertCounts[$expertId] = ($expertCounts[$expertId] ?? 0) + 1;
            }
        }

        $names = $expertCounts
            ? DB::table('experts')->whereIn('id', array_keys($expertCounts))->pluck('name', 'id')
            : collect();

        $experts = [];
        foreach ($expertCounts as $expertId => $count) {
            if (isset($names[$expertId])) {
                $experts[$expertId] = ['label' => $names[$expertId], 'count' => $count];
            }
        }

        return [
            'event'   => $this->topFacets($events),
            'edition' => $this->topFacets($editions),
            'expert'  => $this->topFacets($experts),
        ];
    }

    private function topFacets(array $items): array
    {
        uasort($items, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($items, 0, self::FACET_LIMIT, true);
    }

    /* ===================== RÉPONSES ===================== */

    /**
     * @param  array<int, array{type: string, value: mixed, label: string, count: ?int}>  $options
     */
    private function question(ConversationSession $session, string $message, array $options, ?int $total, array $usage, string $action): array
    {
        $session->options = [];
        $payloadOptions = [];
        foreach (array_values($options) as $i => $option) {
            $optionId = 'o' . ($i + 1);
            $session->options[$optionId] = $option;
            $payloadOptions[] = ['id' => $optionId, 'label' => $option['label'], 'count' => $option['count']];
        }
        $session->save();

        return [
            'session_id' => $session->id,
            'type'       => 'question',
            'message'    => $message,
            'options'    => $payloadOptions,
            // null : pas de liste de résultats derrière la question (l'app masque « Voir les résultats »).
            'total'      => $total,
            'usage'      => $usage,
            'meta'       => ['round' => $session->round, 'action' => $action, 'result_count' => $total ?? 0],
        ];
    }

    private function noResults(ConversationSession $session, string $message): array
    {
        $session->lastIds = [];
        $session->lastMatches = [];
        $session->options = [];
        $session->save();

        return $this->results($session, $message, self::EMPTY_USAGE, 'no_candidates');
    }

    private function noResultsMessage(ConversationSession $session): string
    {
        if (! $session->choices) {
            return 'Je n\'ai trouvé aucune vidéo correspondante. Essayez de reformuler votre recherche.';
        }

        $criteria = implode(', ', array_unique($session->choices));

        return "Aucune vidéo ne correspond à tous vos critères ({$criteria}). "
            . 'Lancez une nouvelle recherche avec moins de critères.';
    }

    private function results(ConversationSession $session, string $message, array $usage, string $action): array
    {
        $ids = array_slice($session->lastIds, 0, config('ai_search.conversation.max_results'));

        return [
            'session_id' => $session->id,
            'type'       => 'results',
            'message'    => $message,
            'videos'     => $this->videos($ids, $session->lastMatches),
            'total'      => count($session->lastIds),
            'usage'      => $usage,
            'meta'       => ['round' => $session->round, 'action' => $action, 'result_count' => count($session->lastIds)],
        ];
    }

    /**
     * Même forme que la recherche classique (content.* + expert_names + thumbnail),
     * pour que l'app réutilise ses composants d'affichage.
     */
    private function videos(array $ids, array $matches): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Revérifie la publication : la vidéo a pu être masquée depuis l'indexation.
        $videos = OldContent::where('type', 'flash')
            ->where('published', '1')
            ->select('content.*')
            ->selectRaw('content.author as expert_names')
            ->whereIn('id_content', $ids)
            ->orderByRaw("FIELD(id_content, {$placeholders})", $ids)
            ->get();

        return $videos->map(function (OldContent $video) use ($matches) {
            $match = $matches[$video->id_content] ?? null;
            $video->setAttribute('matched_chapter', $match && $match['kind'] === 'chapter' && $match['timestamp_sec'] !== null
                ? ['label' => $match['text'], 'seconds' => $match['timestamp_sec']]
                : null);

            return $video;
        })->all();
    }

    private function showMessage(int $count): string
    {
        return match (true) {
            $count === 0 => 'Je n\'ai trouvé aucune vidéo correspondante. Essayez de reformuler votre recherche.',
            $count === 1 => 'Voici la vidéo qui correspond à votre recherche.',
            default      => "Voici les {$count} vidéos qui correspondent à votre recherche.",
        };
    }
}