<?php

namespace App\Support\Brochures;

use App\Models\Expert;
use App\Services\Booking\ExpertProfileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Contenu d'une page partenaire (brouillon et version publiée).
 *
 * Stockage :
 *   - profil de l'expert, partagé avec l'application (écran « Modifier le profil ») :
 *       colonnes experts.honorific, credentials, lede, palette + domaines (domain_expert) + photo.
 *       Enregistré directement sur l'expert, sans brouillon ni publication : une seule source.
 *   - propre à la page (brouillon expert_brochure_drafts, puis experts à la publication) :
 *       colonne model + JSON (data / brochure) : places, doctolib, linkedin, facebook, titles,
 *       formation, parcours
 *
 * Forme complète manipulée par l'éditeur et les vues :
 * [
 *   'palette' => 'turquoise', 'model' => 'spine',
 *   'honorific' => 'Docteur', 'credentials' => '', 'lede' => '',
 *   'expertise' => [2, 7],
 *   'places' => [['name' => '', 'address' => '', 'city' => ''], …],
 *   'doctolib' => '', 'linkedin' => '', 'facebook' => '',
 *   'titles' => ['formation' => 'Formation', 'parcours' => 'Parcours'],
 *   'formation' => [['year' => '', 'title' => '', 'place' => ''], …],
 *   'parcours'  => [ … ],
 * ]
 */
class BrochureDraft
{
    public const PALETTES = [
        'turquoise' => ['Turquoise', '#1f8379'],
        'orange'    => ['Orange OrthoMaster', '#FF9600'],
        'ocean'     => ['Bleu océan', '#2563EB'],
        'rose'      => ['Rose framboise', '#C8336A'],
        'lavande'   => ['Violet lavande', '#6D4FD1'],
    ];

    public const MODELS = [
        'spine' => 'Colonne vertébrale',
        'knee'  => 'Genou',
        'hand'  => 'Main',
        'foot'  => 'Pied',
        'legs'  => 'Membres inférieurs',
    ];

    /** Sections chronologiques et leur titre par défaut (le titre est modifiable). */
    public const TIMELINES = [
        'formation' => 'Formation',
        'parcours'  => 'Parcours',
    ];

    public const LINKS = ['doctolib', 'linkedin', 'facebook'];

    /** Colonnes propres à la page (brouillon puis publication) ; le reste de la page va dans le JSON. */
    public const COLUMNS = ['model'];

    /** Profil de l'expert : lu et écrit directement sur experts (comme l'application). */
    public const PROFILE_COLUMNS = ['honorific', 'credentials', 'lede', 'palette'];

    /** Mêmes limites que l'application (ExpertProfileService). */
    public const LEDE_MAX = ExpertProfileService::LEDE_MAX;
    public const CREDENTIALS_MAX = ExpertProfileService::CREDENTIALS_MAX;

    private const MAX_PILLS = 12;
    private const MAX_PLACES = 4;
    private const MAX_ENTRIES = 30;

    /** Valeurs initiales de l'éditeur (une ligne vide par liste pour guider la saisie). */
    public static function defaults(): array
    {
        $empty = [['year' => '', 'title' => '', 'place' => '']];

        return [
            'palette'     => 'turquoise',
            'model'       => 'spine',
            'honorific'   => '',
            'credentials' => '',
            'lede'        => '',
            'expertise'   => [],
            'places'      => [['name' => '', 'address' => '', 'city' => '']],
            'doctolib'    => '',
            'linkedin'    => '',
            'facebook'    => '',
            'titles'      => self::TIMELINES,
            'formation'   => $empty,
            'parcours'    => $empty,
        ];
    }

    /**
     * Sépare des données normalisées en [colonnes de la page, JSON de la page, profil de l'expert].
     * 'expertise' est exclu (enregistré dans domain_expert).
     */
    public static function split(array $data): array
    {
        return [
            Arr::only($data, self::COLUMNS),
            Arr::except($data, [...self::COLUMNS, ...self::PROFILE_COLUMNS, 'expertise']),
            Arr::only($data, self::PROFILE_COLUMNS),
        ];
    }

    /**
     * Reconstitue les données complètes : page depuis un brouillon ou la version publiée
     * (colonnes + JSON), profil depuis l'expert.
     */
    public static function fromRecord(?Model $record, string $jsonColumn, Expert $expert): array
    {
        $json = $record?->{$jsonColumn};
        $columns = [];
        foreach (self::COLUMNS as $column) {
            $columns[$column] = $record?->{$column} ?? '';
        }
        foreach (self::PROFILE_COLUMNS as $column) {
            $columns[$column] = $expert->{$column} ?? '';
        }

        // Page jamais enregistrée : valeurs initiales de l'éditeur, avec le profil de l'expert.
        $base = $record ? (is_array($json) ? $json : []) : self::defaults();

        return self::normalize(array_merge($base, array_filter($columns, fn ($v) => $v !== '') + ['honorific' => '']));
    }

