<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['has_children', 'label'];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Topic::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Topic::class, 'parent_id')
            ->where('active_flg', '1')
            ->orderBy('order_dsp');
    }

    public function getHasChildrenAttribute(): bool
    {
        return $this->relationLoaded('children') && $this->children->isNotEmpty();
    }

    public function getLabelAttribute(): string
    {
        if (!$this->parent_id) {
            return $this->name;
        }

        $parent = $this->relationLoaded('parent')
            ? $this->parent
            : $this->parent()->first();

        return $parent
            ? $parent->name . ' · ' . $this->name
            : $this->name;
    }

    public static function getAll()
    {
        return Topic::where('active_flg', '1')
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('order_dsp')
            ->get();
    }
}
