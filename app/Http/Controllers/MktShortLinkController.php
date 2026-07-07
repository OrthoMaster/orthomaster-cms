<?php

namespace App\Http\Controllers;

use App\Models\MktClickEvent;
use App\Models\MktShortLink;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MktShortLinkController extends Controller
{
    /* ============================================================
     |  GESTION DES SHORT LINKS  (liste façon Voyager)
     * ============================================================ */

    /**
     * Liste paginée des short links + recherche.
     * Route : GET /dashboard/short-links
     */
    public function index(Request $request)
    {
        $searchTitle  = trim((string) $request->query('search_title', ''));
        $searchSource = trim((string) $request->query('search_source', ''));
        $searchType   = trim((string) $request->query('search_type', ''));

        $links = MktShortLink::query()
            ->when($searchTitle !== '', function ($q) use ($searchTitle) {
                $q->where(function ($sub) use ($searchTitle) {
                    $sub->where('title', 'like', "%{$searchTitle}%")
                        ->orWhere('code', 'like', "%{$searchTitle}%")
                        ->orWhere('original_url', 'like', "%{$searchTitle}%");
                });
            })
            ->when($searchSource !== '', fn ($q) => $q->where('utm_source', $searchSource))
            ->when($searchType !== '', fn ($q) => $q->where('source_type', $searchType))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        // Valeurs distinctes pour les filtres (sources réellement utilisées)
        $sources = MktShortLink::query()
            ->whereNotNull('utm_source')
            ->distinct()
            ->orderBy('utm_source')
            ->pluck('utm_source');

        return view('mkt.short-links', [
            'links'        => $links,
            'sources'      => $sources,
            'searchTitle'  => $searchTitle,
            'searchSource' => $searchSource,
            'searchType'   => $searchType,
        ]);
    }

    /* ============================================================
     |  TABLEAU DE BORD DE TRACKING
     * ============================================================ */

    /**
     * Page dashboard de tracking (clics QR + short links).
     * Route : GET /dashboard/short-links/tracking
     */
    public function tracking(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $shortLinkId = $request->integer('short_link_id') ?: null;
        $source      = trim((string) $request->query('utm_source', '')) ?: null;
        $campaign    = trim((string) $request->query('utm_campaign', '')) ?: null;
        $type        = trim((string) $request->query('source_type', '')) ?: null;
        $granularity = in_array($request->query('granularity'), ['day', 'week', 'month'], true)
            ? $request->query('granularity')
            : 'day';

        // Listes pour les filtres
        $links = MktShortLink::query()
            ->orderByDesc('created_at')
            ->get(['id', 'code', 'title', 'utm_source', 'utm_campaign']);

        $sources = MktShortLink::query()
            ->whereNotNull('utm_source')->distinct()->orderBy('utm_source')->pluck('utm_source');

        $campaigns = MktShortLink::query()
            ->whereNotNull('utm_campaign')->distinct()->orderBy('utm_campaign')->pluck('utm_campaign');

        $filters = compact('shortLinkId', 'source', 'campaign', 'type', 'granularity');

        return view('mkt.tracking', [
            'links'        => $links,
            'sources'      => $sources,
            'campaigns'    => $campaigns,
            'shortLinkId'  => $shortLinkId,
            'source'       => $source,
            'campaign'     => $campaign,
            'type'         => $type,
            'granularity'  => $granularity,
            'from'         => $from,
            'to'           => $to,
            'chart'        => $this->buildTimeSeries($from, $to, $filters),
            'summary'      => $this->buildSummary($from, $to, $filters),
            'breakdowns'   => $this->buildBreakdowns($from, $to, $filters),
            'chartDataUrl' => route('dashboard.short-links.tracking.data'),
        ]);
    }

    /**
     * Endpoint JSON (fetch dynamique quand on change les filtres).
     * Route : GET /dashboard/short-links/tracking/data
     */
    public function trackingData(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $granularity = in_array($request->query('granularity'), ['day', 'week', 'month'], true)
            ? $request->query('granularity')
            : 'day';

        $filters = [
            'shortLinkId' => $request->integer('short_link_id') ?: null,
            'source'      => trim((string) $request->query('utm_source', '')) ?: null,
            'campaign'    => trim((string) $request->query('utm_campaign', '')) ?: null,
            'type'        => trim((string) $request->query('source_type', '')) ?: null,
            'granularity' => $granularity,
        ];

        return response()->json([
            'chart'      => $this->buildTimeSeries($from, $to, $filters),
            'summary'    => $this->buildSummary($from, $to, $filters),
            'breakdowns' => $this->buildBreakdowns($from, $to, $filters),
        ]);
    }

    /* ============================================================
     |  HELPERS DE REQUÊTE
     * ============================================================ */

    /**
     * Applique les filtres communs à une requête sur mkt_click_events.
     */
    private function baseQuery(string $from, string $to, array $f)
    {
        return MktClickEvent::query()
            ->join('mkt_short_links', 'mkt_click_events.short_link_id', '=', 'mkt_short_links.id')
            ->whereBetween('mkt_click_events.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('mkt_click_events.is_bot', false)
            ->when($f['shortLinkId'] ?? null, fn ($q, $v) => $q->where('mkt_click_events.short_link_id', $v))
            ->when($f['source'] ?? null,   fn ($q, $v) => $q->where('mkt_short_links.utm_source', $v))
            ->when($f['campaign'] ?? null, fn ($q, $v) => $q->where('mkt_short_links.utm_campaign', $v))
            ->when($f['type'] ?? null,     fn ($q, $v) => $q->where('mkt_click_events.source_type', $v));
    }

    /**
     * Série temporelle des clics, groupée par jour / semaine / mois.
     */
    private function buildTimeSeries(string $from, string $to, array $f): array
    {
        $granularity = $f['granularity'] ?? 'day';

        // Expression SQL du "bucket" temporel (compatible MySQL)
        $bucketExpr = match ($granularity) {
            'week'  => "DATE_FORMAT(mkt_click_events.created_at, '%x-W%v')", // année-semaine ISO
            'month' => "DATE_FORMAT(mkt_click_events.created_at, '%Y-%m')",
            default => "DATE(mkt_click_events.created_at)",
        };

        $rows = $this->baseQuery($from, $to, $f)
            ->selectRaw("$bucketExpr as bucket")
            ->selectRaw('COUNT(*) as clicks')
            ->selectRaw('COUNT(DISTINCT mkt_click_events.ip_hash) as visitors')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        // Construire une liste continue de buckets pour ne pas avoir de trous
        $labels = [];
        $clicks = [];
        $visitors = [];

        foreach ($this->enumerateBuckets($from, $to, $granularity) as $bucketKey => $label) {
            $row = $rows->get($bucketKey);
            $labels[]   = $label;
            $clicks[]   = (int) ($row->clicks ?? 0);
            $visitors[] = (int) ($row->visitors ?? 0);
        }

        return [
            'labels'   => $labels,
            'clicks'   => $clicks,
            'visitors' => $visitors,
            'from'     => $from,
            'to'       => $to,
        ];
    }

    /**
     * Génère la liste ordonnée des buckets [clé_sql => label_affiché].
     */
    private function enumerateBuckets(string $from, string $to, string $granularity): array
    {
        $cursor = Carbon::parse($from)->startOfDay();
        $end    = Carbon::parse($to)->startOfDay();
        $out    = [];

        if ($granularity === 'month') {
            $cursor = $cursor->startOfMonth();
            while ($cursor->lte($end)) {
                $out[$cursor->format('Y-m')] = $cursor->translatedFormat('M Y');
                $cursor->addMonth();
            }
        } elseif ($granularity === 'week') {
            $cursor = $cursor->startOfWeek();
            while ($cursor->lte($end)) {
                // %x-W%v : année ISO + numéro de semaine ISO
                $key = $cursor->isoFormat('GGGG') . '-W' . $cursor->isoFormat('WW');
                $out[$key] = 'S' . $cursor->isoFormat('WW') . ' ' . $cursor->isoFormat('GGGG');
                $cursor->addWeek();
            }
        } else {
            while ($cursor->lte($end)) {
                $out[$cursor->toDateString()] = $cursor->format('d/m/Y');
                $cursor->addDay();
            }
        }

        return $out;
    }

    /**
     * 4 chiffres clés pour la barre de résumé.
     */
    private function buildSummary(string $from, string $to, array $f): array
    {
        $totalClicks   = (clone $this->baseQuery($from, $to, $f))->count();
        $uniqueVisitors = (clone $this->baseQuery($from, $to, $f))
            ->distinct()->count('mkt_click_events.ip_hash');

        // Nombre de liens actifs (ayant reçu au moins 1 clic sur la période)
        $activeLinks = (clone $this->baseQuery($from, $to, $f))
            ->distinct()->count('mkt_click_events.short_link_id');

        // Part mobile (%)
        $mobileClicks = (clone $this->baseQuery($from, $to, $f))
            ->where('mkt_click_events.device_type', 'mobile')->count();
        $mobileShare = $totalClicks > 0 ? round($mobileClicks / $totalClicks * 100, 1) : 0.0;

        return [
            'total_clicks'    => $totalClicks,
            'unique_visitors' => $uniqueVisitors,
            'active_links'    => $activeLinks,
            'mobile_share'    => $mobileShare,
        ];
    }

    /**
     * Répartitions (camemberts + barres) : device, browser, platform, country.
     */
    private function buildBreakdowns(string $from, string $to, array $f): array
    {
        $groupCount = function (string $column) use ($from, $to, $f) {
            $rows = $this->baseQuery($from, $to, $f)
                ->selectRaw("COALESCE(NULLIF($column, ''), 'Inconnu') as label")
                ->selectRaw('COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->limit(12)
                ->get();

            return [
                'labels' => $rows->pluck('label')->all(),
                'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
            ];
        };

        return [
            'device'   => $groupCount('mkt_click_events.device_type'),
            'browser'  => $groupCount('mkt_click_events.browser'),
            'platform' => $groupCount('mkt_click_events.platform'),
            'country'  => $groupCount('mkt_click_events.country'),
        ];
    }

    /**
     * Plage de dates depuis la requête, défaut = 30 derniers jours.
     */
    private function resolveDateRange(Request $request): array
    {
        $to   = $request->date('to')   ?: Carbon::today();
        $from = $request->date('from') ?: (clone $to)->subDays(29);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    public function edit(\App\Models\MktShortLink $shortLink)
    {
        return view('mkt.short-link-edit', ['link' => $shortLink]);
    }
 
    public function update(\Illuminate\Http\Request $request, \App\Models\MktShortLink $shortLink)
    {
        $data = $request->validate([
            'title'            => ['nullable', 'string', 'max:255'],
            'utm_campaign'     => ['nullable', 'string', 'max:255'],
            'tracking_enabled' => ['nullable', 'boolean'],
        ]);
 
        $shortLink->update([
            'title'            => $data['title'] ?? null,
            'utm_campaign'     => $data['utm_campaign'] ?? null,
            'tracking_enabled' => (bool) ($data['tracking_enabled'] ?? false),
        ]);
 
        return redirect()
            ->route('dashboard.short-links')
            ->with('message', 'Short link mis à jour.');
    }
}