<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un passage d'un participant dans la salle Meet d'une demi-journée (relevé Google).
 */
class BookingMeetPresence extends Model
{
    public const KIND_GOOGLE    = 'google';
    public const KIND_ANONYMOUS = 'anonymous';
    public const KIND_PHONE     = 'phone';

    protected $table = 'booking_meet_presences';

    protected $guarded = ['id'];

    protected $casts = [
        'block_id'  => 'integer',
        'joined_at' => 'datetime',
        'left_at'   => 'datetime',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(BookingBlock::class, 'block_id');
    }

    /** Durée du passage en secondes (jusqu'à maintenant s'il est encore dans la salle). */
    public function seconds(): int
    {
        if (!$this->joined_at) {
            return 0;
        }

        return max(0, ($this->left_at ?? now())->getTimestamp() - $this->joined_at->getTimestamp());
    }
}