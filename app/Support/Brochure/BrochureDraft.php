<?php

namespace App\Support\Brochure;

use DOMDocument;
use DOMElement;

/**
 * Brouillon d'une page partenaire (édité sur /demo/{slug}).
 *
 * Forme des données :
 * [
 *   'palette' => 'turquoise', 'model' => 'spine',
 *   'honorific' => 'Docteur', 'credentials' => '', 'lede' => '',
 *   'expertise' => [2, 7],                                   // ids de ExpertiseCatalog
 *   'places' => [['name' => '', 'address' => '', 'city' => ''], …],   // lieux d'exercice
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

    private const MAX_PILLS = 12;
    private const MAX_PLACES = 4;
    private const MAX_ENTRIES = 30;

    public static function defaults(): array
    {
        $empty = [['year' => '', 'title' => '', 'place' => '']];

        return [
            'palette'     => 'turquoise',
            'model'       => 'spine',
            'honorific'   => 'Docteur',
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

    /** Valide et nettoie des données venant du navigateur ou d'un fichier XML. */
    public static function normalize(array $in): array
    {
        $palette = is_string($in['palette'] ?? null) ? $in['palette'] : '';
        $model = is_string($in['model'] ?? null) ? $in['model'] : '';

        $out = [
            'palette'     => array_key_exists($palette, self::PALETTES) ? $palette : 'turquoise',
            'model'       => array_key_exists($model, self::MODELS) ? $model : 'spine',
            // vide autorisé (aucun titre affiché) ; absent = « Docteur »
            'honorific'   => array_key_exists('honorific', $in) ? self::text($in['honorific'], 40) : 'Docteur',
            'credentials' => self::text($in['credentials'] ?? '', 200),
            'lede'        => self::text($in['lede'] ?? '', 600),
            'expertise'   => [],
            'places'      => [],
            'titles'      => [],
        ];

        foreach (self::LINKS as $link) {
            $out[$link] = self::url($in[$link] ?? '');
        }

        // Domaines d'expertise : ids du catalogue (un ancien libellé est converti en id s'il existe)
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

    // ---------------------------------------------------------------- XML

    public static function toXml(array $data): string
    {
        $data = self::normalize($data);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('brochure');
        $root->setAttribute('version', '1');
        $root->setAttribute('updated', date(DATE_ATOM));
        $doc->appendChild($root);

        foreach (['palette', 'model', 'honorific', 'credentials', 'lede'] as $field) {
            self::el($doc, $root, $field, $data[$field]);
        }

        $links = self::el($doc, $root, 'links');
        foreach (self::LINKS as $link) {
            self::el($doc, $links, $link, $data[$link]);
        }

        $expertise = self::el($doc, $root, 'expertise');
        foreach ($data['expertise'] as $id) {
            self::el($doc, $expertise, 'item')->setAttribute('id', (string) $id);
        }

        $places = self::el($doc, $root, 'places');
        foreach ($data['places'] as $place) {
            $node = self::el($doc, $places, 'place');
            foreach (['name', 'address', 'city'] as $f) {
                self::el($doc, $node, $f, $place[$f]);
            }
        }

        foreach (array_keys(self::TIMELINES) as $key) {
            $list = self::el($doc, $root, $key);
            $list->setAttribute('title', $data['titles'][$key]);
            foreach ($data[$key] as $entry) {
                $node = self::el($doc, $list, 'entry');
                foreach (['year', 'title', 'place'] as $f) {
                    self::el($doc, $node, $f, $entry[$f]);
                }
            }
        }

        return $doc->saveXML();
    }

    public static function fromXml(?string $xml): array
    {
        if (! $xml) {
            return self::defaults();
        }

        $prev = libxml_use_internal_errors(true);
        $x = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        libxml_use_internal_errors($prev);

        if ($x === false) {
            return self::defaults();
        }

        $data = [
            'palette'     => (string) $x->palette,
            'model'       => (string) $x->model,
            // anciens brouillons sans <honorific> : « Docteur »
            'honorific'   => isset($x->honorific) ? (string) $x->honorific : 'Docteur',
            'credentials' => (string) $x->credentials,
            'lede'        => (string) $x->lede,
            'expertise'   => [],
            'places'      => [],
            'titles'      => [],
        ];
        foreach (self::LINKS as $link) {
            $data[$link] = (string) $x->links->{$link};
        }
        foreach ($x->expertise->item ?? [] as $item) {
            // <item id="3"/> ; anciens brouillons : <item>Libellé</item>
            $data['expertise'][] = isset($item['id']) ? (string) $item['id'] : (string) $item;
        }
        foreach ($x->places->place ?? [] as $place) {
            $data['places'][] = [
                'name'    => (string) $place->name,
                'address' => (string) $place->address,
                'city'    => (string) $place->city,
            ];
        }
        foreach (array_keys(self::TIMELINES) as $key) {
            $data['titles'][$key] = (string) ($x->{$key}['title'] ?? '');
            $data[$key] = [];
            foreach ($x->{$key}->entry ?? [] as $entry) {
                $data[$key][] = [
                    'year'  => (string) $entry->year,
                    'title' => (string) $entry->title,
                    'place' => (string) $entry->place,
                ];
            }
        }

        return self::normalize($data);
    }

    // ---------------------------------------------------------------- Blade export

    /**
     * Génère le contenu de resources/views/brochures/experts/{id}/index.blade.php.
     * $identity : ['id' => …, 'slug' => …, 'name' => …]
     *
     * Toutes les valeurs sont placées dans un bloc @php, en chaînes PHP échappées
     * (@ et accolades encodés) : aucune saisie ne peut être interprétée par Blade.
     */
    public static function toBlade(array $data, array $identity): string
    {
        $d = self::normalize($data);
        $name = self::text($identity['name'] ?? '', 160);

        $brochure = [
            'layout' => [
                'title'   => "{$name}, partenaire OrthoMaster",
                'palette' => $d['palette'],
                'model'   => $d['model'],
            ],
            'hero' => [
                'honorific'   => $d['honorific'],
                'name'        => $name,
                'credentials' => $d['credentials'],
                'expertise'   => $d['expertise'],   // ids : libellés résolus par ExpertiseCatalog
                'lede'        => $d['lede'],
                'places'      => $d['places'],
                'doctolib'    => $d['doctolib'],
                'linkedin'    => $d['linkedin'],
                'facebook'    => $d['facebook'],
            ],
        ];
        foreach (array_keys(self::TIMELINES) as $key) {
            if ($d[$key]) {
                $brochure[$key] = ['id' => $key, 'title' => $d['titles'][$key], 'items' => $d[$key]];
            }
        }

        $includes = "    @include('brochures.partials.hero', \$brochure['hero'])\n";
        foreach (array_keys(self::TIMELINES) as $key) {
            if (isset($brochure[$key])) {
                $includes .= "    @include('brochures.partials.timeline', \$brochure['{$key}'])\n";
            }
        }

        $id = preg_replace('/\D/', '', (string) ($identity['id'] ?? '')) ?: '{id}';
        $slug = preg_replace('/[^a-z0-9-]/', '', (string) ($identity['slug'] ?? ''));
        $who = str_replace(['{{', '}}', '--'], ['{ {', '} }', '- -'], $name); // ne pas casser le commentaire Blade
        $date = date('d/m/Y H:i');
        $code = self::export($brochure, 1);

        return <<<BLADE
{{--
    {$who}
    Généré le {$date} depuis /demo/{$slug}
    À placer dans : resources/views/brochures/experts/{$id}/index.blade.php
--}}
@php
    \$brochure = {$code};
@endphp

@extends('brochures.layout', \$brochure['layout'])

@section('content')
{$includes}
    {{-- Ajoutez ici les sections propres à ce médecin --}}
@endsection

BLADE;
    }

    // ---------------------------------------------------------------- helpers

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

    private static function el(DOMDocument $doc, DOMElement $parent, string $name, ?string $text = null): DOMElement
    {
        $el = $doc->createElement($name);
        if ($text !== null) {
            $el->appendChild($doc->createTextNode($text));
        }
        $parent->appendChild($el);

        return $el;
    }

    /** Export PHP lisible (tableaux courts, chaînes entre guillemets doubles échappées). */
    public static function export(mixed $value, int $depth = 1): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }
            $pad = str_repeat('    ', $depth + 1);
            $list = array_is_list($value);
            $lines = [];
            foreach ($value as $k => $v) {
                $lines[] = $pad.($list ? '' : self::export((string) $k).' => ').self::export($v, $depth + 1).',';
            }

            return "[\n".implode("\n", $lines)."\n".str_repeat('    ', $depth).']';
        }
        if (is_int($value)) {
            return (string) $value;
        }

        return '"'.strtr((string) $value, [
            '\\' => '\\\\', '"' => '\\"', '$' => '\\$',
            '@' => '\\x40', '{' => '\\x7B', '}' => '\\x7D',
            "\n" => '\\n', "\r" => '', "\t" => ' ',
        ]).'"';
    }
}