<?php

namespace App\Services\AiSearch;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reconnaît un congrès (et éventuellement une édition) cité dans la saisie de l'utilisateur.
 *
 * Fait par le code et non par le LLM : le LLM ne voit que les congrès présents parmi les
 * candidates, il ne peut donc ni filtrer de façon fiable ni dire qu'une édition n'existe pas.
 */
class EventMatcher
{
    private const CACHE_KEY = 'ai_search:event_catalog';

    /** Le catalogue change rarement ; quelques minutes de retard après une modification suffisent. */
    private const CACHE_MINUTES = 10;

    /** Formes normalisées (sans accent) des mois tels qu'ils apparaissent dans edition_year ou la saisie. */
    private const MONTHS = [
        'janvier' => 1, 'janv' => 1, 'jan' => 1,
        'fevrier' => 2, 'fevr' => 2, 'fev' => 2,
        'mars' => 3,
        'avril' => 4, 'avr' => 4,
        'mai' => 5,
        'juin' => 6,
        'juillet' => 7, 'juil' => 7,
        'aout' => 8,
        'septembre' => 9, 'sept' => 9, 'sep' => 9,
        'octobre' => 10, 'oct' => 10,
        'novembre' => 11, 'nov' => 11,
        'decembre' => 12, 'dec' => 12,
    ];

    /** Mots qui n'ont de sens qu'autour du congrès reconnu : retirés de la requête avec lui. */
    private const EVENT_WORDS = ['congres', 'congre', 'edition', 'editions'];

    /**
     * @return array{
     *     status: 'none'|'matched'|'edition_not_found',
     *     remaining: string,
     *     filter_key?: string,
     *     ids?: int[],
     *     label?: string,
     *     event_name?: string,
     *     requested?: string,
     *     available?: array<int, array{label: string, ids: int[]}>,
     *     editions?: array<int, array{label: string, ids: int[]}>
     * }
     *     remaining : saisie débarrassée du congrès, de l'année et du mois reconnus.
     */
    public function match(string $text): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        // Chaque mot d'origine peut donner plusieurs jetons normalisés (« l'épaule » → « l », « epaule ») :
        // on garde l'index du mot d'origine pour pouvoir le retirer ensuite.
        $tokens = [];
        foreach ($words as $index => $word) {
            foreach (explode(' ', $this->normalize($word)) as $token) {
                if ($token !== '') {
                    $tokens[] = ['token' => $token, 'word' => $index];
                }
            }
        }
        $flat = array_column($tokens, 'token');

        $event = $this->findEvent($flat);
        if (! $event) {
            return ['status' => 'none', 'remaining' => trim($text)];
        }

        $removed = array_fill_keys(array_map(fn ($i) => $tokens[$i]['word'], $event['positions']), true);

        // L'année et le mois ne comptent que s'ils accompagnent un congrès :
        // une année seule peut désigner bien autre chose qu'une édition.
        $year = null;
        $month = null;
        foreach ($tokens as $i => $token) {
            if ($year === null && preg_match('/^20\d{2}$/', $token['token'])) {
                $year = (int) $token['token'];
                $removed[$token['word']] = true;
            } elseif ($month === null && isset(self::MONTHS[$token['token']])) {
                $month = self::MONTHS[$token['token']];
                $monthWord = $token['word'];
            }
        }
        // Un mois sans année reste un mot ordinaire (« mai », « mars »…).
        if ($year === null) {
            $month = null;
        } elseif ($month !== null) {
            $removed[$monthWord] = true;
        }

        foreach ($tokens as $token) {
            if (in_array($token['token'], self::EVENT_WORDS, true)) {
                $removed[$token['word']] = true;
            }
        }

        $remaining = implode(' ', array_diff_key($words, $removed));

