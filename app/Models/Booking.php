<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    /** En attente du paiement : aucune demi-journée n'est encore retenue. */
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    /** Payée (montant réservé sur la carte), en attente de la réponse de l'expert. */
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_DECLINED  = 'declined';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const CANCELLED_BY_USER   = 'user';
    public const CANCELLED_BY_EXPERT = 'expert';
    /** Abandon avant paiement, créneau pris entre-temps… */
    public const CANCELLED_BY_SYSTEM = 'system';
    /** Annulation faite par l'administration sans la rattacher à une partie. */
    public const CANCELLED_BY_ADMIN  = 'admin';

    /** Statuts qui retiennent les demi-journées. */
    public const HOLDING_STATUSES = [self::STATUS_PENDING, self::STATUS_ACCEPTED];

    protected $guarded = ['id'];

    protected $casts = [
        'user_id'           => 'integer',
        'expert_id'         => 'integer',
        'offer_id'          => 'integer',
        'invoice_id'        => 'integer',
        'blocks_count'      => 'integer',
        'amount_ht'         => 'decimal:2',
        'tva_rate'          => 'decimal:2',
        'tva_amount'        => 'decimal:2',
        'amount_ttc'        => 'decimal:2',
        'payment_required'  => 'boolean',
        'commission_rate'   => 'decimal:2',
        'respond_by'        => 'datetime',
        'requested_at'      => 'datetime',
        'responded_at'      => 'datetime',
        'refunded_amount'   => 'decimal:2',
        'refunded_at'       => 'datetime',
        'cancelled_by_admin_id' => 'integer',
        'decline_reason_id' => 'integer',
        'cancelled_at'      => 'datetime',
    ];

    /**
     * Demandes réellement transmises à l'expert : celles abandonnées avant paiement
     * n'apparaissent pas dans les rendez-vous de l'utilisateur.
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereNotNull('requested_at');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(BookingBlock::class)->orderBy('date')->orderBy('start_local');
    }

    public function declineReason(): BelongsTo
    {
        return $this->belongsTo(BookingDeclineReason::class, 'decline_reason_id');
    }

    /** Montant HT reversé à l'expert, commission copiée à la création déduite. */
    public function expertPayout(): float
    {
        return round((float) $this->amount_ht * (1 - (float) $this->commission_rate / 100), 2);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function noShowReports(): HasMany
    {
        return $this->hasMany(BookingNoShowReport::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ExpertOffer::class, 'offer_id')->withTrashed();
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(Expert::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}