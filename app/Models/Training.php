<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $guarded = ['id'];

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }
}
