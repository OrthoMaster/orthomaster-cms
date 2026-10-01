<?php

namespace App\Http\Controllers;

use App\Models\AdsCompany;
use App\Models\VideoAdBudget;
use App\Models\VideoAdDailyStat;
use App\Models\VideoAds;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\VideoAdTarget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class DashboardVideoAdsController extends Controller
{

    /** Axes de ciblage, dans l'ordre d'affichage */
    private const AXES = [
        VideoAdTarget::TYPE_PROFESSION => [
            'label' => 'Professions',
            'table' => 'video_ad_profession_stats',
            'key'   => 'profession_id',
            'ref'   => 'professions',
        ],
        VideoAdTarget::TYPE_TOPIC => [
            'label' => "Centres d'intérêt",
            'table' => 'video_ad_topic_stats',
            'key'   => 'topic_id',
            'ref'   => 'topics',
        ],
    ];

    public function index(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $companyId   = $request->integer('ads_company_id') ?: null;
        $videoAdId   = $request->integer('video_ads_id') ?: null;
        $granularity = $this->resolveGranularity($request);

        $companies = AdsCompany::orderBy('name')->get(['id', 'name']);

        $videoAds = VideoAds::query()
            ->when($companyId, fn($q) => $q->where('ads_company_id', $companyId))
            ->orderBy('title')
            ->get(['id', 'title', 'ads_company_id']);

        $chart = $this->buildChartData($from, $to, $companyId, $videoAdId, $granularity);

        return view('dashboard.ads.video-ads', [
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
            'targeting'    => $this->buildTargeting($from, $to, $companyId, $videoAdId),
            'chartDataUrl' => route('dashboard.video-ads.data'),
            'isShared'     => false,
            'signature'    => null,
            // Le lien partagé doit porter une signature : seul le serveur peut la produire
            'shareBaseUrl' => $companyId
                ? URL::signedRoute('dashboard.video-ads.shared', ['company' => $companyId])
                : null,
        ]);
    }

    public function shared(Request $request, int $company)
    {
        abort_unless(
            $request->hasValidSignatureWhileIgnoring(['from', 'to', 'granularity', 'video_ads_id']),
            403
        );

        $adsCompany = AdsCompany::findOrFail($company);

        [$from, $to] = $this->resolveDateRange($request);

        $videoAdId   = $request->integer('video_ads_id') ?: null;
        $granularity = $this->resolveGranularity($request);

        $videoAds = VideoAds::query()
            ->where('ads_company_id', $adsCompany->id)
            ->orderBy('title')
            ->get(['id', 'title', 'ads_company_id']);

        $chart = $this->buildChartData($from, $to, $adsCompany->id, $videoAdId, $granularity);

        return view('dashboard.ads.video-ads', [
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
            'targeting'    => $this->buildTargeting($from, $to, $adsCompany->id, $videoAdId),
            'chartDataUrl' => route('dashboard.video-ads.shared.data', ['company' => $adsCompany->id]),
            'isShared'     => true,
            'signature'    => $request->query('signature'),
            'shareBaseUrl' => null,
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
        // La signature couvre l'entreprise ; les filtres restent libres
        abort_unless(
            $request->hasValidSignatureWhileIgnoring(['from', 'to', 'granularity', 'video_ads_id']),
            403
        );

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
            ->when($companyId, fn($q) => $q->forCompany($companyId))
            ->when($videoAdId, fn($q) => $q->forVideoAd($videoAdId))
            ->selectRaw('stat_date')
            ->selectRaw('SUM(impressions_count) as impressions')
            ->selectRaw('SUM(views_count) as views')
            ->selectRaw('SUM(completed_views_count) as completed_views')
            ->selectRaw('SUM(contacted_count) as contacted')
            ->groupBy('stat_date')
            ->orderBy('stat_date')
            ->get()
            ->keyBy(fn($r) => Carbon::parse($r->stat_date)->toDateString());

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

    /* ===================== CIBLAGE ===================== */

    /**
     * Répartition des points et retombées réelles, par publicité puis par mois.
     *
     * Un mois clos lit sa photographie ; le mois en cours, encore modifiable,
     * lit la répartition en vigueur à sa dernière journée. Les mois sans
     * répartition sont écartés : sans enveloppe, il n'y a rien à comparer.
     */
    private function buildTargeting(string $from, string $to, ?int $companyId, ?int $videoAdId): array
    {
        $ads = VideoAds::query()
            ->when($companyId, fn($q) => $q->where('ads_company_id', $companyId))
            ->when($videoAdId, fn($q) => $q->where('id', $videoAdId))
            ->orderBy('title')
            ->get(['id', 'title']);

        if ($ads->isEmpty()) {
            return [];
        }

        $months = $this->monthsBetween($from, $to);
        $result = [];

        foreach ($ads as $ad) {
            $periods = [];

            foreach ($months as $month) {
                $points = $this->pointsFor($ad->id, $month);

                if ($points['points_total'] <= 0) {
                    continue;
                }

                $periods[] = [
                    'period'       => $month,
                    'label'        => Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y'),
                    'points_total' => $points['points_total'],
                    'billing'      => $this->billingFor($ad->id, $month, $from, $to),
                    'axes'         => $this->axesFor($ad->id, $points['points'], $month, $from, $to),
                ];
            }

            if (empty($periods)) {
                continue;
            }

            $result[] = [
                'id'      => $ad->id,
                'title'   => $ad->title,
                'periods' => $periods,
            ];
        }

        return $result;
    }

    /**
     * Mois couverts par la période, du plus récent au plus ancien.
     */
    private function monthsBetween(string $from, string $to): array
    {
        $cursor = Carbon::parse($from)->startOfMonth();
        $end    = Carbon::parse($to)->startOfMonth();

        $months = [];

        while ($cursor->lte($end)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return array_reverse($months);
    }

    /**
     * Les deux axes d'une publicité pour un mois : points attribués d'un côté,
     * retombées de l'autre.
     *
     * Le total ne porte que sur les cibles dotées de points : c'est à elles
     * que se compare la répartition prévue. Les autres sont listées à part,
     * comme indication pour le mois suivant.
     */
    private function axesFor(int $adId, array $points, string $period, string $from, string $to): array
    {
        $axes = [];

        foreach (self::AXES as $type => $config) {
            $stats = $this->statsFor($adId, $config, $period, $from, $to);

            $ids = array_unique(array_merge(
                array_keys($points[$type] ?? []),
                array_keys($stats)
            ));

            if (empty($ids)) {
                $axes[$type] = [
                    'label'    => $config['label'],
                    'targeted' => [],
                    'others'   => [],
                    'totals'   => null,
                ];
                continue;
            }

            $names = DB::table($config['ref'])->whereIn('id', $ids)->pluck('name', 'id');

            $targetedIds = array_keys(array_filter($points[$type] ?? [], fn($p) => $p > 0));

            $totalPoints = array_sum($points[$type] ?? []);

            // Seules les cibles dotées entrent dans le total : la part réelle
            // se lit alors en regard de la part prévue
            $totalImpressions = 0;

            foreach ($targetedIds as $id) {
                $totalImpressions += $stats[$id]['impressions'] ?? 0;
            }

            $targeted = [];
            $others   = [];
            $totals   = [
                'points'      => $totalPoints,
                'impressions' => 0,
                'views'       => 0,
                'completed'   => 0,
                'contacted'   => 0,
            ];

            foreach ($ids as $id) {
                $p    = (int) ($points[$type][$id] ?? 0);
                $stat = $stats[$id] ?? [
                    'impressions' => 0,
                    'views' => 0,
                    'completed' => 0,
                    'contacted' => 0,
                ];

                $row = [
                    'name'        => $names[$id] ?? '—',
                    'points'      => $p,
                    'expected'    => $totalPoints > 0 ? $p / $totalPoints * 100 : null,
                    'impressions' => $stat['impressions'],
                    'actual'      => null,
                    'views'       => $stat['views'],
                    'ctr'         => $stat['impressions'] > 0
                        ? $stat['views'] / $stat['impressions'] * 100
                        : null,
                    'completed'   => $stat['completed'],
                    'contacted'   => $stat['contacted'],
                ];

                if ($p > 0) {
                    $row['actual'] = $totalImpressions > 0
                        ? $stat['impressions'] / $totalImpressions * 100
                        : null;

                    $totals['impressions'] += $stat['impressions'];
                    $totals['views']       += $stat['views'];
                    $totals['completed']   += $stat['completed'];
                    $totals['contacted']   += $stat['contacted'];

                    $targeted[] = $row;
                } elseif ($stat['impressions'] > 0) {
                    $others[] = $row;
                }
            }

            usort($targeted, fn($a, $b) => [$b['points'], $b['impressions']] <=> [$a['points'], $a['impressions']]);
            usort($others, fn($a, $b) => $b['impressions'] <=> $a['impressions']);

            $axes[$type] = [
                'label'    => $config['label'],
                'targeted' => $targeted,
                'others'   => $others,
                'totals'   => $totals,
            ];
        }

        return $axes;
    }

    /**
     * Points attribués pour un mois donné : la photographie si elle existe,
     * sinon la répartition en vigueur au dernier jour du mois.
     *
     * @return array{points_total:int, points:array}
     */
    private function pointsFor(int $adId, string $period): array
    {
        $snapshot = DB::table('video_ad_target_snapshots')
            ->where('period', $period)
            ->where('video_ads_id', $adId)
            ->get(['target_type', 'target_id', 'points', 'points_total']);

        if ($snapshot->isNotEmpty()) {
            return [
                'points_total' => (int) $snapshot->first()->points_total,
                'points'       => $this->indexPoints($snapshot),
            ];
        }

        $lastDay = Carbon::createFromFormat('Y-m', $period)->endOfMonth()->toDateString();
        $dates   = VideoAdTarget::activeDates([$adId], $lastDay);

        if (empty($dates[$adId])) {
            return ['points_total' => 0, 'points' => []];
        }

        $effectiveFrom = $dates[$adId];

        $pointsTotal = DB::table('video_ad_budgets')
            ->where('video_ads_id', $adId)
            ->where('effective_from', $effectiveFrom)
            ->value('points_total');

        $live = DB::table('video_ad_targets')
            ->where('video_ads_id', $adId)
            ->where('effective_from', $effectiveFrom)
            ->get(['target_type', 'target_id', 'points']);

        return [
            'points_total' => (int) $pointsTotal,
            'points'       => $this->indexPoints($live),
        ];
    }

    private function indexPoints($rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $map[$row->target_type][$row->target_id] = (int) $row->points;
        }

        return $map;
    }

    /**
     * Retombées d'un mois, bornées par la période demandée : un mois
     * partiellement couvert ne compte que ses jours retenus.
     */
    private function statsFor(int $adId, array $config, string $period, string $from, string $to): array
    {
        [$start, $end] = $this->periodWindow($period, $from, $to);

        $rows = DB::table($config['table'])
            ->select($config['key'] . ' as target_id')
            ->selectRaw('SUM(impressions_count) as impressions')
            ->selectRaw('SUM(views_count) as views')
            ->selectRaw('SUM(completed_views_count) as completed')
            ->selectRaw('SUM(contacted_count) as contacted')
            ->where('video_ads_id', $adId)
            ->whereBetween('stat_date', [$start, $end])
            ->groupBy($config['key'])
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[$row->target_id] = [
                'impressions' => (int) $row->impressions,
                'views'       => (int) $row->views,
                'completed'   => (int) $row->completed,
                'contacted'   => (int) $row->contacted,
            ];
        }

        return $map;
    }

    /**
     * Bornes d'un mois, ramenées à la période demandée.
     */
    private function periodWindow(string $period, string $from, string $to): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth()->toDateString();
        $end   = Carbon::createFromFormat('Y-m', $period)->endOfMonth()->toDateString();

        return [max($start, $from), min($end, $to)];
    }

    /**
     * Volumes réels du mois, pris sur le cumul quotidien.
     *
     * Une consultation y compte une fois, quels que soient le nombre de
     * centres d'intérêt de son auteur : c'est la seule base valable pour
     * ce qui sera facturé.
     */
    private function billingFor(int $adId, string $period, string $from, string $to): array
    {
        [$start, $end] = $this->periodWindow($period, $from, $to);

        $row = DB::table('video_ad_daily_stats')
            ->selectRaw('COALESCE(SUM(views_count), 0) as views')
            ->selectRaw('COALESCE(SUM(free_views_count), 0) as free_views')
            ->selectRaw('COALESCE(SUM(impressions_count), 0) as impressions')
            ->where('video_ads_id', $adId)
            ->whereBetween('stat_date', [$start, $end])
            ->first();

        $views = (int) ($row->views ?? 0);
        $free  = (int) ($row->free_views ?? 0);

        return [
            'impressions' => (int) ($row->impressions ?? 0),
            'views'       => $views,
            'free_views'  => $free,
            'credits'     => $views - $free,
        ];
    }

        /* ===================== RÉPARTITION DES POINTS ===================== */

    /**
     * Écran d'attribution des points.
     * GET /dashboard/video-ads/{videoAd}/targets
     */
    public function targets(int $videoAd)
    {
        $ad = VideoAds::with('adsCompany')->findOrFail($videoAd);

        // Une répartition à venir est un brouillon en cours : on la reprend.
        // Sinon le formulaire part à vide, la répartition en vigueur restant
        // intacte tant qu'une nouvelle n'a pas pris le relais.
        $draft = DB::table('video_ad_budgets')
            ->where('video_ads_id', $ad->id)
            ->where('effective_from', '>', now()->toDateString())
            ->orderByDesc('effective_from')
            ->first();

        $points = $draft
            ? $this->indexPoints(
                DB::table('video_ad_targets')
                    ->where('video_ads_id', $ad->id)
                    ->where('effective_from', $draft->effective_from)
                    ->get(['target_type', 'target_id', 'points'])
            )
            : [];

        return view('dashboard.ads.video-ads-targets', [
            'ad'            => $ad,
            'professions'   => DB::table('professions')->whereNotNull('parent_id')->orderBy('name')->get(['id', 'name']),
            'topics'        => $this->targetableTopics(),
            'points'        => $points,
            'pointsTotal'   => $draft->points_total ?? VideoAdBudget::DEFAULT_POINTS,
            'effectiveFrom' => $draft->effective_from ?? now()->toDateString(),
            'history'       => $this->targetHistory($ad->id),
        ]);
    }

    /**
     * Enregistre une répartition.
     * POST /dashboard/video-ads/{videoAd}/targets
     */
    public function storeTargets(Request $request, int $videoAd)
    {
        $ad = VideoAds::findOrFail($videoAd);

        $data = $request->validate([
            'points_total'   => ['required', 'integer', 'min:0', 'max:10000'],
            'effective_from' => ['required', 'date'],
            'profession'     => ['nullable', 'array'],
            'profession.*'   => ['nullable', 'integer', 'min:0'],
            'topic'          => ['nullable', 'array'],
            'topic.*'        => ['nullable', 'integer', 'min:0'],
        ]);

        $professions = array_filter($data['profession'] ?? [], fn($p) => (int) $p > 0);
        $topics      = array_filter($data['topic'] ?? [], fn($p) => (int) $p > 0);

        // Chaque axe dispose de l'enveloppe entière
        $max = $data['points_total'];

        if (array_sum($professions) > $max || array_sum($topics) > $max) {
            return back()
                ->withInput()
                ->with('message', 'Chaque axe est limité à ' . $max . ' points.')
                ->with('alert-type', 'error');
        }

        $effectiveFrom = Carbon::parse($data['effective_from'])->toDateString();

        DB::transaction(function () use ($ad, $data, $effectiveFrom, $professions, $topics) {
            // Une date déjà utilisée est remplacée : l'avertissement a été
            // donné au moment de la saisie
            DB::table('video_ad_budgets')
                ->where('video_ads_id', $ad->id)
                ->where('effective_from', $effectiveFrom)
                ->delete();

            DB::table('video_ad_targets')
                ->where('video_ads_id', $ad->id)
                ->where('effective_from', $effectiveFrom)
                ->delete();

            DB::table('video_ad_budgets')->insert([
                'video_ads_id'   => $ad->id,
                'effective_from' => $effectiveFrom,
                'points_total'   => $data['points_total'],
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $rows = [];

            foreach ($professions as $id => $points) {
                $rows[] = [
                    'video_ads_id'   => $ad->id,
                    'effective_from' => $effectiveFrom,
                    'target_type'    => VideoAdTarget::TYPE_PROFESSION,
                    'target_id'      => (int) $id,
                    'points'         => (int) $points,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }

            foreach ($topics as $id => $points) {
                $rows[] = [
                    'video_ads_id'   => $ad->id,
                    'effective_from' => $effectiveFrom,
                    'target_type'    => VideoAdTarget::TYPE_TOPIC,
                    'target_id'      => (int) $id,
                    'points'         => (int) $points,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }

            if (!empty($rows)) {
                DB::table('video_ad_targets')->insert($rows);
            }
        });

        return redirect()
            ->route('dashboard.video-ads.targets', $ad->id)
            ->with('message', 'Répartition enregistrée.')
            ->with('alert-type', 'success');
    }

    /**
     * Supprime une répartition qui n'a pas encore pris effet.
     * DELETE /dashboard/video-ads/{videoAd}/targets/{effectiveFrom}
     */
    public function destroyTargets(int $videoAd, string $effectiveFrom)
    {
        $ad   = VideoAds::findOrFail($videoAd);
        $date = Carbon::parse($effectiveFrom)->toDateString();

        // Une répartition déjà appliquée a produit des statistiques :
        // la retirer fausserait les rapports des mois concernés
        if ($date <= now()->toDateString()) {
            return back()
                ->with('message', 'Une répartition déjà en vigueur ne peut pas être supprimée.')
                ->with('alert-type', 'error');
        }

        DB::transaction(function () use ($ad, $date) {
            DB::table('video_ad_targets')
                ->where('video_ads_id', $ad->id)
                ->where('effective_from', $date)
                ->delete();

            DB::table('video_ad_budgets')
                ->where('video_ads_id', $ad->id)
                ->where('effective_from', $date)
                ->delete();
        });

        return redirect()
            ->route('dashboard.video-ads.targets', $ad->id)
            ->with('message', 'Répartition supprimée.')
            ->with('alert-type', 'success');
    }

    /**
     * Sujets pouvant recevoir des points : les sous-sujets, et les sujets
     * qui n'en regroupent aucun. Un sujet parent ne sert qu'à regrouper.
     */
    private function targetableTopics()
    {
        return DB::table('topics as t')
            ->leftJoin('topics as p', 'p.id', '=', 't.parent_id')
            ->select('t.id')
            ->selectRaw("CASE WHEN p.id IS NULL THEN t.name ELSE CONCAT(p.name, ' · ', t.name) END as name")
            ->where('t.active_flg', 1)
            ->whereNotExists(function ($q) {
                $q->from('topics as c')->whereColumn('c.parent_id', 't.id')->selectRaw('1');
            })
            ->orderByRaw("CASE WHEN p.id IS NULL THEN t.name ELSE CONCAT(p.name, ' · ', t.name) END")
            ->get();
    }

    /**
     * Répartitions successives, de la plus récente à la plus ancienne.
     */
    private function targetHistory(int $adId): array
    {
        $today = now()->toDateString();

        $budgets = DB::table('video_ad_budgets')
            ->where('video_ads_id', $adId)
            ->orderByDesc('effective_from')
            ->get(['effective_from', 'points_total']);

        if ($budgets->isEmpty()) {
            return [];
        }

        $counts = DB::table('video_ad_targets')
            ->select('effective_from')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(points) as points')
            ->where('video_ads_id', $adId)
            ->where('points', '>', 0)
            ->groupBy('effective_from')
            ->get()
            ->keyBy(fn($r) => Carbon::parse($r->effective_from)->toDateString());

        // La plus récente des dates déjà atteintes est celle qui s'applique
        $current = $budgets
            ->first(fn($b) => Carbon::parse($b->effective_from)->toDateString() <= $today);

        $currentDate = $current
            ? Carbon::parse($current->effective_from)->toDateString()
            : null;

        return $budgets->map(function ($budget) use ($counts, $today, $currentDate) {
            $date = Carbon::parse($budget->effective_from)->toDateString();
            $row  = $counts->get($date);

            return [
                'effective_from' => $date,
                'label'          => Carbon::parse($date)->format('d/m/Y'),
                'points_total'   => (int) $budget->points_total,
                'points_used'    => (int) ($row->points ?? 0),
                'targets'        => (int) ($row->total ?? 0),
                'status'         => $date > $today
                    ? 'future'
                    : ($date === $currentDate ? 'current' : 'past'),
            ];
        })->all();
    }
}
