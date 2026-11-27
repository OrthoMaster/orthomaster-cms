<?php

namespace App\Support\Scat6;

/**
 * Scores SCAT6, mêmes règles que l'application (src/scat6/core/scoring.ts) :
 *  - une partie non réalisée donne null, jamais 0 ni le maximum (R7) ;
 *  - aucun seuil ni verdict : les scores se lisent à côté de la baseline et du jugement clinique.
 * Le serveur recalcule à la signature pour que le rapport ne dépende pas de ce que l'application envoie.
 */
final class Scat6Scoring
{
    /** Clés du résumé, dans l'ordre du tableau de comparaison (max, unité, « plus haut = pire »). */
    public const ROWS = [
        'symptom_count'    => ['label' => 'Nombre de symptômes', 'max' => 22, 'unit' => null, 'worse_if_higher' => true],
        'symptom_severity' => ['label' => 'Sévérité des symptômes', 'max' => 132, 'unit' => null, 'worse_if_higher' => true],
        'orientation'      => ['label' => 'Orientation', 'max' => 5, 'unit' => null, 'worse_if_higher' => false],
        'immediate'        => ['label' => 'Mémoire immédiate', 'max' => 30, 'unit' => null, 'worse_if_higher' => false],
        'concentration'    => ['label' => 'Concentration', 'max' => 5, 'unit' => null, 'worse_if_higher' => false],
        'delayed'          => ['label' => 'Mémoire différée', 'max' => 10, 'unit' => null, 'worse_if_higher' => false],
        'cognitive_total'  => ['label' => 'Total cognitif', 'max' => 50, 'unit' => null, 'worse_if_higher' => false],
        'mbess'            => ['label' => 'mBESS (erreurs, sol ferme)', 'max' => 30, 'unit' => null, 'worse_if_higher' => true],
        'mbess_foam'       => ['label' => 'mBESS (erreurs, sol instable)', 'max' => 30, 'unit' => null, 'worse_if_higher' => true],
        'tandem_best'      => ['label' => 'Marche sur une ligne (meilleur temps)', 'max' => null, 'unit' => 's', 'worse_if_higher' => true],
        'dual_best'        => ['label' => 'Double tâche (meilleur temps)', 'max' => null, 'unit' => 's', 'worse_if_higher' => true],
    ];

    /** @return array<string, int|float|null> */
    public static function summarize(array $answers, array $tests): array
    {
        $symptoms = self::symptoms($answers);

        $orientationAnswers = [];
        foreach (array_keys(Scat6Content::ORIENTATION) as $code) {
            $orientationAnswers[] = self::isDone($answers[$code] ?? null) ? ($answers[$code]['value'] === true) : null;
        }
        $orientation = in_array(null, $orientationAnswers, true) ? null : count(array_filter($orientationAnswers));

        $memory = $tests['memory'] ?? [];
        $immediate = null;
        if (! empty($memory['endedAt'])) {
            $immediate = 0;
            foreach (array_slice($memory['trials'] ?? [], 0, 3) as $trial) {
                $immediate += self::clamp(is_array($trial) ? count($trial) : 0, 0, 10);
            }
        }

        $digits = ! empty($tests['digits']['results']) ? self::digitsBackward($tests['digits']['results']) : null;
        $monthsTest = $tests['months'] ?? [];
        $months = ! empty($monthsTest['done'])
            ? ((int) ($monthsTest['errors'] ?? 1) === 0 && (float) ($monthsTest['seconds'] ?? 999) < 30 ? 1 : 0)
            : null;
        $concentration = $digits !== null && $months !== null ? $digits + $months : null;

        $delayedTest = $tests['delayed'] ?? [];
        $delayed = ! empty($delayedTest['done']) ? self::clamp(count($delayedTest['recalled'] ?? []), 0, 10) : null;

        $cognitive = in_array(null, [$orientation, $immediate, $concentration, $delayed], true)
            ? null
            : $orientation + $immediate + $concentration + $delayed;

        $mbess = $tests['mbess'] ?? [];
        $tandem = $tests['tandem'] ?? [];

        return [
            'symptom_count'    => $symptoms['count'] ?? null,
            'symptom_severity' => $symptoms['severity'] ?? null,
            'orientation'      => $orientation,
            'immediate'        => $immediate,
            'concentration'    => $concentration,
            'delayed'          => $delayed,
            'cognitive_total'  => $cognitive,
            'mbess'            => self::mbess($mbess['firm'] ?? null),
            'mbess_foam'       => self::mbess($mbess['foam'] ?? null),
            'tandem_best'      => ! empty($tandem['done']) ? self::tandemBest($tandem['trials'] ?? []) : null,
            'dual_best'        => isset($tandem['dualBest']) && is_numeric($tandem['dualBest']) ? (float) $tandem['dualBest'] : null,
        ];
    }

    /** Symptômes : nombre /22 et sévérité /132 sur les items renseignés ; null si aucun. */
    private static function symptoms(array $answers): ?array
    {
        $count = 0;
        $severity = 0;
        $done = 0;
        foreach (array_keys(Scat6Content::SYMPTOMS) as $code) {
            $answer = $answers[$code] ?? null;
            if (! self::isDone($answer)) {
                continue;
            }
            $done++;
            $value = self::clamp((int) $answer['value'], 0, Scat6Content::SYMPTOM_MAX_SEVERITY);
            if ($value > 0) {
                $count++;
            }
            $severity += $value;
        }

        return $done ? ['count' => $count, 'severity' => $severity] : null;
    }

    /** Chiffres à l'envers : longueurs 3 → 6 ; une longueur réussie si l'un des 2 essais l'est ; arrêt au premier échec. */
    private static function digitsBackward(array $results): int
    {
        $score = 0;
        foreach (array_slice($results, 0, 4) as $attempts) {
            if (is_array($attempts) && in_array(true, $attempts, true)) {
                $score++;
            } else {
                break;
            }
        }

        return $score;
    }

    /** 3 positions × 10 erreurs au plus ; null tant qu'une position manque. */
    private static function mbess($stances): ?int
    {
        if (! is_array($stances) || count($stances) !== 3 || in_array(null, $stances, true)) {
            return null;
        }

        return array_sum(array_map(fn ($e) => self::clamp((int) $e, 0, 10), $stances));
    }

    /** Meilleur temps des essais réussis (un essai échoué est noté -1). */
    private static function tandemBest(array $trials): ?float
    {
        $ok = array_filter($trials, fn ($t) => is_numeric($t) && $t > 0);

        return $ok ? (float) min($ok) : null;
    }

    private static function isDone($answer): bool
    {
        return is_array($answer) && ($answer['status'] ?? null) === 'done' && array_key_exists('value', $answer) && $answer['value'] !== null;
    }

    private static function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
