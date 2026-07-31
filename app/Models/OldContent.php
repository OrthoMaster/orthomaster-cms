<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class OldContent extends Model
{
    protected $table = 'content';
    protected $primaryKey = 'id_content';

    protected $guarded = ['id_content'];
    protected $appends = ['thumbnail'];

    public $timestamps = false;

    public function video()
    {
        return $this->hasOne(Video::class);
    }

    public function getThumbnailAttribute()
    {
        $originalPath = $this->s3_path . "/" . $this->title . "/logo.png";
        return Storage::disk('s3_root')->url($originalPath);
    }
}
