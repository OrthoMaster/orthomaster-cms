<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Reads storage/product/rules.json and exposes helpers around the
 * filter tree.
 *
 * Responsibilities:
 *   1. Expand a flat pathology filter set with its "parents" so a product
 *      tagged generically ('age') still matches a query for a specific
 *      value ('age-u25'). [LEGACY pathology behavior, preserved.]
 *   2. Resolve measurement bucket ids → numeric (dimensionKey, min, max)
 *      so callers can check overlap with product size measurements.
 *   3. Match a product against a user's filter selection, combining
 *      pathology per-group logic and measurement range overlap.
 *
 * Caches are busted via FilterRulesService::clearCache() when rules.json
 * changes (e.g. from the rules edit page).
 */
class FilterRulesService
{
    public const CACHE_KEY_PARENT_MAP     = 'filter_rules.parent_map';
    public const CACHE_KEY_BUCKET_MAP     = 'filter_rules.bucket_map';
    public const CACHE_KEY_MEASUREMENT_NODE_IDS = 'filter_rules.measurement_node_ids';
    public const CACHE_KEY_PROTOCOLS      = 'filter_rules.protocols';
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
     * Load the protocols list from storage/app/product/protocols.json.
     * Returns the raw `protocols` array (not wrapped). Empty array on
     * missing/malformed file. Cached for an hour — bust with clearCache().
     *
     * The file shape:
     *   { "protocols": [ { id, title, phases: [ { id, title, groups: [...] } ] }, ... ] }
     */
    public function loadProtocols(): array
    {
        return Cache::remember(self::CACHE_KEY_PROTOCOLS, now()->addMinutes(self::CACHE_MINUTES), function () {
            $path = storage_path('app/product/protocols.json');
            if (!file_exists($path)) return [];
            $raw = file_get_contents($path);
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) return [];
            $protocols = $decoded['protocols'] ?? [];
            return is_array($protocols) ? $protocols : [];
        });
    }

    /**
     * Given a child id (e.g. "cer-trau-tor") from the rules tree, return
     * the protocolId attached to that child — or null when the child has
     * no protocol. Used by the API to decide whether to surface the
     * protocol screen on the mobile app or skip straight to products.
     */
    public function protocolIdForChild(string $childId): ?string
    {
        foreach ($this->loadTree() as $node) {
            foreach (($node['children'] ?? []) as $child) {
                if (($child['id'] ?? null) === $childId) {
                    $pid = $child['protocolId'] ?? null;
                    return (is_string($pid) && $pid !== '') ? $pid : null;
                }
            }
        }
        return null;
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
     */
    public function parentMap(): array
    {
        return Cache::remember(self::CACHE_KEY_PARENT_MAP, now()->addMinutes(self::CACHE_MINUTES), function () {
            return $this->buildParentMap();
        });
    }

    /**
     * Build a map: bucketChildId => ['dimensionKey' => ..., 'min' => N, 'max' => N]
     *
     * Only includes children of measurement nodes (nodes with a
     * top-level "dimensionKey" field). Children without numeric
     * min/max are SKIPPED — they're malformed for a measurement bucket.
     */
    public function bucketMap(): array
    {
        return Cache::remember(self::CACHE_KEY_BUCKET_MAP, now()->addMinutes(self::CACHE_MINUTES), function () {
            $tree = $this->loadTree();
            $map = [];
            foreach ($tree as $node) {
                $dimKey = $node['dimensionKey'] ?? null;
                if (!is_string($dimKey) || $dimKey === '') continue;
                foreach (($node['children'] ?? []) as $child) {
                    $cid = $child['id'] ?? null;
                    if (!is_string($cid) || $cid === '') continue;
                    if (!isset($child['min']) || !isset($child['max'])) continue;
                    if (!is_numeric($child['min']) || !is_numeric($child['max'])) continue;
                    $map[$cid] = [
                        'dimensionKey' => $dimKey,
                        'min'          => (float) $child['min'],
                        'max'          => (float) $child['max'],
                    ];
                }
            }
            return $map;
        });
    }

    /**
     * Set of node ids that are measurement nodes (have a dimensionKey).
     * Used to detect non-measurement (pathology) tags vs measurement
     * buckets when partitioning a user's filter list.
     */
    public function measurementNodeIds(): array
    {
        return Cache::remember(self::CACHE_KEY_MEASUREMENT_NODE_IDS, now()->addMinutes(self::CACHE_MINUTES), function () {
            $ids = [];
            foreach ($this->loadTree() as $node) {
                $dk = $node['dimensionKey'] ?? null;
                if (is_string($dk) && $dk !== '') {
                    $ids[] = $node['id'];
                }
            }
            return $ids;
        });
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_PARENT_MAP);
        Cache::forget(self::CACHE_KEY_BUCKET_MAP);
        Cache::forget(self::CACHE_KEY_MEASUREMENT_NODE_IDS);
        Cache::forget(self::CACHE_KEY_PROTOCOLS);
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
     * Master match function: does this product satisfy the user's
     * filter selection?
     *
     * Combines three independent checks:
     *   1. Pathology tags (per-group rule, see pathologyMatch())
     *   2. Measurement buckets (range-overlap with product.sizes, see
     *      measurementMatch())
     *   3. Protocol group (when a doctor has drilled into a protocol's
     *      group, the product must be tagged with that group id).
     *
     * Product passes if ALL active checks pass. If user supplied no
     * filter / no groupId in a category, that category's check is
     * vacuously true.
     */
    public function productMatches(Product $product, array $userFilters, ?string $groupId = null): bool
    {
        $hasFilters = !empty($userFilters);
        $hasGroup   = is_string($groupId) && $groupId !== '';
        if (!$hasFilters && !$hasGroup) return true;

        // Split user's filters into pathology tags vs measurement buckets.
        $bucketMap = $hasFilters ? $this->bucketMap() : [];
        $pathologyFilters = [];
        $measurementFilters = [];
        foreach ($userFilters as $f) {
            if (!is_string($f) || $f === '') continue;
            if (isset($bucketMap[$f])) {
                $measurementFilters[] = $f;
            } else {
                $pathologyFilters[] = $f;
            }
        }

        // Pathology check uses the product's filter tags.
        if (!empty($pathologyFilters)) {
            $tags = $product->relationLoaded('filters')
                ? $product->filters->pluck('filter_key')->all()
                : $product->filters()->pluck('filter_key')->all();
            if (!$this->pathologyMatch($tags, $pathologyFilters)) {
                return false;
            }
        }

        // Measurement check uses the product's structured sizes JSON.
        if (!empty($measurementFilters)) {
            if (!$this->measurementMatch($product->sizes, $measurementFilters)) {
                return false;
            }
        }

        // Protocol group check — when set, product must be tagged with
        // this exact group id. Plain set membership against the product's
        // `groups` JSON column.
        if ($hasGroup) {
            $groups = $product->groups ?? [];
            if (!is_array($groups) || !in_array($groupId, $groups, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pathology per-group match (preserved legacy logic).
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
    public function pathologyMatch(array $productTags, array $userFilters): bool
    {
        if (empty($userFilters)) return true;

        $groupMap = $this->groupMap();

        // Set of node ids — used to detect "generic" tags (tag id == a group id).
        $groupIds = array_flip(array_values(array_unique(array_values($groupMap))));

        // Index product tags by group:
        //   groupId => ['generic' => bool, 'specifics' => [childId, ...]]
        $productByGroup = $this->indexTagsByGroup($productTags, $groupMap, $groupIds);
        $userByGroup    = $this->indexTagsByGroup($userFilters, $groupMap, $groupIds);

        foreach ($userByGroup as $groupId => $userBucket) {
            $productBucket = $productByGroup[$groupId] ?? null;

            // Rule 2: product has nothing in this group → ok.
            if ($productBucket === null) continue;

            // Rule 4: product has specifics → user must share at least one.
            if (!empty($productBucket['specifics'])) {
                $overlap = array_intersect($productBucket['specifics'], $userBucket['specifics']);
                if (empty($overlap)) {
                    return false;
                }
                continue;
            }

            // Rule 3: product has only generic → match anything in group.
            if ($productBucket['generic']) continue;
        }

        return true;
    }

    /**
     * Helper: split a flat list of tags into per-group buckets.
     */
    private function indexTagsByGroup(array $tags, array $groupMap, array $groupIds): array
    {
        $out = [];
        foreach ($tags as $tag) {
            if (!is_string($tag) || $tag === '') continue;
            // Generic tag = tag id is a group id (= a node id).
            if (isset($groupIds[$tag])) {
                if (!isset($out[$tag])) $out[$tag] = ['generic' => false, 'specifics' => []];
                $out[$tag]['generic'] = true;
            } elseif (isset($groupMap[$tag])) {
                $g = $groupMap[$tag];
                if (!isset($out[$g])) $out[$g] = ['generic' => false, 'specifics' => []];
                $out[$g]['specifics'][] = $tag;
            }
            // Tags not in the map are stale/unknown — ignore.
        }
        return $out;
    }

    /**
     * Measurement match — every user-selected bucket must have at least
     * one product size option whose measurement for the same dimension
     * overlaps the bucket's [min, max] range (inclusive boundaries).
     *
     * Schema expected for $sizes:
     *   {
     *     "options": [
     *       {
     *         "label": "Taille 1",
     *         "displayRange": "19-21 cm",
     *         "measurements": {
     *           "tour_de_cheville": { "min": 19, "max": 21, "displayRange": "19 cm - 21 cm" }
     *         }
     *       },
     *       ...
     *     ]
     *   }
     *
     * Rules:
     *   - No sizes data → fails (we can't promise fit).
     *   - User picked a bucket for dimension X, but product has no
     *     option that declares X → fails for that bucket.
     *   - Otherwise: at least one option's [opt.min, opt.max] must
     *     overlap bucket's [bk.min, bk.max] (inclusive).
     */
    public function measurementMatch($sizes, array $bucketIds): bool
    {
        if (empty($bucketIds)) return true;

        $bucketMap = $this->bucketMap();

        // Without sizes data, we cannot guarantee any taille fits — fail.
        if (!is_array($sizes)) return false;
        $options = $sizes['options'] ?? null;
        if (!is_array($options) || empty($options)) return false;

        foreach ($bucketIds as $bucketId) {
            $bucket = $bucketMap[$bucketId] ?? null;
            if (!$bucket) {
                // Bucket id unknown (stale data) — be strict and fail.
                return false;
            }
            $dimKey = $bucket['dimensionKey'];
            $bMin   = $bucket['min'];
            $bMax   = $bucket['max'];

            $matched = false;
            foreach ($options as $opt) {
                if (!is_array($opt)) continue;
                $measurements = $opt['measurements'] ?? null;
                if (!is_array($measurements)) continue;
                $m = $measurements[$dimKey] ?? null;
                if (!is_array($m)) continue;
                if (!isset($m['min']) || !isset($m['max'])) continue;
                if (!is_numeric($m['min']) || !is_numeric($m['max'])) continue;

                // Inclusive overlap: a.min <= b.max && b.min <= a.max
                if ((float) $m['min'] <= $bMax && $bMin <= (float) $m['max']) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) return false;
        }

        return true;
    }

    /**
     * Walk a rule and collect every (nodeId, childId) reference,
     * returning just the childIds. Used to detect explicit parent links.
     */
    private function collectRuleChildRefs($rule): array
    {
        if (!$rule || !is_array($rule)) return [];

        if (isset($rule['kind'])) {
            if ($rule['kind'] === 'selected' && isset($rule['childId'])) {
                return [$rule['childId']];
            }
            return [];
        }

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

    private function buildParentMap(): array
    {
        $tree = $this->loadTree();
        $parentMap = [];

        // All child ids in the tree — for detecting indicator-pattern parents.
        $allChildIds = [];
        foreach ($tree as $node) {
            foreach (($node['children'] ?? []) as $child) {
                $allChildIds[$child['id']] = true;
            }
        }

        foreach ($tree as $node) {
            $nodeId = $node['id'];
            $isIndicator = isset($allChildIds[$nodeId]);
            foreach (($node['children'] ?? []) as $child) {
                $cid = $child['id'];
                if (!isset($parentMap[$cid])) $parentMap[$cid] = [];

                if ($isIndicator) {
                    $parentMap[$cid][] = $nodeId;
                }

                $rule = $child['visibilityRule'] ?? null;
                $refs = $this->collectRuleChildRefs($rule);
                foreach ($refs as $ref) {
                    $parentMap[$cid][] = $ref;
                }
            }
        }

        foreach ($parentMap as $k => $list) {
            $parentMap[$k] = array_values(array_unique($list));
        }

        return $parentMap;
    }

    /**
     * Expand a flat list of filter keys with all transitive parents.
     *
     * Input:  ['age-u25', 'rhumato']
     * Output: ['age-u25', 'rhumato', 'age']
     *
     * Pure function over the cached parentMap. Order is not guaranteed;
     * caller should treat it as a set.
     */
    public function expandWithParents(array $keys): array
    {
        $map = $this->parentMap();
        $result = [];
        $queue  = $keys;

        while (!empty($queue)) {
            $k = array_shift($queue);
            if (isset($result[$k])) continue;
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