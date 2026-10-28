<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingBlock;
use App\Models\BookingEvent;
use App\Models\BookingMeetPresence;
use App\Services\Google\GoogleMeetService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Salles Google Meet des demi-journées : création, suppression, fermeture, réouverture,
 * relevé des présences, et fermeture automatique (job booking:meet-sweep).
 *
 * Règles de fermeture automatique (salle ouverte, demi-journée terminée) :
 *  - personne dans la salle                       → fermée (« empty ») ;
 *  - une seule personne, 30 min après la fin      → fermée (« alone ») ;
 *  - dans tous les cas, 3 h après la fin          → fermée (« max_duration ») ;
 *  - rendez-vous annulé / refusé / expiré         → fermée tout de suite (« booking_closed »).
 * Une salle rouverte par l'admin n'est pas touchée pendant REOPEN_HOURS.
 *
 * Fermer = terminer la réunion en cours et passer l'accès à RESTRICTED (le lien ne sert
 * plus). Les présences sont relevées juste après, puis une dernière fois au passage suivant
 * du job, le temps que Google finalise ses enregistrements.
 *
 * Chaque opération verrouille la ligne de la demi-journée : le job et l'admin ne peuvent
 * pas agir en même temps sur la même salle.
 */
class BookingMeetService
{
    public const ALONE_AFTER_MINUTES = 30;
    public const MAX_HOURS_AFTER_END = 3;
    public const REOPEN_HOURS        = 2;
    public const BATCH               = 50;

    public const REASON_EMPTY          = 'empty';
    public const REASON_ALONE          = 'alone';
    public const REASON_MAX_DURATION   = 'max_duration';
    public const REASON_BOOKING_CLOSED = 'booking_closed';
    public const REASON_ADMIN          = 'admin';

    public const REASON_LABELS = [
        self::REASON_EMPTY          => 'salle vide après la fin',
        self::REASON_ALONE          => 'une seule personne 30 min après la fin',
        self::REASON_MAX_DURATION   => '3 h après la fin',
        self::REASON_BOOKING_CLOSED => 'rendez-vous clos',
        self::REASON_ADMIN          => "fermée par l'administration",
    ];

    public function __construct(private GoogleMeetService $meet)
    {
    }

    /* ===================== CRÉER / SUPPRIMER ===================== */

    public function create(int $blockId, int $adminId): BookingBlock
    {
        return DB::transaction(function () use ($blockId, $adminId) {
            $block = $this->lock($blockId);
            if ($block->meet_url) {
                return $block; // double clic : déjà créé
            }

            $space = $this->meet->createSpace();

            $block->update([
                'meet_url'            => $space['url'],
                'meet_space'          => $space['name'],
                'meet_created_at'     => now(),
                'meet_closed_at'      => null,
                'meet_close_reason'   => null,
                'meet_reopened_until' => null,
                'meet_synced_at'      => null,
            ]);

            $this->log($block, 'meet_created', BookingEvent::ACTOR_ADMIN, ['url' => $space['url']], $adminId);

            return $block;
        });
    }

    /**
     * Supprime le lien avant la demi-journée (lien à refaire, erreur…). Après la fin,
     * on ferme la salle au lieu de la supprimer, pour garder les présences.
     */
    public function delete(int $blockId, int $adminId): void
    {
        DB::transaction(function () use ($blockId, $adminId) {
            $block = $this->lock($blockId);
            if (!$block->meet_url) {
                return;
            }
            if ($block->end_at && $block->end_at->isPast()) {
                throw ValidationException::withMessages([
                    'meet' => 'La demi-journée est terminée : fermez la salle plutôt que de supprimer le lien (les présences sont conservées).',
                ]);
            }

            if ($block->meetIsOpen()) {
                $this->meet->closeSpace($block->meet_space);
            }

            $url = $block->meet_url;
            $block->presences()->delete();
            $block->update([
                'meet_url'            => null,
                'meet_space'          => null,
                'meet_created_at'     => null,
                'meet_closed_at'      => null,
                'meet_close_reason'   => null,
                'meet_reopened_until' => null,
                'meet_synced_at'      => null,
            ]);

            $this->log($block, 'meet_cancelled', BookingEvent::ACTOR_ADMIN, ['url' => $url], $adminId);
        });
    }

