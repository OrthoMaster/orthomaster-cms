<?php

namespace App\Http\Controllers;

use App\Models\RptDailyAppOpens;
use App\Models\RptSystemControls;
use App\Models\User;
use App\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{

    public function chatbot() {
        return view('dashboard.chatbot');
    }

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

        return view('dashboard.index', [
            'dailyAppOpens' => $dailyAppOpens,
            'totalUsers' => [
                'label' => ['Odoo', 'CMS'],
                'data' => [$totalActiveUsers - $dbActiveUsers, $dbActiveUsers],
            ],
            'userAgents' => $userAgents
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
