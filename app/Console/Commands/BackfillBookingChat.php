<?php

namespace App\Console\Commands;

use App\Models\BookingEvent;
use App\Services\Booking\BookingChatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Crée les fils et y recopie les événements des rendez-vous existants (à lancer une fois
 * après la migration, avant la mise en ligne de la messagerie dans l'application).
 * Les événements déjà recopiés sont ignorés, mais tous les fils sont marqués comme lus :
 * ne pas la relancer une fois que les parties s'écrivent.
 */
class BackfillBookingChat extends Command
{
    protected $signature = 'booking:chat-backfill';

    protected $description = 'Recopie les événements des rendez-vous existants dans la messagerie';

    public function handle(BookingChatService $chat): int
    {
        $copied = 0;

        BookingEvent::query()
            ->whereIn('type', BookingChatService::EVENT_TYPES)
            ->orderBy('id')
            ->chunkById(200, function ($events) use ($chat, &$copied) {
                foreach ($events as $event) {
                    if ($chat->recordEvent($event)) {
                        $copied++;
                    }
                }
            });

        // L'historique repris n'est pas une nouveauté : pas de badge pour des événements anciens.
        DB::table('booking_conversations')
            ->whereNotNull('last_message_id')
            ->update([
                'user_last_read_id'   => DB::raw('GREATEST(user_last_read_id, last_message_id)'),
                'expert_last_read_id' => DB::raw('GREATEST(expert_last_read_id, last_message_id)'),
            ]);

        $this->info("Événements recopiés : {$copied}");

        return self::SUCCESS;
    }
}
