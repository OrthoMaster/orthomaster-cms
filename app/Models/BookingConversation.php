<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fil de discussion entre un expert et un demandeur, tous rendez-vous confondus.
 * Ouvert à l'écriture dès le premier rendez-vous accepté, sans limite de durée.
 */
class BookingConversation extends Model
{
    public const SIDE_USER   = 'user';
    public const SIDE_EXPERT = 'expert';

    protected $guarded = ['id'];

    protected $casts = [
        'expert_id'           => 'integer',
        'user_id'             => 'integer',
        'opened_at'           => 'datetime',
        'last_message_id'     => 'integer',
        'last_message_at'     => 'datetime',
        'user_last_read_id'   => 'integer',
        'expert_last_read_id' => 'integer',
        'blocked_attempts'    => 'integer',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(BookingMessage::class, 'conversation_id');
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(Expert::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->opened_at !== null;
    }

    public function lastReadId(string $side): int
    {
        return $side === self::SIDE_EXPERT ? $this->expert_last_read_id : $this->user_last_read_id;
    }

    public static function otherSide(string $side): string
    {
        return $side === self::SIDE_EXPERT ? self::SIDE_USER : self::SIDE_EXPERT;
    }
}
