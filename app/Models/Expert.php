<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Expert extends Model
{
    protected $guarded = ['id'];

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn() => $this->avatar
            ? Storage::disk('s3')->url($this->avatar)
            : null);
    }

    public function videos()
    {
        return $this->belongsToMany(Video::class);
    }
}
