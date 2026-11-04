<?php

namespace App\Http\Controllers;

use App\Models\Expert;
use App\Support\Brochures\BrochureDraft;
use App\Support\Brochures\DemoExpert;
use App\Support\Brochures\ExpertVideos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class BrochureController extends Controller
{
    /**
     * Page publique : https://expert.orthomaster.fr/{slug} (et /experts/{slug} sur les autres domaines).
     * Lit la version publiée (experts.brochure + model) et le profil de l'expert
     * (titre, références, présentation, couleur, domaines, photo : les mêmes que dans l'application).
     * 404 tant que rien n'a été publié.
     */
    public function show(Request $request, Expert $expert)
    {
        abort_unless($expert->brochure_published_at, 404);

        $showOrthoMaster = $this->showOrthoMaster($request);
        $expert->loadMissing('domains');

        return view('brochures.expert', [
            'expert'          => $expert,
            'brochure'        => BrochureDraft::fromRecord($expert, 'brochure', $expert),
            'canonicalUrl'    => self::publicUrl($expert),
            'showOrthoMaster' => $showOrthoMaster,
            'videos'          => $showOrthoMaster ? ExpertVideos::for($expert) : [],
            'registerUrl'     => config('brochures.register_url'),
            'orthomasterUrl'  => config('brochures.orthomaster_url'),
        ]);
    }

    /** Page de démonstration avec des données fictives : /demo */
    public function sample(Request $request)
    {
        $showOrthoMaster = $this->showOrthoMaster($request);

        return view('brochures.expert_demo', [
            'name'            => DemoExpert::NAME,
            'demo'            => DemoExpert::data(),
            'showOrthoMaster' => $showOrthoMaster,
            'videos'          => $showOrthoMaster ? DemoExpert::videos() : [],
            'registerUrl'     => config('brochures.register_url'),
            'orthomasterUrl'  => config('brochures.orthomaster_url'),
        ]);
    }

    /* ===================== LIENS SUR LE SOUS-DOMAINE ===================== */

    /** Adresse officielle de la page : https://expert.orthomaster.fr/{slug} */
    public static function publicUrl(Expert $expert): ?string
    {
        return $expert->slug ? self::onExpertDomain(fn () => route('brochures.public', $expert)) : null;
    }

    /** Lien signé de l'éditeur, à envoyer à l'expert : https://expert.orthomaster.fr/demo/{slug}?signature=… */
    public static function editorUrl(Expert $expert): string
    {
        return self::onExpertDomain(fn () => URL::signedRoute('brochures.demo', $expert));
    }

    /**
     * Génère une adresse sur le sous-domaine, même depuis le CMS.
     * La signature porte sur l'adresse complète : elle doit être calculée avec le bon domaine.
     * Même schéma (http/https) que la requête en cours, comme le reste des liens générés.
     */
    private static function onExpertDomain(callable $build): string
    {
        $domain = config('brochures.domain');
        if (! $domain || strcasecmp(request()->getHost(), $domain) === 0) {
            return $build();
        }

        URL::forceRootUrl(request()->getScheme().'://'.$domain);
        try {
            return $build();
        } finally {
            URL::forceRootUrl(null);
        }
    }

    /** Blocs OrthoMaster : affichés sans paramètre inapp ou avec ?inapp=WEB, masqués sinon. */
    protected function showOrthoMaster(Request $request): bool
    {
        $inapp = (string) $request->query('inapp', '');

        return $inapp === '' || strtoupper($inapp) === 'WEB';
    }
}