    /* ===================== FERMER / ROUVRIR ===================== */

    /**
     * Ferme la salle puis relève les présences. Renvoie false si elle était déjà fermée.
     * $adminId null : fermeture automatique par le job.
     */
    public function close(int $blockId, string $reason, ?int $adminId = null): bool
    {
        $closed = DB::transaction(function () use ($blockId, $reason, $adminId) {
            $block = $this->lock($blockId);
            if (!$block->meetIsOpen()) {
                return false;
            }

            $this->meet->closeSpace($block->meet_space);

            $block->update([
                'meet_closed_at'      => now(),
                'meet_close_reason'   => $reason,
                'meet_reopened_until' => null,
            ]);

            $this->log(
                $block,
                'meet_closed',
                $adminId ? BookingEvent::ACTOR_ADMIN : BookingEvent::ACTOR_SYSTEM,
                ['reason' => self::REASON_LABELS[$reason] ?? $reason],
                $adminId
            );

            return true;
        });

        if ($closed) {
            $this->syncQuietly($blockId);
        }

        return $closed;
    }

    /** Rouvre une salle fermée (même lien) ; le job la laisse ouverte REOPEN_HOURS. */
    public function reopen(int $blockId, int $adminId): BookingBlock
    {
        return DB::transaction(function () use ($blockId, $adminId) {
            $block = $this->lock($blockId);
            if (!$block->meet_space || !$block->meet_closed_at) {
                throw ValidationException::withMessages(['meet' => "Cette salle n'est pas fermée."]);
            }

            $this->meet->reopenSpace($block->meet_space);

            $until = now()->addHours(self::REOPEN_HOURS);
            $block->update([
                'meet_closed_at'      => null,
                'meet_close_reason'   => null,
                'meet_reopened_until' => $until,
            ]);

            $this->log($block, 'meet_reopened', BookingEvent::ACTOR_ADMIN, [
                'until' => $until->toIso8601String(),
            ], $adminId);

            return $block;
        });
    }

    /* ===================== PRÉSENCES ===================== */

    /**
     * Relève toutes les présences de la salle chez Google et les enregistre.
     * Idempotent : chaque passage est identifié par sa session Google.
     */
    public function sync(int $blockId): BookingBlock
    {
        $block = BookingBlock::findOrFail($blockId);
        if (!$block->meet_space) {
            return $block;
        }

        // Appel Google hors verrou : il peut être long (pagination) et n'écrit rien.
        $data = $this->meet->attendance($block->meet_space);
        $tz   = config('app.timezone');
        $time = fn (?string $value) => $value ? Carbon::parse($value)->setTimezone($tz) : null;

        DB::transaction(function () use ($block, $data, $time) {
            $locked = $this->lock($block->id);
            if ($locked->meet_space !== $block->meet_space) {
                return; // lien supprimé ou refait entre-temps
            }

            foreach ($data['sessions'] as $row) {
                BookingMeetPresence::updateOrCreate(
                    ['session' => $row['session']],
                    [
                        'block_id'     => $locked->id,
                        'conference'   => $row['conference'],
                        'participant'  => $row['participant'],
                        'display_name' => $row['display_name'] ? mb_substr($row['display_name'], 0, 255) : null,
                        'kind'         => $row['kind'],
                        'google_user'  => $row['google_user'],
                        'joined_at'    => $time($row['joined_at']),
                        'left_at'      => $time($row['left_at']),
                    ]
                );
            }

            $locked->update(['meet_synced_at' => now()]);
        });

        return $block->fresh();
    }

    /**
     * Présences regroupées par participant, pour l'affichage.
     *
     * @return array<int, array{name:string, kind:string, sessions:array<int, BookingMeetPresence>, seconds:int, active:bool}>
     */
    public static function summary(BookingBlock $block): array
    {
        $presences = $block->relationLoaded('presences') ? $block->presences : $block->presences()->get();

        return $presences
            ->groupBy('participant')
            ->map(fn ($rows) => [
                'name'     => $rows->first()->display_name ?: 'Participant sans nom',
                'kind'     => $rows->first()->kind,
                'sessions' => $rows->sortBy('joined_at')->values()->all(),
                'seconds'  => $rows->sum(fn (BookingMeetPresence $p) => $p->seconds()),
                'active'   => $rows->contains(fn (BookingMeetPresence $p) => $p->left_at === null),
            ])
            ->sortBy(fn ($p) => $p['sessions'][0]->joined_at?->getTimestamp() ?? PHP_INT_MAX)
            ->values()
            ->all();
    }