        return $this->resolveEdition($event['event'], $year, $month) + ['remaining' => $remaining];
    }

    /* ===================== CONGRÈS ===================== */

    /**
     * Le terme le plus long l'emporte : « Chirurgie du Sport » plutôt qu'un alias plus court
     * qui en serait un morceau.
     *
     * @param  string[]  $flat
     * @return array{event: array, positions: int[]}|null
     */
    private function findEvent(array $flat): ?array
    {
        $best = null;

        foreach ($this->catalog() as $event) {
            foreach ($event['terms'] as $term) {
                $positions = $this->findSequence($flat, $term);
                if ($positions && (! $best || count($term) > $best['length'])) {
                    $best = ['event' => $event, 'positions' => $positions, 'length' => count($term)];
                }
            }
        }

        return $best ? ['event' => $best['event'], 'positions' => $best['positions']] : null;
    }

    /**
     * @param  string[]  $haystack
     * @param  string[]  $needle
     * @return int[]|null  positions des jetons trouvés
     */
    private function findSequence(array $haystack, array $needle): ?array
    {
        $length = count($needle);
        for ($start = 0; $start + $length <= count($haystack); $start++) {
            if (array_slice($haystack, $start, $length) === $needle) {
                return range($start, $start + $length - 1);
            }
        }

        return null;
    }

    /* ===================== ÉDITIONS ===================== */

    private function resolveEdition(array $event, ?int $year, ?int $month): array
    {
        if ($year === null) {
            return [
                'status'     => 'matched',
                'filter_key' => 'event_ids',
                'ids'        => [$event['id']],
                'label'      => $event['name'],
                // Pour proposer de choisir l'édition quand le congrès est cité seul.
                'editions'   => $this->groupByYear($event),
            ];
        }

        $sameYear = array_values(array_filter($event['editions'], fn ($e) => $e['year'] === $year));

        if (! $sameYear) {
            return [
                'status'     => 'edition_not_found',
                'event_name' => $event['name'],
                'requested'  => (string) $year,
                'available'  => $this->groupByYear($event),
            ];
        }

        if ($month !== null) {
            $sameMonth = array_values(array_filter($sameYear, fn ($e) => $e['month'] === $month));

            if (! $sameMonth) {
                return [
                    'status'     => 'edition_not_found',
                    'event_name' => $event['name'],
                    'requested'  => array_search($month, self::MONTHS, true) . " {$year}",
                    'available'  => array_map(fn ($e) => [
                        'label' => "{$event['name']} {$e['label']}",
                        'ids'   => [$e['id']],
                    ], $sameYear),
                ];
            }

            $sameYear = $sameMonth;
        }

        // Plusieurs éditions la même année (« 2024 - 1 », « 2024 - 2 ») : on les prend toutes.
        return [
            'status'     => 'matched',
            'filter_key' => 'past_event_ids',
            'ids'        => array_column($sameYear, 'id'),
            'label'      => count($sameYear) === 1
                ? "{$event['name']} {$sameYear[0]['label']}"
                : "{$event['name']} {$year}",
        ];
    }

    /**
     * @return array<int, array{label: string, ids: int[]}>
     */
    private function groupByYear(array $event): array
    {
        $groups = [];
        foreach ($event['editions'] as $edition) {
            if ($edition['year'] === null) {
                continue;
            }
            $groups[$edition['year']]['label'] = "{$event['name']} {$edition['year']}";
            $groups[$edition['year']]['ids'][] = $edition['id'];
        }
        krsort($groups);

        return array_values($groups);
    }

    /* ===================== CATALOGUE ===================== */

    /**
     * @return array<int, array{id: int, name: string, terms: array<int, string[]>, editions: array}>
     */
    private function catalog(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_MINUTES), function () {
            $editions = DB::table('past_events')
                ->where('active_flg', 1)
                ->get(['id', 'id_event', 'edition_year'])
                ->groupBy('id_event');

            return DB::table('events')
                ->where('active_flg', 1)
                ->get(['id', 'name', 'aliases'])
                ->map(function ($event) use ($editions) {
                    $names = array_merge([$event->name], explode(',', (string) $event->aliases));
                    $terms = array_values(array_filter(array_map(
                        fn ($name) => array_values(array_filter(explode(' ', $this->normalize($name)))),
                        $names
                    )));

                    return [
                        'id'       => (int) $event->id,
                        'name'     => $event->name,
                        'terms'    => $terms,
                        'editions' => ($editions[$event->id] ?? collect())->map(fn ($e) => $this->parseEdition($e))->values()->all(),
                    ];
                })
                ->all();
        });
    }

    /**
     * edition_year est un texte libre : « 2024 », « 2024 - 1 », « Avr 2025 », « Avril 2026 ».
     */
    private function parseEdition(object $edition): array
    {
        $normalized = $this->normalize((string) $edition->edition_year);

        $year = preg_match('/\b(20\d{2})\b/', $normalized, $m) ? (int) $m[1] : null;

        $month = null;
        foreach (explode(' ', $normalized) as $token) {
            if (isset(self::MONTHS[$token])) {
                $month = self::MONTHS[$token];
                break;
            }
        }

        return [
            'id'    => (int) $edition->id,
            'label' => trim((string) $edition->edition_year),
            'year'  => $year,
            'month' => $month,
        ];
    }

    private function normalize(string $value): string
    {
        $ascii = Str::lower(Str::ascii($value));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }
}