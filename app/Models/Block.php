<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Block extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public static function getFirstByLesson($lessonId) {
        $blocks = Block::where('lesson_id', $lessonId)->get();
        if (sizeof($blocks) > 0) {
            return $blocks->first();
        }

        return Block::create([
            'lesson_id' => $lessonId
        ]);
    }
}
