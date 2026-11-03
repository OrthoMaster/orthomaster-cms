<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Créneau proposé hors calendrier (voir BookingWindowService).
 * Consultation : start_time / end_time. Thème : start_period / end_period (demi-journées incluses).
 */
class BookingWindow extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'booking_id' => 'integer',
        'position'   => 'integer',
        'start_date' => 'date',
        'end_date'   => 'date',
        'start_at'   => 'datetime',
        'end_at'     => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
