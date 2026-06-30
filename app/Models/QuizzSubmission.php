<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizzSubmission extends Model
{
    protected $table = 'quizz_submissions';

    protected $fillable = [
        'topic_id', 'user_name', 'email', 'allow_contact',
        'raw_score', 'max_possible_score', 'final_score', 'completed_at',
    ];

    protected $casts = [
        'allow_contact' => 'boolean',
        'completed_at'  => 'datetime',
        'final_score'   => 'decimal:2',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(QuizzTopic::class, 'topic_id');
    }

    public function submissionAnswers(): HasMany
    {
        return $this->hasMany(QuizzSubmissionAnswer::class, 'submission_id');
    }
}