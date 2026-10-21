<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingNoShowReport extends Model
{
    public const REPORTER_USER   = 'user';
    public const REPORTER_EXPERT = 'expert';

    public const STATUS_OPEN      = 'open';
    public const STATUS_RESOLVED  = 'resolved';
    public const STATUS_DISMISSED = 'dismissed';

    protected $table = 'booking_noshow_reports';

    protected $guarded = ['id'];

    protected $casts = [
        'booking_id'       => 'integer',
        'reporter_user_id' => 'integer',
        'block_ids'        => 'array',
        'resolved_at'      => 'datetime',
        'resolved_by_admin_id' => 'integer',
        'refund_amount'    => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}