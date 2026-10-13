<?php

namespace App\Http\Controllers;

use App\Services\AiSearch\AiSearchStats;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Tableau de bord de l'assistant de recherche, dans l'administration Voyager.
 *
 * GET /admin/ai-search-dashboard?from=2026-10-01&to=2026-10-13&staff=0
 */
class AiSearchDashboardController extends Controller
{
    /** Au-delà, les graphiques jour par jour deviennent illisibles et les requêtes lentes. */
    private const MAX_DAYS = 366;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'from'  => ['nullable', 'date_format:Y-m-d'],
            'to'    => ['nullable', 'date_format:Y-m-d'],
            'staff' => ['nullable', 'in:0,1'],
        ]);

        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();
        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        // Dates inversées : on les remet dans l'ordre plutôt que d'afficher une page vide.
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->copy()->subDays(self::MAX_DAYS)->startOfDay();
        }

        // Par défaut, l'équipe interne (tests) n'est pas comptée.
        $includeStaff = ($validated['staff'] ?? '0') === '1';

        $stats = new AiSearchStats($from, $to, ! $includeStaff);

        return view('dashboard.ai-search.index', [
            'from'           => $from,
            'to'             => $to,
            'includeStaff'   => $includeStaff,
            'summary'        => $stats->summary(),
            'daily'          => $stats->daily(),
            'inputTypes'     => $stats->inputTypes(),
            'actions'        => $stats->actions(),
            'errorCodes'     => $stats->errorCodes(),
            'rounds'         => $stats->roundsDistribution(),
            'topUsers'       => $stats->topUsers(),
            'noResults'      => $stats->recentNoResultQueries(),
            'logQueries'     => (bool) config('ai_search.logging.log_queries'),
            'pricing'        => config('ai_search.pricing'),
            'model'          => config('ai_search.chat.model'),
            'rateLimit'      => (int) config('ai_search.conversation.rate_limit_per_hour'),
        ]);
    }
}