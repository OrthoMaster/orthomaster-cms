<?php

namespace App\Console\Commands;

use App\Services\Booking\BookingDecisionService;
use Illuminate\Console\Command;

/**
 * Clôt les demandes restées sans réponse après leur délai : empreinte bancaire libérée,
 * demi-journées rendues au calendrier de l'expert.
 */
class ExpireBookingRequests extends Command
{
    protected $signature = 'booking:expire-requests';

    protected $description = 'Expire les demandes de rendez-vous sans réponse après le délai.';

    public function handle(BookingDecisionService $decisions): int
    {
        $count = $decisions->expireOverdue();
        $this->info("{$count} demande(s) expirée(s).");

        return self::SUCCESS;
    }
}