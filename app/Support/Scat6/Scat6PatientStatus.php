<?php

namespace App\Support\Scat6;

use App\Models\ScatAssessment;
use App\Models\ScatRts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * État d'un patient SCAT6, déduit des examens signés et du suivi « retour au sport ».
 * Ce n'est jamais un verdict de l'application : chaque état vient d'une décision enregistrée du médecin
 * (diagnostic à la signature, étapes validées, reprise autorisée).
 *
 *  rts              → suivi de reprise en cours (étape 1 à 5)
 *  commotion        → dernier examen « commotion : Oui », sans suivi de reprise ouvert
 *  retest           → dernier examen « diagnostic différé »
 *  reprise          → retour à la compétition autorisé (étape 6 atteinte), après le dernier examen
 *  sans_restriction → dernier examen « commotion : Non »
 *  null             → aucun examen après un choc
 */
final class Scat6PatientStatus
{
    /** @param Collection $assessments lignes (modèles ou stdClass) du patient ; $rts : suivis du patient */
    public static function compute(Collection $assessments, Collection $rts): array
    {
        $active = $rts->first(fn ($r) => $r->status === ScatRts::STATUS_ACTIVE);
        if ($active) {
            return [
                'code'    => 'rts',
                'step'    => (int) $active->current_step,
                'rts_id'  => (int) $active->id,
                'since'   => self::iso($active->injury_at),
            ];
        }

        $exam = $assessments
            ->filter(fn ($a) => $a->status === ScatAssessment::STATUS_COMPLETED && $a->exam_kind !== 'baseline' && $a->diagnosis)
            ->sortByDesc(fn ($a) => (string) $a->completed_at)
            ->first();
        $done = $rts
            ->filter(fn ($r) => $r->status === ScatRts::STATUS_COMPLETED && $r->completed_at)
            ->sortByDesc(fn ($r) => (string) $r->completed_at)
            ->first();

        // Reprise autorisée après le dernier examen : c'est l'état le plus récent.
        if ($done && (! $exam || Carbon::parse($done->completed_at)->gte(Carbon::parse($exam->completed_at)))) {
            return ['code' => 'reprise', 'since' => self::iso($done->completed_at), 'rts_id' => (int) $done->id];
        }
        if (! $exam) {
            return ['code' => null];
        }

        return [
            'code'  => ['oui' => 'commotion', 'differe' => 'retest', 'non' => 'sans_restriction'][$exam->diagnosis] ?? null,
            'since' => self::iso($exam->completed_at),
        ];
    }

    private static function iso($date): ?string
    {
        return $date ? Carbon::parse($date)->toIso8601String() : null;
    }
}
