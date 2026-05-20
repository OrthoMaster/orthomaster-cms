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
     * Chọn được: công ty, video, khoảng ngày.
     *
     * Route gợi ý: GET /dashboard/video-ads  (middleware: admin/auth)
     */
    public function index(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);
 
        $companyId = $request->integer('ads_company_id') ?: null;
        $videoAdId = $request->integer('video_ads_id') ?: null;
 
        $companies = AdsCompany::orderBy('name')->get(['id', 'name']);
 
        // Danh sách video để chọn (lọc theo công ty nếu đã chọn)
        $videoAds = VideoAds::query()
            ->when($companyId, fn ($q) => $q->where('ads_company_id', $companyId))
            ->orderBy('title')
            ->get(['id', 'title', 'ads_company_id']);
 
        $chart = $this->buildChartData($from, $to, $companyId, $videoAdId);
 
        return view('dashboard.video-ads', [
            'isAdmin'      => true,
            'companies'    => $companies,
            'videoAds'     => $videoAds,
            'companyId'    => $companyId,
            'videoAdId'    => $videoAdId,
            'from'         => $from,
            'to'           => $to,
            'chart'        => $chart,
            // dùng cho JS fetch cập nhật động
            'chartDataUrl' => route('dashboard.video-ads.data'),
            'isShared'     => false,
            'signature'    => null,
        ]);
    }
 
    /**
     * Trang dashboard cho ĐỐI TÁC (không cần đăng nhập).
     * Công ty bị cố định theo link signed. Chỉ chọn được video + ngày.
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
 
        $videoAdId = $request->integer('video_ads_id') ?: null;
 
        $videoAds = VideoAds::query()
            ->where('ads_company_id', $adsCompany->id)
            ->orderBy('title')
            ->get(['id', 'title', 'ads_company_id']);
 
        $chart = $this->buildChartData($from, $to, $adsCompany->id, $videoAdId);
 
        return view('dashboard.video-ads', [
            'isAdmin'      => false,
            'companies'    => collect([$adsCompany]),
            'videoAds'     => $videoAds,
            'companyId'    => $adsCompany->id,
            'videoAdId'    => $videoAdId,
            'from'         => $from,
            'to'           => $to,
            'chart'        => $chart,
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
 
        $companyId = $request->integer('ads_company_id') ?: null;
        $videoAdId = $request->integer('video_ads_id') ?: null;
 
        return response()->json(
            $this->buildChartData($from, $to, $companyId, $videoAdId)
        );
    }
 
    /**
     * Endpoint JSON cho ĐỐI TÁC (signed).
     * GET /dashboard/video-ads/shared/{company}/data
     */
    public function sharedChartData(Request $request, int $company)
    {
        $adsCompany = AdsCompany::findOrFail($company);
 
        [$from, $to] = $this->resolveDateRange($request);
 
        $videoAdId = $request->integer('video_ads_id') ?: null;
 
        return response()->json(
            $this->buildChartData($from, $to, $adsCompany->id, $videoAdId)
        );
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
     * Truy vấn video_ad_daily_stats, gộp theo NGÀY, trả về labels + 3 series.
     * Nếu không chọn video cụ thể -> tổng hợp tất cả video (của công ty nếu có).
     */
    private function buildChartData(
        string $from,
        string $to,
        ?int $companyId,
        ?int $videoAdId
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
 
        // Tạo dải ngày liên tục (kể cả ngày không có dữ liệu -> 0)
        $labels = [];
        $impressions = [];
        $views = [];
        $completedViews = [];
        $contacted = [];
 
        $cursor = Carbon::parse($from);
        $end    = Carbon::parse($to);
 
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);
 
            $labels[]         = $cursor->format('d/m/Y');
            $impressions[]    = (int) ($row->impressions ?? 0);
            $views[]          = (int) ($row->views ?? 0);
            $completedViews[] = (int) ($row->completed_views ?? 0);
            $contacted[]      = (int) ($row->contacted ?? 0);
 
            $cursor->addDay();
        }
 
        return [
            'labels'          => $labels,
            'impressions'     => $impressions,
            'views'           => $views,
            'completed_views' => $completedViews,
            'contacted'       => $contacted,
            'from'            => $from,
            'to'              => $to,
        ];
    }
}
