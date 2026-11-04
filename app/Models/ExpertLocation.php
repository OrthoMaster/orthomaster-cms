<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lieu où l'expert reçoit en présentiel. Réutilisable sur plusieurs demi-journées
 * de la grille ; archivé plutôt que supprimé, car les réservations y font référence.
 */
class ExpertLocation extends Model
{
    protected $fillable = [
        'expert_id',
        'label',
        'address_line',
        'postal_code',
        'city',
        'country',
        'lat',
        'lng',
        'google_place_id',
        'access_details',
        'archived_at',
    ];

    protected $casts = [
        'expert_id'   => 'integer',
        'lat'         => 'float',
        'lng'         => 'float',
        'archived_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ExpertAvailabilityRule::class, 'location_id');
    }

    /** « 12 rue des Lilas, 67000 Strasbourg » : ce que voit le client sur l'offre (3b). */
    public function getFullAddressAttribute(): string
    {
        $city = trim(($this->postal_code ?? '') . ' ' . $this->city);

        return implode(', ', array_filter([$this->address_line, $city]));
    }

    /**
     * Forme publique pour l'application : adresse visible sur l'offre (3b),
     * sans les informations d'accès réservées aux rendez-vous acceptés (3c).
     */
    public function publicPayload(): array
    {
        $payload = [
            'id'           => $this->id,
            'label'        => $this->label,
            'address_line' => $this->address_line,
            'postal_code'  => $this->postal_code,
            'city'         => $this->city,
            'country'      => $this->country,
            'lat'          => $this->lat,
            'lng'          => $this->lng,
        ];

        return $payload + ['maps_url' => self::mapsUrl($payload + ['google_place_id' => $this->google_place_id])];
    }

    /**
     * Lien d'itinéraire Google Maps ; accepte un lieu ou la copie stockée sur une réservation.
     * Coordonnées si connues (plus précis), sinon l'adresse en texte.
     */
    public static function mapsUrl(array $l): string
    {
        $params = ['api' => 1];

        if (!empty($l['lat']) && !empty($l['lng'])) {
            $params['destination'] = $l['lat'] . ',' . $l['lng'];
        } else {
            $params['destination'] = trim(($l['address_line'] ?? '') . ', ' . trim(($l['postal_code'] ?? '') . ' ' . ($l['city'] ?? '')), ', ');
        }
        if (!empty($l['google_place_id'])) {
            $params['destination_place_id'] = $l['google_place_id'];
        }

        return 'https://www.google.com/maps/dir/?' . http_build_query($params);
    }

    /**
     * Copie figée dans booking_blocks.location au moment de la réservation :
     * modifier ou archiver le lieu ensuite ne change pas ce que le client a réservé.
     */
    public function snapshot(): array
    {
        return [
            'label'           => $this->label,
            'address_line'    => $this->address_line,
            'postal_code'     => $this->postal_code,
            'city'            => $this->city,
            'country'         => $this->country,
            'lat'             => $this->lat,
            'lng'             => $this->lng,
            'google_place_id' => $this->google_place_id,
            'access_details'  => $this->access_details,
        ];
    }
}