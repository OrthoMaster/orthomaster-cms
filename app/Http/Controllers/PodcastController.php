<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PodcastController extends Controller
{
    /** Rôles correspondant aux utilisateurs de l'application */
    private const USER_ROLES = [2, 4];

    /**
     * Liste des podcasts.
     * GET /podcasts
     */
    public function index()
    {
        // Auditeurs distincts, comptés depuis le journal de lecture
        $listeners = "(SELECT COUNT(DISTINCT v.user_id)
                       FROM trk_content_views v
                       WHERE v.type = 'podcast' AND v.content_id = p.id)";

        // Mises en favori, hors comptes internes
        $favourites = "(SELECT COUNT(*)
                        FROM favourites f
                        JOIN users u ON u.id = f.user_id
                        WHERE f.type = 'podcast'
                          AND f.item_id = p.id
                          AND u.role_id IN (" . implode(',', self::USER_ROLES) . "))";

        $podcasts = DB::table('podcasts as p')
            ->leftJoin('topics as t', 't.id', '=', 'p.topic_id')
            ->select(
                'p.id',
                'p.title',
                'p.author',
                'p.count',
                'p.published_at',
                'p.expired_at',
                't.name as topic_name'
            )
            ->selectRaw("{$listeners} as listeners")
            ->selectRaw("{$favourites} as favourites")
            ->orderByDesc('p.published_at')
            ->orderByDesc('p.id')
            ->paginate(25);

        return view('podcasts.index', [
            'podcasts' => $podcasts,
        ]);
    }

    /**
     * Formulaire de création.
     * GET /podcasts/create
     */
    public function create()
    {
        return view('podcasts.create', [
            'topics'        => $this->topicOptions(),
            'podcast'       => null,
            'selectedVideo' => null,
        ]);
    }

    /**
     * Enregistrement.
     * POST /podcasts
     */
    public function store(Request $request)
    {
        $data = $this->validatePodcast($request);

        $id = DB::table('podcasts')->insertGetId(
            $this->payload($data) + [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return redirect()
            ->route('voyager.podcasts.edit', $id)
            ->with('message', 'Le podcast a bien été créé.')
            ->with('alert-type', 'success');
    }

    /**
     * Formulaire d'édition.
     * GET /podcasts/{id}/edit
     */
    public function edit(int $id)
    {
        $podcast = $this->podcastOrFail($id);

        return view('podcasts.edit', [
            'topics'        => $this->topicOptions(),
            'podcast'       => $podcast,
            'selectedVideo' => $this->videoLabel($podcast->video_id),
        ]);
    }

    /**
     * Mise à jour.
     * PUT /podcasts/{id}
     */
    public function update(Request $request, int $id)
    {
        $podcast = $this->podcastOrFail($id);
        $data    = $this->validatePodcast($request);

        DB::table('podcasts')
            ->where('id', $podcast->id)
            ->update($this->payload($data) + ['updated_at' => now()]);

        return redirect()
            ->route('voyager.podcasts.edit', $podcast->id)
            ->with('message', 'Le podcast a bien été mis à jour.')
            ->with('alert-type', 'success');
    }

    /* ===================== HELPERS FORMULAIRE ===================== */

    /**
     * Règles communes création / édition.
     */
    private function validatePodcast(Request $request): array
    {
        $v = $request->validate([
            'video_id'           => ['nullable', 'integer', 'exists:videos,id'],
            'topic_id'           => ['required', 'integer', 'exists:topics,id'],
            'title'              => ['required', 'string', 'max:255'],
            'author'             => ['nullable', 'string', 'max:255'],
            'link'               => ['required', 'string', 'max:1000'],
            'image'              => ['nullable', 'string', 'max:1000'],
            'duration'           => ['nullable', 'string', 'max:50'],
            'published_at'       => ['nullable', 'date'],
            'expired_at'         => ['nullable', 'date', 'after_or_equal:published_at'],
            'notification_title' => ['nullable', 'string', 'max:255'],
            'notification_body'  => ['nullable', 'string', 'max:500'],
        ], [
            'expired_at.after_or_equal' => 'La date de fin ne peut pas précéder la date de publication.',
        ], [
            'video_id'           => 'vidéo',
            'topic_id'           => 'sujet',
            'title'              => 'titre',
            'author'             => 'auteur',
            'link'               => 'lien du fichier audio',
            'image'              => 'vignette',
            'duration'           => 'durée',
            'published_at'       => 'date de publication',
            'expired_at'         => 'date de fin',
            'notification_title' => 'titre de la notification',
            'notification_body'  => 'message de la notification',
        ]);

        $v['video_id'] = $v['video_id'] ?: null;

        return $v;
    }

    /**
     * Colonnes communes à l'insert et à l'update.
     */
    private function payload(array $data): array
    {
        return [
            'video_id'           => $data['video_id'],
            'topic_id'           => $data['topic_id'],
            'title'              => $data['title'],
            'author'             => $data['author'] ?? null,
            'link'               => $data['link'],
            'image'              => $data['image'] ?? null,
            'duration'           => $data['duration'] ?? null,
            // Saisie en date seule : début au premier instant du jour,
            // fin au dernier, pour que la journée choisie soit incluse
            'published_at'       => $this->startOfDay($data['published_at'] ?? null),
            'expired_at'         => $this->endOfDay($data['expired_at'] ?? null),
            'notification_title' => $data['notification_title'] ?? null,
            'notification_body'  => $data['notification_body'] ?? null,
        ];
    }

    private function startOfDay(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->startOfDay()->toDateTimeString() : null;
    }

    private function endOfDay(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->endOfDay()->toDateTimeString() : null;
    }

    private function podcastOrFail(int $id)
    {
        $podcast = DB::table('podcasts')->where('id', $id)->first();

        abort_if(!$podcast, 404);

        return $podcast;
    }

    /**
     * Sujets pouvant recevoir un contenu : les sous-sujets, et les sujets
     * qui n'en regroupent aucun. Un sujet parent ne sert que de regroupement.
     */
    private function topicOptions()
    {
        return DB::table('topics as t')
            ->leftJoin('topics as p', 'p.id', '=', 't.parent_id')
            ->select('t.id')
            ->selectRaw("CASE WHEN p.id IS NULL THEN t.name ELSE CONCAT(p.name, ' · ', t.name) END as name")
            ->where('t.active_flg', 1)
            ->whereNotExists(function ($q) {
                $q->from('topics as c')->whereColumn('c.parent_id', 't.id')->selectRaw('1');
            })
            ->orderByRaw('COALESCE(p.order_dsp, t.order_dsp), t.order_dsp')
            ->get();
    }

    private function videoLabel(?int $videoId): ?string
    {
        return $videoId
            ? DB::table('videos')->where('id', $videoId)->value('title')
            : null;
    }
}
