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

    /**
     * Build a map: childId => nodeId   (which node OWNS this child).
     *
     * Each childId belongs to exactly one node in the tree (the one
     * whose `children[]` array contains it). This is the "group" the
     * child is part of for matching purposes.
     */
    public function groupMap(): array
    {
        $tree = $this->loadTree();
        $map = [];
        foreach ($tree as $node) {
            foreach (($node['children'] ?? []) as $child) {
                $map[$child['id']] = $node['id'];
            }
        }
        return $map;
    }

    /**
     * Return all child IDs belonging to a given group (nodeId).
     */
    public function childrenOfGroup(string $nodeId): array
    {
        $tree = $this->loadTree();
        foreach ($tree as $node) {
            if ($node['id'] === $nodeId) {
                return array_map(fn ($c) => $c['id'], $node['children'] ?? []);
            }
        }
        return [];
    }

    /**
     * Pure match logic: does `$productTags` satisfy `$userFilters`?
     *
     * Rule per group:
     *   1. If user has no filter in a group → group doesn't restrict.
     *   2. If product has no tag in that group → product is unrestricted
     *      for this group → match.
     *   3. If product has ONLY the generic tag (= the group's nodeId) →
     *      product is "any sub-option" → match.
     *   4. If product has at least one SPECIFIC tag in the group →
     *      ignore any generic tag it might also have; product matches
     *      only if user's filter shares at least one specific tag.
     *
     * Across groups: product must pass EVERY group the user filtered on.
     */
    public function productMatches(array $productTags, array $userFilters): bool
    {
        if (empty($userFilters)) return true;
 
        $groupMap = $this->groupMap();
 
        // Index product tags by group:
        //   groupId => ['generic' => bool, 'specifics' => [childId, ...]]
        // - generic = true when product tagged the group's nodeId itself
        //   (e.g. 'activite' rather than 'activ-2')
        // - specifics = list of specific child IDs in this group
        $productByGroup = [];
        foreach ($productTags as $tag) {
            // A tag is "generic" if its own ID is a groupId (= some node id).
            $isGeneric = isset(array_flip($groupMap)[$tag]); // tag appears as a value (= a group)
            if ($isGeneric) {
                if (!isset($productByGroup[$tag])) {
                    $productByGroup[$tag] = ['generic' => false, 'specifics' => []];
                }
                $productByGroup[$tag]['generic'] = true;
            } elseif (isset($groupMap[$tag])) {
                $g = $groupMap[$tag];
                if (!isset($productByGroup[$g])) {
                    $productByGroup[$g] = ['generic' => false, 'specifics' => []];
                }
                $productByGroup[$g]['specifics'][] = $tag;
            }
            // Tags not in either map are stale/unknown — ignore.
        }
 
        // Group user's filters the same way.
        $userByGroup = [];
        foreach ($userFilters as $tag) {
            $isGeneric = isset(array_flip($groupMap)[$tag]);
            if ($isGeneric) {
                if (!isset($userByGroup[$tag])) {
                    $userByGroup[$tag] = ['generic' => false, 'specifics' => []];
                }
                $userByGroup[$tag]['generic'] = true;
            } elseif (isset($groupMap[$tag])) {
                $g = $groupMap[$tag];
                if (!isset($userByGroup[$g])) {
                    $userByGroup[$g] = ['generic' => false, 'specifics' => []];
                }
                $userByGroup[$g]['specifics'][] = $tag;
            }
        }
 
        // Check each group the user filtered on.
        foreach ($userByGroup as $groupId => $userBucket) {
            $productBucket = $productByGroup[$groupId] ?? null;
 
            // Rule 2: product has nothing in this group → ok.
            if ($productBucket === null) continue;
 
            // Rule 4: product has specifics → user must share at least one.
            if (!empty($productBucket['specifics'])) {
                $overlap = array_intersect($productBucket['specifics'], $userBucket['specifics']);
                if (empty($overlap)) {
                    return false;       // group failed
                }
                continue;
            }
 
            // Rule 3: product has only generic → match anything in group.
            if ($productBucket['generic']) continue;
 
            // Product had something we didn't recognize — treat as unrestricted.
        }
 
        return true;
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