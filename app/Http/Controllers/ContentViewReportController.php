<?php

namespace App\Http\Controllers;

use App\Models\OldContent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContentViewReportController extends Controller
{
    private function applyDateRange($query, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->where('trk_content_views.created_at', '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to) {
            $query->where('trk_content_views.created_at', '<=', Carbon::parse($to)->endOfDay());
        }
    }

    /**
     * GET /report/views-by-hour?from=2026-03-01&to=2026-03-16
     */
    public function viewsByHour(Request $request)
    {
        $from = $request->query('from');
        $to   = $request->query('to');
        $type = $request->query('type') ?? 'flash';

        $query = DB::table('trk_content_views')
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as total_views')
            ->where('trk_content_views.type', '=', $type)
            ->groupByRaw('HOUR(created_at)')
            ->orderByRaw('HOUR(created_at)');

        $this->applyDateRange($query, $from, $to);

        $results = $query->get()->keyBy('hour');
        $hours   = collect(range(0, 23));

        return response()->json([
            'labels' => $hours->map(fn($h) => sprintf('%02d:00', $h))->values(),
            'data'   => $hours->map(fn($h) => $results->get($h)?->total_views ?? 0)->values(),
        ]);
    }

    /**
     * GET /report/views-by-profession?content_id=5&from=2026-03-01&to=2026-03-16
     */
    public function viewsByProfession(Request $request)
    {
        $contentId = $request->query('content_id');
        $from      = $request->query('from');
        $to        = $request->query('to');
        $type      = $request->query('type') ?? 'flash';

        $query = DB::table('trk_content_views')
            ->join('users', 'users.id', '=', 'trk_content_views.user_id')
            ->join('professions', 'professions.id', '=', 'users.profession_id')
            ->where('trk_content_views.type', '=', $type)
            ->selectRaw('professions.name as profession_name, COUNT(*) as total_views')
            ->groupBy('professions.id', 'professions.name')
            ->orderByDesc('total_views');

        if ($contentId) {
            $query->where('trk_content_views.content_id', $contentId);
        }

        $this->applyDateRange($query, $from, $to);

        $results = $query->get();
        $data = [
            'labels' => $results->pluck('profession_name')->values(),
            'data'   => $results->pluck('total_views')->values(),
        ];

        $content = null;
        if ($contentId && ($type == 'flash' || $type== 'lesson')) {
            $content = OldContent::where('id_content', $contentId)->first();
        }

        return view('reports.content', array(
            'content' => $content,
            'data' => $data
        )); 
    }
}
