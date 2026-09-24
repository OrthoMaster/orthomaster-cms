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
     */
    public function getFeed(?User $user = null, int $limit = self::FEED_SIZE)
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

        $scored = $ads->map(function (VideoAds $ad) use ($targets, $budgets, $professionId, $topicIds) {
            $ad->setAttribute('selection_score', $this->scoreFor(
                $targets[$ad->id] ?? [],
                $budgets[$ad->id] ?? 0,
                $professionId,
                $topicIds
            ));

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

    public function recordImpression(VideoAds $videoAd, ?int $userId = null): VideoAdImpression
    {
        return DB::transaction(function () use ($videoAd, $userId) {
            $impression = VideoAdImpression::create([
                'video_ads_id' => $videoAd->id,
                'user_id'      => $userId,
            ]);

            $videoAd->incrementImpressions();

            $this->recordTargetedStats($videoAd, $userId, ['impressions_count' => 1]);
            $this->bumpDailyStat($videoAd, impressions: 1);

            return $impression;
        });
    }

    public function recordView(
        VideoAds $videoAd,
        bool $isCompleted = false,
        ?int $userId = null,
        ?bool $isContacted = false,
    ): VideoAdView {
        return DB::transaction(function () use (
            $videoAd,
            $isCompleted,
            $userId,
            $isContacted
        ) {
            $view = VideoAdView::create([
                'video_ads_id' => $videoAd->id,
                'user_id'      => $userId,
                'is_completed' => $isCompleted,
                'is_contacted' => $isContacted
            ]);

            $videoAd->incrementViews();

            $this->recordTargetedStats($videoAd, $userId, [
                'views_count'           => 1,
                'completed_views_count' => $isCompleted ? 1 : 0,
                'contacted_count'       => $isContacted ? 1 : 0,
            ]);

            $this->bumpDailyStat(
                $videoAd,
                views: 1,
                completedViews: $isCompleted ? 1 : 0,
                contactedActions: $isContacted ? 1 : 0
            );

            return $view;
        });
    }

    protected function bumpDailyStat(
        VideoAds $videoAd,
        int $impressions = 0,
        int $views = 0,
        int $completedViews = 0,
        int $contactedActions = 0
    ): void {
        $today = Carbon::today()->toDateString();

        VideoAdDailyStat::firstOrCreate(
            [
                'video_ads_id' => $videoAd->id,
                'stat_date'    => $today,
            ],
            [
                'ads_company_id'        => $videoAd->ads_company_id,
                'impressions_count'     => 0,
                'views_count'           => 0,
                'completed_views_count' => 0,
                'contacted_count'       => 0,
            ]
        );

        VideoAdDailyStat::query()
            ->where('video_ads_id', $videoAd->id)
            ->where('stat_date', $today)
            ->incrementEach([
                'impressions_count'     => $impressions,
                'views_count'           => $views,
                'completed_views_count' => $completedViews,
                'contacted_count'       => $contactedActions,
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
}
