<?php

namespace App\Support\Brochure;

/**
 * Liste des domaines d'expertise proposés dans l'éditeur.
 * Plus tard : remplacer all() par une requête, par exemple
 *   return Expertise::orderBy('label')->pluck('label', 'id')->all();
 */
class ExpertiseCatalog
{
    /** @return array<int, string> id => libellé */
    public static function all(): array
    {
        return [
            1  => 'Rééducation orthopédique',
            2  => 'Médecine du sport',
            3  => 'Médecine régénérative',
            4  => 'Médecine physique et de réadaptation',
            5  => 'Rhumatologie',
            6  => 'Rhumatismes inflammatoires',
            7  => 'Échographie musculo-squelettique',
            8  => 'Infiltrations écho-guidées',
            9  => 'Traumatologie du sport',
            10 => 'Pathologies tendineuses',
            11 => 'Arthroscopie',
            12 => 'Prothèses articulaires',
            13 => 'Chirurgie du genou',
            14 => 'Chirurgie de l’épaule',
            15 => 'Chirurgie de la hanche',
            16 => 'Chirurgie de la main et du poignet',
            17 => 'Chirurgie du pied et de la cheville',
            18 => 'Chirurgie du rachis',
            19 => 'Podologie médicale',
            20 => 'Ostéoporose',
        ];
    }

    /**
     * Convertit une liste d'ids (ou de libellés, pour les anciennes pages) en libellés à afficher.
     * Les ids inconnus sont ignorés ; un texte libre est conservé tel quel.
     */
    public static function labels(array $items): array
    {
        $all = self::all();
        $out = [];
        foreach ($items as $item) {
            if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                if (isset($all[(int) $item])) {
                    $out[] = $all[(int) $item];
                }
            } elseif (is_string($item) && trim($item) !== '') {
                $out[] = trim($item);
            }
        }

        return $out;
    }

    /** Retrouve l'id d'un libellé (insensible à la casse), ou null. */
    public static function idFor(string $label): ?int
    {
        $needle = mb_strtolower(trim($label));
        foreach (self::all() as $id => $l) {
            if (mb_strtolower($l) === $needle) {
                return $id;
            }
        }

        return null;
    }
}