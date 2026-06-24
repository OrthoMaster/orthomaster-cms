<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use App\Models\KpiDailyLog;
use App\Models\KpiTarget;
use App\Models\RptDailyAppOpens;
use App\Models\RptSystemControls;
use App\Models\User;
use App\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function staffIndex(Request $request)
    {
        $isSale = $request->user()->roles->contains('name', 'csr');
        $saleId = $isSale ? $request->user()->id : $request->sale_id;
        $sales = User::whereHas('roles', fn($q) => $q->where('name', 'csr'))->orderBy('name')->get();

        // Nếu là CSR thì chỉ xem của mình, ngược lại nếu có sale_id thì lọc, không thì lấy tất cả
        $isAll = !$isSale && !$saleId;
        $saleIds = $isAll ? $sales->pluck('id') : collect([$saleId]);
        $sale = $isAll ? null : User::find($saleId);

        if (!$request->filled('to') || !$request->filled('from')) {
            $from = today()->startOfMonth();
            $to   = today()->endOfMonth();

            $request->merge([
                'from' => today()->startOfMonth()->toDateString(),
                'to'   => today()->endOfMonth()->toDateString(),
            ]);
        } else {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();
        }

        $currentKpi = $isAll ? KpiTarget
            ::where('effective_from', '<=', today())
            ->orderByDesc('effective_from')
            ->first()
            : KpiTarget::where('user_id', $saleId)
            ->where('effective_from', '<=', today())
            ->orderByDesc('effective_from')
            ->first();

        // Dùng whereIn thay vì where cho user_id
        $kpiDailyLogsQuery = KpiDailyLog::whereIn('user_id', $saleIds)
            ->whereBetween('log_date', [$from, $to])
            ->orderBy('log_date', 'asc');

        $dailyLogsCount = KpiDailyLog::whereIn('user_id', $saleIds)
            ->whereBetween('log_date', [$from, $to])
            ->count('log_date');
        $cntPastLogs = KpiDailyLog::whereIn('user_id', $saleIds)
            ->whereBetween('log_date', [$from, $to])
            ->where('log_date', '<=', today()->toDateString())
            ->count('log_date');
        $logCount = $dailyLogsCount > 0 ? $dailyLogsCount : 1;

        $sumCall           = $kpiDailyLogsQuery->sum('calls_count');
        $avgCall           = round($kpiDailyLogsQuery->sum('calls_count') / $logCount, 2);
        $sumCallNew        = $kpiDailyLogsQuery->sum('calls_new_count');
        $sumCallBack       = $kpiDailyLogsQuery->sum('calls_back_count');
        $sumCallResultBack = $kpiDailyLogsQuery->sum('calls_result_back_count');

        $sumAptm = $kpiDailyLogsQuery->sum('appointments_count');
        $avgAptm = round($kpiDailyLogsQuery->sum('appointments_count') / $logCount, 2);

        $sumAten  = $kpiDailyLogsQuery->sum('attended_count');
        $avgAten  = round($kpiDailyLogsQuery->sum('attended_count') / $logCount, 2);
        $sumAten1 = $kpiDailyLogsQuery->sum('attended_1_count');
        $sumAten2 = $kpiDailyLogsQuery->sum('attended_2_count');
        $sumAten3 = $kpiDailyLogsQuery->sum('attended_3_count');

        $sumReg  = $kpiDailyLogsQuery->sum('registrations_count');
        $avgReg  = round($kpiDailyLogsQuery->sum('registrations_count') / $logCount, 2);
        $sumComp = $kpiDailyLogsQuery->sum('companies_count');

        $sumPreIns    = $kpiDailyLogsQuery->sum('preinscriptions_count');
        $sumFinalDoss = $kpiDailyLogsQuery->sum('final_opco_dossiers_count');

        $cntLogs     = $logCount;
        $kpiDailyLogs = $kpiDailyLogsQuery->paginate(30)->withQueryString();

        // Income: lấy customers của tất cả sales trong $saleIds
        $targetSales = $isAll ? $sales : $sales->filter(fn($s) => $s->id == $saleId);
        $customerIds = $targetSales->loadMissing('customers')
            ->flatMap(fn($s) => $s->customers->pluck('id'))
            ->unique();

        $totalOpcoContracts = Contract::whereHas(
            'cart',
            fn($q) => $q->whereIn('user_id', $customerIds)
        )->where('organization', 'OPCO-EP')
            ->whereBetween('updated_at', [$from, $to])
            ->where('type', 'signed')
            ->count();

        $totalOpcoContractIncome = Contract::join('carts', 'contracts.cart_id', '=', 'carts.id')
            ->where('contracts.organization', 'OPCO-EP')
            ->where('contracts.type', 'signed')
            ->whereIn('carts.user_id', $customerIds)
            ->whereBetween('carts.updated_at', [$from, $to])
            ->sum('carts.price_total_ht');
        $totalOpcoContractIncome = round($totalOpcoContractIncome / 3 * 2, 2);

        $totalOpcoContractUser = Contract::join('carts', 'contracts.cart_id', '=', 'carts.id')
            ->join('cart_user', 'carts.id', '=', 'cart_user.cart_id')
            ->where('contracts.organization', 'OPCO-EP')
            ->where('contracts.type', 'signed')
            ->whereIn('carts.user_id', $customerIds)
            ->whereBetween('carts.updated_at', [$from, $to])
            ->distinct()
            ->count('cart_user.user_id');

        $totalFifplContracts = Contract::whereHas(
            'cart',
            fn($q) => $q->whereIn('user_id', $customerIds)
        )->where('organization', 'FIFPL')
            ->whereBetween('updated_at', [$from, $to])
            ->where('type', 'signed')
            ->count();

        $totalFifplContractIncome = Contract::join('carts', 'contracts.cart_id', '=', 'carts.id')
            ->where('contracts.organization', 'FIFPL')
            ->where('contracts.type', 'signed')
            ->whereIn('carts.user_id', $customerIds)
            ->whereBetween('carts.updated_at', [$from, $to])
            ->sum('carts.price_total_ht');

        return view('sales.dashboard', compact(
            'isSale',
            'isAll',
            'saleId',
            'sale',
            'sales',
            'currentKpi',
            'cntLogs',
            'cntPastLogs',
            'kpiDailyLogs',
            'sumCall',
            'avgCall',
            'sumAptm',
            'avgAptm',
            'sumAten',
            'avgAten',
            'sumReg',
            'avgReg',
            'sumComp',
            'sumCallNew',
            'sumCallBack',
            'sumCallResultBack',
            'sumAten1',
            'sumAten2',
            'sumAten3',
            'sumPreIns',
            'sumFinalDoss',
            'totalOpcoContracts',
            'totalOpcoContractIncome',
            'totalOpcoContractUser',
            'totalFifplContracts',
            'totalFifplContractIncome',
        ));
    }

    public function syncStaff(Request $request)
    {
        $saleId = $request->sale_id;
        $sale = User::find($saleId);
        if (!$sale || !$request->filled('to') || !$request->filled('from')) {
            return 'False';
        }

        /* CUSTOMER - SALE Assignment */
        // Date update
        $from = Carbon::parse($request->from)->startOfDay();
        $to = Carbon::parse($request->to)->endOfDay();

        // Update registed user + company
        $newCustomerIds = User::where('created_by', $sale->id)
            ->where('id', '!=', $sale->id)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('id');

        $newCompanyUserIds = Company::where('created_by', $sale->id)
            ->whereBetween('created_at', [$from, $to])
            ->with('users')
            ->get()
            ->flatMap(fn($company) => $company->users->pluck('id'));

        // Sync assignment
        $sale->customers()->syncWithoutDetaching(
            $newCustomerIds->merge($newCompanyUserIds)->unique()
        );

        // Update into report
        $customerEntries = User::where('created_by', $sale->id)
            ->where('id', '!=', $sale->id)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('id, DATE(created_at) as date')
            ->get()
            ->map(fn($u) => ['id' => $u->id, 'date' => $u->date]);

        $companyUserEntries = Company::where('created_by', $sale->id)
            ->whereBetween('created_at', [$from, $to])
            ->with('users')
            ->get()
            ->flatMap(fn($company) => $company->users->map(fn($user) => [
                'id'   => $user->id,
                'date' => Carbon::parse($user->created_at)->toDateString(),
            ]));

        $totalByDay = $customerEntries
            ->merge($companyUserEntries)
            ->unique('id')
            ->groupBy('date')
            ->map(fn($entries) => $entries->count());

        $pendingUpdates = [];
        foreach ($totalByDay as $date => $count) {
            $log = KpiDailyLog::whereDate('log_date', $date)->first();
            if (!$log) {
                $log = KpiDailyLog::whereDate('log_date', '>', $date)
                    ->orderBy('log_date', 'asc')
                    ->first();
            }
            if ($log) {
                $key = $log->log_date->toDateString();
                $pendingUpdates[$key] = ($pendingUpdates[$key] ?? 0) + $count;
            }
        }
        foreach ($pendingUpdates as $logDate => $totalCount) {
            $log = KpiDailyLog::whereDate('log_date', $logDate)->first();

            KpiDailyLog::whereDate('log_date', $logDate)->update([
                'registrations_count' => $totalCount
            ]);
        }

        /* COMPANY */
        $companiesByDay = Company::where('created_by', $sale->id)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        // Update report
        $pendingCompanyUpdates = [];
        foreach ($companiesByDay as $date => $count) {
            $log = KpiDailyLog::whereDate('log_date', $date)->first();

            if (!$log) {
                $log = KpiDailyLog::whereDate('log_date', '>', $date)
                    ->orderBy('log_date', 'asc')
                    ->first();
            }

            if ($log) {
                $key = $log->log_date->toDateString();
                $pendingCompanyUpdates[$key] = ($pendingCompanyUpdates[$key] ?? 0) + $count;
            }
        }
        foreach ($pendingCompanyUpdates as $logDate => $totalCount) {
            $log = KpiDailyLog::whereDate('log_date', $logDate)->first();

            KpiDailyLog::whereDate('log_date', $logDate)->update([
                'companies_count' => $totalCount
            ]);
        }


        return back()->withInput();
    }

    public function confirmStaffLog(Request $request, $id)
    {
        $log = KpiDailyLog::find($id);
        if ($log) {
            $log->status = 1;
            $log->save();
        }

        return back()->withInput();
    }

    public function staffCalendarIndex(Request $request)
    {
        $isSale = $request->user()->roles->contains('name', 'csr');
        if ($isSale) {
            return abort(403);
        }

        $sales = User::whereHas('roles', fn($q) => $q->where('name', 'csr'))->orderBy('name')->get();
        $selectedSale = $sales->firstWhere('id', $request->sale_id) ?? $sales->first();

        // 1. Tạo list $months: 3 tháng trước + hiện tại + 3 tháng sau
        $months = collect();
        for ($i = -3; $i <= 3; $i++) {
            $months->push(now()->startOfMonth()->addMonths($i)->format('m/Y'));
        }

        // 2. Lấy tháng được chọn, fallback về tháng hiện tại
        $selectedMonth = $request->month && $months->contains($request->month)
            ? $request->month
            : now()->format('m/Y');

        // Parse sang Carbon để lấy các ngày trong tháng
        $startOfMonth = \Carbon\Carbon::createFromFormat('m/Y', $selectedMonth)->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        // 3. Tạo $days: mỗi phần tử gồm date + tên thứ
        // Lấy logs hiện có để hiển thị checkbox
        $existingLogs = KpiDailyLog::where('user_id', $selectedSale?->id)
            ->whereBetween('log_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get()
            ->keyBy(fn($log) => \Carbon\Carbon::parse($log->log_date)->format('Y-m-d'));

        // Build $days
        $days     = collect();
        $dayNames = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

        for ($date = $startOfMonth->copy(); $date->lte($endOfMonth); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $log     = $existingLogs->get($dateStr);

            $days->push([
                'date'       => $dateStr,
                'day'        => (int) $date->format('d/m'),
                'day_name'   => $dayNames[$date->dayOfWeek],
                'day_of_week' => $date->dayOfWeek,
                'is_checked' => (bool) $log,
                'can_uncheck' => !$log || $log->status == 0, // false = checkbox bị lock
                'status'     => $log?->status,
            ]);
        }


        return view('sales.calendar', compact(
            'months',
            'selectedMonth',
            'days',
            'sales',
            'selectedSale',
        ));
    }

    public function syncStaffCalendar(Request $request)
    {
        $checkedDates = collect($request->input('days', [])); // ['Y-m-d', ...]
        $selectedMonth = $request->month;

        $startOfMonth = \Carbon\Carbon::createFromFormat('m/Y', $selectedMonth)->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        // Sales + selectedSale
        $sales        = User::whereHas('roles', fn($q) => $q->where('name', 'csr'))->orderBy('name')->get();
        $selectedSale = $sales->firstWhere('id', $request->sale_id);


        // Lấy tất cả ngày trong tháng
        $allDates = collect();
        for ($date = $startOfMonth->copy(); $date->lte($endOfMonth); $date->addDay()) {
            $allDates->push($date->format('Y-m-d'));
        }

        // Lấy logs hiện có của sale trong tháng
        $existingLogs = KpiDailyLog::where('user_id', $selectedSale->id)
            ->whereBetween('log_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get()
            ->keyBy(fn($log) => \Carbon\Carbon::parse($log->log_date)->format('Y-m-d'));

        foreach ($allDates as $dateStr) {
            $isChecked   = $checkedDates->contains($dateStr);
            $existingLog = $existingLogs->get($dateStr);

            if ($isChecked && !$existingLog) {
                // Chưa có → thêm mới
                KpiDailyLog::create([
                    'user_id'  => $selectedSale->id,
                    'log_date' => $dateStr,
                    'status'   => 0,
                ]);
            } elseif (!$isChecked && $existingLog) {
                // Đã có nhưng bỏ chọn → chỉ xoá nếu status == 0
                if ($existingLog->status == 0 && $existingLog->calls_count == 0) {
                    $existingLog->delete();
                }
            }
        }

        return back()->withInput();
    }

    public function editStaffDailyLog(Request $request, $id)
    {
        $log = KpiDailyLog::find($id);
        if (!$log) {
            return abort(404);
        }
        $isSale = $request->user()->roles->contains('name', 'csr');
        if ($isSale && $request->user()->id !== $log->user_id) {
            return abort(403);
        }
        if ($isSale && $log->status != '0') {
            return abort(403);
        }

        return view('sales.log-edit', compact(
            'log'
        ));
    }

    public function updateStaffDailyLog(Request $request, $id)
    {
        $log = KpiDailyLog::find($id);
        if (!$log) {
            return abort(404);
        }

        $log->calls_new_count = $request->calls_new_count;
        $log->calls_back_count = $request->calls_back_count;
        $log->calls_count = $request->calls_new_count + $request->calls_back_count;
        $log->calls_result_back_count = $request->calls_result_back_count;
        $log->appointments_count = $request->appointments_count;

        $log->attended_count = $request->attended_count;
        $log->attended_1_count = $request->attended_1_count;
        $log->attended_2_count = $request->attended_2_count;
        $log->attended_3_count = $request->attended_3_count;

        $log->preinscriptions_count = $request->preinscriptions_count;
        $log->final_opco_dossiers_count = $request->final_opco_dossiers_count;

        $log->save();
        return back();
    }

    public function index()
    {
        $authUser = Auth::user();
        // $isAdmin  = $authUser->hasPermission('edit_admins');
        // if (!$isAdmin) {
        //     return redirect()->route('telesales.dashboard');
        // }

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
