<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Une façon de suivre une offre : en ligne ou en présentiel, avec son prix et son calendrier.
 * Une offre a au plus une ligne par mode ; désactiver un mode garde sa configuration.
 */
class ExpertOfferMode extends Model
{
    public const ONLINE = 'online';
    public const ONSITE = 'onsite';

    public const MODE_LABELS = [
        self::ONLINE => 'En ligne',
        self::ONSITE => 'En présentiel',
    ];

    /** Toutes les demi-journées de la grille (en présentiel : celles qui ont un lieu). */
    public const SCHEDULE_ALL = 'weekly_all';
    /** Une partie seulement de la grille (ex. lundi matin et mardi matin). */
    public const SCHEDULE_SELECTED = 'weekly_selected';
    /** Des dates précises, prises dans la grille. */
    public const SCHEDULE_DATES = 'dates';

    public const SCHEDULE_LABELS = [
        self::SCHEDULE_ALL      => 'Toutes les demi-journées',
        self::SCHEDULE_SELECTED => 'Certaines demi-journées',
        self::SCHEDULE_DATES    => 'Dates précises',
    ];

    // price : TTC de toute l'offre (toutes ses demi-journées), propre à chaque mode.
    protected $fillable = [
        'offer_id',
        'mode',
        'price',
        'schedule_type',
        'book_ahead_days',
        'active_flg',
    ];

    protected $casts = [
        'offer_id'        => 'integer',
        'price'           => 'decimal:2',
        'book_ahead_days' => 'integer',
        'active_flg'      => 'boolean',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ExpertOffer::class, 'offer_id')->withTrashed();
    }

    public function getModeLabelAttribute(): string
    {
        return self::MODE_LABELS[$this->mode] ?? $this->mode;
    }

    public function getScheduleLabelAttribute(): string
    {
        return self::SCHEDULE_LABELS[$this->schedule_type] ?? $this->schedule_type;
    }

    /**
     * Pas de relation Eloquent : la clé est composée (offre + mode), ce que hasMany ne sait pas
     * charger en eager loading.
     *
     * @return Collection<int, ExpertOfferSlot>
     */
    public function slots(): Collection
    {
        return ExpertOfferSlot::where('offer_id', $this->offer_id)
            ->where('mode', $this->mode)
            ->orderBy('weekday')
            ->orderByRaw("FIELD(period, 'morning', 'afternoon')")
            ->get();
    }

    /** @return Collection<int, ExpertOfferDate> */
    public function dates(?string $from = null, ?string $to = null): Collection
    {
        return ExpertOfferDate::where('offer_id', $this->offer_id)
            ->where('mode', $this->mode)
            ->when($from, fn ($q) => $q->where('date', '>=', $from))
            ->when($to, fn ($q) => $q->where('date', '<=', $to))
            ->orderBy('date')
            ->orderByRaw("FIELD(period, 'morning', 'afternoon')")
            ->get();
    }
}