<?php

namespace App\Jobs;

use App\Models\RptSystemControls;
use App\Models\TrkAppOpens;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GenerateUserAgentsReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct() {}

    public function handle(): void
    {
        $exists = RptSystemControls::where('type', 'user-agents')->exists();

        $regIos = 'iPhone|iPad|iPod|CFNetwork|Darwin|ios';
        $regAndroid = 'Android|okhttp';
        $regWeb = $regIos . '|' . $regAndroid;

        if (!$exists) {
            $counts = [
                'ios'     => TrkAppOpens::where('user_agent', 'REGEXP', $regIos)->count(),
                'android' => TrkAppOpens::where('user_agent', 'REGEXP', $regAndroid)->count(),
                'web'     => TrkAppOpens::whereRaw("user_agent NOT REGEXP ?", [$regWeb])->count(),
            ];
        } else {
            $yesterday = Carbon::yesterday()->toDateString();
            $counts = [
                'ios'     => TrkAppOpens::whereDate('created_at', $yesterday)->where('user_agent', 'REGEXP', $regIos)->count(),
                'android' => TrkAppOpens::whereDate('created_at', $yesterday)->where('user_agent', 'REGEXP', $regAndroid)->count(),
                'web'     => TrkAppOpens::whereDate('created_at', $yesterday)->whereRaw("user_agent NOT REGEXP ?", [$regWeb])->count(),
            ];
        }

        foreach ($counts as $label => $count) {
            $report = RptSystemControls::firstOrNew([
                'type'  => 'user-agents',
                'label' => $label,
            ]);

            $report->data_int = ($report->data_int ?? 0) + $count;
            $report->save();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('api')->error('GenerateUserAgentsReportJob failed', [
            'message' => $exception->getMessage(),
        ]);
    }
}
