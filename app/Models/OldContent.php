<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;

class OldContent extends Model
{
    use Searchable;

    protected $table = 'content';
    protected $primaryKey = 'id_content';
    protected $guarded = ['id_content'];
    protected $appends = ['thumbnail'];
    public $timestamps = false;

    #[SearchUsingFullText(['title', 'description', 'author', 'tags'])]
    public function toSearchableArray(): array
    {
        return [
            'title'       => $this->title,
            'description' => $this->description,
            'author'      => $this->author,
            'tags'        => $this->tags,
        ];
    }

    public function video()
    {
        return $this->hasOne(Video::class);
    }

    public function getThumbnailAttribute()
    {
        if ($this->version !== 'v2') {
            $originalPath = $this->s3_path . "/" . $this->title . "/logo.png";
            return Storage::disk('s3_root')->url($originalPath);
        }
        
        if ($this->video->thumbnail) {
            $s3ImagePath = 'https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/';
            return $s3ImagePath . $this->video->thumbnail;
        }
        
        return null;
    }
}