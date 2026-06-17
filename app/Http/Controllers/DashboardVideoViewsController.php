<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardVideoViewsController extends Controller
{
    /**
     * Trang dashboard thống kê views cho video (content.type = 'flash').
     * GET /dashboard/videos
     */
    public function index()
    {
        $experts = DB::table('experts')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('dashboard.video-views', [
            'experts' => $experts,
            'dataUrl' => route('dashboard.videos.data'),
        ]);
    }

    /**
     * JSON: dữ liệu bảng. Mỗi dòng = 1 video, lọc theo expert (rỗng = tất cả).
     * Kèm tên event: content.event_id -> events.id -> events.name.
     *
     * Views:
     *   - Không chọn khoảng thời gian -> dùng content.views (cộng dồn, như cũ).
     *   - Có chọn khoảng thời gian      -> đếm số record trong trk_content_views
     *     theo content_id + created_at trong khoảng đó.
     *
     * GET /dashboard/videos/data?expert_id=...&from=...&to=...
     */
    public function tableData(Request $request)
    {
        $expertId = $request->integer('expert_id') ?: null;
        [$from, $to, $hasRange] = $this->resolveDateRange($request);

        $query = $this->baseVideoQuery($expertId)
            ->leftJoin('events', 'events.id', '=', 'content.event_id');

        if ($hasRange) {
            // Đếm views theo khoảng thời gian từ trk_content_views.
            // LEFT JOIN + điều kiện ngày trong ON để vẫn giữ video có 0 view.
            $rows = $query
                ->leftJoin('trk_content_views as trk', function ($join) use ($from, $to) {
                    $join->on('trk.content_id', '=', 'content.id_content');
                    if ($from) {
                        $join->where('trk.created_at', '>=', $from);
                    }
                    if ($to) {
                        $join->where('trk.created_at', '<=', $to);
                    }
                })
                ->groupBy('content.id_content', 'content.title', 'events.name')
                ->selectRaw('content.id_content as id, content.title, events.name as event_name, COUNT(trk.content_id) as views')
                ->orderByRaw('COUNT(trk.content_id) DESC')
                ->get();
        } else {
            // Như cũ: dùng tổng views cộng dồn trên content.
            $rows = $query
                ->distinct()
                ->orderByDesc('content.views')
                ->get([
                    'content.id_content as id',
                    'content.title',
                    'events.name as event_name',
                    'content.views',
                ]);
        }

        return response()->json([
            'rows' => $rows->map(fn ($r) => [
                'title'      => $r->title,
                'event_name' => $r->event_name,
                'views'      => (int) $r->views,
            ])->values(),
            'total_views' => (int) $rows->sum('views'),
        ]);
    }

    /**
     * Xác định khoảng ngày. Trả [from, to, hasRange].
     * Không có from lẫn to -> hasRange = false (giữ hành vi cũ).
     */
    private function resolveDateRange(Request $request): array
    {
        $fromRaw = $request->query('from');
        $toRaw   = $request->query('to');

        $hasRange = !empty($fromRaw) || !empty($toRaw);

        $from = $fromRaw ? Carbon::parse($fromRaw)->startOfDay() : null;
        $to   = $toRaw ? Carbon::parse($toRaw)->endOfDay() : null;

        // Bảo vệ: nếu cả hai có và from > to thì hoán đổi.
        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [
            $from?->toDateTimeString(),
            $to?->toDateTimeString(),
            $hasRange,
        ];
    }

    /**
     * Query gốc: content.type = 'flash'.
     * Nếu lọc theo expert -> join bảng pivot expert_video.
     */
    private function baseVideoQuery(?int $expertId)
    {
        $query = DB::table('content')
            ->where('content.type', 'flash');

        if ($expertId) {
            $query->join('expert_video', 'expert_video.content_id', '=', 'content.id_content')
                  ->where('expert_video.expert_id', $expertId);
        }

        return $query;
    }
}