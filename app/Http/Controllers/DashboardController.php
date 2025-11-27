<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Odoo\OdooAccountController;
use App\Models\RptDailyAppOpens;
use App\Models\User;
use App\Services\OdooService;
use App\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $report = RptDailyAppOpens::firstOrCreate(
            ['date' => Carbon::now()->toDateString()],
            ['count' => 0]
        );
        if ($report->wasRecentlyCreated) {
            $odooAccountController = new OdooAccountController(app(OdooService::class));
            $report->total_users = $odooAccountController->countTotalActiveUser();
            $report->save();
        }

        $totalActiveUsers = $report->total_users;
        $dbActiveUsers = User::where('status', Status::ACTIVE->value)->count();

        return view('dashboard.index', [
            'dailyAppOpens' => $dailyAppOpens,
            'totalUsers' => [
                'label' => ['Odoo', 'CMS'],
                'data' => [$totalActiveUsers - $dbActiveUsers, $dbActiveUsers],
            ]
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
}
