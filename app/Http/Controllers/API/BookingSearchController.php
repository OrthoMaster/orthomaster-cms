<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Booking\BookingSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Recherche intelligente de l'application (un tour) : experts ou thèmes.
 * Les résultats ont la même forme que les listes (GET /booking/experts, GET /booking/themes),
 * avec en plus « reason » : pourquoi le résultat correspond (null sans Gemini).
 */
class BookingSearchController extends Controller
{
    public function __construct(private BookingSearchService $search, private BookingExpertController $catalog)
    {
    }

    /**
     * GET /booking/search?type=experts|themes&q=
     * Experts : consultation=1 (liste Consultation : domaines, références et présentation
     * seulement, sans les thèmes) ; name= et city= restreignent les candidats avant l'IA.
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([BookingSearchService::TYPE_EXPERTS, BookingSearchService::TYPE_THEMES])],
            'q'            => ['required', 'string', 'min:2', 'max:200'],
            'consultation' => ['nullable', 'boolean'],
            'name'         => ['nullable', 'string', 'max:100'],
            'city'         => ['nullable', 'string', 'max:120'],
        ]);

        $userId = $request->user()->id;
        $themes = $data['type'] === BookingSearchService::TYPE_THEMES;

        // Ce que l'utilisateur peut voir : thèmes réservables, ou tous les experts avec un compte sauf lui.
        $consultation = !$themes && $request->boolean('consultation');
        $eligible = $themes
            ? $this->catalog->themeQuery($userId)->pluck('o.id')->map(fn ($id) => (int) $id)->all()
            : $this->catalog->searchableExpertIds($userId, $consultation, (string) ($data['name'] ?? ''), (string) ($data['city'] ?? ''));

        $searchType = $consultation ? BookingSearchService::TYPE_CONSULTANTS : $data['type'];
        $found   = $this->search->search($searchType, $data['q'], $eligible, $userId);
        $ids     = array_column($found['results'], 'id');
        $reasons = array_column($found['results'], 'reason', 'id');

        $items = ($themes ? $this->catalog->themePayloads($ids) : $this->catalog->expertListPayloads($ids))
            ->map(fn (array $item) => $item + ['reason' => $reasons[$item['id']] ?? null])
            ->values();

        return response()->json(['success' => true, 'data' => [
            'type'     => $data['type'],
            'query'    => $data['q'],
            'results'  => $items,
            'used_llm' => $found['used_llm'],
        ]]);
    }
}
