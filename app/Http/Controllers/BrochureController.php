<?php

namespace App\Http\Controllers;

use App\Models\Expert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

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
}
