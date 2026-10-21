<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingBlock extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'booking_id' => 'integer',
        'expert_id'  => 'integer',
        'date'       => 'date:Y-m-d',
        'start_at'   => 'datetime',
        'end_at'     => 'datetime',
        'meet_created_at' => 'datetime',
    ];

    public static function holdKey(int $expertId, string $date, string $period): string
    {
        return $expertId . '|' . $date . '|' . $period;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}