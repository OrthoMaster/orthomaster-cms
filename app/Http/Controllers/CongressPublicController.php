<?php

namespace App\Http\Controllers;

use App\Models\CongressBoard;
use App\Support\Congress\CongressBoardService;

/**
 * Pages publiques, sur auth.orthomaster.fr/congres.
 */
class CongressPublicController extends Controller
{
    /**
     * Saisie du code affiché à l'écran, pour qui ne peut pas scanner le QR.
     * GET /congres
     */
    public function enter()
    {
        return view('congres.enter', ['operator' => false]);
    }

    /**
     * Saisie du code des intervenants.
     * GET /congres/operator
     */
    public function operatorEnter()
    {
        return view('congres.enter', ['operator' => true]);
    }

    /**
     * Écran des intervenants : les questions, à supprimer ou réordonner au toucher.
     * Le code se change dans le CMS : l'ancien cesse alors de répondre.
     * GET /congres/operator/{code}
     */
    public function operator(string $code)
    {
        $board = CongressBoard::where('operator_code', strtoupper($code))->firstOrFail();

        return view('congres.operator', ['board' => $board]);
    }

    /**
     * Page du public, ouverte depuis le QR.
     * GET /congres/{code}
     */
    public function reader(string $code)
    {
        $board = CongressBoard::where('code', strtoupper($code))->with('themes')->firstOrFail();

        return view('congres.reader', [
            'board'     => $board,
            'maxLength' => CongressBoardService::QUESTION_MAX_LENGTH,
        ]);
    }

    /**
     * Écran de projection. Le jeton change quand l'admin régénère le lien : l'ancien lien cesse de répondre.
     * GET /congres/{code}/ecran/{token}
     */
    public function screen(string $code, string $token)
    {
        $board = CongressBoard::where('code', strtoupper($code))->where('screen_token', $token)->firstOrFail();

        return view('congres.screen', ['board' => $board]);
    }
}
