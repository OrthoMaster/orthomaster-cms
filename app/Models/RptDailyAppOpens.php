<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RptDailyAppOpens extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'count' => 'integer',
        'total_users' => 'integer',
    ];
}
