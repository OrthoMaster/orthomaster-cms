<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'published_flg' => 'boolean',
        'demo_flg' => 'boolean'
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function blocks()
    {
        return $this->hasMany(Block::class);
    }

    public function scopePublished($query)
    {
        return $query->where('published_flg', true);
    }

    public static function findById($id)
    {
        return Lesson::where('id', $id)
            ->with(['module'])
            ->first();
    }

    public static function getByModuleId($moduleId)
    {
        return Lesson::where('module_id', $moduleId)
            ->orderBy('order_dsp')
            ->orderBy('id')
            ->get();
    }

    public static function getByContentId($contentId) {
        return Lesson::select('id', 'title', 'published_flg')
            ->where('content_id', $contentId)
            ->first();
    }

    public static function findByIdWithPaginatedContent($id, $page = 1)
    {
        $lesson = Lesson::where('id', $id)->with('blocks')->first();

        // If not exist
        if (!$lesson || (sizeof($lesson->blocks) <= 0)) {
            return [
                'success' => false,
                'message' => "Leçon non trouvée ou sans contenu."
            ];
        }

        // If not published
        if (!$lesson->published_flg) {
            return [
                'success' => true,
                'content' => [
                    'lesson' => [
                        'id' => $lesson->id,
                        'published_flg' => $lesson->published_flg
                    ],
                    'blocks' => [],
                ],
                'message' => "Leçon non trouvée."
            ];
        }

        // Parse JSON string thành array
        $contentData = json_decode($lesson->blocks->first()->content, true);

        // Kiểm tra parse có thành công không
        if (json_last_error() !== JSON_ERROR_NONE || !isset($contentData['blocks'])) {
            return [
                'success' => false,
                'message' => 'Invalid content format'
            ];
        }

        // Get all blocks
        $allBlocks = $contentData['blocks'];
        $totalBlocks = count($allBlocks);

        // Pagination logic
        $limit = $lesson->block_limit ?? 5;
        $startIndex = ($page - 1) * $limit;
        $endIndex = $startIndex + $limit;
        $paginatedBlocks = array_slice($allBlocks, $startIndex, $limit);

        // Calculate pagination info
        $totalPages = ceil($totalBlocks / $limit);

        return [
            'success' => true,
            'content' => [
                'lesson' => [
                    'id' => $lesson->id,
                    'published_flg' => $lesson->published_flg
                ],
                'blocks' => $paginatedBlocks,
            ],
            'pagination' => [
                'currentPage' => $page,
                'limit' => $limit,
                'totalBlocks' => $totalBlocks,
                'totalPages' => $totalPages,
                'hasMore' => $endIndex < $totalBlocks,
                'isFirstPage' => $page === 1,
                'isLastPage' => $endIndex >= $totalBlocks
            ]
        ];
    }
}
