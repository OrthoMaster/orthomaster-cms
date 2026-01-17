<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchHistory extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'search_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
