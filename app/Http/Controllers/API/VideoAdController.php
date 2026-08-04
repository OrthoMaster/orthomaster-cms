<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\VideoAds;
use App\Services\VideoAdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VideoAdController extends Controller
{
    public function __construct(
        private readonly VideoAdService $service
    ) {}

    /**
     * Query params:
     *  - profession_id (optional): filter by profession
     *  - limit (optional): default 4
     */
    public function feed(Request $request)
    {
        $validated = $request->validate([
            'profession_id' => ['nullable', 'integer'],
            'limit'         => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $videos = $this->service->getFeed(
            professionId: $validated['profession_id'] ?? null,
            limit: $validated['limit'] ?? VideoAdService::FEED_SIZE
        );

        return response()->json([
            'success' => true,
            'data' => $videos->map(fn(VideoAds $v) => [
                'id'            => $v->id,
                'title'         => $v->title,
                'video_url'     => $v->video_url,
                'thumbnail_url' => $v->thumbnail_url,
                'ads_company'   => [
                    'id'   => $v->ads_company_id,
                    'name' => $v->adsCompany?->name,
                ],
            ]),
        ]);
    }

    public function impression(Request $request, int $id)
    {
        $videoAd = VideoAds::findOrFail($id);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
        ]);

        if (Auth::check() && Auth::user()->role->name == 'user') {
            $this->service->recordImpression(
                $videoAd,
                userId: $validated['user_id'] ?? $request->user()?->id
            );
        }

        return response()->json([
            'video_ads_id'       => $videoAd->id,
            'total_impressions' => $videoAd->fresh()->total_impressions,
        ]);
    }

    public function view(Request $request, int $id)
    {
        $videoAd = VideoAds::findOrFail($id);

        $data = $request->all();

        if (Auth::check() && Auth::user()->role->name == 'user') {
            $this->service->recordView(
                $videoAd,
                isCompleted: $data['is_completed'] ?? false,
                userId: $data['user_id'] ?? $request->user()?->id
            );
        }

        return response()->json([
            'video_ads_id' => $videoAd->id,
            'total_views' => $videoAd->fresh()->total_views,
        ]);
    }

    public function contact(Request $request, int $id)
    {
        $videoAd = VideoAds::findOrFail($id);

        $data = $request->all();

        if (Auth::check() && Auth::user()->role->name == 'user') {
            $this->service->recordView(
                $videoAd,
                isCompleted: true,
                userId: $data['user_id'] ?? $request->user()?->id,
                isContacted: $data['is_contacted'] ?? false,
            );
        }

        return response()->json([
            'video_ads_id' => $videoAd->id,
        ]);
    }
}
