<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrendingKeyword extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'search_count' => 'integer',
    ];
}
