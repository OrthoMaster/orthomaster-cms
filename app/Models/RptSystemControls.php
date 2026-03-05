<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RptSystemControls extends Model
{
    protected $table = 'rpt_system_controls';
    protected $guarded = ['id'];

    protected $casts = [
        'data_int' => 'integer',
        'data_float' => 'decimal',
    ];
}
