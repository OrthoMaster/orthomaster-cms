<?php

namespace App\Services\AiSearch;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * État d'une conversation, conservé en cache côté serveur.
 *
 * Les options proposées à l'app sont des identifiants opaques (o1, o2…) : l'app renvoie
 * l'identifiant choisi et le serveur retrouve ici le filtre correspondant, ce qui empêche
 * un client de forger un filtre arbitraire.
 */
class ConversationSession
{
    private const CACHE_PREFIX = 'ai_search:session:';

    /** Textes saisis par l'utilisateur et sous-thèmes choisis : forment la requête de recherche. */
    public array $messages = [];

    /** Filtres choisis (congrès, intervenant…) : montrés au LLM mais exclus de la requête. */
    public array $choices = [];

    public array $filters = [
        'event_ids'          => [],
        'past_event_ids'     => [],
        'expert_ids'         => [],
        'exclude_expert_ids' => [],
    ];

    public int $round = 0;

    /** @var array<string, array{type: string, value: int|string|int[], label: string}> */
    public array $options = [];

    /** @var int[]  vidéos pertinentes du dernier tour, dans l'ordre */
    public array $lastIds = [];

    /** @var array<int, array>  passage le plus pertinent de chaque vidéo du dernier tour */
    public array $lastMatches = [];

    private function __construct(
        public readonly string $id,
        public readonly int $userId,
    ) {}

    public static function start(int $userId): self
    {
        return new self((string) Str::uuid(), $userId);
    }

    /**
     * @throws SessionExpiredException
     */
    public static function resume(string $id, int $userId): self
    {
        $data = Cache::get(self::CACHE_PREFIX . $id);

        // Même message pour « inconnue » et « à quelqu'un d'autre » : ne rien révéler.
        if (! is_array($data) || $data['user_id'] !== $userId) {
            throw new SessionExpiredException('Session de recherche expirée');
        }

        $session = new self($id, $userId);
        foreach (['messages', 'choices', 'filters', 'round', 'options', 'lastIds', 'lastMatches'] as $field) {
            $session->{$field} = $data[$field];
        }

        return $session;
    }

    public function save(): void
    {
        Cache::put(self::CACHE_PREFIX . $this->id, [
            'user_id'     => $this->userId,
            'messages'    => $this->messages,
            'choices'     => $this->choices,
            'filters'     => $this->filters,
            'round'       => $this->round,
            'options'     => $this->options,
            'lastIds'     => $this->lastIds,
            'lastMatches' => $this->lastMatches,
        ], now()->addMinutes(config('ai_search.conversation.session_ttl_minutes')));
    }

    /**
     * Texte envoyé à la recherche : toute la conversation, pour qu'une précision
     * (« et chez l'enfant ? ») garde le sujet initial.
     */
    public function query(): string
    {
        return trim(implode("\n", $this->messages));
    }

    public function hasFilters(): bool
    {
        return (bool) array_filter($this->filters);
    }

    /**
     * Ajoute à un filtre existant (affinage par option : les choix se cumulent).
     *
     * @param  int[]  $ids
     */
    public function addFilter(string $key, array $ids): void
    {
        $this->filters[$key] = array_values(array_unique(array_merge($this->filters[$key], $ids)));
    }

    /**
     * Remplace le congrès en cours : citer un autre congrès en cours de conversation
     * doit changer de congrès, pas exiger les deux à la fois (ce qui ne donnerait rien).
     *
     * @param  int[]  $ids
     */
    public function replaceEventFilter(string $key, array $ids): void
    {
        $this->filters['event_ids'] = [];
        $this->filters['past_event_ids'] = [];
        $this->filters[$key] = array_values(array_unique($ids));
    }
}