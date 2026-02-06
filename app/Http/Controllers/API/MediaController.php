<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MediaController extends Controller
{
    public function getEvents(Request $request) {
        try {
            $events = Event::get();

            return response()->json([
                'success' => true,
                'data' => $events
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['media', 'get-events']);
            return response()->json([
                'success' => false,
            ]);
        }
    }
}
