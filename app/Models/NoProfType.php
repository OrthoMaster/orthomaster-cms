<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoProfType extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active_flg' => 'boolean'
    ];

    public static function getAll() {
        return NoProfType::where('active_flg', true)
            ->orderBy('order_dsp')
            ->orderBy('name')
            ->get();
    }
}
