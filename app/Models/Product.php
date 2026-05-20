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
     * Extract distinct, non-empty filter_key values from the sizes JSON.
     * Used by the admin controller to merge size-derived keys into the
     * product_filters table — so a product with a size mapped to
     * "poignet-m" automatically matches the "poignet-m" pathology filter.
     *
     * New schema (nested):
     *   {
     *     "dimensions": [
     *       {
     *         "name": "Tour de taille",
     *         "filter_node": "mesure_taille",
     *         "options": [
     *           {
     *             "label": "M",
     *             "range": "Var.",
     *             "filter_keys": ["taille-m"],          // simple match
     *             "variants": [                          // OR conditional
     *               { "filter_keys": ["taille-m", "homme"], "range": "32-36 cm" },
     *               { "filter_keys": ["taille-s", "femme"], "range": "20 cm"   }
     *             ]
     *           }
     *         ]
     *       }
     *     ]
     *   }
     *
     * @return array<int, string>
     */
    public function sizeFilterKeys(): array
    {
        $sizes = $this->sizes ?? [];
        if (!is_array($sizes)) return [];
 
        $keys = [];
        $dims = $sizes['dimensions'] ?? [];
        if (!is_array($dims)) return [];
 
        foreach ($dims as $dim) {
            foreach (($dim['options'] ?? []) as $opt) {
                // Top-level filter_keys
                foreach (($opt['filter_keys'] ?? []) as $k) {
                    if (is_string($k) && $k !== '') $keys[] = $k;
                }
                // Variant filter_keys
                foreach (($opt['variants'] ?? []) as $variant) {
                    foreach (($variant['filter_keys'] ?? []) as $k) {
                        if (is_string($k) && $k !== '') $keys[] = $k;
                    }
                }
            }
        }
        return array_values(array_unique($keys));
    }
 
    /**
     * Sync filter keys (delete + insert).
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
