<?php

namespace App\Jobs;

use App\Http\Controllers\Odoo\OdooAccountController;
use App\Models\RptDailyAppOpens;
use App\Models\TrkAppOpens;
use App\Services\OdooService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GenerateDailyAppOpensReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct() {}

    public function handle(): void
    {
        $yesterday = Carbon::yesterday()->toDateString();
        $today = Carbon::now()->toDateString();

        // Yesterday
        $yesterdayReport = RptDailyAppOpens::firstOrNew(['date' => $yesterday]);
        if (!$yesterdayReport->total_users > 0) {
            $odooAccountController = new OdooAccountController(app(OdooService::class));
            $yesterdayReport->total_users = $odooAccountController->countTotalActiveUser();
        }
        $yesterdayReport->count = TrkAppOpens::whereDate('created_at', $yesterday)->count();
        $yesterdayReport->save();

        // Today
        $todayReport = RptDailyAppOpens::firstOrNew(['date' => $today]);
        if (!$todayReport->total_users > 0) {
            $odooAccountController = $odooAccountController ?? new OdooAccountController(app(OdooService::class));
            $todayReport->total_users = $odooAccountController->countTotalActiveUser();
        }
        $todayReport->count = TrkAppOpens::whereDate('created_at', $today)->count();
        $todayReport->save();
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('api')->error('GenerateDailyAppOpensReportJob failed', [
            'message' => $exception->getMessage(),
        ]);
    }
}
