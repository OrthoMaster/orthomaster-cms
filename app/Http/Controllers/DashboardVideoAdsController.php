<?php

namespace App\Http\Controllers;

use App\Models\AdsCompany;
use App\Models\VideoAdDailyStat;
use App\Models\VideoAds;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardVideoAdsController extends Controller
{
    /**
     * Trang dashboard cho ADMIN (đã đăng nhập).
     * Chọn được: công ty, video, khoảng ngày, granularité.
     *
     * Route gợi ý: GET /dashboard/video-ads  (middleware: admin/auth)
     */
    public function index(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $companyId   = $request->integer('ads_company_id') ?: null;
        $videoAdId   = $request->integer('video_ads_id') ?: null;
        $granularity = $this->resolveGranularity($request);

        $companies = AdsCompany::orderBy('name')->get(['id', 'name']);

        // Danh sách video để chọn (lọc theo công ty nếu đã chọn)
        $videoAds = VideoAds::query()
            ->when($companyId, fn ($q) => $q->where('ads_company_id', $companyId))
            ->orderBy('title')
            ->get(['id', 'title', 'ads_company_id']);

        $chart = $this->buildChartData($from, $to, $companyId, $videoAdId, $granularity);

        return view('dashboard.video-ads', [
            'isAdmin'      => true,
            'companies'    => $companies,
            'videoAds'     => $videoAds,
            'companyId'    => $companyId,
            'videoAdId'    => $videoAdId,
            'granularity'  => $granularity,
            'from'         => $from,
            'to'           => $to,
            'chart'        => $chart,
            'summary'      => $this->buildSummary($chart),
            // dùng cho JS fetch cập nhật động
            'chartDataUrl' => route('dashboard.video-ads.data'),
            'isShared'     => false,
            'signature'    => null,
        ]);
    }

    /**
     * Trang dashboard cho ĐỐI TÁC (không cần đăng nhập).
     * Công ty bị cố định theo link signed. Chỉ chọn được video + ngày + granularité.
     *
     * Route gợi ý:
     *   GET /dashboard/video-ads/shared/{company}  (middleware: signed)
     *
     * Tạo link bằng:
     *   URL::signedRoute('dashboard.video-ads.shared', ['company' => $company->id]);
     */
    public function shared(Request $request, int $company)
    {
        // middleware 'signed' đã đảm bảo chữ ký hợp lệ.
        $adsCompany = AdsCompany::findOrFail($company);

        [$from, $to] = $this->resolveDateRange($request);

        $videoAdId   = $request->integer('video_ads_id') ?: null;
        $granularity = $this->resolveGranularity($request);

        $videoAds = VideoAds::query()
            ->where('ads_company_id', $adsCompany->id)
            ->orderBy('title')
            ->get(['id', 'title', 'ads_company_id']);

        $chart = $this->buildChartData($from, $to, $adsCompany->id, $videoAdId, $granularity);

        return view('dashboard.video-ads', [
            'isAdmin'      => false,
            'companies'    => collect([$adsCompany]),
            'videoAds'     => $videoAds,
            'companyId'    => $adsCompany->id,
            'videoAdId'    => $videoAdId,
            'granularity'  => $granularity,
            'from'         => $from,
            'to'           => $to,
            'chart'        => $chart,
            'summary'      => $this->buildSummary($chart),
            'chartDataUrl' => route('dashboard.video-ads.shared.data', ['company' => $adsCompany->id]),
            'isShared'     => true,
            // truyền lại các tham số ký để JS fetch giữ được chữ ký hợp lệ
            'signature'    => $request->query('signature'),
        ]);
    }

    /**
     * Endpoint JSON cho ADMIN (fetch động khi đổi filter).
     * GET /dashboard/video-ads/data
     */
    public function chartData(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $companyId   = $request->integer('ads_company_id') ?: null;
        $videoAdId   = $request->integer('video_ads_id') ?: null;
        $granularity = $this->resolveGranularity($request);

        $chart = $this->buildChartData($from, $to, $companyId, $videoAdId, $granularity);

        return response()->json($chart + ['summary' => $this->buildSummary($chart)]);
    }

    /**
     * Endpoint JSON cho ĐỐI TÁC (signed).
     * GET /dashboard/video-ads/shared/{company}/data
     */
    public function sharedChartData(Request $request, int $company)
    {
        $adsCompany = AdsCompany::findOrFail($company);

        [$from, $to] = $this->resolveDateRange($request);

        $videoAdId   = $request->integer('video_ads_id') ?: null;
        $granularity = $this->resolveGranularity($request);

        $chart = $this->buildChartData($from, $to, $adsCompany->id, $videoAdId, $granularity);

        return response()->json($chart + ['summary' => $this->buildSummary($chart)]);
    }

    /**
     * Granularité valide : day (défaut) / week / month.
     */
    private function resolveGranularity(Request $request): string
    {
        return in_array($request->query('granularity'), ['day', 'week', 'month'], true)
            ? $request->query('granularity')
            : 'day';
    }

    /**
     * Xác định khoảng ngày từ request, mặc định 7 ngày gần nhất.
     */
    private function resolveDateRange(Request $request): array
    {
        $to   = $request->date('to')   ?: Carbon::today();
        $from = $request->date('from') ?: (clone $to)->subDays(6);

        // Bảo vệ: from <= to
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    /**
     * 4 chỉ số tổng kết cho hàng KPI (tổng trên toàn bộ dải đang xem).
     */
    private function buildSummary(array $chart): array
    {
        return [
            'impressions'     => array_sum($chart['impressions']),
            'views'           => array_sum($chart['views']),
            'completed_views' => array_sum($chart['completed_views']),
            'contacted'       => array_sum($chart['contacted']),
        ];
    }

    /**
     * Truy vấn video_ad_daily_stats, gộp theo NGÀY, rồi ré-agréger theo
     * granularité (day/week/month). Trả về labels + 4 series.
     * Nếu không chọn video cụ thể -> tổng hợp tất cả video (của công ty nếu có).
     */
    private function buildChartData(
        string $from,
        string $to,
        ?int $companyId,
        ?int $videoAdId,
        string $granularity = 'day'
    ): array {
        $rows = VideoAdDailyStat::query()
            ->betweenDates($from, $to)
            ->when($companyId, fn ($q) => $q->forCompany($companyId))
            ->when($videoAdId, fn ($q) => $q->forVideoAd($videoAdId))
            ->selectRaw('stat_date')
            ->selectRaw('SUM(impressions_count) as impressions')
            ->selectRaw('SUM(views_count) as views')
            ->selectRaw('SUM(completed_views_count) as completed_views')
            ->selectRaw('SUM(contacted_count) as contacted')
            ->groupBy('stat_date')
            ->orderBy('stat_date')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->stat_date)->toDateString());

        // Parcourir chaque jour, additionner dans le bon "bucket" temporel.
        $buckets = []; // clé bucket => ['label'=>..., 'impressions'=>..., ...]

        $cursor = Carbon::parse($from);
        $end    = Carbon::parse($to);

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);

            [$bucketKey, $bucketLabel] = $this->bucketFor($cursor, $granularity);

            if (!isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'label'           => $bucketLabel,
                    'impressions'     => 0,
                    'views'           => 0,
                    'completed_views' => 0,
                    'contacted'       => 0,
                ];
            }

            $buckets[$bucketKey]['impressions']     += (int) ($row->impressions ?? 0);
            $buckets[$bucketKey]['views']           += (int) ($row->views ?? 0);
            $buckets[$bucketKey]['completed_views'] += (int) ($row->completed_views ?? 0);
            $buckets[$bucketKey]['contacted']       += (int) ($row->contacted ?? 0);

            $cursor->addDay();
        }

        // Les buckets sont déjà dans l'ordre chronologique (on parcourt jour par jour).
        $labels         = [];
        $impressions    = [];
        $views          = [];
        $completedViews = [];
        $contacted      = [];

        foreach ($buckets as $b) {
            $labels[]         = $b['label'];
            $impressions[]    = $b['impressions'];
            $views[]          = $b['views'];
            $completedViews[] = $b['completed_views'];
            $contacted[]      = $b['contacted'];
        }

        return [
            'labels'          => $labels,
            'impressions'     => $impressions,
            'views'           => $views,
            'completed_views' => $completedViews,
            'contacted'       => $contacted,
            'granularity'     => $granularity,
            'from'            => $from,
            'to'              => $to,
        ];
    }

    /**
     * Retourne [clé_bucket, label_affiché] pour une date donnée selon la granularité.
     */
    private function bucketFor(Carbon $date, string $granularity): array
    {
        return match ($granularity) {
            'month' => [
                $date->format('Y-m'),
                $date->translatedFormat('M Y'),   // ex: janv. 2026
            ],
            'week' => [
                $date->isoFormat('GGGG-[W]WW'),   // ex: 2026-W03 (année + semaine ISO)
                'S' . $date->isoFormat('WW') . ' ' . $date->isoFormat('GGGG'),
            ],
            default => [
                $date->toDateString(),
                $date->format('d/m/Y'),
            ],
        };
    }
}