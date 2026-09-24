<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class VideoAdTarget extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'effective_from' => 'date',
        'points'         => 'integer',
    ];

    /** Types de ciblage, chacun disposant de la moitié de l'enveloppe */
    public const TYPE_PROFESSION = 'profession';
    public const TYPE_TOPIC      = 'topic';

    public function videoAd()
    {
        return $this->belongsTo(VideoAds::class, 'video_ads_id');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('target_type', $type);
    }

    /**
     * Date de la répartition en vigueur à une date donnée, pour chaque
     * publicité concernée.
     *
     * L'enveloppe fait foi : une répartition existe dès qu'un budget a
     * été souscrit, même si aucun point n'a encore été placé.
     *
     * @return array<int,string> video_ads_id => effective_from
     */
    public static function activeDates(array $adIds, ?string $onDate = null): array
    {
        if (empty($adIds)) {
            return [];
        }

        $onDate = $onDate ?: now()->toDateString();

        return DB::table('video_ad_budgets')
            ->selectRaw('video_ads_id, MAX(effective_from) as effective_from')
            ->whereIn('video_ads_id', $adIds)
            ->where('effective_from', '<=', $onDate)
            ->groupBy('video_ads_id')
            ->pluck('effective_from', 'video_ads_id')
            ->all();
    }
}