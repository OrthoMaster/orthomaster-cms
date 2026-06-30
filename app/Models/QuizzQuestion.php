<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizzQuestion extends Model
{
    protected $table = 'quizz_questions';

    protected $fillable = ['topic_id', 'content', 'image', 'order'];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(QuizzTopic::class, 'topic_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizzAnswer::class, 'question_id')->orderBy('order');
    }
}