<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingNoShowReport;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Signalement d'une absence sur un rendez-vous accepté.
 *
 * Ouvert dès le début de la première demi-journée et jusqu'à 24 h après la fin de la
 * dernière (décision produit). Une seule déclaration par partie et par rendez-vous :
 * elle est examinée par l'administration, qui décide d'un éventuel remboursement.
 */
class BookingNoShowService
{
    public const REPORT_WINDOW_HOURS = 24;
    public const NOTE_MAX            = 500;

    /** Le signalement est-il ouvert pour cette partie ? */
    public function canReport(Booking $booking, string $reporter): bool
    {
        return $this->windowOpen($booking) && !$this->reportOf($booking, $reporter);
    }

    public function reportOf(Booking $booking, string $reporter): ?BookingNoShowReport
    {
        return $booking->relationLoaded('noShowReports')
            ? $booking->noShowReports->firstWhere('reporter', $reporter)
            : $booking->noShowReports()->where('reporter', $reporter)->first();
    }

    /**
     * @param array{block_ids?:array<int>, note?:string|null} $input
     */
    public function report(Booking $booking, string $reporter, int $reporterUserId, array $input): BookingNoShowReport
    {
        if ($booking->status !== Booking::STATUS_ACCEPTED) {
            throw ValidationException::withMessages(['booking' => 'Seul un rendez-vous accepté peut faire l\'objet d\'un signalement.']);
        }
        if (!$this->windowOpen($booking)) {
            throw ValidationException::withMessages([
                'booking' => 'Le signalement est possible du début du rendez-vous jusqu\'à 24 h après sa dernière demi-journée.',
            ]);
        }
        if ($this->reportOf($booking, $reporter)) {
            throw ValidationException::withMessages(['booking' => 'Vous avez déjà signalé une absence pour ce rendez-vous.']);
        }

        // Seules les demi-journées déjà commencées peuvent être déclarées manquées.
        $startedIds = $booking->blocks
            ->filter(fn ($b) => $b->start_at && $b->start_at->isPast())
            ->pluck('id')
            ->all();

        $blockIds = array_values(array_unique(array_map('intval', $input['block_ids'] ?? [])));
        if (!$blockIds || array_diff($blockIds, $startedIds)) {
            throw ValidationException::withMessages(['block_ids' => 'Choisissez la ou les demi-journées concernées.']);
        }

        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > self::NOTE_MAX) {
            throw ValidationException::withMessages(['note' => 'Le message ne doit pas dépasser ' . self::NOTE_MAX . ' caractères.']);
        }

        try {
            $report = BookingNoShowReport::create([
                'booking_id'       => $booking->id,
                'reporter'         => $reporter,
                'reporter_user_id' => $reporterUserId,
                'block_ids'        => $blockIds,
                'note'             => $note ?: null,
                'status'           => BookingNoShowReport::STATUS_OPEN,
            ]);
        } catch (QueryException $e) {
            // 23000 : index unique (booking_id, reporter) — double envoi simultané.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            throw ValidationException::withMessages(['booking' => 'Vous avez déjà signalé une absence pour ce rendez-vous.']);
        }

        BookingEvent::log($booking, 'noshow_reported', $reporter, array_filter([
            'report_id' => $report->id,
            'blocks'    => count($blockIds),
            'note'      => $note ?: null,
        ]));

        return $report;
    }

    /**
     * Bloc « absence » des fiches : état du signalement de la partie qui consulte.
     */
    public function payload(Booking $booking, string $reporter): array
    {
        $report = $this->reportOf($booking, $reporter);

        return [
            'can_report' => $booking->status === Booking::STATUS_ACCEPTED && $this->canReport($booking, $reporter),
            // Demi-journées que l'on peut déclarer manquées : celles déjà commencées.
            'reportable_block_ids' => $booking->blocks
                ->filter(fn ($b) => $b->start_at && $b->start_at->isPast())
                ->pluck('id')
                ->values(),
            'report' => $report ? [
                'status'     => $report->status,
                'block_ids'  => $report->block_ids,
                'note'       => $report->note,
                'created_at' => $report->created_at?->toIso8601String(),
            ] : null,
            'note_max' => self::NOTE_MAX,
        ];
    }

    private function windowOpen(Booking $booking): bool
    {
        $firstStart = $booking->blocks->min('start_at');
        $lastEnd    = $booking->blocks->max('end_at');
        if (!$firstStart || !$lastEnd) {
            return false;
        }

        return now()->gte($firstStart) && now()->lte($lastEnd->copy()->addHours(self::REPORT_WINDOW_HOURS));
    }
}