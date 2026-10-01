<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Lesson;
use App\Models\OldContent;
use App\Models\SearchHistory;
use App\Models\TrendingKeyword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if (!$request->has('keyword')) {
            return response()->json([
                'success' => true,
                'data' => ['keyword' => '', 'results' => null],
            ]);
        }

        // normalize inline
        $keyword = str_replace(['’', '‘', '´', '`'], "'", $request->keyword);
        $keyword = str_replace("\u{00A0}", ' ', $keyword);
        $keyword = trim(preg_replace('/\s+/u', ' ', $keyword));

        if ($keyword === '') {
            return response()->json([
                'success' => true,
                'data' => ['keyword' => '', 'results' => null],
            ]);
        }

        $userId      = $request->user()->id;
        $lessonsPage = max(1, (int) $request->input('lessons_page', 1));
        $videosPage  = max(1, (int) $request->input('videos_page', 1));
        $perPage     = 6;

        // ===== Khối 1: lưu keyword — lỗi ở đây KHÔNG được làm chết search
        if ($lessonsPage === 1 && $videosPage === 1) {
            try {
                DB::transaction(function () use ($userId, $keyword) {
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

                    $trendingKeyword = TrendingKeyword::firstOrNew([
                        'keyword' => $keyword,
                    ]);
                    if ($trendingKeyword->exists) {
                        $trendingKeyword->increment('search_count');
                    } else {
                        $trendingKeyword->search_count = 1;
                        $trendingKeyword->save();
                    }
                }, 3);
            } catch (\Throwable $e) {
                Log::channel('api')->warning('save keyword failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'keyword'   => $keyword,
                ]);
            }
        }

        // ===== Khối 2: search — read-only, không transaction
        try {
            $categories = ['all', 'lesson', 'video'];
            $category = in_array($request->category, $categories) ? $request->category : null;

            $results = ['lessons' => null, 'videos' => null];

            // --- Lessons
            if (!$category || $category === 'all' || $category === 'lesson') {
                $escaped = addcslashes($keyword, '%_\\');

                $lessons = Lesson::with(['module', 'module.package', 'module.package.training'])
                    ->published()
                    ->where('title', 'LIKE', '%' . $escaped . '%')
                    ->paginate($perPage, ['*'], 'lessons_page', $lessonsPage);

                $results['lessons'] = [
                    'data'         => $lessons->items(),
                    'current_page' => $lessons->currentPage(),
                    'total'        => $lessons->total(),
                    'per_page'     => $lessons->perPage(),
                    'has_more'     => $lessons->hasMorePages(),
                ];
            }

            // Search Videos
            if (!$category || $category === 'all' || $category === 'video') {

                $eventIds = Event::search($keyword)->take(500)->keys()->toArray();

                $isAdmin = in_array($request->user()->role->name, ['admin', 'directeur', 'manager']);

                // Scout trả về theo thứ tự relevance giảm dần
                $scout = OldContent::search($keyword)->where('type', 'flash');
                if (!$isAdmin) {
                    $scout->where('published', '1');
                }
                $videoIdsFromKeyword = $scout->take(500)->keys()->toArray();

                // Video thuộc các event khớp keyword
                $videoIdsFromEvents = [];
                if (!empty($eventIds)) {
                    $videoIdsFromEvents = OldContent::where('type', 'flash')
                        ->whereIn('event_id', $eventIds)
                        ->orderByDesc('id_content')
                        ->pluck('id_content')
                        ->toArray();
                }

                // merge: khớp keyword đứng trước, khớp qua event đứng sau
                // array_unique giữ lần xuất hiện ĐẦU TIÊN -> video vừa khớp keyword vừa
                // thuộc event sẽ giữ nguyên vị trí relevance cao của nó
                $allVideoIds = array_values(array_unique(
                    array_merge($videoIdsFromKeyword, $videoIdsFromEvents)
                ));

                $videosQuery = OldContent::where('type', 'flash')
                    ->select('content.*')
                    ->selectRaw('content.author as expert_names')
                    ->whereIn('id_content', $allVideoIds)
                    ->when(!$isAdmin, fn($q) => $q->where('published', '1'));

                // ép DB sort đúng theo thứ tự mảng $allVideoIds
                if (!empty($allVideoIds)) {
                    $placeholders = implode(',', array_fill(0, count($allVideoIds), '?'));
                    $videosQuery->orderByRaw("FIELD(id_content, {$placeholders})", $allVideoIds);
                }

                $videos = $videosQuery->paginate($perPage, ['*'], 'videos_page', $videosPage);

                $results['videos'] = [
                    'data'         => $videos->items(),
                    'current_page' => $videos->currentPage(),
                    'total'        => $videos->total(),
                    'per_page'     => $videos->perPage(),
                    'has_more'     => $videos->hasMorePages(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => ['keyword' => $keyword, 'results' => $results],
            ]);
        } catch (\Throwable $e) {
            Log::channel('api')->error('search failed: ' . $e->getMessage(), [
                'exception' => $e,
                'keyword'   => $keyword,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
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
