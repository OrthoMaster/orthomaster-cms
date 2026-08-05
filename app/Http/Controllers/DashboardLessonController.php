<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardLessonController extends Controller
{
    /** type dans `trk_content_views` */
    private const CONTENT_TYPE = 'lesson';

    /** Nb max de métiers affichés sur le graphique */
    private const MAX_PROFESSIONS = 10;

    /** Libellé pour les users sans profession */
    private const PROFESSION_FALLBACK = 'Non renseigné';

    /**
     * Trang dashboard lessons.
     * GET /dashboard/lessons
     */
    public function index(Request $request)
    {
        $f = $this->resolveFilters($request);

        return view('dashboard.lessons.index', [
            'filters'        => $f,
            'chart'          => $this->buildChartData($f),
            'summary'        => $this->buildSummary($f),
            'lessons'        => $this->buildTableRows($f),
            'trainings'      => $this->trainingOptions(),
            'packages'       => $this->packageOptions(),
            'selectedLesson' => $this->lessonLabel($f['lesson_id']),
        ]);
    }

    /**
     * Endpoint JSON (fetch dynamique si besoin).
     * GET /dashboard/lessons/data
     */
    public function chartData(Request $request)
    {
        $f = $this->resolveFilters($request);

        return response()->json(
            $this->buildChartData($f) + ['summary' => $this->buildSummary($f)]
        );
    }

    /**
     * Autocomplete leçons (min 3 caractères), filtré par formation / paquet.
     * GET /dashboard/lessons/search-lessons?q=...&training_id=...&package_id=...
     */
    public function searchLessons(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 3) {
            return response()->json([]);
        }

        $items = $this->baseLessonQuery([
                'training_id' => $request->integer('training_id') ?: null,
                'package_id'  => $request->integer('package_id') ?: null,
                'lesson_id'   => null,
            ])
            ->select('lessons.id', 'lessons.title as text')
            ->where('lessons.title', 'like', '%' . $term . '%')
            ->orderBy('lessons.title')
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    /* ===================== FILTRES ===================== */

    /**
     * Tous les filtres normalisés en un seul tableau.
     */
    private function resolveFilters(Request $request): array
    {
        [$from, $to] = $this->resolveDateRange($request);

        return [
            'from'        => $from,
            'to'          => $to,
            'training_id' => $request->integer('training_id') ?: null,
            'package_id'  => $request->integer('package_id') ?: null,
            'lesson_id'   => $request->integer('lesson_id') ?: null,
            'granularity' => $this->resolveGranularity($request),
        ];
    }

    /**
     * Granularité valide : day (défaut) / month / year.
     */
    private function resolveGranularity(Request $request): string
    {
        return in_array($request->query('granularity'), ['day', 'month', 'year'], true)
            ? $request->query('granularity')
            : 'day';
    }

    /**
     * Khoảng ngày, mặc định 7 ngày gần nhất (to = hôm nay).
     */
    private function resolveDateRange(Request $request): array
    {
        $to   = $request->date('to') ?: Carbon::today($this->displayTz());
        $from = $request->date('from');

        if ($from && $from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from?->toDateString(), $to->toDateString()];
    }

    /* ===================== TIMEZONE ===================== */

    private function displayTz(): string
    {
        return config('app.display_timezone', 'Asia/Ho_Chi_Minh');
    }

    /**
     * Décalage (minutes) entre le TZ de stockage (app.timezone) et le TZ
     * d'affichage. Nécessaire car `created_at` est stocké en UTC.
     */
    private function tzOffsetMinutes(): int
    {
        $app  = config('app.timezone', 'UTC');
        $disp = $this->displayTz();

        return (int) round(Carbon::now($disp)->utcOffset() - Carbon::now($app)->utcOffset());
    }

    /**
     * Expression SQL : colonne datetime convertie en heure locale.
     */
    private function localExpr(string $column): string
    {
        $m = $this->tzOffsetMinutes();

        return $m === 0 ? $column : "DATE_ADD({$column}, INTERVAL {$m} MINUTE)";
    }

    /**
     * Bornes [début, fin] en heure de stockage à partir de dates locales.
     */
    private function storageBounds(?string $from, string $to): array
    {
        $disp = $this->displayTz();
        $app  = config('app.timezone', 'UTC');

        return [
            $from
                ? Carbon::parse($from . ' 00:00:00', $disp)->setTimezone($app)->format('Y-m-d H:i:s')
                : null,
            Carbon::parse($to . ' 23:59:59', $disp)->setTimezone($app)->format('Y-m-d H:i:s'),
        ];
    }

    /* ===================== QUERIES DE BASE ===================== */

    /**
     * Leçons filtrées par formation / paquet / leçon.
     * La jointure remonte toute la chaîne trainings > packages > modules > lessons.
     */
    private function baseLessonQuery(array $f)
    {
        $trainingId = $f['training_id'] ?? null;
        $packageId  = $f['package_id'] ?? null;
        $lessonId   = $f['lesson_id'] ?? null;

        return DB::table('lessons')
            ->join('modules as m', 'm.id', '=', 'lessons.module_id')
            ->join('packages as p', 'p.id', '=', 'm.package_id')
            ->join('trainings as t', 't.id', '=', 'p.training_id')
            ->when($lessonId, fn ($q) => $q->where('lessons.id', $lessonId))
            ->when($packageId, fn ($q) => $q->where('p.id', $packageId))
            ->when($trainingId, fn ($q) => $q->where('t.id', $trainingId));
    }

    /**
     * Sous-requête : ids des leçons correspondant aux filtres hiérarchiques.
     * Sert à restreindre les vues sans dupliquer les jointures.
     */
    private function filteredLessonIds(array $f)
    {
        return DB::table('lessons')
            ->join('modules as m2', 'm2.id', '=', 'lessons.module_id')
            ->join('packages as p2', 'p2.id', '=', 'm2.package_id')
            ->select('lessons.id')
            ->when($f['package_id'], fn ($q) => $q->where('p2.id', $f['package_id']))
            ->when($f['training_id'], fn ($q) => $q->where('p2.training_id', $f['training_id']));
    }

    /**
     * Vues (log brut) filtrées par période + hiérarchie + leçon.
     */
    private function viewsQuery(array $f)
    {
        [$start, $end] = $this->storageBounds($f['from'], $f['to']);

        return DB::table('trk_content_views as v')
            ->where('v.type', self::CONTENT_TYPE)
            ->when($start, fn ($q) => $q->where('v.created_at', '>=', $start))
            ->where('v.created_at', '<=', $end)
            ->when($f['lesson_id'], fn ($q) => $q->where('v.content_id', $f['lesson_id']))
            ->when(
                $f['training_id'] || $f['package_id'],
                fn ($q) => $q->whereIn('v.content_id', $this->filteredLessonIds($f))
            );
    }

    /**
     * Sous-requête : vues agrégées par leçon sur la période.
     */
    private function periodViewsSub(array $f)
    {
        [$start, $end] = $this->storageBounds($f['from'], $f['to']);

        return DB::table('trk_content_views')
            ->select('content_id')
            ->selectRaw('COUNT(*) as period_views')
            ->selectRaw('COUNT(DISTINCT user_id) as period_viewers')
            ->where('type', self::CONTENT_TYPE)
            ->when($start, fn ($q) => $q->where('created_at', '>=', $start))
            ->where('created_at', '<=', $end)
            ->groupBy('content_id');
    }

    /**
     * Sous-requête : vues cumulées par leçon, toutes périodes confondues.
     * `lessons` n'ayant pas de compteur, le total est recalculé ici.
     */
    private function allTimeViewsSub()
    {
        return DB::table('trk_content_views')
            ->select('content_id')
            ->selectRaw('COUNT(*) as all_views')
            ->where('type', self::CONTENT_TYPE)
            ->groupBy('content_id');
    }

    /* ===================== SUMMARY ===================== */

    /**
     * KPI : leçons (total / période), vues (total historique / période),
     * utilisateurs uniques sur la période.
     */
    private function buildSummary(array $f): array
    {
        // Toutes les leçons correspondant aux filtres, période exclue
        $all = $this->baseLessonQuery($f)
            ->selectRaw('COUNT(*) as total_lessons')
            ->first();

        // Uniquement celles vues sur la période
        $seen = $this->baseLessonQuery($f)
            ->joinSub($this->periodViewsSub($f), 'pv', 'pv.content_id', '=', 'lessons.id')
            ->selectRaw('COUNT(*) as period_lessons')
            ->first();

        // Vues cumulées (toutes périodes) sur le périmètre filtré
        $allViews = $this->baseLessonQuery($f)
            ->joinSub($this->allTimeViewsSub(), 'av', 'av.content_id', '=', 'lessons.id')
            ->selectRaw('COALESCE(SUM(av.all_views), 0) as total_views_all')
            ->first();

        $period = $this->viewsQuery($f)
            ->selectRaw('COUNT(*) as total_views_period')
            ->selectRaw('COUNT(DISTINCT v.user_id) as total_viewers')
            ->first();

        return [
            'total_lessons'      => (int) ($all->total_lessons ?? 0),
            'period_lessons'     => (int) ($seen->period_lessons ?? 0),
            'total_views_all'    => (int) ($allViews->total_views_all ?? 0),
            'total_views_period' => (int) ($period->total_views_period ?? 0),
            'total_viewers'      => (int) ($period->total_viewers ?? 0),
        ];
    }

    /* ===================== CHARTS ===================== */

    /**
     * 4 séries : temporelle, horaire (0-23), jours de la semaine, métiers.
     */
    private function buildChartData(array $f): array
    {
        return [
            'timeline'    => $this->timelineSeries($f),
            'hours'       => $this->hoursSeries($f),
            'weekdays'    => $this->weekdaysSeries($f),
            'professions' => $this->professionsSeries($f),
            'granularity' => $f['granularity'],
            'from'        => $f['from'],
            'to'          => $f['to'],
        ];
    }

    /**
     * Chart 1 : vues par bucket temporel, sans trou de date.
     */
    private function timelineSeries(array $f): array
    {
        $expr = $this->localExpr('v.created_at');

        $rows = $this->viewsQuery($f)
            ->selectRaw("DATE({$expr}) as stat_date")
            ->selectRaw('COUNT(*) as views')
            ->groupBy('stat_date')
            ->orderBy('stat_date')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->stat_date)->toDateString());

        // Sans borne inférieure, on part de la première vue réellement enregistrée
        $start = $f['from'] ?: ($rows->keys()->first() ?: $f['to']);

        $buckets = [];
        $cursor  = Carbon::parse($start);
        $end     = Carbon::parse($f['to']);

        while ($cursor->lte($end)) {
            $row = $rows->get($cursor->toDateString());

            [$key, $label] = $this->bucketFor($cursor, $f['granularity']);

            if (!isset($buckets[$key])) {
                $buckets[$key] = ['label' => $label, 'views' => 0];
            }

            $buckets[$key]['views'] += (int) ($row->views ?? 0);

            $cursor->addDay();
        }

        return [
            'labels' => array_column($buckets, 'label'),
            'views'  => array_column($buckets, 'views'),
        ];
    }

    /**
     * Chart 2 : répartition des vues sur les 24 heures (heure locale).
     */
    private function hoursSeries(array $f): array
    {
        $expr = $this->localExpr('v.created_at');

        $rows = $this->viewsQuery($f)
            ->selectRaw("HOUR({$expr}) as h")
            ->selectRaw('COUNT(*) as views')
            ->groupBy('h')
            ->get()
            ->keyBy('h');

        $labels = [];
        $views  = [];

        for ($h = 0; $h < 24; $h++) {
            $labels[] = sprintf('%02dh', $h);
            $views[]  = (int) (optional($rows->get($h))->views ?? 0);
        }

        return ['labels' => $labels, 'views' => $views];
    }

    /**
     * Chart 2 bis : répartition des vues par jour de la semaine (heure locale),
     * de lundi à dimanche.
     */
    private function weekdaysSeries(array $f): array
    {
        $expr = $this->localExpr('v.created_at');

        // WEEKDAY() : 0 = lundi ... 6 = dimanche
        $rows = $this->viewsQuery($f)
            ->selectRaw("WEEKDAY({$expr}) as d")
            ->selectRaw('COUNT(*) as views')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $labels = [];
        $views  = [];

        $monday = Carbon::parse('2024-01-01');

        for ($d = 0; $d < 7; $d++) {
            $labels[] = ucfirst((clone $monday)->addDays($d)->translatedFormat('l'));
            $views[]  = (int) (optional($rows->get($d))->views ?? 0);
        }

        return ['labels' => $labels, 'views' => $views];
    }

    /**
     * Chart 3 : vues + utilisateurs uniques par métier.
     */
    private function professionsSeries(array $f): array
    {
        $rows = $this->viewsQuery($f)
            ->leftJoin('users as u', 'u.id', '=', 'v.user_id')
            ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
            ->selectRaw('COALESCE(p.name, ?) as profession', [self::PROFESSION_FALLBACK])
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT v.user_id) as viewers')
            ->groupBy('profession')
            ->orderByDesc('views')
            ->limit(self::MAX_PROFESSIONS)
            ->get();

        return [
            'labels'  => $rows->pluck('profession')->all(),
            'views'   => $rows->pluck('views')->map(fn ($n) => (int) $n)->all(),
            'viewers' => $rows->pluck('viewers')->map(fn ($n) => (int) $n)->all(),
        ];
    }

    /**
     * [clé_bucket, label_affiché] selon la granularité.
     */
    private function bucketFor(Carbon $date, string $granularity): array
    {
        return match ($granularity) {
            'year'  => [$date->format('Y'), $date->format('Y')],
            'month' => [$date->format('Y-m'), $date->translatedFormat('M Y')],
            default => [$date->toDateString(), $date->format('d/m')],
        };
    }

    /* ===================== TABLE ===================== */

    /**
     * Liste paginée des leçons (toutes, même sans vue sur la période).
     */
    private function buildTableRows(array $f)
    {
        return $this->baseLessonQuery($f)
            ->leftJoinSub($this->periodViewsSub($f), 'pv', 'pv.content_id', '=', 'lessons.id')
            ->leftJoinSub($this->allTimeViewsSub(), 'av', 'av.content_id', '=', 'lessons.id')
            ->select(
                'lessons.id',
                'lessons.title',
                't.name as training_name',
                'p.name as package_name'
            )
            ->selectRaw('COALESCE(pv.period_views, 0) as period_views')
            ->selectRaw('COALESCE(pv.period_viewers, 0) as period_viewers')
            ->selectRaw('COALESCE(av.all_views, 0) as all_views')
            ->orderByDesc(DB::raw('COALESCE(pv.period_views, 0)'))
            ->orderBy('lessons.title')
            ->paginate(25)
            ->withQueryString();
    }

    /* ===================== OPTIONS / LABELS ===================== */

    private function trainingOptions()
    {
        return DB::table('trainings')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Paquets avec leur formation, pour le filtrage en cascade.
     */
    private function packageOptions()
    {
        return DB::table('packages')
            ->orderBy('name')
            ->get(['id', 'name', 'training_id']);
    }

    private function lessonLabel(?int $lessonId): ?string
    {
        if (!$lessonId) {
            return null;
        }

        return DB::table('lessons')->where('id', $lessonId)->value('title');
    }
}