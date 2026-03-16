<?php

namespace App\Http\Controllers;

use App\Models\RptDailyAppOpens;
use App\Models\RptSystemControls;
use App\Models\User;
use App\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $days = request()->get('days', 7);
        $dailyAppOpens = $this->getDailyAppOpens($days);

        // Users
        $totalActiveUsers = 0;
        $report = RptDailyAppOpens::latest()->first();

        $totalActiveUsers = $report->total_users;
        $dbActiveUsers = User::where('status', Status::ACTIVE->value)->count();

        // User Agents
        $userAgents = $this->getUserAgentsChartData();

        // Media Views by hours
        $mediaViews = $this->getContentViewsByHourChartData($days, 'flash');

        // Lesson Views by hours
        $lessonViews = $this->getContentViewsByHourChartData($days, 'lesson');

        return view('dashboard.index', [
            'dailyAppOpens' => $dailyAppOpens,
            'totalUsers' => [
                'label' => ['Odoo', 'CMS'],
                'data' => [$totalActiveUsers - $dbActiveUsers, $dbActiveUsers],
            ],
            'userAgents' => $userAgents,
            'mediaViews' => $mediaViews,
            'lessonViews' => $lessonViews,
        ]);
    }

    public function getAppOpensChartData(Request $request)
    {
        $days = $request->get('days', 7);
        $dailyAppOpens = $this->getDailyAppOpens($days);

        return response()->json([
            'labels' => $dailyAppOpens->pluck('formatted_date'),
            'counts' => $dailyAppOpens->pluck('count')
        ]);
    }

    public function getContentViewsByHourChartData($days, $type)
    {
        $to = Carbon::today();
        $from = $to->copy()->subDays($days - 1);

        $query = DB::table('trk_content_views')
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as total_views')
            ->groupByRaw('HOUR(created_at)')
            ->orderByRaw('HOUR(created_at)');

        $query->where('type', '=', $type);
        $query->where('trk_content_views.created_at', '>=', Carbon::parse($from)->startOfDay());
        $query->where('trk_content_views.created_at', '<=', Carbon::parse($to)->endOfDay());

        $results = $query->get()->keyBy('hour');
        $hours = collect(range(0, 23));

        return [
            'labels' => $hours->map(fn($h) => sprintf('%02d', $h))->values(),
            'data'   => $hours->map(fn($h) => $results->get($h)?->total_views ?? 0)->values(),
        ];
    }

    private function getDailyAppOpens($days)
    {
        $today = Carbon::today();
        $fromDate = $today->copy()->subDays($days - 1);

        return RptDailyAppOpens::query()
            ->whereBetween('date', [$fromDate, $today])
            ->orderBy('date', 'asc')
            ->get(['date', 'count'])
            ->map(function ($item) {
                $item->formatted_date = Carbon::parse($item->date)->format('d/m');
                return $item;
            });
    }

    public function getUserAgentsChartData()
    {
        $userAgents = RptSystemControls::where('type', 'user-agents')->get();
        if ($userAgents->isEmpty()) {
            return null;
        }

        return [
            'label' => $userAgents->pluck('label')->map(fn($l) => ucfirst($l)),
            'data' => $userAgents->pluck('data_int')
        ];
    }
}
