<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Expert;
use App\Models\ExpertBrochureDraft;
use App\Support\Brochures\BrochureDraft;
use App\Support\Brochures\ExpertiseCatalog;
use App\Support\Brochures\ExpertVideos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Éditeur /demo/{slug}.
 *  - Contenu de la page : enregistré automatiquement dans expert_brochure_drafts (brouillon).
 *  - « Publier » (admin) : copie le brouillon dans experts (colonnes + experts.brochure).
 *  - Domaines et avatar : enregistrés directement dans la table principale.
 *
 * Accès : lien signé (envoyé au médecin) OU administrateur connecté.
 */
class BrochureDemoController extends Controller
{
    public function show(Request $request, Expert $expert)
    {
        $this->authorizeAccess($request);

        $draft = $expert->brochureDraft;
        $data = match (true) {
            $draft !== null                      => BrochureDraft::fromRecord($draft, 'data'),
            $expert->brochure_published_at !== null => BrochureDraft::fromRecord($expert, 'brochure'),
            default                              => BrochureDraft::defaults(),
        };
        $data['expertise'] = $expert->domains()->pluck('domains.id')->all();
        $isAdmin = $this->isAdmin();

        return view('brochures.demo', [
            'expert'           => $expert,
            'identity'         => $this->identity($expert),
            'draft'            => $data,
            'palettes'         => BrochureDraft::PALETTES,
            'models'           => BrochureDraft::MODELS,
            'timelines'        => BrochureDraft::TIMELINES,
            'expertiseCatalog' => ExpertiseCatalog::all(),
            'isAdmin'          => $isAdmin,
            'saveUrl'          => URL::signedRoute('brochures.demo.save', $expert),
            'avatarUrl'        => URL::signedRoute('brochures.demo.avatar', $expert),
            'domainUrl'        => URL::signedRoute('brochures.demo.domain', $expert),
            'publishUrl'       => $isAdmin ? route('brochures.demo.publish', $expert) : null,
            'shareUrl'         => $isAdmin ? URL::signedRoute('brochures.demo', $expert) : null,
            'savedAt'          => $draft?->updated_at?->format('d/m/Y H:i'),
            'publication'      => $this->publication($expert, $draft),

            // données attendues par le gabarit commun
            'title'            => 'Édition : '.$expert->name,
            'palette'          => $data['palette'],
            'model'            => $data['model'],
            'showOrthoMaster'  => true,
            'videos'           => ExpertVideos::for($expert),
            'registerUrl'      => config('brochures.register_url'),
            'orthomasterUrl'   => config('brochures.orthomaster_url'),
        ]);
    }

    /**
     * Enregistrement AJAX.
     * Domaines : directement dans domain_expert. Reste : brouillon (colonnes + JSON).
     * Toute modification du brouillon le repasse en « non publié » (published_at = NULL).
     */
    public function save(Request $request, Expert $expert)
    {
        $this->authorizeAccess($request);

        $data = BrochureDraft::normalize($request->json()->all());
        [$columns, $json] = BrochureDraft::split($data);

        $draft = DB::transaction(function () use ($expert, $data, $columns, $json) {
            $expert->domains()->sync(
                collect($data['expertise'])
                    ->values()
                    ->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i]])
                    ->all()
            );

            $draft = $expert->brochureDraft()->lockForUpdate()->firstOrNew();

            $changed = ! $draft->exists
                || $draft->data != $json
                || $draft->only(BrochureDraft::COLUMNS) != $columns;

            if ($changed) {
                $draft->forceFill($columns);
                $draft->data = $json;
                $draft->published_at = null;
                $draft->save();
            }

