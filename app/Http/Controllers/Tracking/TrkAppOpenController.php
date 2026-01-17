<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Odoo\OdooAccountController;
use App\Models\RptDailyAppOpens;
use App\Models\TrkAppOpens;
use App\Services\OdooService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrkAppOpenController extends Controller
{
    public function store(Request $request)
    {
        if (!$request->token) {
            return response()->json([], 400);
        }

        $result = $this->tracking($request->token, $request->agent);
        return response()->json([
            'success' => $result
        ], 200);
    }

    public function tracking($token, $agent)
    {
        $date = Carbon::now()->toDateString();
        if (!$token) {
            return false;
        }

        try {
            $created = TrkAppOpens::firstOrCreate(
                ['token' => $token, 'tracking_date' => $date],
                ['user_agent' => $agent]
            );

            if ($created->wasRecentlyCreated) {
                $report = RptDailyAppOpens::firstOrNew(['date' => $date]);
                $report->increment('count');

                // Get total users from Odoo
                if (!$report->total_users > 0) {
                    $odooAccountController = new OdooAccountController(app(OdooService::class));
                    $report->total_users = $odooAccountController->countTotalActiveUser();
                    $report->save();
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['tracking-open-app']);
            return false;
        }
    }
}
