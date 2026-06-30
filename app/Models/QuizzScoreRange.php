<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizzScoreRange extends Model
{
    protected $table = 'quizz_score_ranges';

    protected $fillable = [
        'topic_id', 'min_score', 'max_score', 'label', 'title', 'comment', 'color',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(QuizzTopic::class, 'topic_id');
    }
}