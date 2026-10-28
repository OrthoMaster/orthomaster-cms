<?php

namespace App\Services\Booking;

use App\Support\Booking\ChatContactFilter;
use RuntimeException;

/**
 * Message refusé par le filtre de coordonnées. Séparée de ValidationException pour que
 * l'API renvoie un code stable (« contact_blocked ») que l'application peut traiter à part.
 */
class ContactBlockedException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(ChatContactFilter::message($reason));
    }
}
