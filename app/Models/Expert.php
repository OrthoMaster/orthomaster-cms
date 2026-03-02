<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expert extends Model
{
    protected $guarded = ['id'];

    public function videos()
    {
        return $this->belongsToMany(Video::class);
    }
}
