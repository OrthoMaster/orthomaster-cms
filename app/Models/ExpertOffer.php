<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpertOffer extends Model
{
    use SoftDeletes;

    // Le prix n'est plus ici : chaque mode (en ligne, en présentiel) a le sien.
    protected $fillable = [
        'title',
        'description',
        'blocks_count',
        'currency',
        'active_flg',
        'order_dsp',
    ];

    protected $casts = [
        'expert_id'    => 'integer',
        'blocks_count' => 'integer',
        'active_flg'   => 'boolean',
        'order_dsp'    => 'integer',
    ];

    protected $appends = ['duration_label'];

    /** Illustrations, dans l'ordre d'affichage. */
    public function images(): HasMany
    {
        return $this->hasMany(ExpertOfferImage::class, 'offer_id')->orderBy('order_dsp')->orderBy('id');
    }

    /** Modes configurés, actifs ou non ; en ligne d'abord. */
    public function modes(): HasMany
    {
        return $this->hasMany(ExpertOfferMode::class, 'offer_id')->orderByRaw("FIELD(mode, 'online', 'onsite')");
    }

    public function activeModes(): HasMany
    {
        return $this->modes()->where('active_flg', true);
    }

    /** Mode actif demandé, ou null s'il n'est pas proposé. Utilise la relation si elle est chargée. */
    public function activeMode(string $mode): ?ExpertOfferMode
    {
        $modes = $this->relationLoaded('modes') ? $this->modes : $this->modes()->get();

        return $modes->first(fn (ExpertOfferMode $m) => $m->mode === $mode && $m->active_flg);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active_flg', true);
    }

    public function getDurationLabelAttribute(): string
    {
        return self::durationLabel((int) $this->blocks_count);
    }

    /**
     * 1 → « 0,5 jour », 3 → « 1,5 jour », 4 → « 2 jours ».
     * Statique pour servir aussi aux listes déroulantes, sans instance.
     */
    public static function durationLabel(int $blocks): string
    {
        $days = $blocks / 2;
        $text = rtrim(rtrim(number_format($days, 1, ',', ''), '0'), ',');

        // En français, « jour » reste au singulier en dessous de 2.
        return $text . ($days >= 2 ? ' jours' : ' jour');
    }
}