            return $draft;
        });

        return response()->json([
            'ok'        => true,
            'saved_at'  => now()->format('H:i'),
            'published' => $draft->isPublished(),
        ]);
    }

    /** Copie le brouillon dans experts (admin). */
    public function publish(Expert $expert)
    {
        abort_unless($this->isAdmin(), 403);

        $now = now();

        DB::transaction(function () use ($expert, $now) {
            $draft = $expert->brochureDraft()->lockForUpdate()->first();
            abort_unless($draft, 422, 'Aucune modification à publier.');

            $expert->forceFill([
                ...$draft->only(BrochureDraft::COLUMNS),
                'brochure'              => $draft->data,
                'brochure_published_at' => $now,
            ])->save();

            $draft->timestamps = false; // conserver la date du dernier enregistrement
            $draft->forceFill(['published_at' => $now])->save();
        });

        return response()->json(['ok' => true, 'published_at' => $now->format('d/m/Y H:i')]);
    }

    /**
     * Upload AJAX de l'avatar (enregistré directement sur l'expert).
     * L'ancien fichier est supprimé une fois le nouveau enregistré.
     */
    public function avatar(Request $request, Expert $expert)
    {
        $this->authorizeAccess($request);

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=200,min_height=200'],
        ], [
            'avatar.image'      => 'Le fichier doit être une image.',
            'avatar.mimes'      => 'Formats acceptés : JPG, PNG ou WebP.',
            'avatar.max'        => 'L’image ne doit pas dépasser 5 Mo.',
            'avatar.dimensions' => 'L’image doit faire au moins 200 × 200 pixels.',
        ]);

        $disk = Storage::disk(config('brochures.avatar_disk'));
        $old = $expert->avatar;

        $path = $request->file('avatar')->store("experts/{$expert->id}", config('brochures.avatar_disk'));
        abort_unless($path, 500, 'Upload impossible.');

        $expert->forceFill(['avatar' => $path])->save();

        if ($old && $old !== $path && $disk->exists($old)) {
            $disk->delete($old);
        }

        return response()->json(['ok' => true, 'url' => $expert->avatar_url]);
    }

    /**
     * Ajout AJAX d'un domaine d'expertise absent de la liste.
     * Si un domaine de même libellé existe déjà (sans tenir compte de la casse), il est renvoyé tel quel.
     */
    public function addDomain(Request $request, Expert $expert)
    {
        $this->authorizeAccess($request);

        $label = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $request->input('label', ''));
        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? '');
        $request->merge(['label' => $label]);

        $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:60'],
        ], [
            'label.required' => 'Saisissez un domaine.',
            'label.min'      => 'Le domaine doit comporter au moins 2 caractères.',
            'label.max'      => 'Le domaine ne doit pas dépasser 60 caractères.',
        ]);

        $domain = Domain::whereRaw('LOWER(label) = ?', [mb_strtolower($label)])->first()
            ?? Domain::createOrFirst(
                ['label' => $label],
                ['sort_order' => (int) Domain::max('sort_order') + 1]
            );

        return response()->json([
            'id'      => $domain->id,
            'label'   => $domain->label,
            'created' => $domain->wasRecentlyCreated,
        ]);
    }

    // ------------------------------------------------------------------

    protected function authorizeAccess(Request $request): void
    {
        abort_unless($request->hasValidSignature() || $this->isAdmin(), 403);
    }

    protected function isAdmin(): bool
    {
        return in_array((int) auth()->user()?->role_id, [1, 3, 7], true);
    }

    protected function identity(Expert $expert): array
    {
        return [
            'id'   => $expert->id,
            'slug' => $expert->slug,
            'name' => (string) $expert->name,
        ];
    }

    /** État de publication affiché dans l'éditeur. */
    protected function publication(Expert $expert, ?ExpertBrochureDraft $draft): array
    {
        if ($draft && ! $draft->isPublished()) {
            return ['state' => 'pending', 'label' => 'Modifications non publiées'];
        }
        if ($expert->brochure_published_at) {
            return ['state' => 'published', 'label' => 'Publié le '.$expert->brochure_published_at->format('d/m/Y H:i')];
        }

        return ['state' => 'new', 'label' => 'Pas encore publié'];
    }
}