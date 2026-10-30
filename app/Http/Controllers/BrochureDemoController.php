<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Expert;
use App\Models\ExpertBrochureDraft;
use App\Services\Booking\ExpertProfileService;
use App\Support\Brochures\BrochureDraft;
use App\Support\Brochures\ExpertiseCatalog;
use App\Support\Brochures\ExpertVideos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Éditeur /demo/{slug}.
 *  - Contenu propre à la page (modèle 3D, lieux, liens, formation, parcours) :
 *    enregistré automatiquement dans expert_brochure_drafts (brouillon).
 *  - « Publier » (admin) : copie le brouillon dans experts (model + experts.brochure).
 *  - Profil (titre, références, présentation, couleur), domaines et photo : enregistrés
 *    directement sur l'expert, comme depuis l'application (une seule source).
 *
 * Accès : lien signé (envoyé à l'expert, sur expert.orthomaster.fr) OU administrateur connecté.
 */
class BrochureDemoController extends Controller
{
    public function show(Request $request, Expert $expert)
    {
        $this->authorizeAccess($request);

        $draft = $expert->brochureDraft;
        $data = match (true) {
            $draft !== null                         => BrochureDraft::fromRecord($draft, 'data', $expert),
            $expert->brochure_published_at !== null => BrochureDraft::fromRecord($expert, 'brochure', $expert),
            default                                 => BrochureDraft::fromRecord(null, 'data', $expert),
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
            'honorifics'       => array_keys(ExpertProfileService::HONORIFICS),
            'ledeMax'          => BrochureDraft::LEDE_MAX,
            'expertiseCatalog' => ExpertiseCatalog::all(),
            'isAdmin'          => $isAdmin,
            'saveUrl'          => URL::signedRoute('brochures.demo.save', $expert),
            'avatarUrl'        => URL::signedRoute('brochures.demo.avatar', $expert),
            'domainUrl'        => URL::signedRoute('brochures.demo.domain', $expert),
            'publishUrl'       => $isAdmin ? route('brochures.demo.publish', $expert) : null,
            // Liens sur le sous-domaine, même quand l'administrateur édite depuis le CMS.
            'shareUrl'         => $isAdmin ? BrochureController::editorUrl($expert) : null,
            'publicUrl'        => BrochureController::publicUrl($expert),
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
     * Profil et domaines : directement sur l'expert. Reste : brouillon (model + JSON).
     * Toute modification du brouillon le repasse en « non publié » (published_at = NULL).
     */
    public function save(Request $request, Expert $expert)
    {
        $this->authorizeAccess($request);

        $data = BrochureDraft::normalize($request->json()->all());
        [$columns, $json, $profile] = BrochureDraft::split($data);

        $draft = DB::transaction(function () use ($expert, $data, $columns, $json, $profile) {
            // forceFill : comme ExpertProfileService (le $fillable d'Expert est géré ailleurs).
            $expert->forceFill(BrochureDraft::profileColumns($profile, $expert))->save();

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
     * Même traitement que depuis l'application : S3, photo orientée et réduite à 600 px,
     * l'ancien fichier supprimé.
     */
    public function avatar(Request $request, Expert $expert, ExpertProfileService $profile)
    {
        $this->authorizeAccess($request);

        $expert = $profile->updateAvatar($expert, $request->file('avatar'));

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