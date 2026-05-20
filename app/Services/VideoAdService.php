<?php

namespace App\Services;

use App\Models\VideoAds;
use App\Models\VideoAdDailyStat;
use App\Models\VideoAdImpression;
use App\Models\VideoAdView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VideoAdService
{
    public const FEED_SIZE = 4;

    public function getFeed(?int $professionId, int $limit = self::FEED_SIZE): Collection
    {
        $videos = collect();

        if ($professionId !== null) {
            $videos = VideoAds::query()
                ->whereHas('professions', function ($q) use ($professionId) {
                    $q->where('professions.id', $professionId);
                })
                ->orderByDesc('priority')
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        }

        if ($videos->count() < $limit) {
            $remaining = $limit - $videos->count();

            $fillers = VideoAds::query()
                ->when($videos->isNotEmpty(), function ($q) use ($videos) {
                    $q->whereNotIn('id', $videos->pluck('id'));
                })
                ->orderByDesc('priority')
                ->inRandomOrder()
                ->limit($remaining)
                ->get();

            $videos = $videos->concat($fillers);
        }

        return $videos->values();
    }

    public function recordImpression(VideoAds $videoAd, ?int $userId = null): VideoAdImpression
    {
        return DB::transaction(function () use ($videoAd, $userId) {
            $impression = VideoAdImpression::create([
                'video_ads_id' => $videoAd->id,
                'user_id'     => $userId,
            ]);

            $videoAd->incrementImpressions();

            $this->bumpDailyStat($videoAd, impressions: 1);

            return $impression;
        });
    }

    public function recordView(
        VideoAds $videoAd,
        bool $isCompleted = false,
        ?int $userId = null
    ): VideoAdView {
        return DB::transaction(function () use (
            $videoAd, $isCompleted, $userId
        ) {
            $view = VideoAdView::create([
                'video_ads_id'     => $videoAd->id,
                'user_id'         => $userId,
                'is_completed'    => $isCompleted,
            ]);

            $videoAd->incrementViews();

            $this->bumpDailyStat(
                $videoAd,
                views: 1,
                completedViews: $isCompleted ? 1 : 0
            );

            return $view;
        });
    }

    protected function bumpDailyStat(
        VideoAds $videoAd,
        int $impressions = 0,
        int $views = 0,
        int $completedViews = 0
    ): void {
        $today = Carbon::today()->toDateString();

        VideoAdDailyStat::firstOrCreate(
            [
                'video_ads_id' => $videoAd->id,
                'stat_date'   => $today,
            ],
            [
                'ads_company_id'        => $videoAd->ads_company_id,
                'impressions_count'     => 0,
                'views_count'           => 0,
                'completed_views_count' => 0,
            ]
        );

        VideoAdDailyStat::query()
            ->where('video_ads_id', $videoAd->id)
            ->where('stat_date', $today)
            ->incrementEach([
                'impressions_count'     => $impressions,
                'views_count'           => $views,
                'completed_views_count' => $completedViews,
            ]);
    }
}