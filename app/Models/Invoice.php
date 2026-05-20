<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'sent_cnt' => 'integer',
        'sent_at' => 'datetime',
        'created_at' => 'datetime:d-m-Y H:i',
        'updated_at' => 'datetime:d-m-Y H:i',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsSent()
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'paid_at' => now(),
        ]);
    }

    public function markAsPaid()
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed($reason = null)
    {
        $this->update([
            // 'status' => self::STATUS_FAILED,
            'failed_reason' => $reason,
        ]);
    }

    public static function getInvoiceByUser($userId) {
        return Invoice::where('user_id', $userId)
            ->where('status', self::STATUS_PAID)
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
