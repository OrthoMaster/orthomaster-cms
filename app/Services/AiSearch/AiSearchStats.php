<?php

namespace App\Services\AiSearch;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Calculs du tableau de bord de l'assistant, à partir de ai_search_logs.
 *
 * Vocabulaire :
 * - tour : une ligne du journal (une question, un choix de pastille ou « Voir les vidéos ») ;
 * - conversation : un session_id ;
 * - tour réussi : status = ok. Les refus (quota, indisponibilité…) sont comptés à part.
 */
class AiSearchStats
{
    /** Actions par lesquelles l'assistant pose une question au lieu d'afficher des vidéos. */
    private const ASK_ACTIONS = ['ask', 'ask_edition', 'edition_not_found'];

    /** Actions qui affichent une liste de vidéos. */
    private const SHOW_ACTIONS = ['show', 'show_now'];

    public function __construct(
        private CarbonInterface $from,
        private CarbonInterface $to,
        private bool $excludeStaff,
    ) {}

    public function summary(): array
    {
        $ok = $this->ok();

        $totals = (clone $ok)->selectRaw('
            COUNT(*) AS turns,
            COUNT(DISTINCT l.session_id) AS conversations,
            COUNT(DISTINCT l.user_id) AS users,
            SUM(l.used_llm) AS llm_calls,
            SUM(l.input_tokens) AS input_tokens,
            SUM(l.output_tokens) AS output_tokens,
            SUM(l.thought_tokens) AS thought_tokens,
            SUM(l.embedding_tokens) AS embedding_tokens,
            AVG(l.latency_ms) AS avg_latency,
            AVG(CASE WHEN l.used_llm = 1 THEN l.input_tokens + l.output_tokens + l.thought_tokens END) AS avg_llm_tokens
        ')->first();

        $turns = (int) $totals->turns;
        $conversations = (int) $totals->conversations;
        $chatTokens = (int) $totals->input_tokens + (int) $totals->output_tokens + (int) $totals->thought_tokens;
        $allTokens = $chatTokens + (int) $totals->embedding_tokens;
        $cost = $this->cost((int) $totals->input_tokens, (int) $totals->output_tokens + (int) $totals->thought_tokens, (int) $totals->embedding_tokens);

        $all = (clone $this->base())->count();
        $errors = $all - $turns;

        return [
            'turns'                 => $turns,
            'conversations'         => $conversations,
            'users'                 => (int) $totals->users,
            'llm_calls'             => (int) $totals->llm_calls,
            'llm_call_rate'         => $this->ratio((int) $totals->llm_calls, $turns),
            'tokens_total'          => $allTokens,
            'tokens_input'          => (int) $totals->input_tokens,
            'tokens_output'         => (int) $totals->output_tokens + (int) $totals->thought_tokens,
            'tokens_embedding'      => (int) $totals->embedding_tokens,
            'tokens_per_turn'       => $turns ? (int) round($allTokens / $turns) : 0,
            'tokens_per_llm_call'   => (int) round((float) $totals->avg_llm_tokens),
            'cost_total'            => $cost,
            'cost_per_conversation' => $conversations ? $cost / $conversations : 0,
            'turns_per_user'        => $totals->users ? round($turns / $totals->users, 1) : 0,
            'avg_latency'           => (int) round((float) $totals->avg_latency),
            'p95_latency'           => $this->p95Latency(),
            'avg_rounds'            => $this->avgRounds(),
            'success_rate'          => $this->conversationSuccessRate($conversations),
            'no_result_rate'        => $this->ratio($this->noResultTurns(), $turns),
            'ask_rate'              => $this->ratio((clone $ok)->whereIn('l.action', self::ASK_ACTIONS)->count(), $turns),
            'errors'                => $errors,
            'error_rate'            => $this->ratio($errors, $all),
        ];
    }

    /**
     * Une ligne par jour, jours sans activité compris, pour les graphiques.
     */
    public function daily(): array
    {
        $rows = (clone $this->ok())
            ->selectRaw('
                DATE(l.created_at) AS day,
                COUNT(*) AS turns,
                COUNT(DISTINCT l.session_id) AS conversations,
                COUNT(DISTINCT l.user_id) AS users,
                SUM(l.input_tokens) AS input_tokens,
                SUM(l.output_tokens + l.thought_tokens) AS output_tokens,
                SUM(l.embedding_tokens) AS embedding_tokens
            ')
            ->groupByRaw('DATE(l.created_at)')
            ->get()
            ->keyBy('day');

        $days = [];
        for ($day = $this->from->copy()->startOfDay(); $day->lte($this->to); $day->addDay()) {
            $key = $day->toDateString();
            $row = $rows[$key] ?? null;
            $days[] = [
                'day'           => $key,
                'turns'         => (int) ($row->turns ?? 0),
                'conversations' => (int) ($row->conversations ?? 0),
                'users'         => (int) ($row->users ?? 0),
                'tokens'        => (int) (($row->input_tokens ?? 0) + ($row->output_tokens ?? 0) + ($row->embedding_tokens ?? 0)),
                'cost'          => $this->cost((int) ($row->input_tokens ?? 0), (int) ($row->output_tokens ?? 0), (int) ($row->embedding_tokens ?? 0)),
            ];
        }

        return $days;
    }

    /**
     * Comment les utilisateurs avancent : saisie libre, choix d'une pastille, ou « Voir les vidéos ».
     */
    public function inputTypes(): array
    {
        return (clone $this->ok())
            ->selectRaw('l.input_type, COUNT(*) AS total')
            ->groupBy('l.input_type')
            ->pluck('total', 'input_type')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Réponses de l'assistant : question, liste de vidéos, aucun résultat…
     */
    public function actions(): array
    {
        return (clone $this->ok())
            ->selectRaw('l.action, COUNT(*) AS total')
            ->groupBy('l.action')
            ->orderByDesc('total')
            ->pluck('total', 'action')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function errorCodes(): array
    {
        return (clone $this->base())
            ->where('l.status', 'error')
            ->selectRaw('l.error_code, COUNT(*) AS total')
            ->groupBy('l.error_code')
            ->orderByDesc('total')
            ->pluck('total', 'error_code')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Nombre de conversations selon leur nombre de tours (1, 2, 3, 4, 5+) :
     * des conversations longues signalent des réponses qui ne satisfont pas.
     */
    public function roundsDistribution(): array
    {
        $rows = DB::query()
            ->fromSub($this->perConversation(), 'c')
            ->selectRaw('LEAST(c.rounds, 5) AS bucket, COUNT(*) AS total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $distribution = [];
        foreach ([1, 2, 3, 4, 5] as $bucket) {
            $distribution[$bucket === 5 ? '5+' : (string) $bucket] = (int) ($rows[$bucket] ?? 0);
        }

        return $distribution;
    }

    /**
     * Utilisateurs les plus actifs : repère les gros consommateurs et les abus éventuels.
     */
    public function topUsers(int $limit = 15): array
    {
        $rows = (clone $this->base())
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->selectRaw("
                l.user_id,
                u.name,
                u.email,
                SUM(CASE WHEN l.status = 'ok' THEN 1 ELSE 0 END) AS turns,
                COUNT(DISTINCT CASE WHEN l.status = 'ok' THEN l.session_id END) AS conversations,
                SUM(l.input_tokens) AS input_tokens,
                SUM(l.output_tokens + l.thought_tokens) AS output_tokens,
                SUM(l.embedding_tokens) AS embedding_tokens,
                SUM(CASE WHEN l.error_code = 'rate_limited' THEN 1 ELSE 0 END) AS rate_limited,
                MAX(l.created_at) AS last_used
            ")
            ->groupBy('l.user_id', 'u.name', 'u.email')
            ->orderByDesc('turns')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            $tokens = (int) $row->input_tokens + (int) $row->output_tokens + (int) $row->embedding_tokens;

            return [
                'user_id'       => $row->user_id,
                'name'          => $row->name,
                'email'         => $row->email,
                'turns'         => (int) $row->turns,
                'conversations' => (int) $row->conversations,
                'tokens'        => $tokens,
                'cost'          => $this->cost((int) $row->input_tokens, (int) $row->output_tokens, (int) $row->embedding_tokens),
                'rate_limited'  => (int) $row->rate_limited,
                'last_used'     => $row->last_used,
            ];
        })->all();
    }

    /**
     * Dernières saisies sans résultat : la meilleure source d'idées pour améliorer l'index
     * (synonymes, alias de congrès, vidéos manquantes). Vide si le texte n'est pas journalisé.
     */
    public function recentNoResultQueries(int $limit = 20): array
    {
        return $this->noResultQuery(clone $this->ok())
            ->whereNotNull('l.query_text')
            ->orderByDesc('l.created_at')
            ->limit($limit)
            ->get(['l.query_text', 'l.created_at', 'l.action', 'l.result_count'])
            ->all();
    }

    /* ===================== CALCULS ===================== */

    /**
     * Coût estimé en euros : tarifs Google en USD, convertis au taux configuré.
     */
    private function cost(int $inputTokens, int $outputTokens, int $embeddingTokens): float
    {
        $price = config('ai_search.pricing');

        $usd = ($inputTokens * $price['chat_input']
            + $outputTokens * $price['chat_output']
            + $embeddingTokens * $price['embedding']) / 1_000_000;

        return $usd * $price['usd_to_eur'];
    }

    private function ratio(int $part, int $total): float
    {
        return $total ? round($part / $total * 100, 1) : 0.0;
    }

    private function noResultQuery(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('l.action', 'no_candidates')
                ->orWhere(fn ($q2) => $q2->whereIn('l.action', self::SHOW_ACTIONS)->where('l.result_count', 0));
        });
    }

    private function noResultTurns(): int
    {
        return $this->noResultQuery(clone $this->ok())->count();
    }

    /**
     * Conversations ayant affiché au moins une vidéo.
     */
    private function conversationSuccessRate(int $conversations): float
    {
        $successful = (clone $this->ok())
            ->whereIn('l.action', self::SHOW_ACTIONS)
            ->where('l.result_count', '>', 0)
            ->distinct()
            ->count('l.session_id');

        return $this->ratio($successful, $conversations);
    }

    private function perConversation(): Builder
    {
        return (clone $this->ok())
            ->whereNotNull('l.session_id')
            ->selectRaw('l.session_id, COUNT(*) AS rounds')
            ->groupBy('l.session_id');
    }

    private function avgRounds(): float
    {
        $avg = DB::query()->fromSub($this->perConversation(), 'c')->avg('c.rounds');

        return round((float) $avg, 1);
    }

    /**
     * 95 % des tours répondent en moins de ce temps : plus parlant que la moyenne,
     * que quelques réponses rapides sans LLM tirent vers le bas.
     */
    private function p95Latency(): int
    {
        $count = (clone $this->ok())->count();
        if ($count === 0) {
            return 0;
        }

        return (int) (clone $this->ok())
            ->orderBy('l.latency_ms')
            ->offset((int) floor(($count - 1) * 0.95))
            ->limit(1)
            ->value('l.latency_ms');
    }

    /* ===================== REQUÊTES DE BASE ===================== */

    /**
     * Toutes les lignes de la période, équipe interne exclue si demandé.
     */
    private function base(): Builder
    {
        $query = DB::table('ai_search_logs as l')
            ->whereBetween('l.created_at', [$this->from, $this->to]);

        if ($this->excludeStaff) {
            $query->whereNotIn('l.user_id', function ($q) {
                $q->select('users.id')
                    ->from('users')
                    ->join('roles', 'roles.id', '=', 'users.role_id')
                    ->whereIn('roles.name', config('ai_search.logging.staff_roles'));
            });
        }

        return $query;
    }

    private function ok(): Builder
    {
        return (clone $this->base())->where('l.status', 'ok');
    }
}