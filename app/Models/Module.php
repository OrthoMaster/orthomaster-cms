<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $guarded = ['id'];

    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('order_dsp');
    }

    public function scopeActive($query)
    {
        return $query->where('active_flg', true);
    }
}
