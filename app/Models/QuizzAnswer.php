<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizzAnswer extends Model
{
    protected $table = 'quizz_answers';

    protected $fillable = ['question_id', 'content', 'score', 'order'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizzQuestion::class, 'question_id');
    }
}