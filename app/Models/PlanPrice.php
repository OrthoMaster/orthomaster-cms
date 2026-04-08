<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanPrice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price'          => 'decimal:2',
        'original_price' => 'decimal:2',
        'active_flg'      => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
