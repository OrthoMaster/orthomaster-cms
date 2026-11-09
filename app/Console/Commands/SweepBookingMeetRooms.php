<?php

namespace App\Console\Commands;

use App\Services\Booking\BookingMeetService;
use Illuminate\Console\Command;

/**
 * Ferme les salles Meet arrivées à échéance et relève leurs présences.
 * Planifié toutes les 10 minutes (routes/console.php).
 */
class SweepBookingMeetRooms extends Command
{
    protected $signature = 'booking:meet-sweep';

    protected $description = 'Crée les liens Google Meet 1 h avant le début, ferme les salles terminées et enregistre les présences';

    public function handle(BookingMeetService $meet): int
    {
        $stats = $meet->sweep();

        $this->info("Liens créés : {$stats['created']} · salles fermées : {$stats['closed']} · relevés : {$stats['synced']} · erreurs : {$stats['errors']}");

        return self::SUCCESS;
    }
}