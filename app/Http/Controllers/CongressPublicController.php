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
