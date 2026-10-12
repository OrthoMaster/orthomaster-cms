<?php

namespace App\Http\Controllers;

use App\Models\Expert;
use App\Support\Brochures\BrochureDraft;
use App\Support\Brochures\DemoExpert;
use App\Support\Brochures\ExpertVideos;
use Illuminate\Http\Request;

class BrochureController extends Controller
{
    /**
     * Page publique : https://expert.orthomaster.fr/{slug}
     * Lit la version publiée (colonnes de experts + experts.brochure). 404 tant que rien n'a été publié.
     */
    public function show(Request $request, Expert $expert)
    {
        abort_unless($expert->brochure_published_at, 404);

        $showOrthoMaster = $this->showOrthoMaster($request);
        $expert->loadMissing('domains');

        return view('brochures.expert', [
            'expert'          => $expert,
            'brochure'        => BrochureDraft::fromRecord($expert, 'brochure'),
            'showOrthoMaster' => $showOrthoMaster,
            'videos'          => $showOrthoMaster ? ExpertVideos::for($expert) : [],
            'registerUrl'     => config('brochures.register_url'),
            'orthomasterUrl'  => config('brochures.orthomaster_url'),
        ]);
    }

    /** Page de démonstration avec des données fictives : /expert_demo */
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

    /** Blocs OrthoMaster : affichés sans paramètre inapp ou avec ?inapp=WEB, masqués sinon. */
    protected function showOrthoMaster(Request $request): bool
    {
        $inapp = (string) $request->query('inapp', '');

        return $inapp === '' || strtoupper($inapp) === 'WEB';
    }
}