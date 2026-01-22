<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\OldContent;
use App\Models\SearchHistory;
use App\Models\TrendingKeyword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SearchController extends Controller
{
    /**
     * Get search history and trending keywords
     */
    public function getPreSearch(Request $request)
    {
        $userId = $request->user()->id;
        $limit = 7;
        $trendLimit = 5;

        $histories = SearchHistory::where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get(['id', 'keyword', 'search_count', 'updated_at']);

        $trending = TrendingKeyword::orderByDesc('search_count')
            ->limit($trendLimit)
            ->get(['id', 'keyword', 'search_count']);

        $userPackages = $request->user()->packages()->get()->keyBy('id');
        $userPackageIds = $userPackages->keys()->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'histories' => $histories,
                'trending' => $trending,
                'activePackageIds' => $userPackageIds
            ]
        ]);
    }

    /**
     * Search and store keyword
     */
    public function search(Request $request)
    {
        $results = null;
        if (!$request->has('keyword')) {
            return response()->json([
                'success' => true,
                'data' => [
                    'keyword' => '',
                    'results' => $results,
                ]
            ]);
        }

        $keyword = trim($request->keyword);
        $keyword = mb_check_encoding($keyword, 'UTF-8') ? $keyword : mb_convert_encoding($keyword, 'UTF-8', 'UTF-8');;

        $userId = $request->user()->id;

        DB::beginTransaction();
        try {
            // Save keyword
            $searchHistory = SearchHistory::firstOrNew([
                'user_id' => $userId,
                'keyword' => $keyword,
            ]);

            if ($searchHistory->exists) {
                $searchHistory->increment('search_count');
            } else {
                $searchHistory->search_count = 1;
                $searchHistory->save();
            }

            // Save trending keyword
            $trendingKeyword = TrendingKeyword::firstOrNew([
                'keyword' => $keyword,
            ]);

            if ($trendingKeyword->exists) {
                $trendingKeyword->increment('search_count');
            } else {
                $trendingKeyword->search_count = 1;
                $trendingKeyword->save();
            }

            DB::commit();

            // Search 
            $categories = ['all', 'lesson', 'media'];
            $category = $request->category;
            if (!in_array($category, $categories)) {
                $category = null;
            }

            $lessonsPage = (int) $request->input('lessons_page', 1);
            $videosPage = (int) $request->input('videos_page', 1);
            $perPage = 5;

            $results = [
                'lessons' => null,
                'videos' => null,
            ];

            // Search Lessons
            if (!$category || $category === 'all' || $category === 'lesson') {
                $lessonsQuery = Lesson::with(['module', 'module.package', 'module.package.training'])
                    ->published()
                    ->where(function ($query) use ($keyword) {
                        $query->where('title', 'LIKE', '%' . $keyword . '%');
                    });

                $lessons = $lessonsQuery->paginate($perPage, ['*'], 'lessons_page', $lessonsPage);

                $results['lessons'] = [
                    'data' => $lessons->items(),
                    'current_page' => $lessons->currentPage(),
                    'total' => $lessons->total(),
                    'per_page' => $lessons->perPage(),
                    'has_more' => $lessons->hasMorePages(),
                ];
            }

            // Search Videos
            // TODO: Change
            if (!$category || $category === 'all' || $category === 'video') {
                $videosQuery = OldContent::where('type', 'flash')
                    ->where('title', 'LIKE', '%' . $keyword . '%');

                $videos = $videosQuery->paginate($perPage, ['*'], 'videos_page', $videosPage);
                $results['videos'] = [
                    'data' => $videos->items(),
                    'current_page' => $videos->currentPage(),
                    'total' => $videos->total(),
                    'per_page' => $videos->perPage(),
                    'has_more' => $videos->hasMorePages(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'keyword' => $keyword,
                    'results' => $results,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('api')->error($e->getMessage(), ['search-content']);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], 500);
        }
    }

    /**
     * Delete 1 keyword
     */
    public function deleteKeyword(Request $request, $id)
    {
        $deleted = SearchHistory::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->delete();

        if ($deleted) {
            return response()->json([
                'success' => true,
            ]);
        }

        return response()->json([
            'success' => false,
        ]);
    }

    /**
     * Clear all search history from user
     */
    public function clearHistory(Request $request)
    {
        SearchHistory::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Check the lesson can be opened
     */
    public function checkOpenableLesson(Request $request)
    {
        if ($request->user()->student_flg) {
            return response()->json([
                'success' => true,
            ]);
        }

        if (!$request->has('lessonId')) {
            return response()->json([
                'success' => false,
            ]);
        }

        $lesson = Lesson::where('id', $request->lessonId)
            ->with(['module', 'module.package', 'module.package.training'])
            ->first();
        if (!$lesson) {
            return response()->json([
                'success' => false,
            ]);
        }

        $user = $request->user();
        $userPackages = $user->packages()->get()->keyBy('id');
        $userPackageIds = $userPackages->keys()->toArray();

        return response()->json([
            'success' => in_array($lesson->module->package->id, $userPackageIds),
        ]);
    }

    /**
     * Get S3 Url base on direction
     */
    public function getS3Url(Request $request)
    {
        if (!$request->has('content')) {
            return response()->json([
                'success' => false
            ]);
        };

        $direction = $request->content;
        try {
            $url = Storage::disk('s3_root')->url($direction);

            return response()->json([
                'success' => true,
                'url' => $url
            ]);
        } catch (\Exception $error) {
            Log::channel('api')->emergency($error, ['s3-url']);
            return response()->json([
                'success' => false
            ]);
        }
    }
}
