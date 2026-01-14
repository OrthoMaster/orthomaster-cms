<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyQuestion extends Model
{
    protected $guarded = ['id'];

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function answers()
    {
        return $this->hasMany(DailyQuestionAnswer::class);
    }

    public function userAnswer($userId)
    {
        return $this->hasOne(DailyQuestionAnswer::class)
            ->whereHas('users', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            });
    }
}
