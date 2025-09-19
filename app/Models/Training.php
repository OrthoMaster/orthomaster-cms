<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $guarded = ['id'];

    public function blocks()
    {
        return $this->hasMany(Block::class);
    }
}
