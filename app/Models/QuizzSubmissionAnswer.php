<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizzSubmissionAnswer extends Model
{
    protected $table = 'quizz_submission_answers';

    protected $fillable = [
        'submission_id', 'question_id', 'answer_id', 'score_earned',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(QuizzSubmission::class, 'submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizzQuestion::class, 'question_id');
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(QuizzAnswer::class, 'answer_id');
    }
}