<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Get content of media
     */
    public function getContent(Request $request)
    {
        if (!$request->has('contentId')) {
            return response()->json([
                'success' => false,
            ]);
        };

        try {
            $content = DB::table('content')
                ->where('id_content', $request->contentId)
                ->where('type', 'flash')
                ->first();

            $direction = $content->s3_path . "/" . $content->title . "/content.xml";
            $xmlContent = Storage::disk('s3_root')->get($direction);

            $baseDirectory = $content->s3_path . "/" . $content->title;
            $trainingController = new TrainingController;
            $parsedContent = $trainingController->processXmlImages($xmlContent, $baseDirectory);

            $content->xml_content = $parsedContent;

            return response()->json([
                'success' => true,
                'data' => $content ?? null
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['media-content']);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    /**
     * Get content of tool
     */
    public function getToolContent(Request $request)
    {
        if (!$request->has('contentId')) {
            return response()->json([
                'success' => false,
            ]);
        };

        try {
            $content = DB::table('content')
                ->where('id_content', $request->contentId)
                ->where('type', 'tool')
                ->first();

            $direction = $content->s3_path . "/" . $content->title . "/content.xml";
            $xmlContent = Storage::disk('s3_root')->get($direction);

            $baseDirectory = $content->s3_path . "/" . $content->title;
            $trainingController = new TrainingController;
            $parsedContent = $trainingController->processXmlImages($xmlContent, $baseDirectory);

            $content->xml_content = $parsedContent;

            return response()->json([
                'success' => true,
                'data' => $content ?? null
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['media-content']);
            return response()->json([
                'success' => false,
            ]);
        }
    }
}
