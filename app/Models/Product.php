<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'images'           => 'array',
        'description_tabs' => 'array',
        'sizes'            => 'array',
        'groups'           => 'array'
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
     * Sync filter keys (delete + insert).
     *
     * `product_filters` is now PATHOLOGY ONLY — measurement bucket
     * matching is handled separately via product.sizes[].measurements
     * numeric range overlap (see FilterRulesService::measurementMatch).
     * The admin controller no longer merges size-derived keys here.
     */
    public function syncFilters(array $keys): void
    {
        $this->filters()->delete();
        if (!empty($keys)) {
            $rows = array_map(fn ($k) => ['filter_key' => $k], array_unique($keys));
            $this->filters()->createMany($rows);
        }
    }

    public function scopeMatchingAny(Builder $query, array $keys): Builder
    {
        if (empty($keys)) return $query;
        return $query->whereHas('filters', function (Builder $q) use ($keys) {
            $q->whereIn('filter_key', $keys);
        });
    }

    public function scopeMatchingAll(Builder $query, array $keys): Builder
    {
        if (empty($keys)) return $query;
        $count = count(array_unique($keys));
        return $query->whereHas('filters', function (Builder $q) use ($keys) {
            $q->whereIn('filter_key', $keys);
        }, '=', $count);
    }
}