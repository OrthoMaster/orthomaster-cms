<?php

namespace App\Services;

use App\Models\User;
use App\Models\VideoAds;
use App\Models\VideoAdDailyStat;
use App\Models\VideoAdImpression;
use App\Models\VideoAdTarget;
use App\Models\VideoAdView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VideoAdService
{
    /** Nombre de publicités renvoyées par appel */
    public const FEED_SIZE = 4;

    /** Poids dont bénéficie toute publicité, ciblage ou non */
    private const BASE_SCORE = 100;

    /** Poids supplémentaire maximal accordé par le ciblage */
    private const MAX_BOOST = 100;

    /**
     * Publicités proposées à un utilisateur.
     *
     * Toutes les publicités concourent : les points ne filtrent pas,
     * ils augmentent la probabilité d'être tirée pour un profil donné.
     *
     * Lorsque le contenu d'origine est connu, les annonceurs qui ont
     * parrainé l'édition dont il est issu voient leur score multiplié,
     * pour la durée convenue lors de la préparation.
     */
    public function getFeed(?User $user = null, int $limit = self::FEED_SIZE, ?int $contentId = null)
    {
        $ads = VideoAds::query()
            ->with('adsCompany')
            ->get();

        if ($ads->isEmpty()) {
            return $ads;
        }

        $professionId = $user?->profession_id;
        $topicIds     = $user ? $this->userTopicIds($user) : [];

        $adIds   = $ads->pluck('id')->all();
        $targets = $this->targetsFor($adIds);
        $budgets = $this->budgetsFor($adIds);

        [$sponsorIds, $multiplier] = $this->sponsorBoostFor($contentId);

        $scored = $ads->map(function (VideoAds $ad) use (
            $targets,
            $budgets,
            $professionId,
            $topicIds,
            $sponsorIds,
            $multiplier
        ) {
            $score = $this->scoreFor(
                $targets[$ad->id] ?? [],
                $budgets[$ad->id] ?? 0,
                $professionId,
                $topicIds
            );

            // Le parrainage ne change pas le ciblage : il amplifie le tout
            if ($multiplier > 1 && in_array($ad->ads_company_id, $sponsorIds, true)) {
                $score *= $multiplier;
            }

            $ad->setAttribute('selection_score', $score);

            return $ad;
        });

        return $this->weightedSample($scored, $limit);
    }

    /**
     * Score d'une publicité pour un profil : un socle commun, augmenté
     * par les points placés sur la profession et sur les sujets suivis.
     *
     * Les deux axes pèsent autant l'un que l'autre, et chacun dispose de
     * l'enveloppe entière : cent points souscrits, c'est cent points à
     * placer sur les professions et autant sur les centres d'intérêt.
     */
    private function scoreFor(array $targets, int $pointsTotal, ?int $professionId, array $topicIds): float
    {
        if ($pointsTotal <= 0) {
            return self::BASE_SCORE;
        }

        $professionPoints = $professionId
            ? ($targets[VideoAdTarget::TYPE_PROFESSION][$professionId] ?? 0)
            : 0;

        // Les sujets suivis cumulent leurs points, sans dépasser l'enveloppe
        $topicPoints = 0;

        foreach ($topicIds as $topicId) {
            $topicPoints += $targets[VideoAdTarget::TYPE_TOPIC][$topicId] ?? 0;
        }

        $professionRatio = min(1, $professionPoints / $pointsTotal);
        $topicRatio      = min(1, $topicPoints / $pointsTotal);

        $boost = self::MAX_BOOST * (0.5 * $professionRatio + 0.5 * $topicRatio);

        return self::BASE_SCORE + $boost;
    }

    /**
     * Points par publicité, indexés par type puis par cible.
     *
     * Seule la répartition en vigueur est retenue : une publicité dont
     * aucune répartition n'a encore pris effet ne reçoit aucun point,
     * et concourt donc à poids égal partout.
     */
    private function targetsFor(array $adIds, ?string $onDate = null): array
    {
        $dates = VideoAdTarget::activeDates($adIds, $onDate);

        if (empty($dates)) {
            return [];
        }

        $query = VideoAdTarget::query()->where('points', '>', 0);

        // Chaque publicité a sa propre date de référence
        $query->where(function ($outer) use ($dates) {
            foreach ($dates as $adId => $date) {
                $outer->orWhere(function ($inner) use ($adId, $date) {
                    $inner->where('video_ads_id', $adId)
                        ->where('effective_from', $date);
                });
            }
        });

        $map = [];

        foreach ($query->get(['video_ads_id', 'target_type', 'target_id', 'points']) as $row) {
            $map[$row->video_ads_id][$row->target_type][$row->target_id] = (int) $row->points;
        }

        return $map;
    }

    /**
     * Enveloppe en vigueur pour chaque publicité.
     *
     * @return array<int,int> video_ads_id => points_total
     */
    private function budgetsFor(array $adIds, ?string $onDate = null): array
    {
        $dates = VideoAdTarget::activeDates($adIds, $onDate);

        if (empty($dates)) {
            return [];
        }

        $rows = DB::table('video_ad_budgets')
            ->where(function ($outer) use ($dates) {
                foreach ($dates as $adId => $date) {
                    $outer->orWhere(function ($inner) use ($adId, $date) {
                        $inner->where('video_ads_id', $adId)
                            ->where('effective_from', $date);
                    });
                }
            })
            ->get(['video_ads_id', 'points_total']);

        $map = [];

        foreach ($rows as $row) {
            $map[$row->video_ads_id] = (int) $row->points_total;
        }

        return $map;
    }

    /**
     * Sujets suivis par l'utilisateur.
     */
    private function userTopicIds(User $user): array
    {
        return DB::table('topic_user')
            ->where('user_id', $user->id)
            ->orderBy('topic_id')
            ->pluck('topic_id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /**
     * Tirage sans remise, chaque publicité pesant son score : une publicité
     * bien ciblée sort plus souvent, sans jamais écarter les autres.
     */
    private function weightedSample($ads, int $limit)
    {
        $pool     = $ads->values()->all();
        $selected = [];

        while (count($selected) < $limit && !empty($pool)) {
            $total = array_sum(array_map(fn($ad) => $ad->selection_score, $pool));

            if ($total <= 0) {
                break;
            }

            $draw       = mt_rand() / mt_getrandmax() * $total;
            $cumulative = 0;

            foreach ($pool as $index => $ad) {
                $cumulative += $ad->selection_score;

                if ($draw <= $cumulative) {
                    $selected[] = $ad;
                    array_splice($pool, $index, 1);
                    break;
                }
            }
        }

        return collect($selected);
    }

    public function recordImpression(VideoAds $videoAd, ?int $userId = null, ?int $contentId = null): VideoAdImpression
    {
        // Les impressions d'un même affichage arrivent en parallèle :
        // un verrou croisé se résout en rejouant la transaction
        return DB::transaction(function () use ($videoAd, $userId, $contentId) {
            // Sans contenu d'origine, la consultation est facturée comme avant
            $isFree = $this->isFreeConsultation($videoAd, $userId, $contentId);

            $impression = VideoAdImpression::create([
                'video_ads_id' => $videoAd->id,
                'user_id'      => $userId,
                'is_free'      => $isFree,
            ]);

            $videoAd->incrementImpressions();

            $this->recordTargetedStats($videoAd, $userId, [
                'impressions_count'      => 1,
                'free_impressions_count' => $isFree ? 1 : 0,
            ]);

            $this->bumpDailyStat($videoAd, impressions: 1, freeImpressions: $isFree ? 1 : 0);

            return $impression;
        }, 5);
    }

    public function recordView(
        VideoAds $videoAd,
        bool $isCompleted = false,
        ?int $userId = null,
        ?bool $isContacted = false,
        ?int $contentId = null,
    ): VideoAdView {
        return DB::transaction(function () use (
            $videoAd,
            $isCompleted,
            $userId,
            $isContacted,
            $contentId
        ) {
            $isFree = $this->isFreeConsultation($videoAd, $userId, $contentId);

            $view = VideoAdView::create([
                'video_ads_id' => $videoAd->id,
                'user_id'      => $userId,
                'is_completed' => $isCompleted,
                'is_contacted' => $isContacted,
                'is_free'      => $isFree,
            ]);

            $videoAd->incrementViews();

            $this->recordTargetedStats($videoAd, $userId, [
                'views_count'                => 1,
                'free_views_count'           => $isFree ? 1 : 0,
                'completed_views_count'      => $isCompleted ? 1 : 0,
                'free_completed_views_count' => ($isCompleted && $isFree) ? 1 : 0,
                'contacted_count'            => $isContacted ? 1 : 0,
                'free_contacted_count'       => ($isContacted && $isFree) ? 1 : 0,
            ]);

            $this->bumpDailyStat(
                $videoAd,
                views: 1,
                freeViews: $isFree ? 1 : 0,
                completedViews: $isCompleted ? 1 : 0,
                freeCompletedViews: ($isCompleted && $isFree) ? 1 : 0,
                contactedActions: $isContacted ? 1 : 0,
                freeContactedActions: ($isContacted && $isFree) ? 1 : 0
            );

            return $view;
        });
    }

    protected function bumpDailyStat(
        VideoAds $videoAd,
        int $impressions = 0,
        int $freeImpressions = 0,
        int $views = 0,
        int $freeViews = 0,
        int $completedViews = 0,
        int $freeCompletedViews = 0,
        int $contactedActions = 0,
        int $freeContactedActions = 0
    ): void {
        $today = Carbon::today()->toDateString();

        VideoAdDailyStat::firstOrCreate(
            [
                'video_ads_id' => $videoAd->id,
                'stat_date'    => $today,
            ],
            [
                'ads_company_id'             => $videoAd->ads_company_id,
                'impressions_count'          => 0,
                'free_impressions_count'     => 0,
                'views_count'                => 0,
                'free_views_count'           => 0,
                'completed_views_count'      => 0,
                'free_completed_views_count' => 0,
                'contacted_count'            => 0,
                'free_contacted_count'       => 0,
            ]
        );

        VideoAdDailyStat::query()
            ->where('video_ads_id', $videoAd->id)
            ->where('stat_date', $today)
            ->incrementEach([
                'impressions_count'          => $impressions,
                'free_impressions_count'     => $freeImpressions,
                'views_count'                => $views,
                'free_views_count'           => $freeViews,
                'completed_views_count'      => $completedViews,
                'free_completed_views_count' => $freeCompletedViews,
                'contacted_count'            => $contactedActions,
                'free_contacted_count'       => $freeContactedActions,
            ]);
    }

    /**
     * Ventile un évènement sur la profession et les sujets de l'utilisateur.
     *
     * Toutes les cibles sont enregistrées, y compris celles qui n'ont reçu
     * aucun point : une audience inattendue est une indication utile pour
     * la répartition du mois suivant.
     *
     * @param array<string,int> $columns Compteurs à incrémenter
     */
    private function recordTargetedStats(VideoAds $videoAd, ?int $userId, array $columns): void
    {
        if (!$userId || empty($columns)) {
            return;
        }

        $user = User::find($userId);

        if (!$user) {
            return;
        }

        $date = now()->toDateString();

        if ($user->profession_id) {
            $this->incrementStat('video_ad_profession_stats', [
                'stat_date'     => $date,
                'video_ads_id'  => $videoAd->id,
                'profession_id' => $user->profession_id,
            ], $videoAd->ads_company_id, $columns);
        }

        foreach ($this->userTopicIds($user) as $topicId) {
            $this->incrementStat('video_ad_topic_stats', [
                'stat_date'    => $date,
                'video_ads_id' => $videoAd->id,
                'topic_id'     => $topicId,
            ], $videoAd->ads_company_id, $columns);
        }
    }

    /**
     * Incrémente plusieurs compteurs en une écriture. La clé unique de la
     * table arbitre les appels concurrents.
     */
    private function incrementStat(string $table, array $keys, ?int $companyId, array $columns): void
    {
        $row = $keys + $columns + [
            'ads_company_id' => $companyId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ];

        $increments = [];

        foreach (array_keys($columns) as $column) {
            $increments[] = "`{$column}` = `{$column}` + VALUES(`{$column}`)";
        }

        $increments[] = '`updated_at` = VALUES(`updated_at`)';

        $fields       = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($row)));
        $placeholders = implode(', ', array_fill(0, count($row), '?'));

        DB::insert(
            "INSERT INTO `{$table}` ({$fields}) VALUES ({$placeholders})
             ON DUPLICATE KEY UPDATE " . implode(', ', $increments),
            array_values($row)
        );
    }

        /* ===================== GRATUITÉ ===================== */

    /**
     * Détermine si une consultation échappe à la facturation.
     *
     * Deux cas l'exonèrent : l'auteur du contenu, qui ne saurait générer
     * de recette sur sa propre production, et le participant d'une édition
     * dont l'annonceur est partenaire — dans la limite de durée convenue.
     *
     * Sans contenu d'origine, la consultation reste facturable : c'est le
     * comportement des clients qui ne transmettent pas encore ce contexte.
     */
    private function isFreeConsultation(VideoAds $videoAd, ?int $userId, ?int $contentId): bool
    {
        if (!$userId || !$contentId) {
            return false;
        }

        $content = DB::table('content')
            ->select('id_content', 'id_past_event', 'video_id')
            ->where('id_content', $contentId)
            ->first();

        if (!$content) {
            return false;
        }

        if ($this->isAuthorOf($content->id_content, $userId)) {
            return true;
        }

        return $this->isSponsoredParticipant($videoAd, $content, $userId);
    }

    /**
     * L'utilisateur figure-t-il parmi les auteurs du contenu ?
     */
    private function isAuthorOf(int $contentId, int $userId): bool
    {
        return DB::table('expert_video as ev')
            ->join('experts as e', 'e.id', '=', 'ev.expert_id')
            ->where('ev.content_id', $contentId)
            ->where('e.user_id', $userId)
            ->exists();
    }

    /**
     * L'utilisateur a-t-il participé à l'édition dont l'annonceur est
     * partenaire, et le contenu est-il encore dans sa période d'avantage ?
     */
    private function isSponsoredParticipant(VideoAds $videoAd, $content, int $userId): bool
    {
        if (!$content->id_past_event || !$videoAd->ads_company_id) {
            return false;
        }

        $sponsored = DB::table('past_event_sponsors')
            ->where('past_event_id', $content->id_past_event)
            ->where('ads_company_id', $videoAd->ads_company_id)
            ->exists();

        if (!$sponsored) {
            return false;
        }

        $participant = DB::table('past_event_users')
            ->where('past_event_id', $content->id_past_event)
            ->where('user_id', $userId)
            ->exists();

        if (!$participant) {
            return false;
        }

        return $this->isWithinAdvantagePeriod($content->id_past_event, $content->video_id);
    }

    /**
     * L'avantage court à partir de la publication du contenu, pour la
     * durée fixée lors de la préparation de l'édition.
     */
    private function isWithinAdvantagePeriod(int $pastEventId, ?int $videoId): bool
    {
        if (!$videoId) {
            return false;
        }

        $publishedAt = DB::table('videos')->where('id', $videoId)->value('published_at');

        if (!$publishedAt) {
            return false;
        }

        $months = DB::table('past_event_settings')
            ->where('past_event_id', $pastEventId)
            ->value('ads_months') ?? 1;

        return Carbon::parse($publishedAt)->addMonths((int) $months)->isFuture();
    }

    /**
     * Annonceurs parrainant l'édition dont relève le contenu, et
     * coefficient dont profitent leurs publicités.
     *
     * L'avantage s'éteint passé le délai convenu, compté depuis la
     * publication du contenu.
     *
     * @return array{0: int[], 1: float}
     */
    private function sponsorBoostFor(?int $contentId): array
    {
        if (!$contentId) {
            return [[], 1.0];
        }

        $content = DB::table('content')
            ->select('id_past_event', 'video_id')
            ->where('id_content', $contentId)
            ->first();

        if (!$content || !$content->id_past_event) {
            return [[], 1.0];
        }

        if (!$this->isWithinAdvantagePeriod($content->id_past_event, $content->video_id)) {
            return [[], 1.0];
        }

        $sponsorIds = DB::table('past_event_sponsors')
            ->where('past_event_id', $content->id_past_event)
            ->pluck('ads_company_id')
            ->map(fn($id) => (int) $id)
            ->all();

        if (empty($sponsorIds)) {
            return [[], 1.0];
        }

        $multiplier = DB::table('past_event_settings')
            ->where('past_event_id', $content->id_past_event)
            ->value('ads_multiplier') ?? 2;

        return [$sponsorIds, (float) $multiplier];
    }
}
