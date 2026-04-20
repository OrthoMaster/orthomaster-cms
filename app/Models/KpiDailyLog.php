<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiDailyLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'log_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
