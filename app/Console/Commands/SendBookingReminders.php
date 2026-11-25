<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingBlock;
use App\Models\BookingEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rappels 24 h avant un rendez-vous accepté (e-mail aux deux parties, voir BookingMailService).
 *
 *  - Un rappel par jour de rendez-vous, envoyé dans les 24 h qui précèdent la première
 *    demi-journée de ce jour ; pas de rappel pour un jour qui suit directement un autre jour
 *    du même rendez-vous (thème sur plusieurs jours d'affilée).
 *  - Pas de rappel si le rendez-vous a été accepté moins de 24 h avant ce début :
 *    l'e-mail de confirmation vient d'arriver.
 *  - Chaque rappel est noté au journal (reminder_sent, data.date) : jamais envoyé deux fois ;
 *    c'est cette entrée qui déclenche l'e-mail.
 */
class SendBookingReminders extends Command
{
    protected $signature = 'booking:send-reminders';

    protected $description = 'Envoie les rappels 24 h avant les rendez-vous acceptés.';

    public const HOURS_BEFORE = 24;

    public function handle(): int
    {
        $now = now();

        $blocks = BookingBlock::query()
            ->where('start_at', '>', $now)
            ->where('start_at', '<=', $now->copy()->addHours(self::HOURS_BEFORE))
            ->whereHas('booking', fn ($b) => $b->where('status', Booking::STATUS_ACCEPTED))
            ->orderBy('start_at')
            ->get(['id', 'booking_id', 'date', 'start_at']);

        $sent = 0;
        foreach ($blocks->groupBy(fn ($b) => $b->booking_id . '|' . Carbon::parse($b->date)->toDateString()) as $group) {
            $first = $group->first();
            $bookingId = (int) $first->booking_id;
            $date = Carbon::parse($first->date)->toDateString();

            // Accepté moins de 24 h avant : pas de rappel.
            $acceptedAt = BookingEvent::where('booking_id', $bookingId)->where('type', 'accepted')->min('created_at');
            if (!$acceptedAt || Carbon::parse($acceptedAt)->gt($first->start_at->copy()->subHours(self::HOURS_BEFORE))) {
                continue;
            }

            // Jour qui suit un autre jour du même rendez-vous : déjà rappelé la veille.
            $dayBefore = Carbon::parse($date)->subDay()->toDateString();
            if (BookingBlock::where('booking_id', $bookingId)->whereDate('date', $dayBefore)->exists()) {
                continue;
            }

            $already = BookingEvent::where('booking_id', $bookingId)
                ->where('type', 'reminder_sent')
                ->get(['data'])
                ->contains(fn ($e) => ($e->data['date'] ?? null) === $date);
            if ($already) {
                continue;
            }

            BookingEvent::log($bookingId, 'reminder_sent', BookingEvent::ACTOR_SYSTEM, [
                'date'      => $date,
                'block_ids' => $group->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ]);
            $sent++;
        }

        $this->info("{$sent} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
