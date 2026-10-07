<?php

namespace App\Http\Controllers;

use App\Models\Expert;
use App\Support\Brochure\BrochureDraft;
use App\Support\Brochure\ExpertiseCatalog;
use App\Support\Brochure\ExpertVideos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Éditeur /demo/{slug} : le médecin modifie sa page, le brouillon est enregistré en XML.
 *
 * Accès : lien signé (envoyé au médecin) OU administrateur connecté (role = 1).
 * Export index.blade.php : administrateur uniquement.
 */
class BrochureDemoController extends Controller
{
    public function show(Request $request, Expert $expert)
    {
        $this->authorizeAccess($request);

        $draft = $this->load($expert);
        $isAdmin = $this->isAdmin();

        return view('brochures.demo', [
            'expert'           => $expert,
            'identity'         => $this->identity($expert),
            'draft'            => $draft,
            'palettes'         => BrochureDraft::PALETTES,
            'models'           => BrochureDraft::MODELS,
            'timelines'        => BrochureDraft::TIMELINES,
            'expertiseCatalog' => ExpertiseCatalog::all(),
            'isAdmin'          => $isAdmin,
            'saveUrl'          => URL::signedRoute('brochures.demo.save', $expert),
            'avatarUrl'        => URL::signedRoute('brochures.demo.avatar', $expert),
            'exportUrl'        => $isAdmin ? route('brochures.demo.export', $expert) : null,
            'shareUrl'         => $isAdmin ? URL::signedRoute('brochures.demo', $expert) : null,
            'savedAt'          => $this->savedAt($expert),

            // données attendues par le gabarit commun
            'title'            => 'Édition : ' . $this->identity($expert)['name'],
            'palette'          => $draft['palette'],
            'model'            => $draft['model'],
            'showOrthoMaster'  => true,
            'videos'           => ExpertVideos::for($expert),
            'registerUrl'      => config('brochures.register_url'),
            'orthomasterUrl'   => config('brochures.orthomaster_url'),
        ]);
    }

    /** Enregistrement AJAX (JSON) du brouillon dans {slug}.xml */
    public function save(Request $request, Expert $expert)
    {
        $this->authorizeAccess($request);

        $data = BrochureDraft::normalize($request->json()->all());
        Storage::disk(config('brochures.drafts_disk'))->put($this->path($expert), BrochureDraft::toXml($data));

        return response()->json(['ok' => true, 'saved_at' => now()->format('H:i')]);
    }

    /**
     * Upload AJAX de l'avatar (enregistré directement sur l'expert, donc visible aussi sur la page officielle).
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

    /** Téléchargement de index.blade.php généré depuis le brouillon enregistré (admin). */
    public function export(Expert $expert)
    {
        abort_unless($this->isAdmin(), 403);

        $blade = BrochureDraft::toBlade($this->load($expert), $this->identity($expert));

        return response()->streamDownload(
            fn() => print($blade),
            'index.blade.php',
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
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

    /** Nom affiché depuis la base (colonne experts.name). */
    protected function identity(Expert $expert): array
    {
        return [
            'id'   => $expert->id,
            'slug' => $expert->slug,
            'name' => (string) $expert->name,
        ];
    }

    protected function path(Expert $expert): string
    {
        return trim(config('brochures.drafts_path'), '/') . "/{$expert->slug}.xml";
    }

    protected function load(Expert $expert): array
    {
        $disk = Storage::disk(config('brochures.drafts_disk'));

        return BrochureDraft::fromXml($disk->exists($this->path($expert)) ? $disk->get($this->path($expert)) : null);
    }

    protected function savedAt(Expert $expert): ?string
    {
        $disk = Storage::disk(config('brochures.drafts_disk'));

        return $disk->exists($this->path($expert))
            ? date('d/m/Y H:i', $disk->lastModified($this->path($expert)))
            : null;
    }
}