    /* ===================== JOB ===================== */

    /**
     * Ferme les salles arrivées à échéance et termine le relevé des salles fermées.
     *
     * @return array{closed:int, synced:int, errors:int}
     */
    public function sweep(): array
    {
        $stats = ['closed' => 0, 'synced' => 0, 'errors' => 0];
        $now   = now();

        // 1. Salles ouvertes à fermer.
        $open = BookingBlock::with('booking:id,status')
            ->whereNotNull('meet_space')
            ->whereNull('meet_closed_at')
            ->where(fn ($q) => $q->whereNull('meet_reopened_until')->orWhere('meet_reopened_until', '<=', $now))
            ->where(fn ($q) => $q
                ->where('end_at', '<=', $now)
                ->orWhereHas('booking', fn ($b) => $b->whereNotIn('status', [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED])))
            ->orderBy('end_at')
            ->limit(self::BATCH)
            ->get();

        foreach ($open as $block) {
            try {
                $reason = $this->dueReason($block);
                if ($reason && $this->close($block->id, $reason)) {
                    $stats['closed']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error("Meet sweep: block #{$block->id} close failed: " . $e->getMessage());
            }
        }

        // 2. Dernier relevé des salles fermées depuis moins de 24 h, le temps que Google finalise.
        BookingBlock::whereNotNull('meet_space')
            ->whereNotNull('meet_closed_at')
            ->where('meet_closed_at', '>=', $now->copy()->subDay())
            ->orderBy('meet_closed_at')
            ->limit(self::BATCH)
            ->get()
            ->filter(fn (BookingBlock $b) => !$b->meet_synced_at || $b->meet_synced_at->lt($b->meet_closed_at->copy()->addMinutes(2)))
            ->each(function (BookingBlock $block) use (&$stats) {
                try {
                    $this->sync($block->id);
                    $stats['synced']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Log::error("Meet sweep: block #{$block->id} sync failed: " . $e->getMessage());
                }
            });

        return $stats;
    }

    /** Motif de fermeture si la salle doit être fermée maintenant, sinon null. */
    private function dueReason(BookingBlock $block): ?string
    {
        $status = $block->booking?->status;
        if ($status && !in_array($status, [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED], true)) {
            return self::REASON_BOOKING_CLOSED;
        }

        if (!$block->end_at || $block->end_at->isFuture()) {
            return null;
        }

        if (now()->gte($block->end_at->copy()->addHours(self::MAX_HOURS_AFTER_END))) {
            return self::REASON_MAX_DURATION;
        }

        $inside = $this->meet->activeParticipants($block->meet_space);
        if ($inside === 0) {
            return self::REASON_EMPTY;
        }
        if ($inside === 1 && now()->gte($block->end_at->copy()->addMinutes(self::ALONE_AFTER_MINUTES))) {
            return self::REASON_ALONE;
        }

        return null; // au moins deux personnes : la séance continue
    }

    /* ===================== OUTILS ===================== */

    private function lock(int $blockId): BookingBlock
    {
        return BookingBlock::whereKey($blockId)->lockForUpdate()->firstOrFail();
    }

    /** Relevé après fermeture : un échec n'annule pas la fermeture (le job réessaiera). */
    private function syncQuietly(int $blockId): void
    {
        try {
            $this->sync($blockId);
        } catch (\Throwable $e) {
            Log::warning("Meet sync after close failed for block #{$blockId}: " . $e->getMessage());
        }
    }

    private function log(BookingBlock $block, string $type, string $actor, array $data, ?int $adminId): void
    {
        BookingEvent::log($block->booking_id, $type, $actor, [
            'block_id' => $block->id,
            'date'     => $block->date->toDateString(),
            'period'   => $block->period,
        ] + $data, null, $adminId);
    }
}