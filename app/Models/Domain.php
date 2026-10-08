<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $guarded = ['id'];

    public function experts()
    {
        return $this->belongsToMany(Expert::class)->withPivot('position');
    }
}
