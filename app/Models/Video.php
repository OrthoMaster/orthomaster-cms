<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $guarded = ['id'];

    protected $casts = [];

    public function experts()
    {
        return $this->belongsToMany(Expert::class);
    }

    public static function findByCode($code) {
        return Video::where('code', $code)->with('experts')->first();
    }
}
