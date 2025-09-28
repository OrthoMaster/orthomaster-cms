<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $guarded = ['id'];

    public function training() {
        return $this->belongsTo(Training::class);
    }

    public function blocks()
    {
        return $this->hasMany(Block::class);
    }

    public static function findById($id) {
        return Lesson::where('id', $id)->with('training')->first();
    }

    public static function getByTrainingId($trainingId) {
        return Lesson::where('training_id', $trainingId)
            ->orderBy('order_dsp')
            ->orderBy('id')
            ->get();
    }
}
