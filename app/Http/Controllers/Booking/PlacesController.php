<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Services\Google\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * CMS : relais vers Google Places pour la saisie d'un lieu d'expert.
 * Le navigateur ne voit jamais la clé ; il envoie seulement la saisie et le jeton de session.
 */
class PlacesController extends Controller
{
    /** UUID généré par le navigateur à chaque nouvelle recherche. */
    private const SESSION_PATTERN = '/^[A-Za-z0-9-]{8,64}$/';

    /** Identifiant de lieu Google : lettres, chiffres, « - » et « _ ». */
    private const PLACE_ID_PATTERN = '/^[A-Za-z0-9_-]{10,300}$/';

    public function __construct(private GooglePlacesService $places)
    {
    }

    /**
     * Format attendu par select2 : {results: [{id, text, main, secondary}]}.
     * GET /booking/places/autocomplete?q=&session=
     */
    public function autocomplete(Request $request): JsonResponse
    {
        try {
            $suggestions = $this->places->autocomplete(
                mb_substr((string) $request->input('q'), 0, 200),
                $this->session($request)
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'results' => []], 503);
        }

        return response()->json([
            'success' => true,
            'results' => array_map(fn ($s) => [
                'id'        => $s['place_id'],
                'text'      => $s['text'],
                'main'      => $s['main'],
                'secondary' => $s['secondary'],
            ], $suggestions),
        ]);
    }

    /**
     * GET /booking/places/{placeId}?session=
     */
    public function details(Request $request, string $placeId): JsonResponse
    {
        abort_unless(preg_match(self::PLACE_ID_PATTERN, $placeId) === 1, 404);

        try {
            $place = $this->places->details($placeId, $this->session($request));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        }

        return response()->json(['success' => true, 'data' => $place]);
    }

    private function session(Request $request): ?string
    {
        $token = (string) $request->input('session');

        return preg_match(self::SESSION_PATTERN, $token) === 1 ? $token : null;
    }
}