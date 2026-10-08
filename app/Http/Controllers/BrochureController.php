<?php

namespace App\Http\Controllers;

use App\Models\Expert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use App\Support\Brochures\DemoExpert;

class BrochureController extends Controller
{
    public function show(Request $request, Expert $expert)
    {
        $view = "brochures.experts.{$expert->id}.index";
        abort_unless(View::exists($view), 404);

        // Blocs OrthoMaster (vidéos, « Toutes ses vidéos », bouton OrthoMaster) :
        // affichés sans paramètre inapp, ou avec ?inapp=WEB. Masqués pour toute autre valeur (IOS, ANDROID…).
        $inapp = (string) $request->query('inapp', '');
        $showOrthoMaster = $inapp === '' || strtoupper($inapp) === 'WEB';

        return view($view, [
            'expert'          => $expert,
            'showOrthoMaster' => $showOrthoMaster,
            'videos'          => $showOrthoMaster ? $this->videosFor($expert) : [],
            'registerUrl'     => 'https://auth.orthomaster.fr/register',
            'orthomasterUrl'  => 'https://www.orthomaster.fr/',
        ]);
    }

    /**
     * Vidéos de l'expert sur OrthoMaster.
     * À brancher sur vos modèles (ex. Expert::findOrFail($expert)->videos).
     * Chaque vidéo : title, description, duration, url, thumbnail (facultatif).
     */
    protected function videosFor(string $expert): array
    {
        return [];
    }

    /**
     * Page de démonstration avec des données fictives : /expert_demo
     * Même règle ?inapp que les pages officielles.
     */
    public function sample(Request $request)
    {
        $inapp = (string) $request->query('inapp', '');
        $showOrthoMaster = $inapp === '' || strtoupper($inapp) === 'WEB';

        return view('brochures.expert_demo', [
            'name'            => DemoExpert::NAME,
            'demo'            => DemoExpert::data(),
            'showOrthoMaster' => $showOrthoMaster,
            'videos'          => $showOrthoMaster ? DemoExpert::videos() : [],
            'registerUrl'     => config('brochures.register_url'),
            'orthomasterUrl'  => config('brochures.orthomaster_url'),
        ]);
    }
}
