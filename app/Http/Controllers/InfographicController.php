<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesContentTopics;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class InfographicController extends Controller
{
    use HandlesContentTopics;

    protected function contentType(): string
    {
        return 'infographic';
    }

    /** Type unique pour l'instant, non modifiable depuis le formulaire */
    private const TYPE = 'info';

    /** Dossier S3 des visuels */
    private const FOLDER = 'infographics';

    /** Largeur maximale de la vignette générée */
    private const THUMBNAIL_WIDTH = 400;

    /** Format du code : 4 groupes de 4 caractères */
    private const CODE_GROUPS = 4;
    private const CODE_GROUP_LENGTH = 4;

    /** Alphabet du code : chiffres et majuscules sans caractères ambigus */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Rôles correspondant aux utilisateurs de l'application */
    private const USER_ROLES = [2, 4];

    /**
     * Liste des infographies.
     * GET /infographics
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        // Consultations et spectateurs distincts, depuis le journal de vues
        $views = "(SELECT COUNT(*)
                   FROM trk_content_views v
                   WHERE v.type = 'infographic' AND v.content_id = i.id)";

        $viewers = "(SELECT COUNT(DISTINCT v.user_id)
                     FROM trk_content_views v
                     WHERE v.type = 'infographic' AND v.content_id = i.id)";

        // Mises en favori, hors comptes internes
        $favourites = "(SELECT COUNT(*)
                        FROM favourites f
                        JOIN users u ON u.id = f.user_id
                        WHERE f.type = 'infographic'
                          AND f.item_id = i.id
                          AND u.role_id IN (" . implode(',', self::USER_ROLES) . "))";

        $infographics = DB::table('infographics as i')
            ->leftJoin('videos as vd', 'vd.id', '=', 'i.video_id')
            ->select(
                'i.id',
                'i.code',
                'i.title',
                'i.thumbnail',
                'i.published_at',
                'i.expired_at',
                'vd.title as video_title'
            )
            ->selectRaw("{$views} as views")
            ->selectRaw("{$viewers} as viewers")
            ->selectRaw("{$favourites} as favourites")
            ->when($search !== '', fn($q) => $q->where('i.title', 'like', '%' . $search . '%'))
            ->orderByDesc('i.created_at')
            ->orderByDesc('i.id')
            ->paginate(25)
            ->withQueryString();

        return view('infographics.index', [
            'infographics' => $infographics,
            'search'       => $search,
        ]);
    }

    /**
     * Formulaire de création.
     * GET /infographics/create
     */
    public function create(Request $request)
    {
        // Création lancée depuis un média : la vidéo est imposée
        $videoId = $request->query('video_id');

        $video = $videoId
            ? DB::table('videos')->where('id', $videoId)->first(['id', 'title', 'code'])
            : null;
        
        return view('infographics.create', [
            'infographic'   => null,
            'selectedVideo' => null,
            'imageUrl'      => null,
            'topics'        => $this->topicOptions(),
            'selectedTopics' => [],
            'lockedVideo' => $video,
        ]);
    }

    /**
     * Enregistrement.
     * POST /infographics
     */
    public function store(Request $request)
    {
        $data = $this->validateInfographic($request);

        // L'envoi précède la transaction : une écriture S3 ne se rollback pas
        $files = $this->storeImage($request->file('image_file'));

        $id = DB::table('infographics')->insertGetId(
            $this->payload($data) + [
                'code'       => $this->generateCode(),
                'type'       => self::TYPE,
                'image'      => $files['image'] ?? null,
                'thumbnail'  => $files['thumbnail'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->syncTopics($id, $request->input('notification_topics', []));

        return redirect()
            ->route('infographics.edit', $id)
            ->with('message', 'L\'infographie a bien été créée.')
            ->with('alert-type', 'success');
    }

    /**
     * Formulaire d'édition.
     * GET /infographics/{id}/edit
     */
    public function edit(int $id)
    {
        $infographic = $this->infographicOrFail($id);

        return view('infographics.edit', [
            'infographic'   => $infographic,
            'selectedVideo' => $this->videoLabel($infographic->video_id),
            'imageUrl'      => $this->fileUrl($infographic->thumbnail ?: $infographic->image),
            'topics'        => $this->topicOptions(),
            'selectedTopics' => $this->selectedTopicIds($infographic->id),
        ]);
    }

    /**
     * Mise à jour.
     * PUT /infographics/{id}
     */
    public function update(Request $request, int $id)
    {
        $infographic = $this->infographicOrFail($id);
        $data        = $this->validateInfographic($request);

        $files   = $this->storeImage($request->file('image_file'));
        $payload = $this->payload($data) + ['updated_at' => now()];

        if (!empty($files)) {
            $payload['image']     = $files['image'];
            $payload['thumbnail'] = $files['thumbnail'];
        }

        DB::table('infographics')->where('id', $infographic->id)->update($payload);

        $this->syncTopics($id, $request->input('notification_topics', []));

        // Les anciens fichiers ne sont supprimés qu'une fois la base à jour
        if (!empty($files)) {
            $this->deleteFiles([$infographic->image, $infographic->thumbnail]);
        }

        return redirect()
            ->route('infographics.edit', $infographic->id)
            ->with('message', 'L\'infographie a bien été mise à jour.')
            ->with('alert-type', 'success');
    }

    /**
     * Suppression de la fiche et de ses visuels.
     * DELETE /infographics/{id}
     */
    public function destroy(int $id)
    {
        $infographic = $this->infographicOrFail($id);

        DB::table('infographics')->where('id', $infographic->id)->delete();

        $this->deleteFiles([$infographic->image, $infographic->thumbnail]);

        return redirect()
            ->route('infographics.index')
            ->with('message', 'L\'infographie a bien été supprimée.')
            ->with('alert-type', 'success');
    }

    /**
     * Autocomplete vidéos (min 3 caractères).
     * GET /infographics/search-videos?q=...
     */
    public function searchVideos(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 3) {
            return response()->json([]);
        }

        $items = DB::table('videos')
            ->select('id', 'title as text')
            ->where('title', 'like', '%' . $term . '%')
            ->orderBy('title')
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    /* ===================== HELPERS FORMULAIRE ===================== */

    /**
     * Règles communes création / édition.
     */
    private function validateInfographic(Request $request): array
    {
        $v = $request->validate([
            'title'              => ['required', 'string', 'max:255'],
            'video_id'           => ['nullable', 'integer', 'exists:videos,id'],
            'topic_id'           => ['required', 'integer', 'exists:topics,id'],
            'image_file'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'notification_title' => ['nullable', 'string', 'max:150'],
            'notification_body'  => ['nullable', 'string', 'max:255'],
            'published_at'       => ['nullable', 'date'],
            'expired_at'         => ['nullable', 'date', 'after_or_equal:published_at'],
            'notification_topics'   => ['nullable', 'array'],
            'notification_topics.*' => ['integer', 'exists:topics,id'],
        ], [
            'image_file.max'   => 'Le visuel ne doit pas dépasser 5 Mo.',
            'image_file.image' => 'Le fichier doit être une image.',
            'expired_at.after_or_equal' => 'La date de fin ne peut pas précéder la date de publication.',
        ], [
            'title'              => 'titre',
            'topic_id'           => 'sujet',
            'video_id'           => 'vidéo',
            'image_file'         => 'visuel',
            'notification_title' => 'titre de la notification',
            'notification_body'  => 'message de la notification',
            'published_at'       => 'date de publication',
            'expired_at'         => 'date de fin',
        ]);

        $v['video_id']     = $v['video_id'] ?: null;
        $v['published_at'] = !empty($v['published_at'])
            ? Carbon::parse($v['published_at'])->toDateString()
            : null;
        $v['expired_at']   = !empty($v['expired_at'])
            ? Carbon::parse($v['expired_at'])->toDateString()
            : null;

        return $v;
    }

    /**
     * Colonnes communes à l'insert et à l'update.
     */
    private function payload(array $data): array
    {
        return [
            'title'              => $data['title'],
            'video_id'           => $data['video_id'],
            'topic_id'           => $data['topic_id'],
            'notification_title' => $data['notification_title'] ?? null,
            'notification_body'  => $data['notification_body'] ?? null,
            'published_at'       => $data['published_at'],
            'expired_at'         => $data['expired_at'],
        ];
    }

    /* ===================== VISUELS ===================== */

    /**
     * Envoie le visuel sur S3 et génère sa vignette.
     * Renvoie les deux chemins relatifs, ou un tableau vide sans fichier.
     */
    private function storeImage(?UploadedFile $file): array
    {
        if (!$file) {
            return [];
        }

        $base      = Str::uuid()->toString();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');

        $imagePath     = self::FOLDER . '/' . $base . '.' . $extension;
        $thumbnailPath = self::FOLDER . '/' . $base . '_thumbnail.' . $extension;

        $disk = Storage::disk('s3');

        // Visuel d'origine
        $disk->put($imagePath, file_get_contents($file->getRealPath()));

        // Vignette : largeur bornée, hauteur calculée pour garder les proportions
        $thumbnail = Image::make($file->getRealPath())
            ->resize(self::THUMBNAIL_WIDTH, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->encode($extension, 80);

        $disk->put($thumbnailPath, (string) $thumbnail);

        return [
            'image'     => $imagePath,
            'thumbnail' => $thumbnailPath,
        ];
    }

    /**
     * Supprime des fichiers devenus inutiles, sans bloquer s'ils ont disparu.
     */
    private function deleteFiles(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            try {
                Storage::disk('s3')->delete($path);
            } catch (\Throwable $e) {
                Log::channel('api')->error($e->getMessage(), ['infographic-delete-file', $path]);
            }
        }
    }

    private function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('s3')->url($path) : null;
    }

    /* ===================== DIVERS ===================== */

    /**
     * Code lisible en 4 groupes de 4 caractères, garanti non utilisé.
     */
    private function generateCode(): string
    {
        $max = strlen(self::CODE_ALPHABET) - 1;

        do {
            $groups = [];

            for ($g = 0; $g < self::CODE_GROUPS; $g++) {
                $group = '';

                for ($i = 0; $i < self::CODE_GROUP_LENGTH; $i++) {
                    $group .= self::CODE_ALPHABET[random_int(0, $max)];
                }

                $groups[] = $group;
            }

            $code = implode('-', $groups);
        } while (DB::table('infographics')->where('code', $code)->exists());

        return $code;
    }

    private function infographicOrFail(int $id)
    {
        $infographic = DB::table('infographics')->where('id', $id)->first();

        abort_if(!$infographic, 404);

        return $infographic;
    }

    private function videoLabel(?int $videoId): ?string
    {
        return $videoId
            ? DB::table('videos')->where('id', $videoId)->value('title')
            : null;
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
}
