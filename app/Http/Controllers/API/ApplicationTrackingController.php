<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApplicationTrackingController extends Controller
{
    public function errorTracking(Request $request) {
        Log::channel('api')->error(strval($request->error), [$request->code, $request->user()->id]);
    }
}
