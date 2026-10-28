<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message d'un fil : texte écrit par une partie, ou événement d'un rendez-vous
 * (demande, acceptation, annulation…) inscrit automatiquement depuis le journal.
 * Jamais modifié ni supprimé.
 */
class BookingMessage extends Model
{
    public const UPDATED_AT = null;

    public const KIND_TEXT  = 'text';
    public const KIND_EVENT = 'event';

    public const SENDER_USER   = 'user';
    public const SENDER_EXPERT = 'expert';
    public const SENDER_ADMIN  = 'admin';
    public const SENDER_SYSTEM = 'system';

    protected $guarded = ['id'];

    protected $casts = [
        'conversation_id'  => 'integer',
        'sender_user_id'   => 'integer',
        'booking_id'       => 'integer',
        'booking_event_id' => 'integer',
        'data'             => 'array',
        'created_at'       => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(BookingConversation::class, 'conversation_id');
    }

    public function isEvent(): bool
    {
        return $this->kind === self::KIND_EVENT;
    }
}
