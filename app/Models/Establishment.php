<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Establishment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active_flg' => 'boolean'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'establishment_id', 'id');
    }

    public static function getAll() {
        return Establishment::where('active_flg', true)
            ->orderBy('order_dsp')
            ->orderBy('name')
            ->get();
    }
}
