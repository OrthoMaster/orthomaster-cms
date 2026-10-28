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

    protected $description = 'Ferme les salles Google Meet terminées et enregistre les présences';

    public function handle(BookingMeetService $meet): int
    {
        $stats = $meet->sweep();

        $this->info("Salles fermées : {$stats['closed']} · relevés : {$stats['synced']} · erreurs : {$stats['errors']}");

        return self::SUCCESS;
    }
}