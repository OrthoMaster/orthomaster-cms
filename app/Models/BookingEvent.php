<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'un rendez-vous, affiché en chronologie dans le CMS.
 * Écrit au fil de l'eau : jamais modifié ni supprimé.
 */
class BookingEvent extends Model
{
    public const UPDATED_AT = null;

    public const ACTOR_USER   = 'user';
    public const ACTOR_EXPERT = 'expert';
    public const ACTOR_ADMIN  = 'admin';
    public const ACTOR_SYSTEM = 'system';

    protected $guarded = ['id'];

    protected $casts = [
        'booking_id' => 'integer',
        'admin_id'   => 'integer',
        'data'       => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Ajoute une ligne au journal. N'échoue jamais bruyamment : le journal ne doit
     * pas faire tomber une annulation ou un paiement.
     */
    public static function log(
        Booking|int $booking,
        string $type,
        string $actor,
        array $data = [],
        ?string $onBehalfOf = null,
        ?int $adminId = null
    ): void {
        try {
            static::create([
                'booking_id'   => $booking instanceof Booking ? $booking->id : $booking,
                'type'         => $type,
                'actor'        => $actor,
                'on_behalf_of' => $onBehalfOf,
                'admin_id'     => $adminId,
                'data'         => $data ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}