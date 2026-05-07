<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'images' => 'array',
        'description_tabs' => 'array',
    ];

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function filters()
    {
        return $this->hasMany(ProductFilter::class);
    }

    /**
     * Sync filter keys for this product (delete + insert in a transaction).
     * Pass an array of strings: ['mem-inf-gen', 'rhumato', ...]
     */
    public function syncFilters(array $keys): void
    {
        $this->filters()->delete();
        if (!empty($keys)) {
            $rows = array_map(fn ($k) => ['filter_key' => $k], array_unique($keys));
            $this->filters()->createMany($rows);
        }
    }

    /**
     * Products matching ANY of the given filter keys (OR semantics).
     * Usage: Product::matchingAny(['mem-inf-gen', 'rhumato'])->get();
     */
    public function scopeMatchingAny(Builder $query, array $keys): Builder
    {
        if (empty($keys)) {
            return $query;
        }
        return $query->whereHas('filters', function (Builder $q) use ($keys) {
            $q->whereIn('filter_key', $keys);
        });
    }
 
    /**
     * Products matching ALL of the given filter keys (AND semantics).
     * A product must have every key in the list.
     * Usage: Product::matchingAll(['mem-inf-gen', 'rhumato'])->get();
     */
    public function scopeMatchingAll(Builder $query, array $keys): Builder
    {
        if (empty($keys)) {
            return $query;
        }
        $count = count(array_unique($keys));
        return $query
            ->whereHas('filters', function (Builder $q) use ($keys) {
                $q->whereIn('filter_key', $keys);
            }, '=', $count);
    }
}
