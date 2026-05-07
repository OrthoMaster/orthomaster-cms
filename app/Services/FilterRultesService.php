<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Reads storage/product/rules.json and exposes helpers around the
 * filter tree.
 *
 * Key responsibility: expanding a flat filter set with its "parents",
 * so that a product tagged generically ('age') still matches a query
 * for a specific value ('age-u25').
 *
 * Parent map is built once and cached for an hour. Bust the cache by
 * calling FilterRulesService::clearCache() when rules.json changes.
 */
class FilterRulesService
{
    public const CACHE_KEY    = 'filter_rules.parent_map';
    public const CACHE_MINUTES = 60;

    /**
     * Load the raw rules tree from storage. Returns [] if missing/invalid.
     */
    public function loadTree(): array
    {
        $path = storage_path('app/product/rules.json');
        if (!file_exists($path)) return [];
        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build a map: childId => [parentChildId, ...]
     *
     * Parent semantics:
     *   1. Every concrete child (e.g. "age-u25") has its node id ("age")
     *      as a parent IF that node id is itself a childId in some other
     *      node (e.g. "age" is a child of "patient"). This mirrors the
     *      indicator pattern.
     *   2. The node id itself ("age") is NOT a child id — only entries
     *      that appear as `children[].id` somewhere are valid filter keys.
     *
     * Example output:
     *   "age-u25"       => ["age"]
     *   "age"           => []         (top-level child of patient indicator)
     *   "tronc-rac-hau" => ["tronc"]  (segment child whose visibility depends on zone=tronc)
     *   "tronc"         => []
     */
    public function parentMap(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_MINUTES), function () {
            return $this->buildParentMap();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function buildParentMap(): array
    {
        $tree = $this->loadTree();
        $parentMap = [];

        // Index: nodeId => node
        $nodeById = [];
        foreach ($tree as $node) {
            $nodeById[$node['id']] = $node;
        }

        // 1) For every concrete child whose node.id is also used as a child id
        //    somewhere else, treat node.id as the child's parent.
        $allChildIds = [];
        foreach ($tree as $node) {
            foreach (($node['children'] ?? []) as $child) {
                $allChildIds[$child['id']] = true;
            }
        }

        foreach ($tree as $node) {
            $nodeId = $node['id'];
            $isIndicator = isset($allChildIds[$nodeId]); // node id appears as a child elsewhere
            foreach (($node['children'] ?? []) as $child) {
                $cid = $child['id'];
                if (!isset($parentMap[$cid])) $parentMap[$cid] = [];

                // Implicit parent = the node id, IF it's a valid child id
                if ($isIndicator) {
                    $parentMap[$cid][] = $nodeId;
                }

                // Explicit parent = via child.visibilityRule referencing
                // another child (e.g. segment.tronc-rac-hau requires zone.tronc).
                $rule = $child['visibilityRule'] ?? null;
                $refs = $this->collectRuleChildRefs($rule);
                foreach ($refs as $ref) {
                    $parentMap[$cid][] = $ref;
                }
            }
        }

        // De-dupe each list
        foreach ($parentMap as $k => $list) {
            $parentMap[$k] = array_values(array_unique($list));
        }

        return $parentMap;
    }

    /**
     * Walk a rule and collect every (nodeId, childId) reference,
     * returning just the childIds. Used to detect explicit parent links.
     */
    private function collectRuleChildRefs($rule): array
    {
        if (!$rule || !is_array($rule)) return [];

        // Leaf rules
        if (isset($rule['kind'])) {
            if ($rule['kind'] === 'selected' && isset($rule['childId'])) {
                return [$rule['childId']];
            }
            // any_selected references a node, not a specific child — skip
            return [];
        }

        // Compound rules
        if (isset($rule['op'])) {
            $op = $rule['op'];
            if (in_array($op, ['AND', 'OR'], true)) {
                $out = [];
                foreach (($rule['rules'] ?? []) as $r) {
                    $out = array_merge($out, $this->collectRuleChildRefs($r));
                }
                return $out;
            }
            if ($op === 'NOT') {
                return $this->collectRuleChildRefs($rule['rule'] ?? null);
            }
        }
        return [];
    }

    /**
     * Expand a flat list of filter keys with all transitive parents.
     *
     * Input:  ['age-u25', 'rhumato']
     * Output: ['age-u25', 'rhumato', 'age']     (assuming age-u25 → age in the map)
     *
     * Pure function over the cached parentMap. Order of returned keys is
     * not guaranteed; caller should treat it as a set.
     */
    public function expandWithParents(array $keys): array
    {
        $map = $this->parentMap();
        $result = [];
        $queue  = $keys;

        while (!empty($queue)) {
            $k = array_shift($queue);
            if (isset($result[$k])) continue;     // already visited
            $result[$k] = true;

            foreach ($map[$k] ?? [] as $parent) {
                if (!isset($result[$parent])) {
                    $queue[] = $parent;
                }
            }
        }

        return array_keys($result);
    }
}