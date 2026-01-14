<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyQuestionAnswer extends Model
{
    protected $guarded = ['id'];
    protected $appends = ['percentage'];

    public function getPercentageAttribute()
    {
        if ($this->question->count) {
            return (int) ($this->count / $this->question->count * 100);
        }

        return 0;
    }

    public function question()
    {
        return $this->belongsTo(DailyQuestion::class, 'daily_question_id', 'id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }
}
