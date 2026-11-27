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
 * C'est toujours le DERNIER événement qui compte : un nouveau choc (examen dont le choc est plus récent
 * que celui du suivi de reprise) remplace l'état « reprise » ou un suivi resté ouvert.
 *
 *  rts              → suivi de reprise en cours (étape 1 à 5) pour le choc le plus récent
 *  commotion        → dernier examen « commotion : Oui », sans suivi de reprise ouvert pour ce choc
 *  retest           → dernier examen « diagnostic différé »
 *  reprise          → retour à la compétition autorisé (étape 6 atteinte), sans examen plus récent
 *  sans_restriction → dernier examen « commotion : Non »
 *  null             → aucun examen après un choc
 */
final class Scat6PatientStatus
{
    /** @param Collection $assessments lignes (modèles ou stdClass) du patient ; $rts : suivis du patient */
    public static function compute(Collection $assessments, Collection $rts): array
    {
        $exam = $assessments
            ->filter(fn ($a) => $a->status === ScatAssessment::STATUS_COMPLETED && $a->exam_kind !== 'baseline' && $a->diagnosis)
            ->sortByDesc(fn ($a) => (string) $a->completed_at)
            ->first();

        $active = $rts->first(fn ($r) => $r->status === ScatRts::STATUS_ACTIVE);
        if ($active && ! self::newerInjury($exam, $active)) {
            return [
                'code'    => 'rts',
                'step'    => (int) $active->current_step,
                'rts_id'  => (int) $active->id,
                'since'   => self::iso($active->injury_at),
            ];
        }

        $done = $rts
            ->filter(fn ($r) => $r->status === ScatRts::STATUS_COMPLETED && $r->completed_at)
            ->sortByDesc(fn ($r) => (string) $r->completed_at)
            ->first();

        // Reprise autorisée, sans examen plus récent (ni nouveau choc, ni examen signé après la reprise).
        if ($done && (! $exam || (! self::newerInjury($exam, $done)
            && Carbon::parse($done->completed_at)->gte(Carbon::parse($exam->completed_at))))) {
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

    /**
     * L'examen porte-t-il sur un choc plus récent que celui du suivi de reprise ? (le suivi ne garde que
     * le jour du choc : un choc d'un jour postérieur est un nouveau choc).
     */
    private static function newerInjury($exam, $rts): bool
    {
        if (! $exam || ! $exam->injury_at || ! $rts->injury_at) {
            return false;
        }

        return Carbon::parse($exam->injury_at)->startOfDay()->gt(Carbon::parse($rts->injury_at)->startOfDay());
    }

    private static function iso($date): ?string
    {
        return $date ? Carbon::parse($date)->toIso8601String() : null;
    }
}
