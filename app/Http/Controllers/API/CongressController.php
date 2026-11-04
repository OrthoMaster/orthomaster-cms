<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CongressBoard;
use App\Models\CongressBoardTheme;
use App\Support\Congress\CongressBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Appels du public et de l'écran. Routes API : sans session, parce que l'écran interroge
 * le serveur toutes les 2 secondes pendant des heures, et que le jeton d'appareil évite au
 * public l'erreur CSRF d'une page restée ouverte plus longtemps que la session.
 */
class CongressController extends Controller
{
    public function __construct(private CongressBoardService $service)
    {
    }

    /**
     * État du tableau, relu par la page du public tant que la séance n'est pas ouverte.
     * GET /api/congres/{code}/status
     */
    public function status(string $code): JsonResponse
    {
        $board = CongressBoard::where('code', $code)->firstOrFail();

        return response()->json(['status' => $board->status]);
    }

    /**
     * POST /api/congres/{code}/join
     */
    public function join(Request $request, string $code): JsonResponse
    {
        $board = CongressBoard::where('code', $code)->firstOrFail();

        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:60']]);

        [$participant, $token] = $this->service->join($board, trim($data['name']));

        return response()->json(['token' => $token, 'name' => $participant->name]);
    }

    /**
     * POST /api/congres/{code}/questions (en-tête X-Participant-Token)
     */
    public function ask(Request $request, string $code): JsonResponse
    {
        $board = CongressBoard::where('code', $code)->firstOrFail();

        $participant = $this->service->participantFor($board, $request->header('X-Participant-Token'));
        abort_unless($participant, 401, 'Participant inconnu.');

        if (!$board->isLive()) {
            return response()->json(['status' => $board->status, 'message' => 'La séance n\'est pas ouverte.'], 409);
        }

        $data = $request->validate([
            'theme_id' => ['required', 'integer'],
            'text'     => ['required', 'string', 'min:3', 'max:' . CongressBoardService::QUESTION_MAX_LENGTH],
        ]);

        abort_unless(
            CongressBoardTheme::where('board_id', $board->id)->whereKey($data['theme_id'])->exists(),
            422,
            'Thème inconnu.'
        );

        $question = $this->service->addQuestion($board, $participant, (int) $data['theme_id'], trim($data['text']));

        return response()->json(['id' => $question->id], 201);
    }

    /**
     * Données de l'écran. Sans changement depuis `rev`, réponse vide : l'écran garde ce qu'il affiche.
     * GET /api/congres/ecran/{token}?rev=
     */
    public function screen(Request $request, string $token): JsonResponse|Response
    {
        $board = CongressBoard::where('screen_token', $token)->firstOrFail();

        if ((string) $board->revision === (string) $request->query('rev')) {
            return response()->noContent();
        }

        return response()->json([
            'revision' => $board->revision,
            'status'   => $board->status,
            'themes'   => $this->service->snapshot($board, true),
        ]);
    }
}
