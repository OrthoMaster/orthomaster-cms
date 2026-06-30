<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizzTopic extends Model
{
    protected $table = 'quizz_topics';

    protected $fillable = [
        'name', 'slug', 'description', 'time_per_question', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizzQuestion::class, 'topic_id')->orderBy('order');
    }

    public function scoreRanges(): HasMany
    {
        return $this->hasMany(QuizzScoreRange::class, 'topic_id')->orderBy('min_score');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(QuizzSubmission::class, 'topic_id');
    }

    /**
     * Total maximum possible : somme du score le plus élevé de chaque question.
     */
    public function maxPossibleScore(): int
    {
        return $this->questions->sum(
            fn ($q) => $q->answers->max('score') ?? 0
        );
    }
}