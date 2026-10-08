<?php

namespace App\Support\Brochures;

use App\Models\Domain;

/**
 * Domaines d'expertise, lus depuis la table `domains`.
 * Mis en cache pour la durée de la requête.
 */
class ExpertiseCatalog
{
    private static ?array $cache = null;

    /** @return array<int, string> id => libellé */
    public static function all(): array
    {
        return self::$cache ??= Domain::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label', 'id')
            ->all();
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