<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;

class APILessonController extends Controller
{
    public function findByContentId(Request $request, $contentId) {
        $lesson = Lesson::getByContentId($contentId);
        if ($lesson && $lesson->published_flg) {
            return response()->json([
                'success' => true,
                'data' => $lesson
            ], 200);
        }

        return response()->json(['success' => false], 404); 
    }

    /**
     * Get lesson paginated content
     */
    public function getContent(Request $request, $id)
    {
        $page = $request->query('page', 1);
        $result = Lesson::findByIdWithPaginatedContent($id, $page);

        try {
            $statusCode = $result['success'] ? 200 : 404;
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e,
                'message' => "Erreur interne du serveur. Veuillez réessayer plus tard."
            ], 500);
        }
    }
}