    /**
     * Valeurs du profil à écrire sur experts (null = vide).
     * Couleur : la page affiche « turquoise » quand l'expert n'en a pas choisi ; ce choix par défaut
     * n'est pas enregistré tant qu'il n'est pas modifié (l'application garde sa couleur par défaut).
     */
    public static function profileColumns(array $profile, Expert $expert): array
    {
        $palette = $profile['palette'] ?? null;
        if ($expert->palette === null && $palette === 'turquoise') {
            $palette = null;
        }

        return [
            'honorific'   => ExpertProfileService::honorificOf($profile['honorific'] ?? null),
            'credentials' => ($profile['credentials'] ?? '') !== '' ? $profile['credentials'] : null,
            'lede'        => ($profile['lede'] ?? '') !== '' ? $profile['lede'] : null,
            'palette'     => $palette,
        ];
    }

    /** Valide et nettoie des données venant du navigateur ou de la base. */
    public static function normalize(array $in): array
    {
        $palette = is_string($in['palette'] ?? null) ? $in['palette'] : '';
        $model = is_string($in['model'] ?? null) ? $in['model'] : '';

        $out = [
            'palette'     => array_key_exists($palette, self::PALETTES) ? $palette : 'turquoise',
            'model'       => array_key_exists($model, self::MODELS) ? $model : 'spine',
            // Liste fermée (Madame, Monsieur, Docteur, Professeur) ou vide ; un ancien titre libre reste affiché.
            'honorific'   => self::honorific($in['honorific'] ?? ''),
            'credentials' => self::text($in['credentials'] ?? '', self::CREDENTIALS_MAX),
            'lede'        => self::text($in['lede'] ?? '', self::LEDE_MAX),
            'expertise'   => [],
            'places'      => [],
            'titles'      => [],
        ];

        foreach (self::LINKS as $link) {
            $out[$link] = self::url($in[$link] ?? '');
        }

        // Domaines d'expertise : ids existants dans la table domains
        $catalog = ExpertiseCatalog::all();
        foreach (self::listOf($in['expertise'] ?? []) as $item) {
            $id = match (true) {
                is_int($item), is_string($item) && ctype_digit($item) => (int) $item,
                is_string($item) => ExpertiseCatalog::idFor($item),
                default => null,
            };
            if ($id !== null && isset($catalog[$id]) && ! in_array($id, $out['expertise'], true)) {
                $out['expertise'][] = $id;
            }
            if (count($out['expertise']) >= self::MAX_PILLS) {
                break;
            }
        }

        // Lieux d'exercice : chaque ligne est facultative (une ville seule suffit)
        foreach (array_slice(self::listOf($in['places'] ?? []), 0, self::MAX_PLACES) as $place) {
            if (! is_array($place)) {
                continue;
            }
            $p = [
                'name'    => self::text($place['name'] ?? '', 160),
                'address' => self::text($place['address'] ?? '', 160),
                'city'    => self::text($place['city'] ?? '', 120),
            ];
            if ($p['name'] !== '' || $p['address'] !== '' || $p['city'] !== '') {
                $out['places'][] = $p;
            }
        }

        $titles = is_array($in['titles'] ?? null) ? $in['titles'] : [];
        foreach (self::TIMELINES as $key => $label) {
            $title = self::text($titles[$key] ?? '', 60);
            $out['titles'][$key] = $title !== '' ? $title : $label;

            $out[$key] = [];
            foreach (array_slice(self::listOf($in[$key] ?? []), 0, self::MAX_ENTRIES) as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $e = [
                    'year'  => self::text($entry['year'] ?? '', 40),
                    'title' => self::text($entry['title'] ?? '', 200),
                    'place' => self::text($entry['place'] ?? '', 200),
                ];
                if ($e['year'] !== '' || $e['title'] !== '' || $e['place'] !== '') {
                    $out[$key][] = $e;
                }
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------- helpers

    private static function honorific(mixed $v): string
    {
        $text = self::text($v, ExpertProfileService::HONORIFIC_MAX);

        return ExpertProfileService::honorificOf($text) ?? $text;
    }

    private static function text(mixed $v, int $max): string
    {
        if (! is_string($v) && ! is_numeric($v)) {
            return '';
        }
        $v = preg_replace('/[\x00-\x1F\x7F\x{2028}\x{2029}]+/u', ' ', (string) $v) ?? '';
        $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');

        return mb_substr($v, 0, $max);
    }

    private static function url(mixed $v): string
    {
        $v = self::text($v, 500);
        if ($v === '') {
            return '';
        }
        $scheme = strtolower((string) parse_url($v, PHP_URL_SCHEME));

        return filter_var($v, FILTER_VALIDATE_URL) && in_array($scheme, ['http', 'https'], true) ? $v : '';
    }

    private static function listOf(mixed $v): array
    {
        return is_array($v) ? array_values($v) : [];
    }
}