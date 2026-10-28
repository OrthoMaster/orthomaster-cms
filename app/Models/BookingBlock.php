<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingBlock extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'booking_id'          => 'integer',
        'expert_id'           => 'integer',
        'date'                => 'date:Y-m-d',
        'start_at'            => 'datetime',
        'end_at'              => 'datetime',
        'location_id'         => 'integer',
        // Copie de l'adresse au moment de la réservation (ExpertLocation::snapshot()).
        'location'            => 'array',
        'meet_created_at'     => 'datetime',
        'meet_closed_at'      => 'datetime',
        'meet_reopened_until' => 'datetime',
        'meet_synced_at'      => 'datetime',
    ];

    public static function holdKey(int $expertId, string $date, string $period): string
    {
        return $expertId . '|' . $date . '|' . $period;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function presences(): HasMany
    {
        return $this->hasMany(BookingMeetPresence::class, 'block_id')->orderBy('joined_at');
    }

    /** Salle créée et pas fermée : le lien fonctionne. */
    public function meetIsOpen(): bool
    {
        return $this->meet_space !== null && $this->meet_closed_at === null;
    }

    /** « Cabinet A — 12 rue des Lilas, 67000 Strasbourg », ou null pour une séance en ligne. */
    public function getLocationTextAttribute(): ?string
    {
        if (!$this->location) {
            return null;
        }

        $l    = $this->location;
        $city = trim(($l['postal_code'] ?? '') . ' ' . ($l['city'] ?? ''));

        return ($l['label'] ?? '') . ' — ' . implode(', ', array_filter([$l['address_line'] ?? null, $city]));
    }
}