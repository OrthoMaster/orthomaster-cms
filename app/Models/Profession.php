<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profession extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'child_flg' => 'boolean',
        'active_flg' => 'boolean'
    ];

    public function children()
    {
        return $this->hasMany(Profession::class, 'parent_id');
    }

    public function hasChildren() {
        return $this->children()->exists();
    }

    public static function getAll() {
        return Profession::where('active_flg', true)
            ->orderBy('order_dsp')
            ->orderBy('name')
            ->get();
    }
}
