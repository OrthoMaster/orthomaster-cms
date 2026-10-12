<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpertBrochureDraft extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data'         => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function expert()
    {
        return $this->belongsTo(Expert::class);
    }

    /** Le brouillon a-t-il été copié dans experts.brochure depuis sa dernière modification ? */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}