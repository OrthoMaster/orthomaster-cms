<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /** type dans `content` et `trk_content_views` */
    private const CONTENT_TYPE = 'flash';

    /** version des contenus gérés par ce dashboard */
    private const CONTENT_VERSION = 'v2';

    /** Colonne du nom dans `experts` — à adapter si différent */
    private const EXPERT_NAME_COL = 'name';

    /** Nb max de métiers affichés sur le chart 3 */
    private const MAX_PROFESSIONS = 10;

    /** Libellé pour les users sans profession */
    private const PROFESSION_FALLBACK = 'Non renseigné';

    /**
     * Trang dashboard medias.
     * GET /medias
     */
    public function index(Request $request)
    {
        $f = $this->resolveFilters($request);

        return view('medias.index', [
            'filters'           => $f,
            'chart'             => $this->buildChartData($f),
            'summary'           => $this->buildSummary($f),
            'medias'            => $this->buildTableRows($f),
            'events'            => $this->eventOptions(),
            'pastEvents'        => $this->pastEventOptions(),
            'selectedExpert'    => $this->expertLabel($f['expert_id']),
            'selectedVideo'     => $this->videoLabel($f['content_id']),
        ]);
    }

    /**
     * Endpoint JSON (fetch dynamique si besoin).
     * GET /medias/data
     */
    public function chartData(Request $request)
    {
        $f = $this->resolveFilters($request);

        return response()->json(
            $this->buildChartData($f) + ['summary' => $this->buildSummary($f)]
        );
    }

    /**
     * Autocomplete auteurs (min 3 caractères).
     * GET /medias/search-experts?q=...&event_id=...&past_event_id=...
     */
    public function searchExperts(Request $request)
    {
        return response()->json($this->expertItems($request));
    }

    /**
     * Autocomplete vidéos (min 3 caractères).
     * GET /medias/search-videos?q=...&event_id=...&past_event_id=...&expert_id=...
     */
    public function searchVideos(Request $request)
    {
        return response()->json($this->videoItems($request));
    }

    /**
     * Auteurs correspondants, restreints à l'événement si fourni.
     * $forcedEventId prend le pas sur la query string (mode partagé).
     */
    private function expertItems(Request $request, ?int $forcedEventId = null)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 3) {
            return collect();
        }

        $eventId     = $forcedEventId ?: ($request->integer('event_id') ?: null);
        $pastEventId = $request->integer('past_event_id') ?: null;
        $col         = self::EXPERT_NAME_COL;

        return DB::table('experts')
            ->select('id', DB::raw("{$col} as text"))
            ->where($col, 'like', '%' . $term . '%')
            ->when($eventId || $pastEventId, fn ($q) => $q->whereExists(function ($sub) use ($eventId, $pastEventId) {
                $sub->from('expert_video as ev')
                    ->join('content as c', 'c.id_content', '=', 'ev.content_id')
                    ->whereColumn('ev.expert_id', 'experts.id')
                    ->where('c.type', self::CONTENT_TYPE)
                    ->when($eventId, fn ($s) => $s->where('c.event_id', $eventId))
                    ->when($pastEventId, fn ($s) => $s->where('c.id_past_event', $pastEventId))
                    ->selectRaw('1');
            }))
            ->orderBy($col)
            ->limit(20)
            ->get();
    }

    /**
     * Vidéos correspondantes, restreintes à l'événement si fourni.
     */
    private function videoItems(Request $request, ?int $forcedEventId = null)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 3) {
            return collect();
        }

        return $this->baseContentQuery([
                'event_id'      => $forcedEventId ?: ($request->integer('event_id') ?: null),
                'past_event_id' => $request->integer('past_event_id') ?: null,
                'expert_id'     => $request->integer('expert_id') ?: null,
                'content_id'    => null,
            ])
            ->select('content.id_content as id', 'content.title as text')
            ->where('content.title', 'like', '%' . $term . '%')
            ->orderBy('content.title')
            ->limit(20)
            ->get();
    }

    /* ===================== CREATE / STORE ===================== */

    /**
     * Formulaire de création.
     * GET /medias/create
     */
    public function create()
    {
        return view('medias.create', [
            'categories'      => $this->mediaCategories(),
            'pastEvents'      => $this->pastEventOptions(null, true),
            'selectedExperts' => collect(),
            'media'           => null,
            'video'           => null,
        ]);
    }

    /**
     * Enregistrement : videos -> content -> expert_video (transaction).
     * POST /medias
     */
    public function store(Request $request)
    {
        $data = $this->validateMedia($request);

        $contentId = DB::transaction(function () use ($data) {
            // 1) La vidéo (code unique généré automatiquement)
            $videoId = DB::table('videos')->insertGetId([
                'code'       => $this->generateVideoCode(),
                'title'      => $data['title'],
                'thumbnail'  => $data['thumbnail'],
                'highlights' => $data['highlights'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2) Le contenu (ancienne table, PK id_content)
            $contentId = DB::table('content')->insertGetId(
                $this->contentPayload($data) + [
                    'title'    => $data['title'],
                    'type'     => self::CONTENT_TYPE,
                    'version'  => self::CONTENT_VERSION,
                    'views'    => 1,
                    'creation' => now()->toDateTimeString(),
                    'video_id' => $videoId,
                ],
                'id_content'
            );

            // 3) Les auteurs
            $this->syncExperts($contentId, $data['experts']);

            return $contentId;
        });

        return redirect()
            ->route('medias.edit', $contentId)
            ->with('message', 'Le média a bien été créé.')
            ->with('alert-type', 'success');
    }

    /* ===================== EDIT / UPDATE ===================== */

    /**
     * Formulaire d'édition.
     * GET /medias/{id}/edit
     */
    public function edit(int $id)
    {
        $media = $this->mediaOrFail($id);

        $video = $media->video_id
            ? DB::table('videos')->where('id', $media->video_id)->first()
            : null;

        return view('medias.edit', [
            'categories'      => $this->mediaCategories(),
            'pastEvents'      => $this->pastEventOptions(null, true),
            'selectedExperts' => $this->selectedExperts($media->id_content),
            'media'           => $media,
            'video'           => $video,
        ]);
    }

    /**
     * Mise à jour : content (+ videos si le média est lié à une vidéo).
     * Sans vidéo liée, le titre reste inchangé.
     * PUT /medias/{id}
     */
    public function update(Request $request, int $id)
    {
        $media    = $this->mediaOrFail($id);
        $hasVideo = (bool) $media->video_id;
        $data     = $this->validateMedia($request, $hasVideo);

        DB::transaction(function () use ($media, $data, $hasVideo) {
            $payload = $this->contentPayload($data);

            if ($hasVideo) {
                $payload['title'] = $data['title'];

                DB::table('videos')
                    ->where('id', $media->video_id)
                    ->update([
                        'title'      => $data['title'],
                        'thumbnail'  => $data['thumbnail'],
                        'highlights' => $data['highlights'],
                        'updated_at' => now(),
                    ]);
            }

            DB::table('content')
                ->where('id_content', $media->id_content)
                ->update($payload);

            $this->syncExperts($media->id_content, $data['experts']);
        });

        return redirect()
            ->route('medias.edit', $media->id_content)
            ->with('message', 'Le média a bien été mis à jour.')
            ->with('alert-type', 'success');
    }

    /* ===================== HELPERS FORMULAIRE ===================== */

    /**
     * Règles communes création / édition.
     * Les champs thumbnail / highlights n'existent que si une vidéo est liée.
     */
    private function validateMedia(Request $request, bool $withVideo = true): array
    {
        $rules = [
            'aws_object_path'   => ['nullable', 'string', 'max:1000'],
            'duration'          => ['nullable', 'string', 'max:50'],
            'description'       => ['nullable', 'string'],
            'tags'              => ['nullable', 'string'],
            'id_media_category' => ['nullable', 'integer', 'exists:media_categories,id'],
            'id_past_event'     => ['nullable', 'integer', 'exists:past_events,id'],
            'experts'           => ['nullable', 'array'],
            'experts.*'         => ['integer', 'exists:experts,id'],
        ];

        if ($withVideo) {
            $rules['thumbnail']  = ['nullable', 'string', 'max:1000'];
            $rules['highlights'] = ['nullable', 'string'];
        }

        $v = $request->validate($rules, [], [
            'aws_object_path'   => 'lien vidéo',
            'thumbnail'         => 'lien thumbnail',
            'tags'              => 'mots-clés',
            'id_media_category' => 'catégorie',
            'id_past_event'     => 'événement',
        ]);

        $v['experts']         = array_values(array_unique($v['experts'] ?? []));
        $v['highlights']      = $v['highlights'] ?? '';
        $v['thumbnail']       = $v['thumbnail'] ?? '';
        $v['tags']            = $v['tags'] ?? '';
        $v['description']     = $v['description'] ?? '';
        $v['aws_object_path'] = $v['aws_object_path'] ?? '';

        return $v;
    }

    /**
     * Contenu de type flash, quelle que soit sa version.
     */
    private function mediaOrFail(int $id)
    {
        $media = DB::table('content')
            ->where('id_content', $id)
            ->where('type', self::CONTENT_TYPE)
            ->first();

        abort_if(!$media, 404);

        return $media;
    }

    /**
     * Colonnes de `content` communes à l'insert et à l'update.
     * `title` est géré à part (non modifiable sans vidéo liée).
     * `event_id` est déduit du past_event choisi.
     */
    private function contentPayload(array $data): array
    {
        return [
            'duration'          => $data['duration'],
            'aws_object_path'   => $data['aws_object_path'],
            'description'       => $data['description'],
            'tags'              => $data['tags'],
            'id_media_category' => $data['id_media_category'],
            'id_past_event'     => $data['id_past_event'],
            'event_id'          => $this->eventIdFromPastEvent($data['id_past_event']),
        ];
    }

    /**
     * Remplace les auteurs liés au contenu.
     * NB : la colonne `video_id` de expert_video reste vide (cohérent avec l'existant).
     */
    private function syncExperts(int $contentId, array $expertIds): void
    {
        DB::table('expert_video')->where('content_id', $contentId)->delete();

        if (empty($expertIds)) {
            return;
        }

        DB::table('expert_video')->insert(
            array_map(fn ($eid) => [
                'expert_id'  => $eid,
                'content_id' => $contentId,
            ], $expertIds)
        );
    }

    /**
     * event_id correspondant au past_event sélectionné.
     */
    private function eventIdFromPastEvent(?int $pastEventId): ?int
    {
        if (!$pastEventId) {
            return null;
        }

        $id = DB::table('past_events')->where('id', $pastEventId)->value('id_event');

        return $id ? (int) $id : null;
    }

    /**
     * Code vidéo type UUID, garanti non utilisé.
     */
    private function generateVideoCode(): string
    {
        do {
            $code = (string) Str::uuid();
        } while (DB::table('videos')->where('code', $code)->exists());

        return $code;
    }

    /**
     * Contenu éditable uniquement si version v2 ET vidéo liée.
     */
    private function editableMediaOrFail(int $id)
    {
        $media = DB::table('content')
            ->where('id_content', $id)
            ->where('type', self::CONTENT_TYPE)
            ->where('version', self::CONTENT_VERSION)
            ->whereNotNull('video_id')
            ->first();

        abort_if(!$media, 404);

        return $media;
    }

    private function mediaCategories()
    {
        return DB::table('media_categories')->orderBy('name')->get(['id', 'name']);
    }

    private function eventOptions()
    {
        return DB::table('events')->orderBy('name')->get(['id', 'name', 'code']);
    }

    /**
     * Options "édition" : année seule. `event_id` sert au filtrage en cascade.
     */
    private function pastEventOptions(?int $eventId = null, bool $withEventName = false)
    {
        return DB::table('past_events as pe')
            ->join('events as e', 'e.id', '=', 'pe.id_event')
            ->when($eventId, fn ($q) => $q->where('pe.id_event', $eventId))
            ->select('pe.id', 'pe.id_event', 'pe.edition_year', 'e.name')
            ->orderByDesc('pe.edition_year')
            ->orderBy('e.name')
            ->get()
            ->map(fn ($r) => (object) [
                'id'       => $r->id,
                'event_id' => $r->id_event,
                'text'     => $withEventName
                    ? $r->edition_year . ' — ' . $r->name
                    : (string) $r->edition_year,
            ]);
    }

    /**
     * Auteurs déjà liés (pour préremplir le select2).
     */
    private function selectedExperts(int $contentId)
    {
        $col = self::EXPERT_NAME_COL;

        return DB::table('expert_video as ev')
            ->join('experts as e', 'e.id', '=', 'ev.expert_id')
            ->where('ev.content_id', $contentId)
            ->select('e.id', DB::raw("e.{$col} as text"))
            ->orderBy("e.{$col}")
            ->get();
    }

    /* ===================== FILTRES ===================== */

    /**
     * Tous les filtres normalisés en un seul tableau.
     */
    private function resolveFilters(Request $request): array
    {
        [$from, $to] = $this->resolveDateRange($request);

        return [
            'from'          => $from,
            'to'            => $to,
            'event_id'      => $request->integer('event_id') ?: null,
            'past_event_id' => $request->integer('past_event_id') ?: null,
            'expert_id'     => $request->integer('expert_id') ?: null,
            'content_id'    => $request->integer('content_id') ?: null,
            'granularity'   => $this->resolveGranularity($request),
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
        $to   = $request->date('to')   ?: Carbon::today($this->displayTz());
        $from = $request->date('from') ?: (clone $to)->subDays(6);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->toDateString(), $to->toDateString()];
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
    private function storageBounds(string $from, string $to): array
    {
        $disp = $this->displayTz();
        $app  = config('app.timezone', 'UTC');

        return [
            Carbon::parse($from . ' 00:00:00', $disp)->setTimezone($app)->format('Y-m-d H:i:s'),
            Carbon::parse($to . ' 23:59:59', $disp)->setTimezone($app)->format('Y-m-d H:i:s'),
        ];
    }

    /* ===================== QUERIES DE BASE ===================== */

    /**
     * Contenus de type flash, filtrés par événement / édition / auteur / vidéo.
     */
    private function baseContentQuery(array $f)
    {
        $eventId     = $f['event_id'] ?? null;
        $pastEventId = $f['past_event_id'] ?? null;
        $expertId    = $f['expert_id'] ?? null;
        $contentId   = $f['content_id'] ?? null;

        return DB::table('content')
            ->where('content.type', self::CONTENT_TYPE)
            ->when($eventId, fn ($q) => $q->where('content.event_id', $eventId))
            ->when($pastEventId, fn ($q) => $q->where('content.id_past_event', $pastEventId))
            ->when($contentId, fn ($q) => $q->where('content.id_content', $contentId))
            ->when($expertId, fn ($q) => $q->whereIn('content.id_content', function ($sub) use ($expertId) {
                $sub->from('expert_video')
                    ->select('content_id')
                    ->where('expert_id', $expertId);
            }));
    }

    /**
     * Vues (log brut) filtrées par période + événement + auteur + vidéo.
     */
    private function viewsQuery(array $f)
    {
        [$start, $end] = $this->storageBounds($f['from'], $f['to']);

        return DB::table('trk_content_views as v')
            ->where('v.type', self::CONTENT_TYPE)
            ->whereBetween('v.created_at', [$start, $end])
            ->when($f['content_id'], fn ($q) => $q->where('v.content_id', $f['content_id']))
            ->when($f['expert_id'], fn ($q) => $q->whereIn('v.content_id', function ($sub) use ($f) {
                $sub->from('expert_video')
                    ->select('content_id')
                    ->where('expert_id', $f['expert_id']);
            }))
            ->when($f['event_id'] || $f['past_event_id'], fn ($q) => $q->whereIn('v.content_id', function ($sub) use ($f) {
                $sub->from('content')
                    ->select('id_content')
                    ->where('type', self::CONTENT_TYPE)
                    ->when($f['event_id'], fn ($s) => $s->where('event_id', $f['event_id']))
                    ->when($f['past_event_id'], fn ($s) => $s->where('id_past_event', $f['past_event_id']));
            }));
    }

    /**
     * Sous-requête : vues agrégées par content_id sur la période.
     */
    private function periodViewsSub(array $f)
    {
        [$start, $end] = $this->storageBounds($f['from'], $f['to']);

        return DB::table('trk_content_views')
            ->select('content_id')
            ->selectRaw('COUNT(*) as period_views')
            ->selectRaw('COUNT(DISTINCT user_id) as period_viewers')
            ->where('type', self::CONTENT_TYPE)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('content_id');
    }

    /* ===================== SUMMARY ===================== */

    /**
     * KPI : vidéos (total / période), vues (total historique / période),
     * spectateurs uniques sur la période.
     */
    private function buildSummary(array $f): array
    {
        // Tous les médias correspondant aux filtres, période exclue
        $all = $this->baseContentQuery($f)
            ->selectRaw('COUNT(*) as total_videos')
            ->selectRaw('COALESCE(SUM(content.views), 0) as total_views_all')
            ->first();

        // Uniquement ceux qui ont été vus sur la période
        $seen = $this->baseContentQuery($f)
            ->joinSub($this->periodViewsSub($f), 'pv', 'pv.content_id', '=', 'content.id_content')
            ->selectRaw('COUNT(*) as period_videos')
            ->first();

        $period = $this->viewsQuery($f)
            ->selectRaw('COUNT(*) as total_views_period')
            ->selectRaw('COUNT(DISTINCT v.user_id) as total_viewers')
            ->first();

        return [
            'total_videos'       => (int) ($all->total_videos ?? 0),
            'period_videos'      => (int) ($seen->period_videos ?? 0),
            'total_views_all'    => (int) ($all->total_views_all ?? 0),
            'total_views_period' => (int) ($period->total_views_period ?? 0),
            'total_viewers'      => (int) ($period->total_viewers ?? 0),
        ];
    }

    /* ===================== CHARTS ===================== */

    /**
     * Séries des graphiques. En mode partagé ($full = false), seules les
     * données de la courbe temporelle sont calculées.
     */
    private function buildChartData(array $f, bool $full = true): array
    {
        $data = [
            'timeline'    => $this->timelineSeries($f),
            'granularity' => $f['granularity'],
            'from'        => $f['from'],
            'to'          => $f['to'],
        ];

        if ($full) {
            $data['hours']       = $this->hoursSeries($f);
            $data['professions'] = $this->professionsSeries($f);
        }

        return $data;
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

        $buckets = [];
        $cursor  = Carbon::parse($f['from']);
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
     * Chart 3 : vues + spectateurs uniques par métier.
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
     * Liste paginée des médias (toutes, même sans vue sur la période).
     * `author` = noms des experts concaténés par " - ".
     * `event_label` = année d'édition — nom de l'événement.
     */
    private function buildTableRows(array $f)
    {
        $col = self::EXPERT_NAME_COL;

        $authorSub = "(SELECT GROUP_CONCAT(e.{$col} ORDER BY e.{$col} SEPARATOR ' - ')
                       FROM expert_video ev
                       JOIN experts e ON e.id = ev.expert_id
                       WHERE ev.content_id = content.id_content)";

        return $this->baseContentQuery($f)
            ->leftJoinSub($this->periodViewsSub($f), 'pv', 'pv.content_id', '=', 'content.id_content')
            ->leftJoin('past_events as pe', 'pe.id', '=', 'content.id_past_event')
            ->leftJoin('events as ev2', 'ev2.id', '=', 'pe.id_event')
            ->select(
                'content.id_content',
                'content.title',
                'content.views',
                'content.version',
                'content.video_id',
                'pe.edition_year',
                'ev2.name as event_name'
            )
            ->selectRaw('COALESCE(pv.period_views, 0) as period_views')
            ->selectRaw('COALESCE(pv.period_viewers, 0) as period_viewers')
            ->selectRaw("{$authorSub} as author")
            ->orderByDesc(DB::raw('COALESCE(pv.period_views, 0)'))
            ->orderBy('content.title')
            ->paginate(25)
            ->withQueryString();
    }

    /* ===================== LABELS SÉLECTION ===================== */

    private function expertLabel(?int $expertId): ?string
    {
        if (!$expertId) {
            return null;
        }

        return DB::table('experts')->where('id', $expertId)->value(self::EXPERT_NAME_COL);
    }

    private function videoLabel(?int $contentId): ?string
    {
        if (!$contentId) {
            return null;
        }

        return DB::table('content')->where('id_content', $contentId)->value('title');
    }

    /* ===================== RAPPORT PARTAGÉ ===================== */

    /**
     * Rapport en lecture seule pour l'organisateur d'un événement.
     * L'événement est imposé par le code présent dans l'URL.
     * GET /medias/shared/{code}
     */
    public function shared(Request $request, string $code)
    {
        $event = $this->eventByCodeOrFail($code);

        $f = $this->resolveFilters($request);
        $f['event_id']      = (int) $event->id;
        $f['past_event_id'] = $this->restrictPastEvent($f['past_event_id'], (int) $event->id);

        return view('medias.shared', [
            'event'          => $event,
            'code'           => $code,
            'filters'        => $f,
            'chart'          => $this->buildChartData($f, false),
            'summary'        => $this->buildSummary($f),
            'medias'         => $this->buildTableRows($f),
            'pastEvents'     => $this->pastEventOptions((int) $event->id),
            'selectedExpert' => $this->expertLabel($f['expert_id']),
            'selectedVideo'  => $this->videoLabel($f['content_id']),
        ]);
    }

    /**
     * Autocomplete auteurs, version partagée (événement imposé).
     * GET /medias/shared/{code}/search-experts
     */
    public function sharedSearchExperts(Request $request, string $code)
    {
        $event = $this->eventByCodeOrFail($code);

        return response()->json(
            $this->expertItems($request, (int) $event->id)
        );
    }

    /**
     * Autocomplete vidéos, version partagée (événement imposé).
     * GET /medias/shared/{code}/search-videos
     */
    public function sharedSearchVideos(Request $request, string $code)
    {
        $event = $this->eventByCodeOrFail($code);

        return response()->json(
            $this->videoItems($request, (int) $event->id)
        );
    }

    private function eventByCodeOrFail(string $code)
    {
        $event = DB::table('events')->where('code', $code)->first();

        abort_if(!$event, 404);

        return $event;
    }

    /**
     * Ignore une édition qui n'appartient pas à l'événement partagé.
     */
    private function restrictPastEvent(?int $pastEventId, int $eventId): ?int
    {
        if (!$pastEventId) {
            return null;
        }

        $ok = DB::table('past_events')
            ->where('id', $pastEventId)
            ->where('id_event', $eventId)
            ->exists();

        return $ok ? $pastEventId : null;
    }
}