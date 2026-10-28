<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Recherche d'adresses via Google Places API (New), appelée uniquement côté serveur :
 * la clé ne quitte jamais le serveur (restreinte par IP dans Google Cloud).
 *
 * Facturation : Autocomplete et Details partagent un même sessionToken, généré par le
 * client à chaque nouvelle recherche. Google compte alors la frappe et le choix final
 * comme une seule session au lieu de facturer chaque lettre tapée.
 *
 * Config : services.google_places (key, regions, language).
 */
class GooglePlacesService
{
    private const BASE_URL = 'https://places.googleapis.com/v1';

    /** Au-delà, l'écran de saisie attend trop : on préfère afficher une erreur. */
    private const TIMEOUT_SECONDS = 6;

    /** Google limite includedRegionCodes à 15 pays. */
    private const MAX_REGIONS = 15;

    public const MIN_INPUT_LENGTH = 3;

    public static function enabled(): bool
    {
        return (string) config('services.google_places.key') !== '';
    }

    /**
     * Suggestions pour une saisie partielle.
     *
     * @return array<int, array{place_id:string, main:string, secondary:string, text:string}>
     */
    public function autocomplete(string $input, ?string $sessionToken = null): array
    {
        $input = trim($input);
        if (mb_strlen($input) < self::MIN_INPUT_LENGTH) {
            return [];
        }

        $body = array_filter([
            'input'              => $input,
            'languageCode'       => $this->language(),
            'includedRegionCodes'=> $this->regions() ?: null,
            'sessionToken'       => $sessionToken,
        ]);

        $json = $this->request('post', '/places:autocomplete', $body, [
            'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.text,suggestions.placePrediction.structuredFormat',
        ]);

        $results = [];
        foreach ($json['suggestions'] ?? [] as $suggestion) {
            $p = $suggestion['placePrediction'] ?? null;
            if (!$p || empty($p['placeId'])) {
                continue; // Les suggestions de requête (queryPrediction) ne désignent pas un lieu.
            }

            $results[] = [
                'place_id'  => $p['placeId'],
                'main'      => $p['structuredFormat']['mainText']['text'] ?? ($p['text']['text'] ?? ''),
                'secondary' => $p['structuredFormat']['secondaryText']['text'] ?? '',
                'text'      => $p['text']['text'] ?? '',
            ];
        }

        return $results;
    }

    /**
     * Adresse détaillée d'un lieu choisi, découpée comme expert_locations.
     * Clôt la session de facturation ouverte par autocomplete().
     *
     * @return array{google_place_id:string, name:?string, is_establishment:bool, address_line:string, postal_code:?string, city:string, country:?string, lat:?float, lng:?float, formatted:string}
     */
    public function details(string $placeId, ?string $sessionToken = null): array
    {
        $query = array_filter([
            'languageCode' => $this->language(),
            'sessionToken' => $sessionToken,
        ]);

        $json = $this->request('get', '/places/' . rawurlencode($placeId) . ($query ? '?' . http_build_query($query) : ''), [], [
            'X-Goog-FieldMask' => 'id,displayName,types,formattedAddress,addressComponents,location',
        ]);

        // Composant par type : long pour l'affichage, court pour le pays (code ISO « FR »).
        $long  = [];
        $short = [];
        foreach ($json['addressComponents'] ?? [] as $component) {
            foreach ($component['types'] ?? [] as $type) {
                $long[$type]  ??= $component['longText'] ?? null;
                $short[$type] ??= $component['shortText'] ?? null;
            }
        }

        // En France le numéro précède la voie : « 12 Rue des Lilas ».
        $street = trim(($long['street_number'] ?? '') . ' ' . ($long['route'] ?? ''));
        if ($street === '') {
            $street = $long['premise'] ?? $long['establishment'] ?? (explode(',', (string) ($json['formattedAddress'] ?? ''))[0] ?? '');
        }

        $types = $json['types'] ?? [];

        return [
            'google_place_id'  => $json['id'] ?? $placeId,
            'name'             => $json['displayName']['text'] ?? null,
            // Un cabinet, un hôpital : son nom peut servir de libellé court.
            'is_establishment' => in_array('establishment', $types, true) || in_array('point_of_interest', $types, true),
            'address_line'     => $street,
            'postal_code'      => $long['postal_code'] ?? null,
            'city'             => $long['locality'] ?? $long['postal_town'] ?? $long['administrative_area_level_2'] ?? '',
            'country'          => isset($short['country']) ? strtoupper($short['country']) : null,
            'lat'              => isset($json['location']['latitude']) ? (float) $json['location']['latitude'] : null,
            'lng'              => isset($json['location']['longitude']) ? (float) $json['location']['longitude'] : null,
            'formatted'        => $json['formattedAddress'] ?? '',
        ];
    }

    /* ===================== OUTILS ===================== */

    private function request(string $method, string $path, array $body, array $headers): array
    {
        if (!self::enabled()) {
            throw new RuntimeException("La recherche d'adresse n'est pas configurée (GOOGLE_PLACES_KEY).");
        }

        $client = Http::timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->withHeaders($headers + ['X-Goog-Api-Key' => config('services.google_places.key')]);

        $response = $method === 'post'
            ? $client->post(self::BASE_URL . $path, $body)
            : $client->get(self::BASE_URL . $path);

        if ($response->failed()) {
            // Le message Google (clé refusée, API non activée, quota…) sert au diagnostic,
            // pas à l'utilisateur.
            Log::warning('Google Places error', [
                'path'   => strtok($path, '?'),
                'status' => $response->status(),
                'error'  => $response->json('error.message') ?? $response->body(),
            ]);

            throw new RuntimeException($response->status() === 403
                ? "Recherche d'adresse refusée par Google (clé ou restriction IP)."
                : "Recherche d'adresse momentanément indisponible.");
        }

        return $response->json() ?? [];
    }

    private function language(): string
    {
        return (string) (config('services.google_places.language') ?: 'fr');
    }

    /** @return array<int, string> codes pays en minuscules (« fr », « be »…) */
    private function regions(): array
    {
        $regions = config('services.google_places.regions', []);
        if (is_string($regions)) {
            $regions = explode(',', $regions);
        }

        return array_slice(array_values(array_filter(array_map(
            fn ($r) => strtolower(trim((string) $r)),
            (array) $regions
        ))), 0, self::MAX_REGIONS);
    }
}